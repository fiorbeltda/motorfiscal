"""Build versioned partial-export scenarios for the 2026.09.28-6 bundle."""

import json
from pathlib import Path

root = Path(__file__).resolve().parents[1] / "resources/fiscal"
base = json.loads((root / "scenarios/consumption-phase4b-2026.09.28-1/export-partial-documented-2026.json").read_text(encoding="utf-8"))
bundle = json.loads((root / "bundles/consumption-phase4b-2026.09.28-6.json").read_text(encoding="utf-8"))
target = root / "scenarios/consumption-phase4b-2026.09.28-6"
target.mkdir(exist_ok=True)

cases = {
    "partial-export-domestic-role-missing-2026": {},
    "partial-export-domestic-nominal-2026": {
        "rtc.export_domestic_recipient_id": "33333333000191",
        "rtc.export_domestic_recipient_country": "BR",
        "rtc.export_domestic_document_id": "dps-domestic-1",
    },
    "partial-export-domestic-recipient-conflict-2026": {
        "rtc.export_domestic_recipient_id": "33333333000191",
        "rtc.export_domestic_recipient_country": "US",
    },
}

for name, facts in cases.items():
    scenario = json.loads(json.dumps(base))
    scenario["scenario_id"] = name
    scenario["knowledge_at"] = "2026-09-28T12:04:00-03:00"
    scenario["rule_bundle_id"] = bundle["bundle_id"]
    scenario["catalog_versions"] = bundle["catalog_versions"]
    scenario["facts"]["rtc.destination_basis"] = "RECIPIENT_DOMICILE"
    scenario["facts"].update(facts)
    path = target / f"{name}.json"
    payload = json.dumps(scenario, ensure_ascii=False, indent=2) + "\n"
    if path.exists() and path.read_text(encoding="utf-8") != payload:
        raise RuntimeError(f"Refusing to overwrite changed scenario: {path}")
    path.write_text(payload, encoding="utf-8")
    print(path.name)
