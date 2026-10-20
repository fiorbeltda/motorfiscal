"""Generate the auditable Phase 4B corpus from one documented operation."""

import json
from pathlib import Path

root = Path(__file__).resolve().parents[1] / "resources/fiscal"
manifest = json.loads((root / "bundles/consumption-phase4b-2026.09.25-1.json").read_text(encoding="utf-8"))
out = root / "scenarios/consumption-phase4b"
out.mkdir(exist_ok=True)

base = {
    "service.canonical_id": "SOFTWARE_DEVELOPMENT",
    "provider.municipality_ibge": "3550308",
    "customer.municipality_ibge": "3509502",
    "customer.person_type": "PJ",
    "operation.service_origin": "DOMESTIC",
    "provider.tax_regime": "LUCRO_REAL",
    "provider.person_type": "PRIVATE_LEGAL_ENTITY",
    "operation.amount": "1000.00",
    "federal.evaluate_withholdings": False,
    "rtc.evaluate": True,
    "rtc.operation_date": "2026-09-25",
    "rtc.operation_kind": "DIGITAL",
    "rtc.buyer_relation": "B2B",
    "rtc.delivery_mode": "DIGITAL",
    "rtc.consideration": "ONEROUS",
    "rtc.market": "DOMESTIC",
    "rtc.supplier_regime": "REGULAR",
    "rtc.supplier_id": "11111111000191",
    "rtc.supplier_country": "BR",
    "rtc.acquirer_id": "22222222000191",
    "rtc.recipient_id": "22222222000191",
    "rtc.acquirer_country": "BR",
    "rtc.recipient_country": "BR",
    "rtc.destination_basis": "ACQUIRER_DOMICILE",
    "rtc.destination_state": "SP",
    "rtc.destination_municipality_ibge": "3509502",
    "rtc.destination_evidence_id": "contract-establishment-1",
    "rtc.document_id": "dps-1",
    "rtc.gross_amount": "1000.00",
    "rtc.gross_currency": "BRL",
    "rtc.legacy_tax_inclusion": "EXCLUDED",
    "rtc.legacy_tax_evidence_id": "net-price-contract-line-1",
    "rtc.base_adjustments": [],
    "rtc.tax_treatment": "STANDARD",
    "rtc.collection_method": "SUPPLIER",
    "rtc.accessory_obligations_fulfilled": True,
    "rtc.credit_context": "OUTPUT",
}


def create(name, date, changes=None, remove=(), knowledge_at="2026-09-25T10:00:00-03:00"):
    facts = dict(base)
    facts["rtc.operation_date"] = date
    facts.update(changes or {})
    if facts["rtc.supplier_regime"] in ("SIMPLES_NACIONAL", "MEI"):
        facts["provider.tax_regime"] = facts["rtc.supplier_regime"]
    for key in remove:
        facts.pop(key, None)
    data = {
        "scenario_id": name,
        "competence_date": date,
        "knowledge_at": knowledge_at,
        "rule_bundle_id": manifest["bundle_id"],
        "catalog_versions": manifest["catalog_versions"],
        "facts": facts,
    }
    (out / (name + ".json")).write_text(json.dumps(data, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


create("regular-saas-2026", "2026-09-25")
create("regular-saas-b2c-2026", "2026-09-25", {"rtc.buyer_relation": "B2C", "customer.person_type": "PF",
    "rtc.acquirer_id": "22222222222", "rtc.recipient_id": "22222222222"})
create("other-digital-service-unclassified-2026", "2026-09-25", {"service.canonical_id": "CTRIBNAC_010201"})
create("regular-saas-foreign-acquirer-brazilian-recipient-2026", "2026-09-25", {
    "rtc.acquirer_id": "US-CUSTOMER-1", "rtc.acquirer_country": "US",
    "rtc.recipient_id": "22222222000191", "rtc.recipient_country": "BR",
    "rtc.destination_basis": "RECIPIENT_DOMICILE", "rtc.destination_evidence_id": "brazilian-recipient-establishment-1"})
create("legacy-taxes-included-2026", "2026-09-25", {
    "rtc.legacy_tax_inclusion": "INCLUDED",
    "rtc.legacy_tax_evidence_id": "invoice-tax-breakdown-1",
    "rtc.base_adjustments": [
        {"kind": "LEGACY_TAX_EXCLUSION", "tax": "ISS", "amount": "50.00", "evidence_id": "invoice-tax-breakdown-1"},
        {"kind": "LEGACY_TAX_EXCLUSION", "tax": "PIS", "amount": "6.50", "evidence_id": "invoice-tax-breakdown-1"},
        {"kind": "LEGACY_TAX_EXCLUSION", "tax": "COFINS", "amount": "30.00", "evidence_id": "invoice-tax-breakdown-1"},
    ],
})
discount = {"kind": "UNCONDITIONAL_DISCOUNT", "amount": "100.00", "evidence_id": "dps-discount-line-1",
    "document_id": "dps-1", "depends_on_later_event": False}
create("unconditional-discount-proven-2026", "2026-09-25", {"rtc.base_adjustments": [discount]})
create("unconditional-discount-unproven-2026", "2026-09-25", {"rtc.base_adjustments": [
    {key: value for key, value in discount.items() if key != "depends_on_later_event"}]})
reimbursement = {"kind": "DOCUMENTED_REIMBURSEMENT", "amount": "100.00", "evidence_id": "third-party-payment-1",
    "third_party_document_id": "third-party-invoice-1", "third_party_id": "33333333000191",
    "document_in_third_party_name": True}
create("third-party-reimbursement-proven-2026", "2026-09-25", {"rtc.base_adjustments": [reimbursement]})
create("third-party-reimbursement-unproven-2026", "2026-09-25", {"rtc.base_adjustments": [
    {**reimbursement, "document_in_third_party_name": False}]})
pis_legacy = {"rtc.legacy_tax_inclusion": "INCLUDED", "rtc.base_adjustments": [
    {"kind": "LEGACY_TAX_EXCLUSION", "tax": "PIS", "amount": "6.50", "evidence_id": "legacy-tax-breakdown-1"}]}
create("legacy-pis-exclusion-2027", "2027-04-10", pis_legacy)
create("legacy-pis-exclusion-2033-invalid", "2033-02-01", pis_legacy)
create("regular-saas-2026-collection-due", "2026-09-25", {"rtc.accessory_obligations_fulfilled": False})
employee_grant = {"rtc.consideration": "NON_ONEROUS", "rtc.non_onerous_legal_basis": "LC214_ART5_I_B_1",
    "rtc.recipient_relationship": "EMPLOYEE", "rtc.recipient_relationship_evidence_id": "hr-record-1",
    "rtc.business_use_predominant": False, "rtc.business_use_evidence_id": "personal-use-policy-1",
    "rtc.market_value_brl": "1000.00", "rtc.market_value_evidence_id": "comparable-saas-price-1",
    "rtc.buyer_relation": "B2C", "customer.person_type": "PF",
    "rtc.acquirer_id": "22222222222", "rtc.recipient_id": "22222222222",
    "rtc.gross_amount": "0.00", "operation.amount": "0.00"}
create("non-onerous-employee-saas-2026", "2026-09-25", employee_grant)
create("non-onerous-employee-market-evidence-missing-2026", "2026-09-25", employee_grant,
    remove=("rtc.market_value_evidence_id",))
create("non-onerous-employee-business-use-2026", "2026-09-25", {**employee_grant,
    "rtc.business_use_predominant": True})
create("non-onerous-employee-below-market-2026", "2026-09-25", {**employee_grant,
    "rtc.gross_amount": "100.00", "operation.amount": "100.00"})
create("non-onerous-employee-zero-market-value-2026", "2026-09-25", {**employee_grant,
    "rtc.market_value_brl": "0.00"})
create("simples-2026", "2026-09-25", {"rtc.supplier_regime": "SIMPLES_NACIONAL"})
create("regular-saas-2027-rate-gap", "2027-04-10", {"rtc.accessory_obligations_fulfilled": False})
create("simples-2027-internal", "2027-04-10", {"rtc.supplier_regime": "SIMPLES_NACIONAL", "rtc.simples_collection_option": "INTERNAL"})
simples_regular = {"rtc.supplier_regime": "SIMPLES_NACIONAL", "rtc.simples_collection_option": "REGULAR",
    "rtc.simples_option_filed_on": "2026-09-20", "rtc.simples_option_receipt_id": "rfb-option-receipt-1",
    "rtc.simples_option_status": "CONFIRMED", "rtc.simples_option_status_evidence_id": "rfb-option-history-1"}
create("simples-regular-h1-2027-confirmed", "2027-04-10", simples_regular,
    knowledge_at="2027-04-10T10:00:00-03:00")
create("simples-regular-h1-2027-unproven", "2027-04-10", simples_regular,
    remove=("rtc.simples_option_receipt_id",), knowledge_at="2027-04-10T10:00:00-03:00")
create("simples-regular-h1-2027-cancelled", "2027-04-10", {**simples_regular,
    "rtc.simples_option_status": "CANCELLED"}, knowledge_at="2027-04-10T10:00:00-03:00")
create("simples-regular-h1-2027-revocable", "2027-04-10", simples_regular,
    knowledge_at="2026-10-10T10:00:00-03:00")
create("simples-regular-h2-2027-unproven", "2027-07-10", simples_regular,
    knowledge_at="2027-07-10T10:00:00-03:00")
create("simples-regular-h1-2027-new-business-gap", "2027-04-10", {**simples_regular,
    "rtc.simples_option_filed_on": "2026-11-15"}, knowledge_at="2027-04-10T10:00:00-03:00")
create("mei-2027-regular-forbidden", "2027-04-10", {"rtc.supplier_regime": "MEI", "rtc.simples_collection_option": "REGULAR"})
create("regular-saas-2029-rate-gap", "2029-03-01", {"rtc.accessory_obligations_fulfilled": False})
create("regular-saas-2030-rate-gap", "2030-06-15", {"rtc.accessory_obligations_fulfilled": False})
create("regular-saas-2032-rate-gap", "2032-12-31", {"rtc.accessory_obligations_fulfilled": False})
create("regular-saas-2033-rate-gap", "2033-02-01", {"rtc.accessory_obligations_fulfilled": False})
create("missing-destination-2026", "2026-09-25", remove=("rtc.destination_evidence_id",))
destination_sources = [
    {"kind": "DECLARED_ADDRESS", "state": "SP", "municipality_ibge": "3509502", "evidence_id": "declared-address-1"},
    {"kind": "PAYMENT_ARRANGEMENT", "state": "SP", "municipality_ibge": "3509502", "evidence_id": "payment-registration-1"},
]
create("unregistered-destination-corroborated-2026", "2026-09-25", {
    "rtc.destination_registration_status": "UNREGISTERED", "rtc.destination_evidence_sources": destination_sources})
create("unregistered-destination-one-criterion-2026", "2026-09-25", {
    "rtc.destination_registration_status": "UNREGISTERED", "rtc.destination_evidence_sources": destination_sources[:1]})
create("unregistered-destination-conflict-2026", "2026-09-25", {
    "rtc.destination_registration_status": "UNREGISTERED", "rtc.destination_evidence_sources": [destination_sources[0],
        {**destination_sources[1], "municipality_ibge": "3550308"}]})
create("export-unproven-2026", "2026-09-25", {"rtc.market": "EXPORT", "rtc.acquirer_country": "US", "rtc.recipient_country": "US"})
create("export-foreign-consumption-2026", "2026-09-25", {"rtc.market": "EXPORT", "rtc.acquirer_country": "US", "rtc.recipient_country": "US",
    "rtc.foreign_consumption_proven": True, "rtc.export_evidence_id": "foreign-consumption-contract-1", "rtc.export_share_percent": "100"})
partial_export = {"rtc.market": "EXPORT", "rtc.acquirer_country": "US", "rtc.recipient_country": "US",
    "rtc.foreign_consumption_proven": True, "rtc.export_evidence_id": "foreign-consumption-contract-1",
    "rtc.export_share_percent": "40", "rtc.domestic_consumption_evidence_id": "brazil-usage-report-1"}
create("export-partial-documented-2026", "2026-09-25", {**partial_export,
    "rtc.export_allocation_method": "METERED_USAGE", "rtc.export_allocation_evidence_id": "usage-meter-sep-2026"})
create("export-partial-unproven-2026", "2026-09-25", partial_export)
create("export-partial-foreign-currency-2026", "2026-09-25", {**partial_export,
    "rtc.export_allocation_method": "METERED_USAGE", "rtc.export_allocation_evidence_id": "usage-meter-sep-2026",
    "rtc.gross_currency": "USD"})
create("export-zero-share-2026", "2026-09-25", {**partial_export, "rtc.export_share_percent": "0"})
create("other-service-export-unclassified-2026", "2026-09-25", {"service.canonical_id": "CTRIBNAC_010201",
    "rtc.market": "EXPORT", "rtc.acquirer_country": "US", "rtc.recipient_country": "US",
    "rtc.foreign_consumption_proven": True, "rtc.export_evidence_id": "foreign-consumption-contract-1", "rtc.export_share_percent": "100"})
create("export-brazilian-recipient-2026", "2026-09-25", {"rtc.market": "EXPORT", "rtc.acquirer_country": "US", "rtc.recipient_country": "BR",
    "rtc.foreign_consumption_proven": True, "rtc.export_evidence_id": "foreign-consumption-contract-1", "rtc.export_share_percent": "100"})
import_base = {"rtc.market": "IMPORT", "rtc.supplier_regime": "FOREIGN", "rtc.supplier_country": "US",
    "rtc.importer_id": "22222222000191", "rtc.import_document_id": "foreign-invoice-1",
    "rtc.supplier_foreign_nif": "US-FOREIGN-123", "rtc.import_transaction_evidence_id": "payment-record-1",
    "rtc.import_fx_contract_applicable": False,
    "rtc.domestic_consumption_evidence_id": "brazil-use-contract-1", "rtc.import_brazil_share_percent": "100",
    "rtc.collection_method": "ACQUIRER", "rtc.collection_legal_basis": "LC214-ART64-V"}
create("import-digital-partial-brazil-consumption-2026", "2026-09-25", {
    **import_base, "rtc.import_brazil_share_percent": "25",
    "rtc.import_allocation_method": "METERED_USAGE", "rtc.import_allocation_evidence_id": "usage-meter-sep-2026"},
    remove=("provider.tax_regime", "operation.service_origin"))
create("import-digital-partial-unproven-2026", "2026-09-25", {
    **import_base, "rtc.import_brazil_share_percent": "25"},
    remove=("provider.tax_regime", "operation.service_origin"))
create("import-digital-foreign-currency-unconverted-2026", "2026-09-25", {
    **import_base, "rtc.gross_currency": "USD"},
    remove=("provider.tax_regime", "operation.service_origin"))
create("import-digital-2026-document-gap", "2026-09-25", import_base,
    remove=("provider.tax_regime", "operation.service_origin"))
create("import-digital-foreign-acquirer-brazilian-recipient-2026", "2026-09-25", {
    **import_base, "rtc.acquirer_id": "US-CUSTOMER-1", "rtc.acquirer_country": "US",
    "rtc.recipient_id": "22222222000191", "rtc.recipient_country": "BR",
    "rtc.importer_id": "22222222000191", "rtc.destination_basis": "RECIPIENT_DOMICILE",
    "rtc.collection_method": "RECIPIENT", "rtc.collection_legal_basis": "LC214-ART64-VI"},
    remove=("provider.tax_regime", "operation.service_origin"))
create("import-digital-2026-missing-consumption", "2026-09-25", import_base,
    remove=("provider.tax_regime", "operation.service_origin", "rtc.domestic_consumption_evidence_id"))
create("import-digital-2026-wrong-taxpayer", "2026-09-25", {**import_base, "rtc.importer_id": "33333333000191"},
    remove=("provider.tax_regime", "operation.service_origin"))
create("unknown-classification-2026", "2026-09-25", {"rtc.cclasstrib": "999999"})
credit_input = {"rtc.credit_context": "INPUT", "rtc.input_document_id": "dfe-purchase-1",
    "rtc.input_document_valid": True, "rtc.prior_tax_extinguished": True,
    "rtc.input_ibs_amount": "3.50", "rtc.input_cbs_amount": "7.25", "rtc.credit_adjustments": []}
create("credit-claimant-regime-missing-2026", "2026-09-25", credit_input)
create("credit-claimant-simples-2026", "2026-09-25", {**credit_input, "rtc.acquirer_regime": "SIMPLES_NACIONAL"})
print(len(list(out.glob("*.json"))), "scenarios")
