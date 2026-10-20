"""Verify fiscal outcomes before capturing immutable goldens for the local DPS cut."""

import json
import subprocess
from pathlib import Path

root = Path(__file__).resolve().parents[1]
version = "2026.10.01-2"
bundle = f"bundles/consumption-phase4b-{version}.json"


def execute(command):
    completed = subprocess.run(
        ["docker", "compose", "exec", "-T", "app", "php", "artisan", *command],
        cwd=root, capture_output=True, text=True, encoding="utf-8", check=False,
    )
    result = json.loads(completed.stdout)
    if completed.returncode or result.get("status") == "ERROR":
        raise RuntimeError(result)
    return result


def capture(family, name, result):
    value = {key: result[key] for key in ("status", "reason_codes", "missing_facts", "decision_hash")}
    path = root / f"resources/fiscal/expected/{family}-{version}/{name}.json"
    payload = json.dumps(value, ensure_ascii=False, indent=2) + "\n"
    if path.exists() and path.read_text(encoding="utf-8") != payload:
        raise RuntimeError(f"Refusing to overwrite {path}")
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(payload, encoding="utf-8")


for name, required in [("dps-standard-pre-mandatory-2026", False), ("dps-standard-mandatory-2026", True)]:
    result = execute(["fiscal:rtc:run", f"resources/fiscal/scenarios/consumption-phase4b-{version}/{name}.json", f"--bundle-manifest={bundle}"])
    assert result["status"] == "VALID" and result["missing_facts"] == []
    assert result["reason_codes"] == ["RTC_2026_COLLECTION_DISPENSED"]
    decision = result["decision"]
    assert decision["ibs"]["nominal_amount"] == "1.00"
    assert decision["cbs"]["nominal_amount"] == "9.00"
    assert decision["document"]["obligation"]["required_by_schedule"] is required
    capture("consumption-phase4b", name, result)

name = "simples-first-semester-supersession-2027"
result = execute(["fiscal:rtc:run", f"resources/fiscal/scenarios/consumption-phase4b-{version}/{name}.json", f"--bundle-manifest={bundle}"])
assert result["status"] == "ACTION_REQUIRED"
assert "RTC_SIMPLES_SUPERSEDING_ACT_REVIEW_REQUIRED" in result["reason_codes"]
capture("consumption-phase4b", name, result)

name = "dps-integrated-iss-4a-rtc-2026"
result = execute(["fiscal:scenario:run", f"resources/fiscal/scenarios/consumption-integrated-{version}/{name}.json", f"--bundle-manifest={bundle}"])
assert result["status"] == "VALID_WITH_WARNINGS" and result["missing_facts"] == []
decision = result["decision"]
assert decision["iss"]["calculation"]["tax_amount"] == "20.00"
assert decision["federal_withholdings"]["total_withholding"] == "61.50"
assert decision["federal_withholdings"]["pcc"]["total_withholding"] == "46.50"
assert decision["consumption_reform"]["decision"]["ibs"]["nominal_amount"] == "1.00"
assert decision["consumption_reform"]["decision"]["cbs"]["nominal_amount"] == "9.00"
capture("consumption-integrated", name, result)
print("Verified and captured 3 RTC cases and 1 integrated ISS/4A/RTC case")
