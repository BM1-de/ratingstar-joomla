#!/usr/bin/env bash
#
# Builds the installable Joomla package pkg_ratingstar.zip from the source
# tree. Output goes to dist/ (git-ignored).
#
#   ./build.sh            → dist/pkg_ratingstar.zip (+ the two extension zips)
#
set -euo pipefail

cd "$(dirname "$0")"

DIST="dist"
STAGE="$DIST/_stage"
PKG_ZIP="$DIST/pkg_ratingstar.zip"

rm -rf "$DIST"
mkdir -p "$STAGE/packages"

echo "→ Zipping mod_ratingstar"
( cd mod_ratingstar && zip -rq "../$STAGE/packages/mod_ratingstar.zip" . -x '*.DS_Store' )

echo "→ Zipping plg_system_ratingstar"
( cd plg_system_ratingstar && zip -rq "../$STAGE/packages/plg_system_ratingstar.zip" . -x '*.DS_Store' )

echo "→ Assembling package"
cp pkg_ratingstar.xml "$STAGE/"
mkdir -p "$STAGE/language"
cp -R language/* "$STAGE/language/"

( cd "$STAGE" && zip -rq "../../$PKG_ZIP" . -x '*.DS_Store' )
rm -rf "$STAGE"

echo "✓ Built $PKG_ZIP"
