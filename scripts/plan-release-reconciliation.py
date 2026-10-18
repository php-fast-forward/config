#!/usr/bin/env python3
"""Print a read-only GitHub Release body reconciliation plan using official notes."""

import argparse
import difflib
import hashlib
import json
import re
import subprocess


def run(*args):
    return subprocess.check_output(args, text=True)


parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--changelog-bin', required=True, help='Verified v0.2.0 CLI binary')
args = parser.parse_args()
repository = 'php-fast-forward/config'
head = run('git', 'rev-parse', 'HEAD').strip()
if run('git', 'status', '--porcelain', '--', 'CHANGELOG.md'):
    raise SystemExit('Commit the canonical changelog before planning.')
releases = json.loads(run('gh', 'api', '--paginate', '--slurp', f'repos/{repository}/releases'))
releases = [release for page in releases for release in page]
by_tag = {release['tag_name']: release for release in releases}
tags = run('git', 'tag', '--list', 'v*', '--sort=-version:refname').splitlines()
if set(by_tag) - set(tags):
    raise SystemExit('A Release has no local tag. Fetch and investigate before proceeding.')
plan = []
for tag in tags:
    if not re.fullmatch(r'v\d+\.\d+\.\d+', tag):
        raise SystemExit(f'Investigate unsupported version identity: {tag}')
    sha = run('git', 'rev-parse', f'{tag}^{{commit}}').strip()
    remote_ref = json.loads(run('gh', 'api', f'repos/{repository}/git/ref/tags/{tag}'))['object']
    while remote_ref['type'] == 'tag':
        remote_ref = json.loads(run('gh', 'api', f"repos/{repository}/git/tags/{remote_ref['sha']}"))['object']
    if remote_ref['type'] != 'commit' or remote_ref['sha'] != sha:
        raise SystemExit(f'Local/remote tag conflict: {tag}')
    subprocess.run(['git', 'merge-base', '--is-ancestor', sha, head], check=True)
    notes = run(args.changelog_bin, 'notes', tag[1:], '--source=tags',
                '--repository=' + repository, '--no-interaction')
    # This consumer deliberately uses only inline links in release bodies.
    # Fail rather than exporting unresolved reference-style links.
    if re.search(r'(?<!!)\[[^\]\n]+\](?!\()', notes):
        raise SystemExit(f'Inspect reference-style links before exporting {tag}')
    release = by_tag.get(tag)
    before = release['body'] or '' if release else ''
    action = 'create' if release is None else 'unchanged' if before == notes else 'update-body'
    request = {'body': notes}
    if release is None:
        request.update(tag_name=tag, target_commitish=sha, name=tag,
                       draft=False, prerelease=False, generate_release_notes=False)
    plan.append({
        'tag': tag, 'sha': sha, 'version': tag[1:],
        'published_at': release['published_at'] if release else None,
        'release_id': release['id'] if release else None,
        'draft': release['draft'] if release else None,
        'prerelease': release['prerelease'] if release else None,
        'asset_ids': [asset['id'] for asset in release['assets']] if release else [],
        'action': action,
        'method': 'POST' if release is None else 'PATCH',
        'endpoint': f'repos/{repository}/releases' + (f"/{release['id']}" if release else ''),
        'request': request,
        'before_sha256': hashlib.sha256(before.encode()).hexdigest(),
        'after_sha256': hashlib.sha256(notes.encode()).hexdigest(),
        'diff': ''.join(difflib.unified_diff(before.splitlines(keepends=True),
                                          notes.splitlines(keepends=True),
                                          fromfile=f'GitHub/{tag}', tofile=f'CHANGELOG/{tag}')),
    })
print(json.dumps({'repository': repository, 'canonical_sha': head,
                  'runtime': 'v0.2.0 / 2d3f84fa9e5350f03636153fdb3ae2a822e5ae60',
                  'publication_authorized': False, 'releases': plan}, indent=2))
