"""Build linked import-credit cases for the Phase 4B candidate."""

import json
from pathlib import Path


root = Path(__file__).resolve().parents[1] / "resources/fiscal"
bundle = json.loads((root / "bundles/consumption-phase4b-2026.09.28-15.json").read_text(encoding="utf-8"))
base = json.loads((root / "scenarios/consumption-phase4b-2026.09.28-1/import-digital-2026-document-gap.json").read_text(encoding="utf-8"))
target = root / "scenarios/consumption-phase4b-2026.09.28-15"
target.mkdir(exist_ok=True)
common = {
    "rtc.credit_context": "INPUT",
    "rtc.acquirer_regime": "REGULAR",
    "rtc.input_document_id": "dfe-import-1",
    "rtc.input_document_valid": True,
    "rtc.prior_tax_extinguished": True,
    "rtc.input_ibs_amount": "1.00",
    "rtc.input_cbs_amount": "9.00",
    "rtc.credit_adjustments": [],
    "rtc.import_credit_fiscal_document_id": "dfe-import-1",
    "rtc.import_credit_extinction_evidence_id": "tax-account-1",
}
cases = {
    "import-credit-proven-2026": {},
    "import-credit-fiscal-document-missing-2026": {"rtc.import_credit_fiscal_document_id": None},
    "import-credit-extinction-proof-missing-2026": {"rtc.import_credit_extinction_evidence_id": None},
    "import-credit-document-link-conflict-2026": {"rtc.import_credit_fiscal_document_id": "other-dfe"},
    "import-credit-debt-not-extinguished-2026": {"rtc.prior_tax_extinguished": False},
    "import-credit-simples-claimant-2026": {"rtc.acquirer_regime": "SIMPLES_NACIONAL"},
    "import-credit-foreign-acquirer-2026": {
        "rtc.acquirer_country": "US", "rtc.recipient_country": "BR",
        "rtc.destination_basis": "RECIPIENT_DOMICILE", "rtc.importer_id": "22222222000191",
        "rtc.acquirer_id": "FOREIGN-ACQUIRER-1", "rtc.collection_method": "RECIPIENT",
        "rtc.collection_legal_basis": "LC214-ART64-VI",
    },
    "import-credit-reversal-2026": {"rtc.credit_adjustments": [
        {"tax": "IBS", "kind": "REVERSAL", "amount": "0.25", "evidence_id": "reversal-1"},
        {"tax": "CBS", "kind": "DISALLOWANCE", "amount": "1.00", "evidence_id": "disallowance-1"},
    ]},
}
for name, overrides in cases.items():
    scenario = json.loads(json.dumps(base))
    scenario["scenario_id"] = name
    scenario["knowledge_at"] = "2026-09-28T18:01:00-03:00"
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
