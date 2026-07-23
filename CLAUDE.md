# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

All commands run from the `cd-lookup/` subdirectory (the plugin root).

```bash
# Install dependencies
composer install

# Run all tests
vendor/bin/phpunit tests/

# Run a single test class
vendor/bin/phpunit tests/LookupDistrictTest.php

# Run the scraper as a CLI tool
php src/LookupDistrict.php "225 Baker St NW, Atlanta, GA 30313"
```

## Architecture

This is a WordPress plugin that looks up U.S. congressional representatives by street address: the district number comes from the Census Bureau's geocoder, and the representative/senator details are scraped from govtrack.us.

**Data flow:**
1. User submits address via the `[cd_lookup]` shortcode form
2. Inline JS POSTs to the WordPress REST endpoint `POST /wp-json/cd-lookup/v1/representatives`
3. `cd-lookup.php` resolves the district via its own `cd_lookup_get_district($address)` (caches the result per address, WP transient, 1 day TTL) and fetches the district page via its own `cd_lookup_fetch_html($url)` (caches per URL, 1 hour TTL); both otherwise delegate to `src/LookupDistrict.php`:
   - `get_district($address)` — calls the Census geocoder (`geocoding.geo.census.gov`) and returns `[$state, $district_number]`
   - `district_page_url($state, $district)` — builds the govtrack district page URL, omitting the district segment for at-large ("0") districts
   - `fetch_html($url)` — fetches the govtrack district page HTML via cURL
   - `parse_reps($html)` — parses the HTML with DOMDocument/XPath, returns `{ senators: [...], representatives: [...] }`
4. Result is rendered in the browser by `renderResults()` in `templates/lookup-form.php`

**Key files:**
- `cd-lookup.php` — plugin entry point; registers REST route and `[cd_lookup]` shortcode
- `src/LookupDistrict.php` — all scraping/parsing logic as global functions; also runnable as a CLI script
- `templates/lookup-form.php` — HTML form + inline vanilla JS REST client
- `tests/bootstrap.php` — WordPress stub functions and HTTP stub functions (overrides cURL-based functions before `LookupDistrict.php` loads, using PHP's `function_exists` guards)
- `tests/data/12th_congressional_district.html` — HTML fixture used by tests

**Testing approach:** Tests never hit the network. `bootstrap.php` defines stub implementations of `get_district` and `fetch_html` before `LookupDistrict.php` is loaded; the `function_exists` guards in `LookupDistrict.php` cause the real cURL implementations to be skipped. `bootstrap.php` also stubs `get_transient`/`set_transient` (backed by an in-memory `$GLOBALS['stub_transients']` array), which `cd_lookup_get_district()`/`cd_lookup_fetch_html()` in `cd-lookup.php` depend on for their caching. The fixture file provides real govtrack HTML for `parse_reps` tests.
