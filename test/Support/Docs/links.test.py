#!/usr/bin/env python3
"""Check local Markdown link destinations in the public documentation."""
from pathlib import Path
import re
from urllib.parse import unquote, urlsplit

ROOT = Path(__file__).resolve().parents[3]
failures = []
checked = 0
for source in [ROOT / 'README.md', ROOT / 'ENV.md', *sorted((ROOT / 'docs').rglob('*.md'))]:
    content = re.sub(r'```.*?```', '', source.read_text(), flags=re.S)
    for match in re.finditer(r'!?\[[^\]\n]*\]\(([^)]+)\)', content):
        destination = match.group(1).strip()
        destination = destination[1:destination.find('>')] if destination.startswith('<') else destination.split()[0]
        parts = urlsplit(destination)
        if parts.scheme or parts.netloc or not parts.path:
            continue
        checked += 1
        target = ROOT / unquote(parts.path).lstrip('/') if parts.path.startswith('/') else source.parent / unquote(parts.path)
        if not target.exists():
            failures.append(f'{source.relative_to(ROOT)}: {destination}')
for failure in failures:
    print(failure)
print(f'Checked {checked} local destinations; {len(failures)} missing (anchors and external URLs excluded).')
raise SystemExit(bool(failures))
