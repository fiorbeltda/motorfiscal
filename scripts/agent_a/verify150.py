"""Independent exact Fraction oracle for measured property locality and road jurisdiction shares."""
import hashlib
import json
from collections import defaultdict
from fractions import Fraction
from pathlib import Path
from xml.etree import ElementTree
from evidence_io import preserve_bytes, preserve_json

ROOT = Path(__file__).resolve().parents[2]
FOLDER = Path("D:/ProjetoSaasExports/agent-a-A150-territorial-measures-20261103-v1")
OLD = Path("D:/ProjetoSaasExports/agent-a-A149-service-locality-20261103-v1")


def sha(raw):
    return hashlib.sha256(raw).hexdigest()


def canonical_hash(value):
    return sha(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":"), allow_nan=False).encode("utf-8"))


def load(name):
    return json.loads((FOLDER / name).read_text(encoding="utf-8-sig"))


def weight(share):
    return Fraction(share["numerator"]) / Fraction(share["denominator"])


def suite(filename):
    path = ROOT / "storage/app" / filename
    xml = ElementTree.parse(path)
    assert not xml.findall(".//failure") and not xml.findall(".//error")
    cases = xml.findall(".//testcase")
    preserve_bytes(FOLDER / filename, path.read_bytes())
    return {"file": filename, "tests": len(cases), "assertions": sum(int(case.get("assertions", "0")) for case in cases),
            "sha256": sha(path.read_bytes())}


def main():
    cases = load("native-cases-v1.json")["observations"]
    assert {case["case"] for case in cases} == {"property", "road", "property_high_precision"}
    oracles = []
    for case in cases:
        report = case["report"]
        operation = report["operation_input"]
        assert report["status"] == "LOCAL_TERRITORIAL_LOCATION_QUALIFIED" and case["replay"] is True
        grouped = defaultdict(Fraction)
        for location in operation["locations"]:
            grouped[(location["uf"], location["municipality_ibge"])] += Fraction(location["measure"])
            assert canonical_hash(location["evidence"]["record"]) == location["evidence"]["record_hash"]
        total = sum(grouped.values(), Fraction())
        assert total == Fraction(operation["territorial_measurements"]["total"]) == Fraction(report["total_measure"])
        actual = {(row["uf"], row["municipality_ibge"]): Fraction(row["measure"]) for row in report["measured_territories"]}
        assert actual == grouped
        if case["case"].startswith("property"):
            maximum = max(grouped.values())
            winners = [key for key, value in grouped.items() if value == maximum]
            assert len(winners) == 1
            uf, municipality = winners[0]
            assert report["locality"] == {"uf": uf, "municipality_ibge": municipality}
            assert len(report["jurisdiction_allocations"]) == 1
            assert weight(report["jurisdiction_allocations"][0]["share"]) == 1
            assert weight(report["state_allocations"][0]["share"]) == 1
            oracles.append({"case": case["case"], "winner": municipality, "winning_area_exact": str(maximum),
                            "whole_supply_share": "1", "area_aggregated_before_selection": True})
        else:
            by_state = defaultdict(Fraction)
            for (uf, _), measure in grouped.items():
                by_state[uf] += measure
            for allocation in report["jurisdiction_allocations"]:
                key = (allocation["uf"], allocation["municipality_ibge"])
                assert weight(allocation["share"]) == grouped[key] / total
            for allocation in report["state_allocations"]:
                assert weight(allocation["share"]) == by_state[allocation["uf"]] / total
            assert sum((weight(row["share"]) for row in report["jurisdiction_allocations"]), Fraction()) == 1
            assert sum((weight(row["share"]) for row in report["state_allocations"]), Fraction()) == 1
            assert any(weight(row["share"]) == Fraction(1, 3) for row in report["jurisdiction_allocations"])
            oracles.append({"case": "road", "municipal_weights": [str(weight(row["share"])) for row in report["jurisdiction_allocations"]],
                            "state_weights": {row["uf"]: str(weight(row["share"])) for row in report["state_allocations"]},
                            "sums_separately_equal_one": True, "no_rounded_percent": True})
        assert report["input_hash"] == canonical_hash(operation)
        claimed = report["report_hash"]
        payload = dict(report)
        del payload["report_hash"]
        assert claimed == canonical_hash(payload)
        assert report["tax_rate_selected"] is False and report["source_record_authenticity_proven"] is False
    assert (FOLDER / "cli-road-first.json").read_bytes() == (FOLDER / "cli-road-second.json").read_bytes()
    assert load("cli-road-first.json")["replay_matches"] is True
    assert load("cli-property.json")["replay_matches"] is True
    tie = load("cli-property-tie.json")["report"]
    assert tie["status"] == "ACTION_REQUIRED" and tie["reason"] == "LOCALITY150_PROPERTY_LARGEST_AREA_TIE" and tie["locality"] is None
    assert (FOLDER / "cli-A149-after.json").read_bytes() == (OLD / "cli-first.json").read_bytes()
    assert load("cli-exits.json") == {"native": 0, "road_first": 0, "road_second": 0, "property": 0, "tie": 1, "legacy": 0}
    assert load("phpstan-global-v1-exit.json") == {"phpstan": 0}
    assert load("pint-final-v3-exit.json") == {"pint": 0}
    assert load("regression-v3-exit.json") == {"regression_exit": 0}
    preflight = load("preflight.json")
    assert preflight["class_sha256"] == sha((FOLDER / "ServiceLocality149-before.php").read_bytes())
    assert preflight["command_sha256"] == sha((FOLDER / "QualifyServiceLocality149Command-before.php").read_bytes())
    directed = suite("agent-a-A150-directed-v2.junit.xml")
    regression = suite("agent-a-A150-regression-v3.junit.xml")
    receipt = {"task": "A150", "scope": "4B.2 locality/4B.4 precision; property largest municipal area and road exact territory weights",
               "fraction_oracles": oracles, "directed_includes_A149_do_not_add": directed,
               "regression_includes_directed_do_not_add": regression, "legacy_A149_cli_bytes_unchanged": True,
               "native_road_cli_identical": True, "native_whole_report_replay": True,
               "phpstan_global_exit": 0, "pint_four_php_files_exit": 0,
               "code_before_sha256": preflight["class_sha256"],
               "code_after_sha256": sha((ROOT / "app/Modules/Fiscal/ConsumptionReform/ServiceLocality149.php").read_bytes()),
               "source_record_authenticity_proven": False, "classification_catalog_integration": "PENDING_AGENT_B",
               "whole_requirement_accepted": False, "full_regression_executed": False, "production_ready": False}
    destination = ROOT / "docs/fiscal/agent-a/20261103/validation-A150.json"
    preserve_json(destination, receipt)
    print(json.dumps({"task": "A150", "exact_fraction_cases": len(oracles), "directed": directed,
                      "regression": regression, "legacy_A149_preserved": True}))


if __name__ == "__main__":
    main()
