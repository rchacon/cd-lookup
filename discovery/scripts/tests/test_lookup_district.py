import pathlib
import pytest
from scripts.lookup_district import parse_reps

DATA = pathlib.Path(__file__).parent / "data"


@pytest.fixture
def ca12_html():
    return (DATA / "12th_congressional_district.html").read_text()


def test_parse_reps_returns_senators_and_representatives_keys(ca12_html):
    result = parse_reps(ca12_html)
    assert "senators" in result
    assert "representatives" in result


def test_parse_reps_senator_count(ca12_html):
    result = parse_reps(ca12_html)
    assert len(result["senators"]) == 2


def test_parse_reps_representative_count(ca12_html):
    result = parse_reps(ca12_html)
    assert len(result["representatives"]) == 1


def test_parse_reps_senator_names(ca12_html):
    senators = parse_reps(ca12_html)["senators"]
    names = [s["full_name"] for s in senators]
    assert 'Alejandro “Alex” Padilla' in names
    assert "Adam Schiff" in names


def test_parse_reps_representative_name(ca12_html):
    reps = parse_reps(ca12_html)["representatives"]
    assert reps[0]["full_name"] == "Lateefah Simon"


def test_parse_reps_person_fields(ca12_html):
    rep = parse_reps(ca12_html)["representatives"][0]
    assert rep["party"] == "Democrat"
    assert rep["phone"] == "202-225-2661"
    assert rep["website"] == "https://simon.house.gov"
    assert rep["profile_url"] == "/congress/members/lateefah_simon/456974"


def test_parse_reps_senator_fields(ca12_html):
    padilla = next(s for s in parse_reps(ca12_html)["senators"] if "Padilla" in s["full_name"])
    assert padilla["party"] == "Democrat"
    assert padilla["phone"] == "202-224-3553"
    assert padilla["website"] == "https://www.padilla.senate.gov"
    assert "Senior Senator" in padilla["role"]


def test_parse_reps_empty_html():
    result = parse_reps("<html></html>")
    assert result == {"senators": [], "representatives": []}
