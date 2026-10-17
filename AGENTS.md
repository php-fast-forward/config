# Config repository

Keep runtime changes within the public API in `src/`; cover changed behavior with
the existing PHPUnit suite and the checks supplied by `fast-forward/dev-tools`.
Use the Composer autoloader and named test fixtures in same-named files. Keep generated
files and consumer installation exercises in disposable directories.

## Bundled consumer skill

Read [the consumer skill](resources/skills/fast-forward-config/SKILL.md) when
changing application integration examples, consumer guides, configuration
bootstraps or the skill. Keep its supported version contract, references and
executable examples aligned with the public API. This source link needs no
installed copy or host alias. Repository maintenance follows this file.

The published package lives only in `resources/skills/fast-forward-config`.
Consumer installation and lifecycle instructions belong in
[the installation guide](docs/advanced/agent-skill.rst). Do not install skills or
Composer plugins into the developer's environment as part of validation.
