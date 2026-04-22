Initial discovery work lives here.

# Installation

```
pip install -r requirements.txt
```

# Usage

```
$ python scripts/lookup_district.py "3506 MacArthur Blvd, Oakland, CA 94619"
{   'representatives': [   {   'full_name': 'Lateefah Simon',
                               'party': 'Democrat',
                               'phone': '202-225-2661',
                               'profile_url': '/congress/members/lateefah_simon/456974',
                               'role': "Representative for California's 12th "
                                       'congressional district',
                               'website': 'https://simon.house.gov'}],
    'senators': [   {   'full_name': 'Alejandro “Alex” Padilla',
                        'party': 'Democrat',
                        'phone': '202-224-3553',
                        'profile_url': '/congress/members/alejandro_padilla/456856',
                        'role': 'Senior Senator for California',
                        'website': 'https://www.padilla.senate.gov'},
                    {   'full_name': 'Adam Schiff',
                        'party': 'Democrat',
                        'phone': '202-224-3841',
                        'profile_url': '/congress/members/adam_schiff/400361',
                        'role': 'Junior Senator for California',
                        'website': 'https://www.schiff.senate.gov'}]}
```

# Testing

Install test dependencies and run the test suite from the project root:

```
pip install -r requirements_test.txt
python -m pytest scripts/tests/
```
