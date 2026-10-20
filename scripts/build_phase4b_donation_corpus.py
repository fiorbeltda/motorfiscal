"""Build art. 6 donation cases without changing earlier scenario files."""

import json
from pathlib import Path


root = Path(__file__).resolve().parents[1] / "resources/fiscal"
bundle = json.loads((root / "bundles/consumption-phase4b-2026.09.28-16.json").read_text(encoding="utf-8"))
base = json.loads((root / "scenarios/consumption-phase4b-2026.09.28-11/administrator-free-2026.json").read_text(encoding="utf-8"))
target = root / "scenarios/consumption-phase4b-2026.09.28-16"
target.mkdir(exist_ok=True)
common = {
    "rtc.non_onerous_legal_basis": "LC214_ART6_VIII",
    "rtc.donation_no_counterbenefit": True,
    "rtc.donation_counterbenefit_evidence_id": "donation-agreement-1",
    "rtc.donation_input_credit_eligible": False,
    "rtc.donation_input_credit_evidence_id": "acquisition-ledger-1",
}
cases = {
    "donation-no-credit-2026": {},
    "donation-counterbenefit-2026": {"rtc.donation_no_counterbenefit": False},
    "donation-onerous-conflict-2026": {"rtc.consideration": "ONEROUS"},
    "donation-amount-conflict-2026": {"rtc.gross_amount": "10.00", "operation.amount": "10.00"},
    "donation-counterbenefit-proof-missing-2026": {"rtc.donation_counterbenefit_evidence_id": None},
    "donation-credit-option-missing-2026": {"rtc.donation_input_credit_eligible": True},
    "donation-credit-annulled-2026": {"rtc.donation_input_credit_eligible": True,
                                       "rtc.donation_credit_option": "ANNUL_CREDITS",
                                       "rtc.donation_credit_annulment_evidence_id": "credit-reversal-ledger-1"},
    "donation-credit-annulment-unproven-2026": {"rtc.donation_input_credit_eligible": True,
                                                "rtc.donation_credit_option": "ANNUL_CREDITS"},
    "donation-tax-market-value-2026": {"rtc.donation_input_credit_eligible": True,
                                       "rtc.donation_credit_option": "TAX_MARKET_VALUE"},
    "donation-credit-market-value-missing-2026": {"rtc.donation_input_credit_eligible": True,
                                               "rtc.donation_credit_option": "TAX_MARKET_VALUE",
                                               "rtc.market_value_brl": None},
}
for name, overrides in cases.items():
    scenario = json.loads(json.dumps(base))
    scenario["scenario_id"] = name
    scenario["knowledge_at"] = "2026-09-28T18:31:00-03:00"
    scenario["rule_bundle_id"] = bundle["bundle_id"]
    scenario["catalog_versions"] = bundle["catalog_versions"]
    for key in list(scenario["facts"]):
        if key.startswith("rtc.recipient_relationship") or key.startswith("rtc.business_use"):
            del scenario["facts"][key]
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
