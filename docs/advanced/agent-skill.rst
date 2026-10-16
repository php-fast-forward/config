Agent skill
===========

The portable ``fast-forward-config`` skill guides agents consuming this library:
source composition, dot paths, mutation, files/providers, persistence, caching
and PSR-11 integration. Its entrypoint is
``skills/fast-forward-config/SKILL.md``. Copy the complete directory, including
references, examples and ``LICENSE``.

Availability and versions
-------------------------

This skill was added after **v1.6.0**. That release is the API baseline for the
examples and does not contain the skill directory. Until a release bundles it,
use a reviewed checkout or full immutable Git revision containing it.
Do not treat a moving branch as a pinned release.

Once a release bundles it, Composer makes the files available under
``vendor/fast-forward/config/skills/fast-forward-config``. Composer does not
activate the skill automatically. No production dependency, Composer plugin,
automatic setup or generated ``AGENTS.md`` is needed.

The agent inspects installed versions before choosing APIs. The baseline
advertises PHP 8.3, but its directory implementation requires ``mb_rtrim()``
(PHP 8.4+) and its config container needs mbstring. The bundled examples
document these and other 1.6.0 limitations instead of assuming pending fixes.

Project-local by default, global by choice
-----------------------------------------

Prefer a **project-local copy** for a team. Review and commit it with the
application, record its source version/full revision, and update it deliberately.
``composer.lock`` pins the library source; copied skills have their own lifecycle.

A **global copy** is opt-in for use across projects. It still needs to inspect
each project's library version. Avoid conflicting duplicate copies; discovery
and precedence depend on the agent.

============  ===================  ======================
Agent         Project              Global
============  ===================  ======================
Codex         ``.agents/skills/``  ``~/.agents/skills/``
Claude Code   ``.claude/skills/``  ``~/.claude/skills/``
============  ===================  ======================

These are default local CLI discovery paths. Honor configured locations.
Other hosts and cloud sessions may differ. See the current
`Codex skills guide <https://learn.chatgpt.com/docs/build-skills>`_ and
`Claude Code skills guide <https://code.claude.com/docs/en/skills>`_.
The portable package has no host-specific adapters.

Guarded copy installation
------------------------

From the consumer project root, choose one source and destination:

.. code-block:: sh

   skill_source="vendor/fast-forward/config/skills/fast-forward-config"
   skill_target=".agents/skills/fast-forward-config"
   # For a reviewed checkout, point skill_source to its skills/fast-forward-config.
   # Opt-in global Codex destination:
   # skill_target="$HOME/.agents/skills/fast-forward-config"
   # Claude Code uses .claude/skills in the corresponding scope.

   test -f "$skill_source/SKILL.md" &&
   test ! -e "$skill_target" &&
   test ! -L "$skill_target" &&
   mkdir -p "$(dirname "$skill_target")" &&
   cp -R "$skill_source" "$skill_target"

The copy refuses existing destinations, including dangling symlinks. A missing
source means the installed release predates the skill: select a reviewed
checkout, rather than creating a placeholder or upgrading production
dependencies solely for agent instructions.

Inspect the entrypoint/resources, refresh the agent session and confirm
``fast-forward-config`` appears in its available skills. Explicitly ask the
agent to use it for a consumer task. Copying or CLI listing does not prove
activation in a live agent.

Update, removal and rollback
----------------------------

Review the new source/revision and diff before replacing a copy. Keep the
previous package **outside every skill discovery directory**. With the same
``skill_target``:

.. code-block:: sh

   skill_backup="$HOME/.agent-skill-backups/fast-forward-config-$(date +%Y%m%d%H%M%S)-$$"
   test -d "$skill_target" &&
   test ! -L "$skill_target" &&
   test ! -e "$skill_backup" &&
   test ! -L "$skill_backup" &&
   mkdir -p "$(dirname "$skill_backup")" &&
   mv "$skill_target" "$skill_backup"

Moving it out removes a manual copy from discovery. For updates, repeat the
guarded copy with the new reviewed source. For rollback, first archive any
replacement, then move the saved complete package to its original destination.
Refresh the agent session. Do not keep backups under ``.agents/skills`` or
``.claude/skills``.

Optional assisted installation
-------------------------------

The `Skills CLI <https://github.com/vercel-labs/skills>`_ is a development tool,
not a PHP runtime dependency. These commands pin ``skills@1.7.2`` and require
Node.js 22.20.0+ and npm; repository sources also require Git. Review a new
CLI version before changing the pin.

For a skill-bearing installed library or reviewed local source:

.. code-block:: sh

   npx --yes skills@1.7.2 add ./vendor/fast-forward/config/skills/fast-forward-config --skill fast-forward-config --agent codex --copy --yes
   npx --yes skills@1.7.2 list --agent codex

Use ``--agent claude-code`` for Claude Code. Add ``--global`` explicitly to
``add`` and ``list`` for a global installation. ``--copy`` prevents a symlink
into a vendor directory Composer might replace.

Before a skill-bearing release, set ``SKILL_REF`` to the **reviewed full commit
SHA containing this directory**, then run:

.. code-block:: sh

   npx --yes skills@1.7.2 add "https://github.com/php-fast-forward/config/tree/$SKILL_REF/skills/fast-forward-config" --skill fast-forward-config --agent codex --copy --yes

Record that revision. For updates, review a new revision and repeat ``add``
with the new pinned source; update-to-latest is a different operation.
Commit project copies and any CLI-generated lockfile.

Remove only this skill:

.. code-block:: sh

   npx --yes skills@1.7.2 remove fast-forward-config --agent codex --yes
   # Explicit global alternative:
   npx --yes skills@1.7.2 remove fast-forward-config --agent codex --global --yes

Automatic installation decision
-------------------------------

A dedicated installer or Composer agent-skill plugin could discover packages
and manage instruction files. That adds execution/trust, destination selection
and generated-file ownership to Composer's lifecycle. This package needs none
of that: project-local guarded copies are the default, global copies are
explicit, and Skills CLI is the assisted alternative.

Teams may adopt their own reviewed development installer. This library does
not register hooks, install into a home directory, run examples or generate
instructions during Composer installation.

Validation scope
----------------

Examples are PHP consumer programs, also exercised by integration tests:

.. code-block:: sh

   php skills/fast-forward-config/examples/composition.php ./vendor/autoload.php

The others are ``sources.php``, ``caching.php`` and ``containers.php``.
Optional Container integration reports when its dependency is absent.
File examples use private temporary fixtures; the educational cache has no TTL.

Installation/update/removal checks use disposable projects and isolated global
directories. They establish file distribution and CLI listing, not live
behavior in every agent. Behavioral evaluation is manual, separate from PHP
execution and official package-format validation.
