# For the AGENTS

Usage, setup, commands and release: `README.md`. This file only adds what agents need beyond it; don't repeat README content here, reference it.

## Code style

- derive from the existing code
- prefix getters with `get`; methods that request data from a remote API may use `fetch`
- names must reflect what code does; if a change alters a class/function's behavior, check its name still fits; propose renames that touch call sites
- don't rename untouched code
- keep comments as short as possible
- do not add comments if not really required
- only use languages I know. No python please
- Use `/** */` block comments (not `//`) for anything that's not an "end of the line" comment
- docblocks state _what_ a thing does, never _why_ it does it or why it was built that way (exception: Inline docs like e.g. "using the raw value because..."); no {@see} cross-references at all; skip the docblock entirely if the name is self-explanatory; hard ceiling of 3 lines, shorter preferred.

## Before making changes

- if the change is big, always first propose what you are planning, even if not in plan mode

## After making changes

- Keep inline code comments as short as possible. Be extremely concise and sacrifice grammar for the sake of concision
- After changes in `assets-src/`, run `pnpm build`; never edit `assets/` by hand
- Issues solved by the current edit: add `Fixes #<n>` to the commit message; never close them directly (see #issue-tracker)

### PHP

- Classes in `CamelCase`, methods / functions etc in `snake_case`
- Keep new code DRY! Propose deduplicating existing code. Ask if unsure.
- Prefer fewer, bigger classes: bundle related functionality in one class/file instead of many small ones. A class doing several related, contained jobs is fine
- Prefer WordPress-native functionality over reimplementing it (e.g. `list_files()` over `glob()`).

### JavaScript

- the public JS API is `window.RHFL` (documented in `README.md`); keep it backwards compatible or flag the break

## Do not touch without explicit request

- Releasing (`./release.sh`), tags, GitHub releases
- `lib/plugin-update-checker/` (unmodified upstream copy, see README)
- No commits without explicit request
- do not edit TODO files

## Project structure

| Path | Purpose |
|------|---------|
| `rh-free-layouts.php` | Plugin entry, main class `RHFreeLayouts` (singleton, `rhfl()`) |
| `inc/` | ACF field types (`Free Layout`, `Reset Free Layouts`), `RHSingleton` base, `RHFLUpdateChecker` |
| `lib/plugin-update-checker/` | Bundled plugin-update-checker (updates from GitHub releases) |
| `assets-src/` | JS + SCSS sources |
| `assets/` | Built assets (committed, generated) |
| `build.mjs` | esbuild build script |
| `release.sh` | Release script (see README) |
| `changelog.md` | Changelog, written by `release.sh` |

## Agent skills

### Issue tracker

Issues and PRDs live as GitHub issues on `hirasso/rh-free-layouts`; use `gh` (`gh issue list`, `gh issue view`, `gh issue create`). They close via `Fixes #<n>` in the commit message when pushed to `main`; never `gh issue close`.

### Triage labels

Canonical vocabulary as GitHub labels (`needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`); `wontfix` issues are closed only on request.
