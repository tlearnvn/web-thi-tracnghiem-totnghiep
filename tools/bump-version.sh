#!/bin/sh
# Tăng số phiên bản trong tệp VERSION (Semantic Versioning: MAJOR.MINOR.PATCH)
#   sh tools/bump-version.sh          -> tăng PATCH  (1.0.3 -> 1.0.4)
#   sh tools/bump-version.sh minor    -> tăng MINOR  (1.0.3 -> 1.1.0)
#   sh tools/bump-version.sh major    -> tăng MAJOR  (1.0.3 -> 2.0.0)
set -e
ROOT=$(cd "$(dirname "$0")/.." && pwd)
FILE="$ROOT/VERSION"
PART=${1:-patch}
V=$(tr -d ' \r\n' < "$FILE" 2>/dev/null || true)
[ -z "$V" ] && V="1.0.0"
MAJ=$(echo "$V" | cut -d. -f1)
MIN=$(echo "$V" | cut -d. -f2)
PAT=$(echo "$V" | cut -d. -f3)
case "$PART" in
  major) MAJ=$((MAJ + 1)); MIN=0; PAT=0 ;;
  minor) MIN=$((MIN + 1)); PAT=0 ;;
  *)     PAT=$((PAT + 1)) ;;
esac
NEW="$MAJ.$MIN.$PAT"
printf '%s\n' "$NEW" > "$FILE"
echo "$NEW"
