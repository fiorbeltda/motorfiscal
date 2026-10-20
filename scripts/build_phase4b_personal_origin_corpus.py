"""Build scenarios for the versioned art. 5, I, b origin guard."""

import json
from pathlib import Path


root = Path(__file__).resolve().parents[1] / "resources/fiscal"
bundle = json.loads((root / "bundles/consumption-phase4b-2026.09.28-13.json").read_text(encoding="utf-8"))
base = json.loads((root / "scenarios/consumption-phase4b-2026.09.28-8/employee-below-market-nominal-2026.json").read_text(encoding="utf-8"))
target = root / "scenarios/consumption-phase4b-2026.09.28-13"
target.mkdir(exist_ok=True)
cases = {
    "personal-origin-missing-2026": {},
    "personal-origin-acquired-conflict-2026": {
        "rtc.personal_use_supply_origin": "ACQUIRED_FROM_THIRD_PARTY",
        "rtc.personal_use_origin_evidence_id": "supplier-invoice-1",
    },
    "personal-origin-self-provided-2026": {
        "rtc.personal_use_supply_origin": "SELF_PRODUCED_OR_PROVIDED",
        "rtc.personal_use_origin_evidence_id": "service-log-1",
    },
}
for name, overrides in cases.items():
    scenario = json.loads(json.dumps(base))
    scenario["scenario_id"] = name
    scenario["knowledge_at"] = "2026-09-28T17:01:00-03:00"
    scenario["rule_bundle_id"] = bundle["bundle_id"]
    scenario["catalog_versions"] = bundle["catalog_versions"]
    scenario["facts"].update(overrides)
    path = target / f"{name}.json"
    payload = json.dumps(scenario, ensure_ascii=False, indent=2) + "\n"
    if path.exists() and path.read_text(encoding="utf-8") != payload:
        raise RuntimeError(f"Refusing to overwrite changed artifact: {path}")
    path.write_text(payload, encoding="utf-8")
print(len(cases))
