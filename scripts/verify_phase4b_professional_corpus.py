"""Verify independently specified professional outcomes before recording immutable hashes."""

import json
import subprocess
from pathlib import Path

root = Path(__file__).resolve().parents[1]
version = "2026.10.01-4"
bundle = f"bundles/consumption-phase4b-{version}.json"


def execute(command):
    completed = subprocess.run(["docker", "compose", "exec", "-T", "app", "php", "artisan", *command],
        cwd=root, capture_output=True, text=True, encoding="utf-8", check=False)
    value = json.loads(completed.stdout)
    if value.get("status") == "ERROR":
        raise RuntimeError(value)
    return value


def capture(family, name, value):
    expected = {key: value[key] for key in ["status", "reason_codes", "missing_facts", "decision_hash"]}
    path = root / f"resources/fiscal/expected/{family}-{version}/{name}.json"
    payload = json.dumps(expected, ensure_ascii=False, indent=2) + "\n"
    if path.exists() and path.read_text(encoding="utf-8") != payload:
        raise RuntimeError(f"Refusing to overwrite {path}")
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(payload, encoding="utf-8")


cases = {
    "lawyer-reduced-2026": "VALID", "accountant-reduced-2026": "VALID",
    "lawyer-individual-reduced-2026": "VALID", "lawyer-standard-2026": "VALID",
    "lawyer-legal-partner-conflict-2026": "INVALID",
    "lawyer-council-missing-2026": "ACTION_REQUIRED", "lawyer-reduced-rate-gap-2027": "ACTION_REQUIRED",
}
for name, status in cases.items():
    value = execute(["fiscal:rtc:run", f"resources/fiscal/scenarios/consumption-phase4b-{version}/{name}.json", f"--bundle-manifest={bundle}"])
    assert value["status"] == status, (name, value)
    if status == "VALID":
        amounts = ("1.00", "9.00") if name == "lawyer-standard-2026" else ("0.70", "6.30")
        assert value["decision"]["ibs"]["nominal_amount"] == amounts[0]
        assert value["decision"]["cbs"]["nominal_amount"] == amounts[1]
        assert value["missing_facts"] == []
    elif status == "INVALID":
        assert value["reason_codes"] == ["RTC_PROFESSIONAL_REDUCTION_NOT_ELIGIBLE"]
    elif name == "lawyer-council-missing-2026":
        assert value["reason_codes"] == ["RTC_PROFESSIONAL_REDUCTION_PROOF_MISSING"]
        assert value["missing_facts"] == ["rtc.professional_council_evidence_id"]
    else:
        assert value["reason_codes"] == ["RTC_RATE_NOT_PROVEN:CBS"], value["reason_codes"]
    capture("consumption-phase4b", name, value)

for profession in ["lawyer", "accountant"]:
    name = f"integrated-{profession}-reduced-2026"
    value = execute(["fiscal:scenario:run", f"resources/fiscal/scenarios/consumption-integrated-{version}/{name}.json", f"--bundle-manifest={bundle}"])
    assert value["status"] == "VALID_WITH_WARNINGS" and value["missing_facts"] == []
    decision = value["decision"]
    assert decision["iss"]["calculation"]["tax_amount"] == "50.00"
    assert decision["iss"]["municipal_parameters"]["source_type"] == "FIXTURE"
    assert decision["federal_withholdings"]["total_withholding"] == "61.50"
    assert decision["consumption_reform"]["decision"]["ibs"]["nominal_amount"] == "0.70"
    assert decision["consumption_reform"]["decision"]["cbs"]["nominal_amount"] == "6.30"
    capture("consumption-integrated", name, value)
print("Verified 7 RTC and 2 integrated professional cases")
