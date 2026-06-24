# CONTRIBUTING.md

## Philosophy

This file describes the remote repository workflow and the conventions between the developer, Claude as co-architect, and the coding agent. The objective is that at any moment the repository state reflects exactly what is done, what is in progress, and what comes next.

The coding agent never commits or pushes without explicit developer confirmation. No git step is executed preemptively or automatically.

---

## Branch structure

```
main          — only code that passed the full quality gate
dev           — continuous integration, usual working branch
feature/xxx   — one branch per work unit, branches off dev
spike/xxx     — one branch per infrastructure spike, branches off dev
fix/xxx       — one branch per fix, branches off dev
```

Never commit directly to `main`. Merges to `main` are always from `dev` and only when the quality gate has fully passed.

---

## Workflow for feature branch and spike branch

### Phase 1 — Implementation

```bash
1. Create branch from dev
   git checkout dev && git pull
   git checkout -b feature/unit-name
   # or: git checkout -b spike/spike-name
```

The agent implements the unit on that branch exclusively.

Upon completing implementation, the agent:
- Runs the quality gate and reports the result
- Notifies the developer that implementation is ready
- Waits for developer confirmation before any additional action

The agent does not commit at this point. Does not push. Does not merge. Waits.

### Phase 2 — Smoke tests and fixes

The developer runs the manual smoke tests defined in the unit's task file.

If the developer finds errors or incorrect behavior:
- The agent applies the necessary fixes on the same branch
- Runs the quality gate again and reports results
- Notifies the developer that fixes are ready
- Waits again for developer confirmation

This cycle repeats as many times as necessary until the developer confirms everything works correctly. The agent does not commit between fixes. All changes from the fix phase accumulate without committing until final confirmation.

### Phase 3 — Commit and merge (only after developer confirmation)

When the developer confirms that smoke tests pass and the unit is complete:

```bash
2. Commit the feature branch
   git add .
   git commit -m "feat: unit description"
   # or: git commit -m "spike: spike description"

3. Merge to dev
   git checkout dev
   git pull

   # If there were dirty intermediate commits (WIP, tests, etc.):
   git merge --squash feature/unit-name
   git commit -m "feat: unit description"

   # If the intermediate commits are clean and have useful history:
   git merge --no-ff feature/unit-name

4. Delete the feature branch
   git branch -d feature/unit-name

5. Push dev
   git push origin dev
```

### Phase 4 — Documentation

```bash
6. Update CONTEXT.md with what was implemented in this unit
   — what was done, what decisions were made, what not to touch

7. If there were architectural decisions or changes during implementation,
   update ARCHITECTURE.md before committing

8. Move the task file to tasks/done/

9. Commit the documentation
   git add CONTEXT.md ARCHITECTURE.md tasks/done/
   git commit -m "chore: update CONTEXT.md post feature-XX"
   git push origin dev
```

### Phase 5 — Merge to main and sync

```bash
10. Rebase dev onto main
    git checkout dev
    git rebase main

11. Advance main with fast-forward
    git checkout main
    git merge --ff-only dev

12. Push main
    git push origin main

13. Push dev (the rebase rewrote commits, requires force push)
    git push origin dev --force-with-lease

14. Verify local and remote are aligned
    git fetch --all
    git status
    # both branches should report "up to date"
```

Always use `--force-with-lease` instead of `--force`. The difference: `--force-with-lease` fails if someone pushed changes to the remote branch in the meantime, preventing overwriting someone else's work.

---

## Workflow for fix branch

### Phase 1 — Implementation

```bash
1. Create branch from dev
   git checkout dev && git pull
   git checkout -b fix/fix-name
```

The agent implements the fix on that branch exclusively. Upon completion, runs the quality gate, reports results, and waits for developer confirmation. Does not commit. Waits.

### Phase 2 — Smoke tests and fixes

The developer verifies that the fix resolves the reported issue. If the fix is incomplete or creates new problems, the agent applies fixes, reports, and waits again. This cycle repeats until the developer confirms.

### Phase 3 — Commit and merge (only after developer confirmation)

```bash
2. Commit the fix branch
   git add .
   git commit -m "fix: fix description"

3. Merge to dev
   git checkout dev
   git pull
   git merge --no-ff fix/fix-name

4. Delete the fix branch
   git branch -d fix/fix-name

5. Push dev
   git push origin dev
```

### Phase 4 — Documentation

```bash
6. Update CONTEXT.md if the fix implies something that should not repeat
   or a new technical decision

7. Move the task file to tasks/done/

8. Commit the documentation
   git add CONTEXT.md tasks/done/
   git commit -m "chore: update CONTEXT.md post fix-XX"
   git push origin dev
```

### Phase 5 — Merge to main and sync

Same as the feature branch workflow, steps 10–14.

---

## Quality gate

No exceptions. A unit is not closed until the developer confirms everything below passes.

```bash
composer test           → all Pest tests green
composer analyse        → Larastan level 5 without errors
smoke test              → observable behavior manually verified by the developer
```

The `test` and `analyse` scripts are defined in `composer.json`:

```json
"scripts": {
    "test": "pest",
    "analyse": "phpstan analyse --level=5"
}
```

### Smoke tests

Each task file defines its own manual smoke tests in the "Done criteria" section. Smoke tests are the only way to contrast what the agent says works with what actually works. They are never skipped under any circumstances. In logic-only units without UI, the task file explicitly documents that those unit's smoke tests are verifiable from tinker or from a simple artisan command.

---

## Versioning

### Format

`major.minor.fix` — three dot-separated numbers. Example: `0.1.0`

- **major**: only changes when the developer decides the package has reached a stable state for public release
- **minor**: increments by 1 for each feature or spike task file completed and merged to `dev`
- **fix**: increments by 1 for each fix or refactor task file completed and merged to `dev`

### Where it lives

The version lives exclusively in git tags. The `version` field was removed from `composer.json` because `composer validate --strict` rejects it for packages distributed via Packagist. The agent creates the tag on unit close (Phase 5), after merge to `main`. The tag follows the format `v{major}.{minor}.{fix}`, example: `v0.4.0`.

### Agent decision rule

The agent always asks before incrementing the version:

> Should I bump the minor to `0.X+1.0` or does this feature represent a milestone that deserves a major bump?

Only the developer decides if a feature closes a full cycle. The agent never assumes a major bump without consulting.

### Current version

```
0.2.0 — first public release: Eloquent relationships, whereHas/has/withCount, referential integrity
```

### Internal vs public versioning

The task-file numeration (feature-01 through feature-05) is an internal tracking mechanism
and does not match the public version. Public version `0.2.0` bundles all internal features
(01–05) because features 01–04 were foundational infrastructure with no user-facing value.
Only feature-05 (Eloquent relationships) constitutes the deliverable functionality.

---

## Commit convention

```
feat:      new functionality or completed unit
fix:       bug fix within an in-progress unit
spike:     result of an infrastructure spike
chore:     update of CONTEXT.md, ARCHITECTURE.md, task files
test:      addition or fix of tests without logic changes
refactor:  code restructuring without behavior change
ux:        user experience, look & feel, or view changes
perf:      performance improvement
```

Correct examples:
- `feat: implement JsonConnection with basic CRUD`
- `fix: fix prefixed column normalization in applyWhere`
- `test: cover where operators in WhereOperatorsTest`
- `chore: update CONTEXT.md post feature-01`

Incorrect examples:
- `cambios varios`
- `WIP`
- `Fix bug`

Commit messages and descriptions always in English, imperative mood, concise.

---

## Files always given to the agent

In every new session the agent receives exactly these three files and nothing more:

```
ARCHITECTURE.md          — always, no exception
CONTEXT.md               — always, no exception
tasks/current-unit.md    — only the active unit's task file
```

Task files from future or closed units are never given to the agent.

---

## Task files

They live in `tasks/`. They are archived in `tasks/done/` when the unit is closed. They are never retroactively modified.

### Naming convention

Format: `[type]-[number]-[name].md`

| Type | Prefix | Example |
|------|--------|---------|
| Feature | `feature` | `feature-01-core-driver.md` |
| Spike | `spike` | `spike-01-json-driver.md` |
| Fix | `fix` | `fix-01-where-null.md` |
| Refactor | `refactor` | `refactor-01-storage-interface.md` |

Rules:
- The number always has two digits (01, 02, ..., 15, etc.)
- The name briefly describes the unit in kebab-case
- The agent auto-numbers based on the last file of the same type in `tasks/done/`

---

## 3-attempt rule

If the agent fails to solve a problem after 3 concrete prompts, it stops. The developer manually intervenes in that specific part, stabilizes, and the agent resumes with what follows. Persistent bugs are almost always a design problem, not a code problem.
