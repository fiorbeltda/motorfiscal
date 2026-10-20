"""Create the Phase 4B fact and question overlays without changing the 4A files."""

import json
from pathlib import Path

root = Path(__file__).resolve().parents[1] / "resources/fiscal"
facts = json.loads((root / "facts/federal-phase4a-public-v4.json").read_text(encoding="utf-8"))

specs = {
    "rtc.evaluate": ("BOOLEAN", None),
    "rtc.operation_date": ("DATE", None),
    "rtc.operation_kind": ("ENUM", ["SERVICE", "GOODS", "DIGITAL", "FINANCIAL", "REAL_ESTATE", "SPECIFIC_REGIME"]),
    "rtc.buyer_relation": ("ENUM", ["B2B", "B2C"]),
    "rtc.delivery_mode": ("ENUM", ["PRESENTIAL", "REMOTE", "DIGITAL"]),
    "rtc.consideration": ("ENUM", ["ONEROUS", "NON_ONEROUS"]),
    "rtc.non_onerous_legal_basis": ("STRING", None),
    "rtc.recipient_relationship": ("ENUM", ["EMPLOYEE", "OTHER"]),
    "rtc.recipient_relationship_evidence_id": ("STRING", None),
    "rtc.business_use_predominant": ("BOOLEAN", None),
    "rtc.business_use_evidence_id": ("STRING", None),
    "rtc.market_value_brl": ("DECIMAL", None),
    "rtc.market_value_evidence_id": ("STRING", None),
    "rtc.market": ("ENUM", ["DOMESTIC", "IMPORT", "EXPORT"]),
    "rtc.supplier_regime": ("ENUM", ["REGULAR", "SIMPLES_NACIONAL", "MEI", "SPECIAL", "FOREIGN"]),
    "rtc.simples_collection_option": ("ENUM", ["INTERNAL", "REGULAR"]),
    "rtc.simples_option_filed_on": ("DATE", None),
    "rtc.simples_option_receipt_id": ("STRING", None),
    "rtc.simples_option_status": ("ENUM", ["CONFIRMED", "PENDING", "CANCELLED"]),
    "rtc.simples_option_status_evidence_id": ("STRING", None),
    "rtc.supplier_id": ("STRING", None),
    "rtc.supplier_country": ("CODE", None),
    "rtc.acquirer_id": ("STRING", None),
    "rtc.recipient_id": ("STRING", None),
    "rtc.importer_id": ("STRING", None),
    "rtc.taxpayer_id": ("STRING", None),
    "rtc.platform_id": ("STRING", None),
    "rtc.acquirer_country": ("CODE", None),
    "rtc.recipient_country": ("CODE", None),
    "rtc.destination_basis": ("ENUM", ["ACQUIRER_DOMICILE", "RECIPIENT_DOMICILE", "PROPERTY", "PERSON_PRESENCE", "EVENT", "GOODS_DELIVERY", "PASSENGER_ORIGIN", "CARGO_DELIVERY", "DOCUMENTED_SPECIAL"]),
    "rtc.destination_state": ("CODE", None),
    "rtc.destination_municipality_ibge": ("CODE", None),
    "rtc.destination_evidence_id": ("STRING", None),
    "rtc.destination_registration_status": ("ENUM", ["REGISTERED", "UNREGISTERED"]),
    "rtc.destination_evidence_sources": ("ARRAY", None),
    "rtc.foreign_consumption_proven": ("BOOLEAN", None),
    "rtc.export_evidence_id": ("STRING", None),
    "rtc.export_share_percent": ("DECIMAL", None),
    "rtc.export_allocation_method": ("ENUM", ["CONTRACTUAL", "METERED_USAGE"]),
    "rtc.export_allocation_evidence_id": ("STRING", None),
    "rtc.domestic_consumption_evidence_id": ("STRING", None),
    "rtc.import_document_id": ("STRING", None),
    "rtc.supplier_foreign_nif": ("STRING", None),
    "rtc.import_transaction_evidence_id": ("STRING", None),
    "rtc.import_fx_contract_applicable": ("BOOLEAN", None),
    "rtc.import_fx_contract_id": ("STRING", None),
    "rtc.import_brazil_share_percent": ("DECIMAL", None),
    "rtc.import_allocation_method": ("ENUM", ["CONTRACTUAL", "METERED_USAGE"]),
    "rtc.import_allocation_evidence_id": ("STRING", None),
    "rtc.gross_currency": ("CODE", None),
    "rtc.specific_regime_rule_id": ("STRING", None),
    "rtc.government_entity_type": ("ENUM", ["DIRECT", "AUTARCHY", "PUBLIC_FOUNDATION", "OTHER"]),
    "rtc.document_id": ("STRING", None),
    "rtc.gross_amount": ("DECIMAL", None),
    "rtc.legacy_tax_inclusion": ("ENUM", ["INCLUDED", "EXCLUDED"]),
    "rtc.legacy_tax_evidence_id": ("STRING", None),
    "rtc.operation_amount_reconciliation_id": ("STRING", None),
    "rtc.base_adjustments": ("ARRAY", None),
    "rtc.tax_treatment": ("ENUM", ["STANDARD", "REDUCED_30", "REDUCED_60", "ZERO_RATE", "EXEMPT", "IMMUNE", "SUSPENDED", "DEFERRAL", "SPECIFIC_REGIME"]),
    "rtc.treatment_rule_id": ("STRING", None),
    "rtc.treatment_evidence_id": ("STRING", None),
    "rtc.cindop": ("CODE", None),
    "rtc.cclasstrib": ("CODE", None),
    "rtc.classification_rule_id": ("STRING", None),
    "rtc.collection_method": ("ENUM", ["SUPPLIER", "ACQUIRER", "RECIPIENT", "SPLIT_PAYMENT", "PLATFORM", "PUBLIC_ENTITY"]),
    "rtc.collection_legal_basis": ("STRING", None),
    "rtc.accessory_obligations_fulfilled": ("BOOLEAN", None),
    "rtc.credit_context": ("ENUM", ["OUTPUT", "INPUT", "PERSONAL_USE"]),
    "rtc.acquirer_regime": ("ENUM", ["REGULAR", "SIMPLES_NACIONAL", "MEI", "NON_TAXPAYER"]),
    "rtc.input_document_id": ("STRING", None),
    "rtc.input_document_valid": ("BOOLEAN", None),
    "rtc.prior_tax_extinguished": ("BOOLEAN", None),
    "rtc.input_ibs_amount": ("DECIMAL", None),
    "rtc.input_cbs_amount": ("DECIMAL", None),
    "rtc.credit_adjustments": ("ARRAY", None),
}

for key, (kind, options) in specs.items():
    item = {"key": key, "type": kind, "material": True}
    if options is not None:
        item["allowed_values"] = options
    if kind == "STRING":
        item["min_length"] = 1
    if key == "rtc.destination_state":
        item["pattern"] = "/^[A-Z]{2}$/"
    if key == "rtc.gross_currency":
        item["pattern"] = "/^[A-Z]{3}$/"
    if key == "rtc.destination_municipality_ibge":
        item["pattern"] = "/^\\d{7}$/"
    if key in ("rtc.cindop", "rtc.cclasstrib"):
        item["pattern"] = "/^\\d{6}$/"
    facts.append(item)

questions = []
for rank, (key, (kind, options)) in enumerate(specs.items(), 150):
    q = {"fact_key": key, "prompt": f"Informe ou comprove {key} para a apuração IBS/CBS.", "rank": rank, "answer_type": kind}
    if options:
        q["options"] = options
    questions.append(q)

(root / "facts/consumption-phase4b.json").write_text(json.dumps(facts, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
(root / "questions/consumption-phase4b.json").write_text(json.dumps(questions, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
print(f"facts={len(facts)} rtc_questions={len(questions)}")
