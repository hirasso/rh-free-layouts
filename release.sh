#!/usr/bin/env bash
#
# Release a new version of the plugin
#
# Usage: ./release.sh <patch|minor|major>    (bumps the last tag, e.g. 1.5.8 → 1.5.9 | 1.6.0 | 2.0.0)
#
# - bumps "Version:" in rh-free-layouts.php
# - prepends a changelog section built from commits since the last tag (opened in your editor for review)
# - builds assets, commits "Release <version>", tags and pushes
# - creates a GitHub release with the changelog section as notes and rh-free-layouts.zip attached (requires gh)

set -euo pipefail

cd "$(dirname "$0")"

PLUGIN_FILE="rh-free-layouts.php"
CHANGELOG="changelog.md"
BRANCH="main"
# commit subjects matching one of these (extended regex, case-insensitive) are left out of the changelog
IGNORE=("^Merge branch " "^Release [0-9]" "#ignore" "update todo" "cleanup")

die() { echo "✘ $*" >&2; exit 1; }

command -v gh >/dev/null || die "gh (GitHub CLI) is required"

[[ "$(git branch --show-current)" == "$BRANCH" ]] || die "Not on $BRANCH"
[[ -z "$(git status --porcelain)" ]] || die "Working tree not clean"

last_tag="$(git describe --tags --abbrev=0)"
[[ "$last_tag" =~ ^([0-9]+)\.([0-9]+)\.([0-9]+)$ ]] || die "Last tag $last_tag is not major.minor.patch"
major="${BASH_REMATCH[1]}" minor="${BASH_REMATCH[2]}" patch="${BASH_REMATCH[3]}"

case "${1:-}" in
  patch) version="$major.$minor.$((patch + 1))" ;;
  minor) version="$major.$((minor + 1)).0" ;;
  major) version="$((major + 1)).0.0" ;;
  *) die "Usage: ./release.sh <patch|minor|major>" ;;
esac
git rev-parse -q --verify "refs/tags/$version" >/dev/null && die "Tag $version already exists"
echo "Releasing $last_tag → $version"

# Collect commit subjects since the last tag
ignore_args=()
for pattern in "${IGNORE[@]}"; do ignore_args+=(-e "$pattern"); done
entries="$(git log --no-merges --pretty=format:'%s (#%h)' "$last_tag"..HEAD | grep -viE "${ignore_args[@]}" | sed 's/^/- /' || true)"
[[ -n "$entries" ]] || die "No changes since $last_tag"

echo
echo "$entries"
echo
read -rp "Continue with $version? [y/N] " answer
[[ "$answer" == [yY] ]] || die "Aborted"

# Let the user review the new section
section_file="$(mktemp -t rhfl-release)"
trap 'rm -f "$section_file"' EXIT
printf '#### %s (%s)\n\n%s\n' "$version" "$(date +%F)" "$entries" > "$section_file"

editor="${VISUAL:-${EDITOR:-}}"
if [[ -z "$editor" ]]; then
  command -v code >/dev/null && editor="code --wait" || editor="vi"
fi
echo "Opening the changelog in ${editor%% *}: save and close it to continue, empty it to abort…"
$editor "$section_file"
[[ -n "$(tr -d '[:space:]' < "$section_file")" ]] || die "Empty changelog, aborted"

# Prepend the section below the "# Changelog" heading
{
  echo "# Changelog"
  echo
  cat "$section_file"
  echo
  tail -n +3 "$CHANGELOG"
} > "$CHANGELOG.tmp"
mv "$CHANGELOG.tmp" "$CHANGELOG"

# Bump the version header
sed -i.bak -E "s/^( \* Version: ).*/\1$version/" "$PLUGIN_FILE" && rm "$PLUGIN_FILE.bak"
grep -q "^ \* Version: $version$" "$PLUGIN_FILE" || die "Failed to bump version in $PLUGIN_FILE"

npm run build

git add "$PLUGIN_FILE" "$CHANGELOG" assets
# --no-verify: assets were just built, skip the pre-commit hook building them again
git commit --no-verify -m "Release $version"
git tag "$version"

echo
git show --stat HEAD
echo
read -rp "Push $BRANCH and tag $version to origin? [y/N] " answer
if [[ "$answer" != [yY] ]]; then
  echo "Not pushed. Undo with: git tag -d $version && git reset --hard HEAD~1"
  exit 0
fi

git push origin "$BRANCH" "$version"

# Plugin zip with a stable folder name, for manual installs and update checks.
# git archive respects export-ignore in .gitattributes
zip_dir="$(mktemp -d)"
trap 'rm -rf "$section_file" "$zip_dir"' EXIT
git archive --format=zip --prefix=rh-free-layouts/ -o "$zip_dir/rh-free-layouts.zip" "$version"

# Release notes: the section without its heading
gh release create "$version" "$zip_dir/rh-free-layouts.zip" --title "$version" --notes "$(tail -n +3 "$section_file")"

echo "✔ Released $version"
