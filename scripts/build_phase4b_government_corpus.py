"""Build dated government-purchase scenarios for bundle -12."""

import json
from pathlib import Path


root = Path(__file__).resolve().parents[1] / "resources/fiscal"
bundle = json.loads((root / "bundles/consumption-phase4b-2026.09.28-12.json").read_text(encoding="utf-8"))
base = json.loads((root / "scenarios/consumption-phase4b-2026.09.28-1/regular-saas-2027-rate-gap.json").read_text(encoding="utf-8"))
target = root / "scenarios/consumption-phase4b-2026.09.28-12"
target.mkdir(exist_ok=True)

common = {
    "customer.person_type": "PUBLIC_ADMINISTRATION",
    "rtc.government_entity_type": "DIRECT",
    "rtc.government_entity_evidence_id": "public-entity-register-1",
    "rtc.government_public_law_person": True,
    "rtc.government_in_person": False,
    "rtc.government_licitation_waived": False,
    "rtc.government_national_uniform_rate": False,
    "rtc.government_federation": "MUNICIPALITY",
}

cases = [
    ("government-ordinary-2026", "2026-10-10", {}),
    ("government-reductor-2027", "2027-04-10", {"rtc.government_payment_date": "2027-04-10", "rtc.government_payment_evidence_id": "payment-1"}),
    ("government-cbs-share-2029", "2029-04-10", {"rtc.government_payment_date": "2029-04-10", "rtc.government_payment_evidence_id": "payment-2"}),
    ("government-both-full-2033", "2033-04-10", {"rtc.government_payment_date": "2033-04-10", "rtc.government_payment_evidence_id": "payment-3"}),
    ("government-exception-in-person-waived-2027", "2027-04-10", {"rtc.government_in_person": True, "rtc.government_licitation_waived": True, "rtc.government_procurement_evidence_id": "dispensa-e-compra-presencial-1"}),
    ("government-payment-evidence-missing-2027", "2027-04-10", {}),
    ("government-uniform-rate-exclusion-2029", "2029-04-10", {"rtc.government_national_uniform_rate": True, "rtc.government_uniform_rate_rule_id": "LC214_ART174", "rtc.government_payment_date": "2029-04-10", "rtc.government_payment_evidence_id": "payment-4"}),
    ("government-entity-conflict-2027", "2027-04-10", {"rtc.government_public_law_person": False}),
]

for name, date, overrides in cases:
    scenario = json.loads(json.dumps(base))
    scenario["scenario_id"] = name
    scenario["competence_date"] = date
    scenario["knowledge_at"] = "2026-09-28T16:31:00-03:00"
    scenario["rule_bundle_id"] = bundle["bundle_id"]
    scenario["catalog_versions"] = bundle["catalog_versions"]
    scenario["facts"].update(common)
    scenario["facts"].update(overrides)
    scenario["facts"]["rtc.operation_date"] = date
    path = target / f"{name}.json"
    payload = json.dumps(scenario, ensure_ascii=False, indent=2) + "\n"
    if path.exists() and path.read_text(encoding="utf-8") != payload:
        raise RuntimeError(f"Refusing to overwrite changed artifact: {path}")
    path.write_text(payload, encoding="utf-8")

print(len(cases))
