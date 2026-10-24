# Contributing

Run `composer install` to restore development tools and the Changelog skill at
`.agents/skills/fast-forward-changelog`. The development-only `llm/skills` plugin
reads the reviewed GitHub source and version range from `skills.json`; generated
skill files are ignored by Git. Inspect the source with `composer skills:show`
and explicitly refresh it with `composer skills:update --from=github`.

All Fast Forward libraries use the same skill configuration. Installed Composer
donors are trusted through `fast-forward/*`, replacing implicit trust of other
direct dependencies. The local `resources/skills` source has no package override.
The GitHub Changelog source uses `^0.4.0` and discovers its payload through
Composer metadata without a skill allowlist. `auto-sync: true` restores skills
after normal Composer install/update operations; `composer skills:update`
refreshes every configured source explicitly.

Add one unique `.changelog/*.md` fragment per observable change. With PHP 8.5+
and CPX, inspect the command help and validate committed contributions:

```sh
cpx --skip-local fast-forward/changelog help add
cpx --skip-local fast-forward/changelog check --since=origin/main --no-interaction
```

Preserve pending fragments and published `CHANGELOG.md` history. The `@v0`
workflow prepares a version PR titled
`chore(release): fast-forward/config v{current_version} → v{next_version}`;
review its exact result before merging. Existing automatic tag and GitHub
Release publication settings remain unchanged. Skill setup publishes nothing.
