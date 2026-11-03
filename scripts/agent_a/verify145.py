"""Independent Decimal oracle and artifact checks for the specified two-supply CLI fixture."""
import hashlib
import json
import sys
from decimal import Decimal, ROUND_HALF_UP
from pathlib import Path
from xml.etree import ElementTree
from evidence_io import preserve_json

ROOT = Path(__file__).resolve().parents[2]
DEFAULT = Path("D:/ProjetoSaasExports/agent-a-A145-combined-supplies-20261103-v1")


def run():
    folder = Path(sys.argv[1]) if len(sys.argv) > 1 else DEFAULT
    first_bytes = (folder / "cli-first.json").read_bytes()
    assert first_bytes == (folder / "cli-second.json").read_bytes(), "Repeated CLI outputs differ"
    output = json.loads(first_bytes.decode("utf-8-sig"))
    report = output["report"]
    assert output["replay_matches"] is True
    assert report["status"] == "LOCAL_COMBINED_SUPPLIES_ASSESSED"
    assert all(report[field] is False for field in (
        "document_obligation_complete", "production_ready", "extinction_proven",
        "government_evidence_authenticated", "credit_appropriation_authorized", "posting_authorized"))
    totals = {key: Decimal("0.00") for key in ("taxable_base_brl", "ibs_state_brl", "ibs_municipal_brl", "cbs_brl")}
    oracle = []
    for supply in report["supplies"]:
        snapshot = supply["rtc_snapshot"]
        facts = snapshot["input"]["facts"]
        base = Decimal(supply["specified_gross_brl"])
        assert facts["rtc.base_adjustments"] == [], "This oracle fixture expects unadjusted specified values"
        rates = snapshot["knowledge"]["documents"]["rtc_rate_catalog"]["rates"]
        factor = Decimal("0.40") if facts["rtc.tax_treatment"] == "REDUCED_60" else Decimal("1")
        assert facts["rtc.tax_treatment"] in ("STANDARD", "REDUCED_60")
        decision = snapshot["result"]["decision"]
        totals["taxable_base_brl"] += base
        for tax, output_key, actual in (
            ("IBS_STATE", "ibs_state_brl", decision["ibs"]["state_amount"]),
            ("IBS_MUNICIPAL", "ibs_municipal_brl", decision["ibs"]["municipal_amount"]),
            ("CBS", "cbs_brl", decision["cbs"]["nominal_amount"]),
        ):
            matches = [row for row in rates if row["tax"] == tax and row.get("jurisdiction") == "BR"
                       and row["valid_from"] <= facts["rtc.operation_date"] <= row["valid_to"]
                       and row["status"] == "ACTIVE"]
            assert len(matches) == 1
            effective_rate = Decimal(matches[0]["percent"]) * factor
            expected = (base * effective_rate / Decimal("100")).quantize(Decimal("0.01"), rounding=ROUND_HALF_UP)
            assert Decimal(actual) == expected, (supply["id"], tax, actual, expected)
            totals[output_key] += expected
            oracle.append({"supply": supply["id"], "tax": tax, "base": str(base),
                           "standard_percent": matches[0]["percent"], "factor": str(factor),
                           "expected": str(expected), "actual": actual})
    assert all(Decimal(report["totals"][key]) == amount for key, amount in totals.items())
    assert sum(Decimal(row["specified_gross_brl"]) for row in report["supplies"]) == Decimal(report["specified_gross_brl"])
    negative = json.loads((folder / "cli-invalid-sum.json").read_text(encoding="utf-8-sig"))
    assert negative["report"]["reason"] == "COMBINED145_SPECIFIED_VALUES_DO_NOT_RECONCILE"
    assert negative["report"]["totals"] is None
    exit_codes = json.loads((folder / "cli-exits.json").read_text(encoding="utf-8-sig"))
    assert exit_codes == {"first": 0, "second": 0, "invalid_sum": 1}, exit_codes
    suites = []
    for filename in ("agent-a-A144-baseline.junit.xml", "agent-a-A145-final.junit.xml"):
        document = ElementTree.parse(ROOT / "storage/app" / filename)
        cases = document.findall(".//testcase")
        assert not document.findall(".//failure") and not document.findall(".//error")
        suites.append({"file": "storage/app/" + filename, "tests": len(cases),
                       "assertions": sum(int(case.get("assertions", "0")) for case in cases),
                       "sha256": hashlib.sha256((ROOT / "storage/app" / filename).read_bytes()).hexdigest()})
    source_rows = [json.loads(line) for line in (folder / "sources.jsonl").read_text(encoding="utf-8-sig").splitlines()]
    for source in source_rows:
        assert hashlib.sha256(Path(source["path"]).read_bytes()).hexdigest() == source["sha256"]
        assert source["http_status"] == 200
    result = {"task": "A145", "status": "DIRECTED_LOCAL_COMBINED_SUPPLIES_VERIFIED_NOT_FULL_REQUIREMENT_ACCEPTANCE",
              "cli_output_sha256": hashlib.sha256(first_bytes).hexdigest(), "cli_exit_codes": exit_codes,
              "whole_report_replay": True, "identical_cli_runs": True, "decimal_oracles": oracle,
              "suites_separately_reported_not_summed": suites, "official_sources": source_rows,
              "formal_groups_closed": 0, "full_regression_executed": False,
              "combined_document_projection": "PENDING_AGENT_B_INTEGRATION", "production_ready": False}
    destination = ROOT / "docs/fiscal/agent-a/20261103/validation-A145.json"
    preserve_json(destination, result)
    print(json.dumps({"task": "A145", "decimal_oracles": len(oracle), "cli_repeated": True,
                      "suites": suites, "receipt": str(destination)}, ensure_ascii=False))


if __name__ == "__main__":
    run()
