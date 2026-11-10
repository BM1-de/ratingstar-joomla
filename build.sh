#!/usr/bin/env bash
#
# Builds the installable Joomla package from the source tree:
#
#   ./build.sh   →   dist/pkg_ratingstar.zip
#
# The package holds the package manifest, its language files and one zip per
# extension. Only the files an extension needs at runtime are copied (an
# explicit list per extension), so repository and development files never end
# up in the release. dist/ is git-ignored.
#
set -euo pipefail

cd "$(dirname "$0")"

DIST="dist"
STAGE="$DIST/_stage"
PKG_ZIP="$DIST/pkg_ratingstar.zip"

MANIFESTS=(pkg_ratingstar.xml mod_ratingstar/mod_ratingstar.xml plg_system_ratingstar/ratingstar.xml)

fail() {
    echo "✗ $*" >&2
    exit 1
}

command -v zip >/dev/null || fail "zip is not installed"

# All three manifests must carry the same version.
VERSION=""
for manifest in "${MANIFESTS[@]}"; do
    [ -f "$manifest" ] || fail "missing $manifest"
    found="$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' "$manifest" | head -n 1)"
    [ -n "$found" ] || fail "no <version> in $manifest"
    if [ -z "$VERSION" ]; then
        VERSION="$found"
    elif [ "$VERSION" != "$found" ]; then
        fail "version mismatch: $manifest has $found, expected $VERSION"
    fi
done

# Optional checks, run when the tools are available.
if command -v xmllint >/dev/null; then
    xmllint --noout "${MANIFESTS[@]}" || fail "manifest is not well-formed XML"
fi

if command -v php >/dev/null; then
    while IFS= read -r -d '' file; do
        php -l "$file" >/dev/null || fail "PHP syntax error in $file"
    done < <(find mod_ratingstar plg_system_ratingstar -name '*.php' -print0)
fi

rm -rf "$DIST"
mkdir -p "$STAGE/packages"

# Zips one extension: <folder> <manifest file> <runtime folders…>
pack() {
    local ext="$1" manifest="$2"
    shift 2

    echo "→ Zipping $ext"
    mkdir -p "$STAGE/$ext"
    cp "$ext/$manifest" "$STAGE/$ext/"

    for folder in "$@"; do
        [ -d "$ext/$folder" ] || fail "missing $ext/$folder"
        cp -R "$ext/$folder" "$STAGE/$ext/"
    done

    find "$STAGE/$ext" \( -name '.DS_Store' -o -name '*.orig' -o -name '*.rej' -o -name '*~' \) -delete
    ( cd "$STAGE/$ext" && zip -rqX "../packages/$ext.zip" . )
    rm -rf "${STAGE:?}/$ext"
}

pack mod_ratingstar mod_ratingstar.xml language services src tmpl
pack plg_system_ratingstar ratingstar.xml language services src

echo "→ Assembling package"
cp pkg_ratingstar.xml "$STAGE/"
cp LICENSE.txt "$STAGE/"
cp -R language "$STAGE/language"
find "$STAGE" -name '.DS_Store' -delete

( cd "$STAGE" && zip -rqX "../../$PKG_ZIP" . )
rm -rf "$STAGE"

echo "✓ Built $PKG_ZIP (version $VERSION)"

if command -v shasum >/dev/null; then
    echo "  SHA-256: $(shasum -a 256 "$PKG_ZIP" | cut -d ' ' -f 1)"
fi
