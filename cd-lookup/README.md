# Installation

```
composer install
```

# Usage:

```
$ php LookupDistrict.php "225 Baker St NW, Atlanta, GA 30313"
Array
(
    [senators] => Array
        (
            [0] => Array
                (
                    [full_name] => Jon Ossoff
                    [role] => Senior Senator for Georgia
                    [party] => Democrat
                    [phone] => 202-224-3521
                    [website] => https://www.ossoff.senate.gov
                    [profile_url] => /congress/members/jon_ossoff/456857
                )

            [1] => Array
                (
                    [full_name] => Raphael Warnock
                    [role] => Junior Senator for Georgia
                    [party] => Democrat
                    [phone] => 202-224-3643
                    [website] => https://www.warnock.senate.gov
                    [profile_url] => /congress/members/raphael_warnock/456858
                )

        )

    [representatives] => Array
        (
            [0] => Array
                (
                    [full_name] => Nikema Williams
                    [role] => Representative for Georgia's 5th congressional district
                    [party] => Democrat
                    [phone] => 202-225-3801
                    [website] => https://nikemawilliams.house.gov
                    [profile_url] => /congress/members/nikema_williams/456811
                )

        )

)
```

# Testing

```
vendor/bin/phpunit tests/
```
