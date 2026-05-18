# Releasing

This module uses [release-please](https://github.com/googleapis/release-please)
for fully automated semantic-version releases. **Do not bump
`composer.json` `version` by hand and do not create `vX.Y.Z` tags
manually** — the bot owns both.

## How a release happens

1. You merge feature/fix PRs into `main` using
   [Conventional Commits](https://www.conventionalcommits.org/) for the
   commit (or PR title, when squash-merging).
2. On every push to `main`, the `Release` workflow runs
   `googleapis/release-please-action`, which keeps a single
   **release PR** (titled e.g. `chore(main): release 1.1.0`) up to
   date. That PR contains:
   - the new `version` in `composer.json`
   - a generated `CHANGELOG.md` entry
   - a bumped `.release-please-manifest.json`
3. When you merge the release PR, the action creates the matching git
   tag (`v1.1.0`) and a GitHub Release with the changelog body.

## Commit message rules

| Prefix          | Bump      | Appears in changelog as |
|-----------------|-----------|-------------------------|
| `feat: …`       | minor     | **Features**            |
| `fix: …`        | patch     | **Bug Fixes**           |
| `perf: …`       | patch     | **Performance**         |
| `refactor: …`   | patch     | **Refactor**            |
| `docs: …`       | none      | **Documentation**       |
| `chore: …`      | none      | hidden                  |
| `feat!: …` or trailer `BREAKING CHANGE:` | major | **Features** + `!` marker |

When using **squash-merge** (recommended), only the PR title needs the
prefix. The merge commit's first line becomes the changelog entry.

## Manual override (rare)

If you need to force a specific version (e.g. correcting a bad bump),
add `Release-As: X.Y.Z` to a commit body on `main`. The bot will pick
that up on the next run.

## The v1.0.0 bootstrap

The 1.0.0 entry in [`CHANGELOG.md`](./CHANGELOG.md) was written by
hand because release-please needs a baseline version to start from.
From v1.0.1 onwards every entry is generated from Conventional Commits —
do not append to the CHANGELOG manually except for an emergency
correction.

If release-please's first PR for this repo proposes a version other
than 1.0.0, set `.release-please-manifest.json` to `{ ".": "1.0.0" }`
on `main` (or pass `--initial-version 1.0.0` to the CLI) so the bot
picks up from where the hand-written entry ends.
