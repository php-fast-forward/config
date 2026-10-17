Agent skill
===========

The portable ``fast-forward-config`` skill supports ``fast-forward/config ^1.7``
on PHP 8.3+. Earlier versions are outside this supported contract. Inspect the
application's installed package before using its guidance; do not upgrade
production dependencies solely to obtain instructions.

Composer distributions include the complete package at
``vendor/fast-forward/config/resources/skills/fast-forward-config``:
``SKILL.md``, references, executable examples and MIT ``LICENSE``.
Shipping files, copying them into a discovery root and activating a skill in an
agent session are separate steps. Composer suggestions are not installed
automatically.

Project scope is the default for reviewable team configuration. Global copies
remain an explicit consumer choice. Record the package version or immutable
source revision and preserve the application's existing discovery configuration.

Optional Composer project synchronization
-----------------------------------------

The optional `llm/skills <https://github.com/roxblnfk/skills>`_ plugin is
development tooling. Config only advertises
``extra.skills.source: resources/skills`` and suggests the plugin; it does not
require, enable or grant approval to it.

For a new consumer setup, create ``skills.json`` **before installing or enabling
the plugin and before its first sync**:

.. code-block:: json

   {
     "target": ".agents/skills",
     "aliases": [],
     "discovery": false,
     "auto-sync": true,
     "vendor-sources": false,
     "dependencies": {
       "composer": {
         "trusted": ["fast-forward/config"],
         "trusted-replace": true
       }
     }
   }

This narrowly selects Config, replaces implicit dependency trust and disables
remote vendor sources. ``aliases: []`` requests no additional host-directory
links. Merge these choices into existing configuration: preserve deliberate
aliases, unrelated dependencies and plugin approvals. A team may intentionally
trust ``fast-forward/*``, but that broader trust is not required.
Claude Code-only consumers can select ``.claude/skills`` as the target.

With authority to install and approve this project plugin, run:

.. code-block:: sh

   composer config allow-plugins.llm/skills true
   composer require --dev 'llm/skills:^1.15'
   composer skills:show

Plugin approval allows Composer to execute code; the donor library cannot grant
it on the consumer's behalf. Preserve other ``allow-plugins`` entries.
Noninteractive approval must already be explicit. Commit the intended
``composer.json``, lockfile and ``skills.json`` according to project policy.
Approved install/update runs synchronize trusted installed donors without an
additional post-install hook.

For deliberate synchronization only, set ``auto-sync: false`` before installing
the plugin, then inspect and apply:

.. code-block:: sh

   composer skills:show
   composer skills:update fast-forward/config --dry-run
   composer skills:update fast-forward/config

Naming a donor selects/trusts it for that run; it does not test the automatic
allowlist policy. With Composer 2.10.3 and llm/skills 1.15.0, ``--no-scripts``
alone still allowed automatic synchronization. ``auto-sync: false`` suppresses
the automatic copy; ``--no-plugins`` prevents plugin loading. Recheck behavior
when changing tool versions and inspect copied bytes as well as exit status.

Synchronization ownership and removal
-------------------------------------

Ordinary llm/skills 1.15 synchronization merges into the destination:

- Incoming same-path files overwrite local edits.
- Extra local files and files removed upstream remain.
- Removing the donor or plugin leaves existing skill copies behind.
- A missing or moved donor path does not remove an old installed copy.

Treat the copied package as donor-managed. Make durable edits in the donor or a
separately named consumer-owned skill. Inspect diffs after library/tool updates.

Cleanup is separate and potentially destructive. Preview first:

.. code-block:: sh

   composer skills:update --clean --dry-run

An unscoped clean can delete every skill in the target, including handwritten
ones. Noninteractive execution must not be assumed to prompt. A scoped clean
only reinstalls the donors selected by that run; it cannot identify an already
removed donor. Inventory affected packages and archive exact stale directories
outside discovery before authorized cleanup. Do not add a blanket cleanup hook.

Manual project/global installation
----------------------------------

Choose one source and destination from the consumer project root:

.. code-block:: sh

   skill_source="vendor/fast-forward/config/resources/skills/fast-forward-config"
   skill_target=".agents/skills/fast-forward-config"
   # Explicit global Codex alternative:
   # skill_target="$HOME/.agents/skills/fast-forward-config"
   # Claude Code: .claude/skills, or its explicit home counterpart.

   test -f "$skill_source/SKILL.md" &&
   test ! -e "$skill_target" &&
   test ! -L "$skill_target" &&
   mkdir -p "$(dirname "$skill_target")" &&
   cp -R "$skill_source" "$skill_target"

The guards refuse an existing destination or dangling symlink. A missing source
calls for inspecting the installed version/archive; do not create an empty
placeholder. A reviewed checkout or immutable revision containing the complete
package is another source choice.

============  ===================  ======================
Agent         Project              Global
============  ===================  ======================
Codex         ``.agents/skills/``  ``~/.agents/skills/``
Claude Code   ``.claude/skills/``  ``~/.claude/skills/``
============  ===================  ======================

Honor the host's configured paths and avoid ambiguous duplicates.
See the `Codex skills guide <https://developers.openai.com/codex/skills/>`_
and `Claude Code skills guide <https://code.claude.com/docs/en/skills>`_.
The portable package needs no host adapter.

For update or removal, archive only the selected current package outside every
discovery directory. With the same ``skill_target``:

.. code-block:: sh

   skill_backup="$HOME/.agent-skill-backups/fast-forward-config-$(date +%Y%m%d%H%M%S)-$$"
   test -d "$skill_target" &&
   test ! -L "$skill_target" &&
   test ! -e "$skill_backup" &&
   test ! -L "$skill_backup" &&
   mkdir -p "$(dirname "$skill_backup")" &&
   mv "$skill_target" "$skill_backup"

Moving it out removes a manual copy from discovery. Review the new source and
repeat the guarded copy for an update. For rollback, first archive any
replacement, then restore the complete saved package to its original path and
refresh the session. Never keep backups under ``.agents/skills`` or
``.claude/skills``. Installer-managed copies can return on the next sync until
donor/trust configuration changes.

Installing the Composer plugin globally does not install skill files globally:
the tool still operates on a selected project/target. Explicit manual or Skills
CLI global copies have their own lifecycle.

Optional Skills CLI
-------------------

The `Skills CLI <https://github.com/vercel-labs/skills>`_ is development tooling,
not a PHP runtime dependency. These commands pin ``skills@1.7.2`` and require
Node.js 22.20.0+ and npm; repository sources also require Git. Review a new CLI
version before changing the pin.

.. code-block:: sh

   npx --yes skills@1.7.2 add ./vendor/fast-forward/config/resources/skills/fast-forward-config --skill fast-forward-config --agent codex --copy --yes
   npx --yes skills@1.7.2 list --agent codex

Choose the actual host, such as ``--agent claude-code``. Add ``--global``
explicitly to both commands for global installation. ``--copy`` avoids a
symlink into replaceable Composer vendor content. Commit project copies and
intended CLI lockfiles.

For a repository source, set ``SKILL_REF`` to its reviewed full commit SHA first:

.. code-block:: sh

   npx --yes skills@1.7.2 add "https://github.com/php-fast-forward/config/tree/$SKILL_REF/resources/skills/fast-forward-config" --skill fast-forward-config --agent codex --copy --yes

For updates, review a new immutable revision and repeat with that source;
update-to-latest is a separate choice. Back up local edits before replacement.
Remove only the selected scope/name:

.. code-block:: sh

   npx --yes skills@1.7.2 remove fast-forward-config --agent codex --yes
   # Explicit global alternative:
   npx --yes skills@1.7.2 remove fast-forward-config --agent codex --global --yes

Verify installation and use
--------------------------

Inspect the installed package, refresh the agent session and confirm discovery
of ``fast-forward-config``. Ask for a small configuration task and check its
result. A file copy, CLI listing, live host activation and a successful task are
different observations.

The examples accept the application's Composer autoloader:

.. code-block:: sh

   php vendor/fast-forward/config/resources/skills/fast-forward-config/examples/application.php ./vendor/autoload.php

The other programs are ``composition.php``, ``sources.php``, ``caching.php``
and ``containers.php``. They use explicit comparisons and the repository's
PHPUnit integration coverage. File examples use private temporary fixtures.
The cache fixture records TTLs without implementing expiration. Optional
Container integration clearly reports when its dependency is absent.

First-party references: `llm/skills 1.15.0 <https://github.com/roxblnfk/skills/tree/1.15.0>`_,
`Composer suggestions <https://getcomposer.org/doc/04-schema.md#suggest>`_ and
`plugin approval <https://getcomposer.org/doc/06-config.md#allow-plugins>`_.
