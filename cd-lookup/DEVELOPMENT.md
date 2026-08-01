# Development

Not included in release zips — see `README.md` for plugin installation/usage.

# Installation

```
composer install
```

# Usage:

Requires a cd-platform API key in `CD_API_KEY` (the CLI has no access to
WordPress's stored option, since it runs outside a WP request):

```
$ CD_API_KEY=<your key> php src/LookupDistrict.php "225 Baker St NW, Atlanta, GA 30313"
Array
(
    [senators] => Array
        (
            [0] => Array
                (
                    [full_name] => Raphael Warnock
                    [role] => Senator
                    [party] => DEMOCRATIC
                    [phone] => (202) 224-3643
                    [website] => https://www.warnock.senate.gov
                    [photo_url] => https://www.congress.gov/img/member/w000790_200.jpg
                )

            [1] => Array
                (
                    [full_name] => Jon Ossoff
                    [role] => Senator
                    [party] => DEMOCRATIC
                    [phone] => (202) 224-3521
                    [website] => https://www.ossoff.senate.gov
                    [photo_url] => https://www.congress.gov/img/member/o000174_200.jpg
                )

        )

    [representatives] => Array
        (
            [0] => Array
                (
                    [full_name] => Nikema Williams
                    [role] => Representative
                    [party] => DEMOCRATIC
                    [phone] => (202) 225-3801
                    [website] => https://nikemawilliams.house.gov
                    [photo_url] => https://www.congress.gov/img/member/w000788_200.jpg
                )

        )

)
```

# Testing

```
vendor/bin/phpunit tests/
```

# Releasing

1. Bump the `Version:` header in `cd-lookup.php`.
2. Commit the version bump.
3. Tag the commit and push the tag, e.g.:
   ```
   git tag v0.2.0
   git push origin v0.2.0
   ```

Pushing a `v*` tag triggers the `WordPress Plugin Release` GitHub Actions
workflow (`.github/workflows/wp-release.yml`), which zips the plugin files
(including `README.md`, but not this file) and publishes a GitHub Release
with the zip attached.
