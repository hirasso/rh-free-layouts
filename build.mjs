// @ts-check

/**
 * esbuild Build Script
 * Author: Rasso Hilber
 * Author URL: https://rassohilber.com
 * License: MIT
 *
 * Arguments:
 *
 *  <entries>:            entry files or quoted glob patterns, e.g. "assets-src/*.{js,ts}"
 *  -o:                   outDir
 *  --production:         build once, minified (default is watch mode with live reload)
 *                        watch mode runs a production build on exit
 */

import * as esbuild from 'esbuild';
import * as sass from 'sass';
import postcss from 'postcss';
import postcssPresetEnv from 'postcss-preset-env';
import browserslist from 'browserslist';
import { resolveToEsbuildTarget } from 'esbuild-plugin-browserslist';
import http from 'node:http';
import { parseArgs } from 'node:util';
import { dirname, basename } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createHash } from 'node:crypto';
import { spawnSync } from 'node:child_process';
import { globSync, readFileSync, rmSync, watch } from 'node:fs';

const { values: args, positionals } = parseArgs({
  allowPositionals: true,
  options: {
    o: { type: 'string', default: 'assets/dist' },
    production: { type: 'boolean', default: false },
  },
});

const isProduction = args.production;
const outDir = args.o;

const entryPoints = positionals.flatMap((pattern) => globSync(pattern));

if (!entryPoints.length) {
  console.warn('No entry files given.');
  process.exit(1);
}

/**
 * Compile .scss files with dart-sass, run postcss-preset-env, then hand the CSS over to esbuild
 * url() is resolved relative to the entry .scss file (same as parcel did)
 * @type {esbuild.Plugin}
 */
const sassPlugin = {
  name: 'sass',
  setup(build) {
    const processor = postcss([postcssPresetEnv()]);
    build.onLoad({ filter: /\.scss$/ }, async (args) => {
      try {
        const result = sass.compile(args.path, {
          sourceMap: !isProduction,
          sourceMapIncludeSources: true,
        });
        const { css: contents } = await processor.process(result.css, {
          from: args.path,
          to: args.path, // keeps map sources relative to the entry's dir
          map: result.sourceMap ? { prev: result.sourceMap, inline: true } : false,
        });
        return {
          contents,
          loader: 'css',
          resolveDir: dirname(args.path),
          watchFiles: result.loadedUrls
            .filter((url) => url.protocol === 'file:')
            .map((url) => fileURLToPath(url)),
        };
      } catch (error) {
        return { errors: [{ text: error instanceof Error ? error.message : String(error) }] };
      }
    });
  },
};

/**
 * Connected live reload clients
 * @type {Set<http.ServerResponse>}
 */
const clients = new Set();

/**
 * Send an event to all connected browsers
 * @param {'reload' | 'css'} event
 * @param {string} [data]
 */
function notifyBrowsers(event, data = '') {
  for (const res of clients) res.write(`event: ${event}\ndata: ${data}\n\n`);
}

/**
 * Content hashes of the last build's output files
 * @type {Map<string, string>}
 */
const outputHashes = new Map();

/**
 * Get the output files whose content changed since the last build
 * @param {esbuild.Metafile} metafile
 */
function getChangedOutputs(metafile) {
  return Object.keys(metafile.outputs)
    .filter((file) => !file.endsWith('.map'))
    .filter((file) => {
      const hash = createHash('md5').update(readFileSync(file)).digest('hex');
      const changed = outputHashes.get(file) !== hash;
      outputHashes.set(file, hash);
      return changed;
    });
}

/**
 * Inject CSS if only stylesheets changed, reload the page otherwise
 * @type {esbuild.Plugin}
 */
const reloadPlugin = {
  name: 'reload',
  setup(build) {
    build.initialOptions.metafile = true;
    build.onEnd((result) => {
      if (result.errors.length || !result.metafile) return;
      const changed = getChangedOutputs(result.metafile);
      if (!changed.length) return;
      if (changed.every((file) => file.endsWith('.css'))) {
        notifyBrowsers('css', JSON.stringify(changed.map((file) => basename(file))));
      } else {
        notifyBrowsers('reload');
      }
    });
  },
};

/**
 * Runs in the browser (injected as a banner in dev builds)
 * @param {string} url
 */
function liveReloadClient(url) {
  const events = new EventSource(url);
  events.addEventListener('reload', () => location.reload());
  events.addEventListener('css', (event) => {
    /** @type {string[]} */
    const files = JSON.parse(event.data);
    /** @type {NodeListOf<HTMLLinkElement>} */
    const links = document.querySelectorAll('link[rel="stylesheet"]');
    for (const link of links) {
      const url = new URL(link.href);
      if (!files.some((file) => url.pathname.endsWith(`/${file}`))) continue;
      url.searchParams.set('v', String(Date.now()));
      // swap after the new stylesheet has loaded to avoid a flash of unstyled content
      const clone = /** @type {HTMLLinkElement} */ (link.cloneNode());
      clone.href = url.href;
      clone.onload = () => link.remove();
      link.after(clone);
    }
  });
}

/**
 * The options for esbuild
 * @type {esbuild.BuildOptions}
 */
const options = {
  entryPoints,
  outdir: outDir,
  bundle: true,
  format: 'iife',
  target: ['es2020', ...resolveToEsbuildTarget(browserslist(), { printUnknownTargets: false })],
  minify: isProduction,
  sourcemap: !isProduction,
  resolveExtensions: ['.ts', '.js', '.mjs', '.json', '.scss', '.css'],
  loader: {
    '.png': 'file',
    '.jpg': 'file',
    '.jpeg': 'file',
    '.gif': 'file',
    '.webp': 'file',
    '.eot': 'file',
    '.svg': 'file',
    '.ttf': 'file',
    '.woff': 'file',
    '.woff2': 'file',
  },
  assetNames: '[name].[hash]',
  define: {
    'process.env.NODE_ENV': JSON.stringify(isProduction ? 'production' : 'development'),
    // parcel's HMR API – doesn't exist with esbuild
    'module.hot': 'undefined',
  },
  plugins: isProduction ? [sassPlugin] : [sassPlugin, reloadPlugin],
  logLevel: 'info',
};

/**
 * DDEV: fixed port, exposed via web_extra_exposed_ports (same https_port)
 */
const ddevUrl = process.env.DDEV_PRIMARY_URL;

/**
 * Start the live reload server (dev only), on a random free port outside of DDEV
 * @returns {Promise<number>} the port
 */
async function startReloadServer() {
  const server = http.createServer((req, res) => {
    res.writeHead(200, {
      'Content-Type': 'text/event-stream',
      'Cache-Control': 'no-cache',
      'Access-Control-Allow-Origin': '*',
    });
    res.flushHeaders();
    clients.add(res);
    req.on('close', () => clients.delete(res));
  });
  await new Promise((resolve) => server.listen(ddevUrl ? 35729 : 0, () => resolve(undefined)));
  return /** @type {import('node:net').AddressInfo} */ (server.address()).port;
}

if (!isProduction) {
  const reloadPort = await startReloadServer();
  const reloadUrl = ddevUrl
    ? `https://${new URL(ddevUrl).hostname}:${reloadPort}`
    : `http://localhost:${reloadPort}`;
  options.banner = { js: `(${liveReloadClient})(${JSON.stringify(reloadUrl)});` };
  console.log(`Live reload on ${reloadUrl}`);
}

rmSync(outDir, { recursive: true, force: true });

if (isProduction) {
  await esbuild.build(options);
} else {
  // also reload on PHP changes in the theme (outDir = <theme>/assets/dist)
  /** @type {NodeJS.Timeout | undefined} */
  let timeout;
  watch(dirname(dirname(outDir)), { recursive: true }, (_event, file) => {
    if (!file?.endsWith('.php') || /^(node_modules|vendor)\//.test(file)) return;
    clearTimeout(timeout);
    timeout = setTimeout(() => notifyBrowsers('reload'), 100);
  });

  const ctx = await esbuild.context(options);
  await ctx.watch();

  // don't leave a dev build behind (it keeps polling the reload server)
  for (const signal of ['SIGINT', 'SIGTERM']) {
    process.once(signal, async () => {
      await ctx.dispose();
      const { status } = spawnSync(process.execPath, [...process.argv.slice(1), '--production'], {
        stdio: 'inherit',
      });
      process.exit(status ?? 1);
    });
  }
}
