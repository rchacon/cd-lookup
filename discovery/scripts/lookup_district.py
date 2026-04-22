import pprint

from bs4 import BeautifulSoup
import requests


URL = "https://www.govtrack.us/"
URL_FOR_TOKEN = f"{URL}_twostream/user-head?path=/"
DISTRICT_ENDPOINT = f"{URL}congress/members/lookup-district.json"


def get_token() -> str:
    """Fetch a CSRF token from govtrack.us required for authenticated POST requests."""
    resp = requests.get(URL_FOR_TOKEN)

    token = resp.cookies["csrftoken"]

    return token


def get_district(address: str, token: str) -> tuple[str, int]:
    """Return the congressional district state and number as a tuple for the given address."""
    resp = requests.post(DISTRICT_ENDPOINT, data={
        "address": address},
        headers={"Referer": URL, "x-csrftoken": token},
        cookies={"csrftoken": token},
    )
    data = resp.json()

    return  data["state"], data["number"]


def parse_reps(html: str) -> dict[str, list[dict]]:
    """Parse a govtrack.us district page and return senators and representatives.

    Returns a dict with keys 'senators' and 'representatives', each a list of
    dicts with keys: full_name, role, party, phone, website, profile_url.
    """
    soup = BeautifulSoup(html, 'html.parser')

    senators = []
    representatives = []

    for row in soup.find_all('div', class_='row', style=lambda s: s and 'margin-bottom: 1.5em' in s):
        info_div = row.find('div', class_='col-sm-9')
        if not info_div:
            continue

        name_tag = info_div.find('a', style=lambda s: s and 'font-weight: bold' in s)
        if not name_tag:
            continue

        full_name = name_tag.get_text(strip=True)
        profile_url = name_tag.get('href', '')

        child_divs = info_div.find_all('div', recursive=False)
        role = child_divs[1].get_text(strip=True) if len(child_divs) > 1 else ''

        party_div = info_div.find('div', style=lambda s: s and 'margin-bottom: .45em' in s)
        party = party_div.get_text(strip=True) if party_div else ''

        phone_tag = info_div.find('a', href=lambda h: h and h.startswith('tel:'))
        phone = phone_tag.get_text(strip=True) if phone_tag else ''

        website = ''
        spanbullets = info_div.find('div', class_='spanbullets')
        if spanbullets:
            website_tag = spanbullets.find('a', href=lambda h: h and not h.startswith('tel:'))
            if website_tag:
                website = website_tag.get('href', '')

        person = {
            'full_name': full_name,
            'role': role,
            'party': party,
            'phone': phone,
            'website': website,
            'profile_url': profile_url,
        }

        if 'Senator' in role:
            senators.append(person)
        else:
            representatives.append(person)

    return {
        'senators': senators,
        'representatives': representatives,
    }


def main(address: str) -> None:
    """Look up and display congressional representatives for the given street address."""
    token = get_token()
    state, district = get_district(address, token)
    resp = requests.get(f"{URL}congress/members/{state}/{district}")
    reps = parse_reps(resp.text)

    pprint.pprint(reps, indent=4)



if __name__ == '__main__':
    import argparse

    parser = argparse.ArgumentParser()
    address = parser.add_argument("address", help="Enter your address")
    args = parser.parse_args()
    main(args.address)
