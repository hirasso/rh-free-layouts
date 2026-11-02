# How to use

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

### Release

```bash
./release.sh <patch|minor|major>
```

Bumps the version in `rh-free-layouts.php`, prepends a `changelog.md` section from the commits since the last tag, builds, commits, tags, pushes and creates a GitHub release with a zip (requires `gh`). Files with `export-ignore` in `.gitattributes` are left out of the zip.
