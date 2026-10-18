# Changelog adoption and Release reconciliation

## Selected runtime and workflow contract

Investigated on 2026-10-18. Latest stable release: [v0.2.0](https://github.com/php-fast-forward/changelog/releases/tag/v0.2.0), published 2026-10-18T07:15:48Z; tag and commit: `2d3f84fa9e5350f03636153fdb3ae2a822e5ae60`.
The maintainer explicitly selected the moving `@v0` workflow channel. The live `refs/heads/v0` resolved to that same SHA during validation. This is an intentional 0.x update policy, including potentially breaking minor updates, rather than an immutable pin.

Read the release's README, consumer SKILL.md, caller, reusable lifecycle and check/version workflows, CLI/history/policy contracts. The CPX cache already contains exactly v0.2.0 at this source reference; its binary runs under PHP 8.5.11. CPX itself could not write its host metadata lock in the sandbox, so validation invokes this verified installed binary directly. No project dependency or global skill was installed. Local CLI commands must select version 0.2.0 explicitly rather than copy older ranges embedded in the upstream README.

The consumer uses English documentation, `main`, `CHANGELOG.md`, `.changelog`, the `v` tag prefix, tag-based history and `resolve-pull-requests: true`. All planning, preparation, checks and any future publication must use these same settings. Ordinary fragments omit unknown PR metadata; the resolver can prove it after merge without a follow-up fragment edit.

Existing workflows use GitHub-hosted Linux/Windows runners and default GitHub tokens. Repository Actions variable and secret inventories were empty; no existing changelog caller, managed version PR, Changesets directory or pending fragments were found. No unrelated workflow or Composer dependency was removed. The new lifecycle uses Linux with Docker/Bash/Git, the default GitHub Bot, managed branch `changelog/version`, and the established title `chore(release): prepare v{next_version}`. `publish: false` is deliberate. A default-token version PR may need workflow approval under GitHub policy; configure an App only through a separately approved credential setup.

## Evidence and historical corrections

Baseline: `038deddd474e87528528539d0668d060939ef973` (main and v1.7.0). Inspected the complete first-parent history and each successive tag interval, merged PR API records and the affected source/workflow diffs. All 13 tags resolve to commits reachable from main, and match their remote refs; 13 corresponding GitHub Releases exist. The first interval is repository root through v1.0.0; every later interval is the previous listed version through that tag. Annotated tags are peeled to commits in the table.

- Preserve the introduction, all historical descriptions and all 13 correct version-link definition lines byte for byte. Remove only the empty legacy Unreleased heading and its HEAD link. No pending descriptions need migration.
- Use the UTC calendar date of GitHub `published_at` consistently. v1.2.0 was published at 2025-05-24T02:06:08Z (2025-05-23 locally); v1.7.0 at 2026-10-17T01:39:54Z (2026-10-16 locally). The old dates can be explained by local time, but were not the UTC publication dates. No timestamps are rewritten on GitHub.
- Move directory-path support in `config()` from 1.3.0 to 1.0.0: it is already in `v1.0.0:src/functions.php`; `v1.2.0..v1.3.0` does not change that file. Preserve its description without inventing a PR.
- PR #2 delivered PhpFileConfig, persistence safeguards and the falsy-value fix; #3 delivered the compatibility alias and Sphinx underline correction. The historical notes imported by #2 do not make it the delivery PR for older behavior. PHP 8.3/Sphinx baseline work was integrated directly, so those notes retain no PR.
- PR #1 delivered ArrayAccess/remove(); #4 the mascot; #5 both status fixes; #7 list aggregation. Reports deployment required #9, #10 and #11, all within v1.5.0..v1.5.1. The version-preparation role of #9 is not used to attribute unrelated changes.
- v1.6.0: #17 native storage; #13 Wiki publication. #14 removes an obsolete Wiki submodule pointer; retain the existing user-facing Wiki summary rather than add an implementation-only release bullet.
- v1.7.0: #34 skill; #31 cache changes; #32 alias removal; #35 provider normalization; #33 file persistence; #37 directory discovery; #30 container/portable functions; #36 file-cache documentation. #38 only prepared release notes and is not their delivery PR.
- Earlier direct commits prove the existing 1.0.0–1.3.0 descriptions. No corresponding delivery PR was proved; no hashes, authors or issues are inserted as substitute note references. No duplicate versions or conflicting identities were found. Old promises, including the 1.5.0 compatibility alias subsequently removed in 1.7.0, remain in their historical context.

## Reconciliation inventory

File status is the proposed canonical history. Every existing Release requires a body-only update; no creation, deletion, tag move, target change, asset change or draft/prerelease change is planned. All are non-draft, non-prerelease and have no assets at capture time. Dates below are publication evidence, not commit timestamps.

| Tag | Peeled commit SHA | Version | Proven publication (UTC) | CHANGELOG | GitHub action |
| --- | --- | --- | --- | --- | --- |
| v1.7.0 | `038deddd474e87528528539d0668d060939ef973` | 1.7.0 | 2026-10-17T01:39:54Z | present; reviewed | PATCH body |
| v1.6.0 | `3282f877808a23382cd41f4ed30946fcc10ec515` | 1.6.0 | 2026-10-16T19:19:22Z | present; reviewed | PATCH body |
| v1.5.1 | `7a85fa548bb10f81550d4d9b576c337a765f73ca` | 1.5.1 | 2026-10-09T18:05:12Z | present; reviewed | PATCH body |
| v1.5.0 | `bee3ac57a9f7f0a377022d5723c71e7c9bea4242` | 1.5.0 | 2026-09-17T23:46:33Z | present; reviewed | PATCH body |
| v1.4.0 | `930434f7f7aaf02d551eb53ab86ed25e6511522c` | 1.4.0 | 2025-06-11T14:38:34Z | present; reviewed | PATCH body |
| v1.3.0 | `eccc7bb8d0cd566b4001462c8e6726b295f0da44` | 1.3.0 | 2025-05-24T16:41:44Z | present; reviewed | PATCH body |
| v1.2.0 | `89385178faece2292ee8f3fbeb0eb953f70a120f` | 1.2.0 | 2025-05-24T02:06:08Z | present; reviewed | PATCH body |
| v1.1.4 | `36ae2c0da0f895adc5b105f08b0871c4a841a7ec` | 1.1.4 | 2025-04-20T15:19:41Z | present; reviewed | PATCH body |
| v1.1.3 | `795abbee1b5bba6ef08e1d2ce9fdd7e32f5c68da` | 1.1.3 | 2025-04-20T03:38:06Z | present; reviewed | PATCH body |
| v1.1.2 | `53818d6da40ff061c603abedaf656834ca179b3e` | 1.1.2 | 2025-04-19T15:46:55Z | present; reviewed | PATCH body |
| v1.1.1 | `f8d530ece45dee2a3308b58a32fe9a46ae6baa14` | 1.1.1 | 2025-04-18T23:32:42Z | present; reviewed | PATCH body |
| v1.1.0 | `d54a476287f280b8c3f964a279299778acd089e2` | 1.1.0 | 2025-04-18T22:46:15Z | present; reviewed | PATCH body |
| v1.0.0 | `6a83377fdaff883b7825760cd9191b5f5084aa88` | 1.0.0 | 2025-04-18T19:10:42Z | present; reviewed | PATCH body |

## Concrete Release differences and authorized follow-up

The new body for each row is exactly the output of the selected runtime's `notes VERSION --source=tags --repository=php-fast-forward/config`. All body links are inline; no footer context is required. The version heading and document footer are excluded by the official extractor. New PR links are added to 1.4.0–1.7.0; older empty/single-line bodies receive the existing curated categories and descriptions. 1.3.0 loses the misattributed directory-support note, which moves to 1.0.0. 1.6.0 loses its extra **Full Changelog** line. No automatic authors, commit lists or second generated change list are included.

Run the read-only planner from a clean, fetched checkout with the exact reviewed CLI:

```sh
python3 scripts/plan-release-reconciliation.py --changelog-bin /absolute/path/to/verified/v0.2.0/changelog
```

It verifies every remote tag (including annotated tag peeling), ancestry, exact canonical HEAD and clean history, extracts the official bodies, and emits per-version unified diffs, original/desired SHA-256, Release ID/flags/assets and a minimal API request. It never writes to GitHub. This PR's concrete JSON plan is also retained with the task review artifacts; regenerate it after integration to bind it to the actual merged SHA.

The library's publication validator is intended for approved version transactions and refuses divergent published notes. It is not used to overwrite historical Releases. After explicit reconciliation authorization AND integration of the canonical history, fetch and verify the merged SHA, reviews, unresolved threads and gates. Rerun this planner there, compare the fresh Release snapshots, and review each request. Execute only the approved `PATCH /repos/php-fast-forward/config/releases/{id}` with JSON `{"body": "<official notes bytes>"}`; do not send tag_name, target_commitish, name, flags or assets. If a missing Release is found later, stop and review that case separately, including that creation now does not establish historical publication. Finally GET each affected Release and compare body bytes plus protected metadata/assets. Preserve any concurrent changes and investigate instead of overwriting them.

## Maintenance authority and validation

This is a normal human migration PR with one independent patch fragment. Central-history edits use the library's legitimate `changelog-maintenance` policy: a live label grant by a GitHub User with verified maintain/admin permission, matching numeric ID/login and the exact PR HEAD. The investigating account is `coisa`, User ID `426835`; the collaborator API reports `permission: admin`, `role_name: admin`. Verify the actual label timeline grant and current permission again for the published head. No trailers, Bot signatures or managed-branch ownership are fabricated. The caller does not disable any check. On initial adoption, privileged feedback only becomes available from the approved base workflow after integration.

No managed version PR exists to regenerate. After approved integration, let the automation generate its own version PR and independently verify Bot ownership, signed head, current base and consumed fragments. Do not manually populate or edit its branch.

Validation results are recorded in the PR after the final SHA is published. Applicable consumer cases are manual history, correct reference footer, empty pending section, published versions, divergent Releases, known/unknown PR references and next-version generation. No-file/no-tag and Changesets/mixed cases do not describe this consumer; relevant upstream coverage at the selected SHA is in `tests/History/HistoryCodecTest.php`, `HistoryReferenceBuilderTest.php`, `Import/HistoryImporterTest.php`, `tests/Release/ReleaseHistoryConsolidatorTest.php`, `tests/Integration/AutomaticPullRequestReferenceTest.php`, `tests/Console/Command/NotesCommandTest.php` and `tests/Automation/Policy/PullRequestPolicyTest.php`. Those tests are evidence of upstream coverage, not a claim they ran in this consumer.

No unresolved version/date identity remains after choosing UTC dates. Unproved historical PR identities deliberately remain absent. Merge, generated-version integration, publication enablement and all real Release writes still require explicit authorization.
