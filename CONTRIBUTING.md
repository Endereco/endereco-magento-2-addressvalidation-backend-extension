# Contributor Guidelines

## General Requirements

- **Repository Structure**: One main branch per major target-platform line. Magento is
  currently still on its 2.4.x line, so there is only one branch, `main`, covering the
  whole 2.4.6–2.4.9 / PHP 8.1–8.5 matrix via tooling (`phpstan.2.4.6.neon` …
  `phpstan.2.4.9.neon`, `test_php_versions.sh`). A `main-2.5` (or similar) branch gets
  introduced only once Magento 2.5 actually exists and needs divergent support — not
  before.
- **Contribution Process**: Create a feature branch from `main` and open a pull request
  against `main`.
- **Branch Naming**: `fix/<short-topic>` for bug fixes, `feat/<short-topic>` for new
  functionality.

## Code Quality Standards

Every commit must meet these requirements:

1. **Stable State**: Each commit should leave the codebase in a working, stable state.
2. **Quality Assurance**: Run `composer run qa` — it must pass without errors. This runs
   phpcs, phpmd, phpstan (all four Magento version configs) and the PHP version
   compatibility check in sequence, each against its own isolated `vendor/` (see
   `composer-phpcs.json`, `composer-phpmd.json`, `composer-phpstan.json`).
3. **Module Lifecycle**: `bin/magento module:enable Parc_AddressValidation` /
   `module:disable` followed by `setup:upgrade` must complete cleanly — the Magento
   equivalent of "installable and uninstallable."
4. **Cross-Version Compatibility**: `composer run phpstan` must pass for all four
   supported Magento versions, and `composer run phpcompat` must pass for the full PHP
   8.1–8.5 range. A change that only works on one Magento or PHP version in the matrix
   is not done.
5. **Don't Break Anything**: Smoke-test the change with `composer run serve`
   (`playground.sh`) against at least one affected Magento version before opening a PR —
   `composer run qa` catches style and static-analysis issues, not runtime behavior.

## Commit Message Guidelines

Follow our commit message format:

### Structure
```
Brief description in imperative mood (under 70 characters)

Body:
Explain WHY the changes are needed (4-5 sentences max).
Reference relevant issues with "Refs #N".
Keep lines under 70 characters for readability.
You are writing this text for a reviewer. Don't make his life hard.

Refs #ISSUE-NUMBER
```

### Requirements

**Title:**
- Use English and imperative language ("Add feature" not "Added feature")
- Answer "WHAT?" — describe what the commit does
- Keep under 70 characters
- No issue numbers in the title

**Body:**
- Explain "WHY?" — provide context for the changes
- Reference issues with `Refs #N` (one per line if there are several)
- Use professional, neutral language
- Break lines at ~70 characters
- Include 4-5 sentences maximum

**Example Good Commit:**

```
Correct operator precedence in setStreet fallback

`a . ' ' . b ?? null` parses as `(a . ' ' . b) ?? null` — the
?? fallback never fires and a missing houseNumber key triggers
a PHP warning. Apply the ?? to each component instead.

Refs #8
Refs #7
```

### What to Avoid

- Vague titles like "fixed stuff" or "updates"
- Multiple unrelated changes in one commit
- Missing context about why changes were made
- Unprofessional language or jokes
- Lines exceeding 70 characters
- Mixing different types of changes (bug fixes + new features + refactoring)
- Adding fixes for previous commits. Just amend them yourself. Please.
- Too much text
- Technical details of the implementation, unless they are not understandable from
  reading the code

## Version-Specific Considerations

**Minimum PHP version (8.1, tied to the Magento 2.4.6 baseline):**
- Avoid syntax or standard-library features newer than PHP 8.1 unless the affected code
  path is guarded to run only on Magento versions that require a newer PHP anyway
  (see the PHP-version-per-Magento-version table in `playground.sh`)
- Let `composer run phpcompat` (`test_php_versions.sh`) be the actual judge, not memory

**For changes touching version-sensitive APIs:**
- Magento core APIs, MariaDB, and OpenSearch versions differ across the 2.4.6–2.4.9
  matrix (see the version table at the top of `playground.sh`) — if your change touches
  code that could be affected, test with `composer run serve` against more than one
  Magento version, not just whichever one you happen to have running
- Document which Magento versions you actually tested against in the PR description

## Pull Request Requirements

Before submitting your PR:

1. ✅ All commits follow the message guidelines above
2. ✅ `composer run qa` passes without errors
3. ✅ Module enables/disables and upgrades cleanly (`module:enable`/`module:disable` +
   `setup:upgrade`)
4. ✅ Feature branch created from `main`
5. ✅ Cross-version compatibility tested (`composer run phpstan`, `composer run phpcompat`)
6. ✅ Smoke-tested via `composer run serve` against at least one affected Magento version

## Quality Checklist

Use this checklist for each commit:

- [ ] Commit has a clear, imperative title under 70 characters, no `type(scope):` prefix
- [ ] Body explains the reason/context for the change
- [ ] Professional language used throughout
- [ ] Lines broken at ~70 characters for readability
- [ ] References to relevant issues included (`Refs #N`)
- [ ] Code passes `composer run qa`
- [ ] Module enable/disable and `setup:upgrade` work cleanly
- [ ] Changes are logically grouped (not mixing unrelated modifications)
- [ ] There are no fixes for previous commits in new commits
- [ ] Cross-version compatibility considered and tested

## Getting Help

If you're unsure about any of these requirements or need clarification on the commit
message format, please ask on the issue before starting work. See also the
[Troubleshooting](README.md#troubleshooting) section in the README for common local
setup problems. We're happy to provide guidance to ensure your contribution meets our
standards.

---

*Note: These guidelines ensure code quality, maintainability, and a clear project
history. Following them helps reviewers understand your changes and makes the codebase
easier to maintain long-term.*
