#!/bin/sh
# Check local Markdown link destinations in the public documentation.
# Anchors, external URLs and links inside fenced code blocks are not checked.
set -eu

cd "$(dirname "$0")/../../.."

find README.md ENV.md docs -name '*.md' | sort | while read -r source; do
    awk '/^[[:space:]]*```/ { fenced = !fenced; next } !fenced' "$source" \
        | grep -oE '\[[^]]*\]\([^)]+\)' \
        | sed -E 's/^\[[^]]*\]\([[:space:]]*//; s/\)$//; /^</!s/[[:space:]].*//; s/^<([^>]*)>.*/\1/; s/[#?].*//' \
        | grep -vE '^$|^[A-Za-z][A-Za-z0-9+.-]*:|^//' \
        | sed "s|^|$source	|" || true
done | {
    checked=0
    missing=0
    while IFS='	' read -r source destination; do
        checked=$((checked + 1))
        case "$destination" in
            /*) target=".$destination" ;;
            *) target="$(dirname "$source")/$destination" ;;
        esac
        # shortcut: percent-encoded paths are not decoded, add decoding when a link needs it
        [ -e "$target" ] || { echo "$source: $destination"; missing=$((missing + 1)); }
    done
    echo "Checked $checked local destinations; $missing missing (anchors and external URLs excluded)."
    [ "$missing" -eq 0 ]
}
