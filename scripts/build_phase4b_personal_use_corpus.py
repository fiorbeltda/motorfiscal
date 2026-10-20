"""Build versioned article 5 personal-use recipient scenarios."""

import json
from pathlib import Path


root = Path(__file__).resolve().parents[1] / "resources/fiscal"
bundle = json.loads((root / "bundles/consumption-phase4b-2026.09.28-11.json").read_text(encoding="utf-8"))
base = json.loads((root / "scenarios/consumption-phase4b-2026.09.28-8/employee-below-market-nominal-2026.json").read_text(encoding="utf-8"))
target = root / "scenarios/consumption-phase4b-2026.09.28-11"
target.mkdir(exist_ok=True)
family = {
    "rtc.non_onerous_legal_basis": "LC214_ART5_I_B_2",
    "rtc.recipient_relationship": "SPOUSE_OR_PARTNER",
    "rtc.recipient_relationship_evidence_id": "civil-record-1",
    "rtc.recipient_anchor_role": "ADMINISTRATOR",
    "rtc.recipient_anchor_evidence_id": "company-register-1",
}
cases = {
    "shareholder-below-market-2026": {
        "rtc.recipient_relationship": "SHAREHOLDER",
        "rtc.recipient_relationship_evidence_id": "company-register-1",
    },
    "administrator-free-2026": {
        "rtc.recipient_relationship": "ADMINISTRATOR",
        "rtc.consideration": "NON_ONEROUS", "rtc.gross_amount": "0.00", "operation.amount": "0.00",
    },
    "spouse-below-market-2026": family,
    "relative-third-degree-free-2026": {
        **family, "rtc.recipient_relationship": "RELATIVE_THIRD_DEGREE",
        "rtc.consideration": "NON_ONEROUS", "rtc.gross_amount": "0.00", "operation.amount": "0.00",
    },
    "family-anchor-missing-2026": {**family, "rtc.recipient_anchor_evidence_id": None},
    "family-basis-conflict-2026": {"rtc.recipient_relationship": "SPOUSE_OR_PARTNER"},
}
for name, overrides in cases.items():
    scenario = json.loads(json.dumps(base))
    scenario["scenario_id"] = name
    scenario["knowledge_at"] = "2026-09-28T15:16:00-03:00"
    scenario["rule_bundle_id"] = bundle["bundle_id"]
    scenario["catalog_versions"] = bundle["catalog_versions"]
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
