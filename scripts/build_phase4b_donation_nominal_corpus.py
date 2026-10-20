"""Build market-value donation cases for the versioned Phase 4B bundle."""

import json
from pathlib import Path


root = Path(__file__).resolve().parents[1] / "resources/fiscal"
bundle = json.loads((root / "bundles/consumption-phase4b-2026.09.28-17.json").read_text(encoding="utf-8"))
base = json.loads((root / "scenarios/consumption-phase4b-2026.09.28-16/donation-tax-market-value-2026.json").read_text(encoding="utf-8"))
target = root / "scenarios/consumption-phase4b-2026.09.28-17"
target.mkdir(exist_ok=True)
cases = {
    "donation-market-nominal-2026": {},
    "donation-market-destination-proof-missing-2026": {"rtc.destination_evidence_id": None},
    "donation-market-unregistered-2026": {"rtc.destination_registration_status": "UNREGISTERED"},
    "donation-market-special-treatment-2026": {"rtc.tax_treatment": "REDUCED_30"},
    "donation-market-rate-missing-2027": {"rtc.operation_date": "2027-09-25"},
}
for name, overrides in cases.items():
    scenario = json.loads(json.dumps(base))
    scenario["scenario_id"] = name
    scenario["knowledge_at"] = "2026-09-28T18:46:00-03:00"
    scenario["rule_bundle_id"] = bundle["bundle_id"]
    scenario["catalog_versions"] = bundle["catalog_versions"]
    scenario["facts"]["rtc.destination_registration_status"] = "REGISTERED"
    for key, value in overrides.items():
        if value is None:
            scenario["facts"].pop(key, None)
        else:
            scenario["facts"][key] = value
    scenario["competence_date"] = scenario["facts"]["rtc.operation_date"]
    path = target / f"{name}.json"
    payload = json.dumps(scenario, ensure_ascii=False, indent=2) + "\n"
    if path.exists() and path.read_text(encoding="utf-8") != payload:
        raise RuntimeError(f"Refusing to overwrite changed artifact: {path}")
    path.write_text(payload, encoding="utf-8")
print(len(cases))
