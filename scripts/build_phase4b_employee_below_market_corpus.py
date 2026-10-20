"""Build the versioned employee below-market scenario corpus."""

import json
from pathlib import Path

root = Path(__file__).resolve().parents[1] / "resources/fiscal"
base = json.loads((root / "scenarios/consumption-phase4b-2026.09.28-1/non-onerous-employee-below-market-2026.json").read_text(encoding="utf-8"))
bundle = json.loads((root / "bundles/consumption-phase4b-2026.09.28-8.json").read_text(encoding="utf-8"))
target = root / "scenarios/consumption-phase4b-2026.09.28-8"
target.mkdir(exist_ok=True)

cases = {
    "employee-below-market-nominal-2026": {},
    "employee-below-market-price-conflict-2026": {"rtc.gross_amount": "1000.00", "operation.amount": "1000.00"},
    "employee-below-market-evidence-missing-2026": {"rtc.market_value_evidence_id": None},
}
for name, overrides in cases.items():
    scenario = json.loads(json.dumps(base))
    scenario["scenario_id"] = name
    scenario["knowledge_at"] = "2026-09-28T13:13:00-03:00"
    scenario["rule_bundle_id"] = bundle["bundle_id"]
    scenario["catalog_versions"] = bundle["catalog_versions"]
    scenario["facts"]["rtc.consideration"] = "ONEROUS"
    for key, value in overrides.items():
        if value is None:
            scenario["facts"].pop(key, None)
        else:
            scenario["facts"][key] = value
    path = target / f"{name}.json"
    payload = json.dumps(scenario, ensure_ascii=False, indent=2) + "\n"
    if path.exists() and path.read_text(encoding="utf-8") != payload:
        raise RuntimeError(f"Refusing to overwrite changed scenario: {path}")
    path.write_text(payload, encoding="utf-8")
    print(path.name)
