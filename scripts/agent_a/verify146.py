"""Independent principal/accessory oracle; retain the accepted independent-supply CLI bytes."""
import hashlib
import json
from decimal import Decimal, ROUND_HALF_UP
from pathlib import Path
from xml.etree import ElementTree
from evidence_io import preserve_bytes, preserve_json

ROOT = Path(__file__).resolve().parents[2]
EXPORTS = Path("D:/ProjetoSaasExports")
FOLDER = EXPORTS / "agent-a-A146-principal-accessories-20261103-v1"


def main():
    raw = (FOLDER / "cli-first.json").read_bytes()
    assert raw == (FOLDER / "cli-second.json").read_bytes()
    before = EXPORTS / "agent-a-A145-combined-supplies-20261103-v1/cli-first.json"
    assert before.read_bytes() == (FOLDER / "cli-A145-after146.json").read_bytes()
    codes = json.loads((FOLDER / "cli-exits.json").read_text(encoding="utf-8-sig"))
    assert codes == {"first": 0, "second": 0, "invalid": 1, "compatibility": 0}
    output = json.loads(raw.decode("utf-8-sig"))
    report = output["report"]
    assert output["replay_matches"] is True
    assert report["status"] == "LOCAL_COMBINED_SUPPLIES_ASSESSED"
    assert report["fiscal_supply_count"] == len(report["supplies"]) == 1
    assert len(report["accessories"]) == 2
    assert all("value_brl" not in item for item in report["accessories"])
    principal = report["supplies"][0]["rtc_snapshot"]
    base = Decimal(report["operation_input"]["declared_gross_brl"])
    assert base == Decimal("1000.00") == Decimal(report["totals"]["taxable_base_brl"])
    oracle = []
    for tax, key in (("IBS_STATE", "ibs_state_brl"), ("IBS_MUNICIPAL", "ibs_municipal_brl"), ("CBS", "cbs_brl")):
        rows = [row for row in principal["knowledge"]["documents"]["rtc_rate_catalog"]["rates"]
                if row["tax"] == tax and row["status"] == "ACTIVE" and row["jurisdiction"] == "BR"
                and row["valid_from"] <= principal["input"]["facts"]["rtc.operation_date"] <= row["valid_to"]]
        assert len(rows) == 1
        expected = (base * Decimal(rows[0]["percent"]) / Decimal("100")).quantize(Decimal("0.01"), rounding=ROUND_HALF_UP)
        assert expected == Decimal(report["totals"][key])
        oracle.append({"tax": tax, "base": str(base), "rate_percent": rows[0]["percent"],
                       "expected": str(expected), "actual": report["totals"][key]})
    negative = json.loads((FOLDER / "cli-invalid.json").read_text(encoding="utf-8-sig"))
    assert negative["report"]["reason"] == "COMBINED146_ACCESSORY_INDEPENDENCE_OR_TREATMENT_CONFLICT"
    assert negative["report"]["totals"] is None
    junit = ROOT / "storage/app/agent-a-A146-final.junit.xml"
    document = ElementTree.parse(junit)
    cases = document.findall(".//testcase")
    assert not document.findall(".//failure") and not document.findall(".//error")
    preserve_bytes(FOLDER / "directed.junit.xml", junit.read_bytes())
    quality = json.loads((FOLDER / "quality-exits.json").read_text(encoding="utf-8-sig"))
    assert quality == {"phpstan": 0, "pint": 0}
    artifacts = {}
    for filename in ("cli-first.json", "cli-second.json", "cli-invalid.json", "cli-A145-after146.json",
                     "directed.junit.xml", "phpstan-final.log", "pint-final.log", "quality-exits.json"):
        artifacts[filename] = hashlib.sha256((FOLDER / filename).read_bytes()).hexdigest()
    receipt = {"task": "A146", "scope": "principal/accessories; single fiscal supply under art7 II and paragraph2",
               "tests_including_A145_not_added_to_it": len(cases),
               "assertions": sum(int(case.get("assertions", "0")) for case in cases),
               "cli_exit_codes": codes, "identical_cli_runs": True, "report_replay": True,
               "A145_cli_bytes_unchanged": True, "decimal_oracles": oracle,
               "cli_sha256": hashlib.sha256(raw).hexdigest(),
               "junit_sha256": hashlib.sha256(junit.read_bytes()).hexdigest(),
               "evidence_directory": str(FOLDER), "whole_requirement_accepted": False,
               "quality_exit_codes": quality, "artifact_sha256": artifacts,
               "full_regression_executed": False, "document_obligation_complete": False,
               "production_ready": False}
    destination = ROOT / "docs/fiscal/agent-a/20261103/validation-A146.json"
    preserve_json(destination, receipt)
    print(json.dumps({"task": "A146", "tests": len(cases), "assertions": receipt["assertions"],
                      "oracles": len(oracle), "A145_unchanged": True}))


if __name__ == "__main__":
    main()
