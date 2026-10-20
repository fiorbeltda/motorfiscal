"""Build acquired personal-use scenarios for bundle -14."""

import json
from pathlib import Path


root = Path(__file__).resolve().parents[1] / "resources/fiscal"
bundle = json.loads((root / "bundles/consumption-phase4b-2026.09.28-14.json").read_text(encoding="utf-8"))
base = json.loads((root / "scenarios/consumption-phase4b-2026.09.28-8/employee-below-market-nominal-2026.json").read_text(encoding="utf-8"))
target = root / "scenarios/consumption-phase4b-2026.09.28-14"
target.mkdir(exist_ok=True)
common = {
    "rtc.personal_use_supply_origin": "ACQUIRED_FROM_THIRD_PARTY",
    "rtc.personal_use_origin_evidence_id": "supplier-invoice-1",
    "rtc.personal_use_input_credit_eligible": True,
    "rtc.personal_use_input_credit_evidence_id": "supplier-invoice-credit-1",
}
cases = {
    "acquired-employee-2026": {"rtc.non_onerous_legal_basis": "LC214_ART5_I_A_3"},
    "acquired-partner-2026": {"rtc.non_onerous_legal_basis": "LC214_ART5_I_A_2",
                               "rtc.recipient_relationship": "PARTNER"},
    "acquired-taxpayer-self-2026": {"rtc.non_onerous_legal_basis": "LC214_ART5_I_A_1",
                                     "rtc.recipient_relationship": "SELF", "provider.person_type": "INDIVIDUAL",
                                     "rtc.supplier_id": "22222222222"},
    "acquired-family-2026": {"rtc.non_onerous_legal_basis": "LC214_ART5_I_A_4",
                             "rtc.recipient_relationship": "SPOUSE_OR_PARTNER",
                             "rtc.recipient_anchor_role": "SELF",
                             "rtc.recipient_anchor_evidence_id": "taxpayer-family-register-1",
                             "provider.person_type": "INDIVIDUAL"},
    "acquired-credit-proof-missing-2026": {"rtc.non_onerous_legal_basis": "LC214_ART5_I_A_3",
                                           "rtc.personal_use_input_credit_evidence_id": None},
    "acquired-credit-ineligible-2026": {"rtc.non_onerous_legal_basis": "LC214_ART5_I_A_3",
                                         "rtc.personal_use_input_credit_eligible": False},
    "acquired-origin-conflict-2026": {"rtc.non_onerous_legal_basis": "LC214_ART5_I_A_3",
                                      "rtc.personal_use_supply_origin": "SELF_PRODUCED_OR_PROVIDED"},
}
for name, overrides in cases.items():
    scenario = json.loads(json.dumps(base))
    scenario["scenario_id"] = name
    scenario["knowledge_at"] = "2026-09-28T17:31:00-03:00"
    scenario["rule_bundle_id"] = bundle["bundle_id"]
    scenario["catalog_versions"] = bundle["catalog_versions"]
    scenario["facts"].update(common)
    for key, value in overrides.items():
        if value is None:
            scenario["facts"].pop(key, None)
        else:
            scenario["facts"][key] = value
    path = target / f"{name}.json"
    payload = json.dumps(scenario, ensure_ascii=False, indent=2) + "\n"
    if path.exists() and path.read_text(encoding="utf-8") != payload:
        raise RuntimeError(f"Refusing to overwrite changed artifact: {path}")
    path.write_text(payload, encoding="utf-8")
print(len(cases))
