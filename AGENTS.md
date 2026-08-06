# AGENTS.md

This file provides guidance to AI coding agents (Claude Code, etc.) when working with code in this repository.

## Commands

All commands run from the `cd-lookup/` subdirectory (the plugin root).

```bash
# Install dependencies
composer install

# Run all tests
vendor/bin/phpunit tests/

# Run a single test class
vendor/bin/phpunit tests/LookupDistrictTest.php

# Run the CLI tool (requires CD_API_KEY -- see cd-lookup/DEVELOPMENT.md)
CD_API_KEY=<key> php src/LookupDistrict.php "225 Baker St NW, Atlanta, GA 30313"
```

## One-time setup

```bash
git config core.hooksPath .githooks  # optional: catches a v* tag/cd-lookup.php
                                      # Version: header mismatch before CI does.
                                      # Repoints ALL git hooks to .githooks, so
                                      # skip this if you use another hooks
                                      # framework (husky, lefthook, pre-commit,
                                      # etc.)
```

## Architecture

This is a WordPress plugin that looks up U.S. congressional representatives by street address: the district number comes from the Census Bureau's geocoder, and the representative/senator details come from [cd-platform](https://github.com/rchacon/cd-platform)'s `cd-api`, not from scraping a third-party site.

**Data flow:**
1. User submits address via the `[cd_lookup]` shortcode form
2. Inline JS POSTs to the WordPress REST endpoint `POST /wp-json/cd-lookup/v1/representatives`
3. `cd-lookup.php` resolves the district via its own `cd_lookup_get_district($address)` (caches the result per address, WP transient, 1 day TTL) and fetches members via its own `cd_lookup_fetch_members($state, $district, $api_key, $endpoint)` (caches per `state:district`, 1 hour TTL); both otherwise delegate to `src/LookupDistrict.php`:
   - `get_district($address)` — calls the Census geocoder (`geocoding.geo.census.gov`) and returns `[$state, $district_number]`
   - `fetch_members($state, $district, $api_key, $endpoint)` — calls cd-api's `GET /members` with an `x-api-key` header, returns `{ senators: [...], representatives: [...] }`
4. The API key comes from the `cd_lookup_api_key` option, set via the Settings → CD Lookup admin page (`src/Settings.php`). The cd-api endpoint URL defaults to a hardcoded constant but can be overridden via the `cd_lookup_api_endpoint` option (e.g. `wp option update cd_lookup_api_endpoint "<url>"`) -- an ops escape hatch, not exposed in the Settings UI.
5. Result is rendered in the browser by `renderResults()` in `templates/lookup-form.php`, which also appends "for the Nth congressional district" to a representative's role when the district isn't at-large.

**Key files:**
- `cd-lookup.php` — plugin entry point; registers REST route and `[cd_lookup]` shortcode
- `src/LookupDistrict.php` — district resolution and cd-api client as global functions; also runnable as a CLI script
- `src/Settings.php` — Settings → CD Lookup admin page for the cd-platform API key
- `templates/lookup-form.php` — HTML form + inline vanilla JS REST client
- `tests/bootstrap.php` — WordPress stub functions and HTTP stub functions (overrides `get_district`/`fetch_members` before `LookupDistrict.php` loads, using PHP's `function_exists` guards)

**Testing approach:** Tests never hit the network. `bootstrap.php` defines stub implementations of `get_district` and `fetch_members` before `LookupDistrict.php` is loaded; the `function_exists` guards in `LookupDistrict.php` cause the real cURL implementations to be skipped. `bootstrap.php` also stubs `get_transient`/`set_transient` (backed by an in-memory `$GLOBALS['stub_transients']` array, used by `cd_lookup_get_district()`/`cd_lookup_fetch_members()`'s caching) and `get_option`/`update_option`/the Settings API (backed by `$GLOBALS['stub_options']`).

## Git conventions

PRs are merged with a merge commit (`gh pr merge --merge`), not squash or
rebase — preserves the individual commit history from the PR branch.
After merging, delete the branch both locally and remotely
(`gh pr merge --merge --delete-branch` does both in one step).

When addressing review comments on an open PR, break the fixes up into
separate commits along logical lines (one commit per distinct issue/fix,
not one commit for everything) rather than a single catch-all commit, and
reply to each review comment on GitHub referencing the specific commit
hash that addressed it, formatted as a hyperlink to the commit rather than
just backticked text (e.g. "Fixed in
[abc1234](https://github.com/<owner>/<repo>/commit/abc1234).") -- keeps
the review thread traceable to the exact change that resolved it, one
click away, rather than a generic "addressed" reply pointing at the whole
PR.

When *submitting* a code review on a PR, post each finding as its own
separate inline review comment (anchored to the specific file/line via
`gh api repos/{owner}/{repo}/pulls/{number}/comments`, not a single bundled
`gh pr comment`) -- a combined comment listing every finding only supports
one flat reply thread, making it impossible to reply to (or resolve)
individual findings separately later.
