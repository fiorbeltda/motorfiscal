"""Verify collection evidence correction, previous valid snapshots and terminal suites."""
import hashlib
import json
import subprocess
from pathlib import Path
from xml.etree import ElementTree
from evidence_io import preserve_bytes, preserve_json

ROOT = Path(__file__).resolve().parents[2]
FOLDER = Path("D:/ProjetoSaasExports/agent-a-A148-collection-evidence-20261103-v1")
CODE = "app/Modules/Fiscal/ConsumptionReform/CollectionDecisionEngine.php"


def sha(raw):
    return hashlib.sha256(raw).hexdigest()


def load(filename):
    return json.loads((FOLDER / filename).read_text(encoding="utf-8-sig"))


def suite(filename, success):
    path = ROOT / "storage/app" / filename
    tree = ElementTree.parse(path)
    failures = tree.findall(".//failure")
    errors = tree.findall(".//error")
    assert (not failures and not errors) == success
    cases = tree.findall(".//testcase")
    preserve_bytes(FOLDER / filename, path.read_bytes())
    return {"file": filename, "tests": len(cases), "failures": len(failures), "errors": len(errors),
            "assertions": sum(int(case.get("assertions", "0")) for case in cases), "sha256": sha(path.read_bytes())}


def main():
    previous = (FOLDER / "CollectionDecisionEngine-before.php").read_bytes()
    assert previous == subprocess.check_output(["git", "show", "HEAD:" + CODE], cwd=ROOT)
    assert previous != (ROOT / CODE).read_bytes()
    before = load("probe-before.json")["observations"]
    after = load("probe-after.json")["observations"]
    assert len(before) == len(after) == 4
    for old, new in zip(before, after, strict=True):
        assert old["field"] == new["field"] and old["value"] == new["value"] == " "
        assert old["rtc"]["status"] == "VALID" and old["full"]["status"] == "VALID_WITH_WARNINGS"
        for runner in ("rtc", "full"):
            assert new[runner]["status"] == "ACTION_REQUIRED"
            assert new["field"] in new[runner]["missing_facts"]
    assert (FOLDER / "cli-valid-before.json").read_bytes() == (FOLDER / "cli-valid-after.json").read_bytes()
    valid = load("cli-valid-after.json")
    assert valid["status"] == "VALID"
    assert valid["decision"]["collection"]["extinction_status"] == "UNCONFIRMED"
    assert load("cli-baseline-replay.json")["replay_matches"] is True
    negative = load("cli-invalid-reference.json")
    assert negative["status"] == "ACTION_REQUIRED" and negative["decision"] == []
    assert "rtc.payment_transaction_id" in negative["missing_facts"]
    assert load("cli-exits.json") == {"before": 0, "after": 0, "blank_reference": 0, "baseline_replay": 0}
    assert load("quality-exits.json") == {"phpstan": 0, "pint": 0}
    assert load("regression-exit.json")["regression_exit"] == 0
    original = suite("agent-a-A148-before.junit.xml", False)
    directed = suite("agent-a-A148-final.junit.xml", True)
    regression = suite("agent-a-A148-regression.junit.xml", True)
    changed_tracked = subprocess.check_output(["git", "diff", "--name-only"], cwd=ROOT, text=True).splitlines()
    assert set(changed_tracked) == {CODE, "app/Modules/Fiscal/ConsumptionReform/TaxPointContext86.php"}
    artifact_hashes = {name: sha((FOLDER / name).read_bytes()) for name in (
        "probe-before.json", "probe-after.json", "cli-valid-before.json", "cli-valid-after.json",
        "cli-invalid-reference.json", "cli-baseline-replay.json", "phpstan-final.log", "pint-final.log")}
    receipt = {"task": "A148", "scope": "4B.3 party and collection reference integrity; no settlement or legal-rule change",
               "code_before_sha256": sha(previous), "code_after_sha256": sha((ROOT / CODE).read_bytes()),
               "native_fields_corrected_in_both_runners": [item["field"] for item in after],
               "before_characterization": original, "directed_suite": directed,
               "regression_includes_directed_do_not_add": regression,
               "previous_valid_cli_bytes_unchanged": True, "previous_valid_snapshot_replay": True,
               "negative_cli_status": "ACTION_REQUIRED", "negative_cli_exit": 0,
               "negative_cli_exit_note": "Existing fiscal:rtc:run uses status, not exit0, to distinguish ACTION_REQUIRED from VALID.",
               "quality_exit_codes": load("quality-exits.json"), "artifact_sha256": artifact_hashes,
               "global_contracts_edited": False, "whole_requirement_accepted": False,
               "full_regression_executed": False, "production_ready": False}
    destination = ROOT / "docs/fiscal/agent-a/20261103/validation-A148.json"
    preserve_json(destination, receipt)
    print(json.dumps({"task": "A148", "directed": directed, "regression": regression,
                      "native_fields_corrected": len(after), "valid_baseline_preserved": True}))


if __name__ == "__main__":
    main()
