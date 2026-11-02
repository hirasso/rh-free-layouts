# RH Free Layouts

Free drag-and-drop layouts for ACF Flexible Content and Repeater fields.

## Installation

### Via Composer (recommended)

```shell
composer require hirasso/rh-free-layouts
```

#### Update via Composer

```shell
# latest version, including major version jumps
composer require hirasso/rh-free-layouts
```

### Via WP CLI

```shell
wp plugin install https://github.com/hirasso/rh-free-layouts/releases/latest/download/rh-free-layouts.zip
```

#### Update via WP CLI

```shell
wp plugin update rh-free-layouts
```

### Manually

1. Download the [bundled zip file from the latest release](https://github.com/hirasso/rh-free-layouts/releases/latest/download/rh-free-layouts.zip)
2. In your browser, login to your WP Admin Area and navigate to /wp-admin/plugin-install.php
3. Click "Upload Plugin" and choose `rh-free-layouts.zip` that you just downloaded

#### Update Manually

If `DISALLOW_FILE_MODS` is not set to `true`, you can update the plugin directly from the WordPress admin plugins screen, just like any other plugin. To avoid being blocked by GitHub, you can define a `RHFL_GITHUB_TOKEN` in your `wp-config.php` file. Learn more about [creating GitHub Tokens](https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/managing-your-personal-access-tokens#creating-a-fine-grained-personal-access-token)

```php
// wp-config.php:
define('RHFL_GITHUB_TOKEN', 'qwf_sdgad142...');
```

## How to use

- Activate the plugin
- Add a field with type `Free Layout` to your ACF `Flexible Content` or `Repeater`.
- Optionally add a field with type `Reset Free Layouts` to your post

### Wrap your module or repeater row like this:

```php
<?php ob_start(); ?>
<div class="my-module">This module will support free layout</div>
<?php echo function_exists('rhfl') ? rhfl()->wrap_item( $value, ob_get_clean() ) : ob_get_clean() ?>
```

Initiate the edit mode from your php template (after all items have been rendered):

```php
if( function_exists('rhfl') ) rhfl()->get_edit_mode_js();
```

For full control over context and options, you can also initiate RHFL using JavaScript:

```js

if (window.RHFL != null) {
  window.RHFL.initEditMode({
    containerElement: document.querySelector('.my-free-layout'),
    groupSelector: '.my-group'
  });
}
```

## Development

Requires Node >= 22 and pnpm. ACF (Pro, for Flexible Content / Repeater) must be active on the site.

```bash
pnpm install    # also points git to .githooks/
pnpm watch      # dev build with live reload; production build on exit
pnpm build      # production build into assets/
```

- Sources live in `assets-src/` (`rh-free-layouts.js`, `scss/`), built by `build.mjs` (esbuild + dart-sass + postcss-preset-env) into `assets/`
- `assets/` is committed: the plugin is installed straight from the repo without building. The pre-commit hook rebuilds and stages it; CI (`.github/workflows/assets.yml`) fails if it doesn't match a fresh build

### plugin-update-checker

Updates are served from GitHub releases via [plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker), bundled as an unmodified copy in `lib/plugin-update-checker/`. To update it, replace the folder with the new release and adjust the versioned `v5pN` import in `inc/class.update-checker.php`.

### Release

```bash
./release.sh <patch|minor|major>
```

Bumps the version in `rh-free-layouts.php`, prepends a `changelog.md` section from the commits since the last tag, builds, commits, tags, pushes and creates a GitHub release with a zip (requires `gh`). Files with `export-ignore` in `.gitattributes` are left out of the zip.
