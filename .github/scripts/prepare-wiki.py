"""Prepare generated API Markdown for GitHub Wiki's page-name URLs."""

import re
import sys
from pathlib import Path
from urllib.parse import quote, urlsplit


def prepare(root: Path, wiki_url: str, source_url: str) -> None:
    pages = sorted(root.rglob('*.md'))
    targets = {}
    names = set()
    for page in pages:
        if page.name == 'AGENTS.md':
            continue
        if page.is_symlink():
            raise ValueError(f'Generated Wiki page is a symlink: {page}')
        name = page.stem
        if name.casefold() in names:
            raise ValueError(f'Ambiguous Wiki page name: {name}')
        names.add(name.casefold())
        targets[page.resolve()] = name

    if (root / 'Home.md').resolve() not in targets:
        raise ValueError('Generated Wiki has no Home.md page')

    link = re.compile(r'\]\(([^)\n]+)\)')
    for page in pages:
        if page.name == 'AGENTS.md':
            page.unlink()
            continue

        def rewrite(match: re.Match) -> str:
            destination = match.group(1)
            parsed = urlsplit(destination)
            if parsed.scheme or parsed.netloc or not parsed.path:
                return match.group(0)
            candidate = (page.parent / parsed.path).resolve()
            if candidate not in targets:
                candidate = Path(str(candidate) + '.md')
            name = targets.get(candidate)
            if name is None:
                return match.group(0)
            anchor = '#' + parsed.fragment if parsed.fragment else ''
            return '](' + wiki_url.rstrip('/') + '/' + quote(name, safe='') + anchor + ')'

        content = link.sub(rewrite, page.read_text())
        if page.name == 'Home.md':
            content = content.rstrip() + '\n\nSource: [repository commit](' + source_url + ').\n'
        page.write_text(content)


if __name__ == '__main__':
    prepare(Path(sys.argv[1]).resolve(), sys.argv[2], sys.argv[3])
