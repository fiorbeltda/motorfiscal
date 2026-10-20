"""Build a distinct, mandatory-origin export transaction prototype from accepted guard structure."""
from pathlib import Path
import hashlib, json

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-partial-export-fx73-preparation-20261014'
live = ROOT / 'app/Modules/Fiscal/ConsumptionReform/ImportFxTransactionQualifier.php'
text = live.read_text(encoding='utf-8')
with (E / 'before-export-transaction-qualifier.php').open('xb') as stream: stream.write(live.read_bytes())
replacements = {
    'ImportFxTransactionQualifier': 'PartialExportFxTransactionPrototype73',
    'ImportFxQuoteConverter': 'PartialExportFxQuotePrototype73',
    'RTC-ORDINARY-IMPORT-FX-LOCAL-2026.10.13-68': 'RTC-ORDINARY-PARTIAL-EXPORT-FX-LOCAL-2026.10.14-73',
    'rtc.import_fx_': 'rtc.export_fx_', 'rtc.supplier_foreign_nif': 'rtc.acquirer_foreign_nif',
    'supplier_foreign_nif': 'acquirer_foreign_nif', 'rtc.importer_id': 'rtc.export_domestic_recipient_id',
    "'importer_id'": "'domestic_recipient_id'", 'rtc.import_document_id': 'rtc.export_foreign_document_id',
    'rtc.import_transaction_evidence_id': 'rtc.export_transaction_evidence_id',
    'ORDINARY_SERVICE_IMPORT': 'ORDINARY_PARTIAL_SERVICE_EXPORT',
    'LOCAL_SOURCE_BOUND_IMPORT_FX_CONVERSION': 'LOCAL_SOURCE_BOUND_PARTIAL_EXPORT_FX_CONVERSION',
    'SYNTHETIC_COMPLETE_IMPORT_FX_TRANSACTION_NOT_REMOTE_VERIFICATION': 'SYNTHETIC_COMPLETE_PARTIAL_EXPORT_FX_TRANSACTION_NOT_REMOTE_VERIFICATION',
    'LOCAL_IMPORT_FX_TRANSACTION_BOUND': 'LOCAL_PARTIAL_EXPORT_FX_TRANSACTION_BOUND',
    'RTC_IMPORT_FX_': 'RTC_PARTIAL_EXPORT_FX_',
}
for before, after in replacements.items():
    assert before in text, before
    text = text.replace(before, after)
text = text.replace('use DateTimeImmutable;', 'use Brick\\Math\\BigDecimal;\nuse DateTimeImmutable;')
needle = "        if (! $this->date($taxPoint)"
assert text.count(needle) == 1
text = text.replace(needle, "        if (($policy['require_material_quote_origin'] ?? null) !== true) {\n            return $this->stop('POLICY_MATERIAL_ORIGIN_REQUIRED');\n        }\n" + needle)
needle = "'rtc.operation_amount_reconciliation_id', 'rtc.export_fx_source_id', 'rtc.export_fx_proof'];"
assert text.count(needle) == 1
text = text.replace(needle, "'rtc.operation_amount_reconciliation_id', 'rtc.export_fx_source_id', 'rtc.export_fx_proof',\n"
    "            'rtc.supplier_country', 'rtc.acquirer_id', 'rtc.acquirer_country', 'rtc.recipient_id', 'rtc.recipient_country',\n"
    "            'rtc.export_domestic_recipient_country', 'rtc.export_domestic_document_id', 'rtc.export_share_percent',\n"
    "            'rtc.export_allocation_method', 'rtc.export_allocation_evidence_id', 'rtc.domestic_consumption_evidence_id', 'rtc.export_evidence_id'];")
needle = "        $sources = $policy['quote_sources'] ?? null;"
guard = """        if ($facts['rtc.supplier_country'] !== 'BR' || $facts['rtc.export_domestic_recipient_country'] !== 'BR'
            || ! preg_match('/^[A-Z]{2}$/', $facts['rtc.acquirer_country']) || $facts['rtc.acquirer_country'] === 'BR'
            || ! preg_match('/^[A-Z]{2}$/', $facts['rtc.recipient_country']) || $facts['rtc.recipient_country'] === 'BR') {
            return $this->stop('EXPORT_AND_DOMESTIC_ROLE_CONFLICT');
        }
        if (($facts['rtc.foreign_consumption_proven'] ?? null) !== true
            || ! in_array($facts['rtc.export_allocation_method'], ['CONTRACTUAL', 'METERED_USAGE'], true)
            || ! preg_match('/^\\d{1,3}(?:[.]\\d{1,8})?$/', $facts['rtc.export_share_percent'])
            || BigDecimal::of($facts['rtc.export_share_percent'])->compareTo('0') <= 0
            || BigDecimal::of($facts['rtc.export_share_percent'])->compareTo('100') >= 0) {
            return $this->stop('EXPLICIT_PARTIAL_EXPORT_ALLOCATION_REQUIRED');
        }
"""
assert text.count(needle) == 1
text = text.replace(needle, guard + needle)
needle = "'quote_source_id' => $facts['rtc.export_fx_source_id'], 'quote_source_sha256' => $converted['raw_source_sha256']];"
assert text.count(needle) == 1
text = text.replace(needle, "'quote_source_id' => $facts['rtc.export_fx_source_id'], 'quote_source_sha256' => $converted['raw_source_sha256'],\n"
    "            'supplier_country' => $facts['rtc.supplier_country'], 'acquirer_id' => $facts['rtc.acquirer_id'],\n"
    "            'acquirer_country' => $facts['rtc.acquirer_country'], 'recipient_id' => $facts['rtc.recipient_id'],\n"
    "            'recipient_country' => $facts['rtc.recipient_country'], 'domestic_recipient_country' => $facts['rtc.export_domestic_recipient_country'],\n"
    "            'domestic_document_id' => $facts['rtc.export_domestic_document_id'], 'foreign_share_percent' => $facts['rtc.export_share_percent'],\n"
    "            'allocation_method' => $facts['rtc.export_allocation_method'], 'allocation_evidence_id' => $facts['rtc.export_allocation_evidence_id'],\n"
    "            'domestic_consumption_evidence_id' => $facts['rtc.domestic_consumption_evidence_id'], 'export_evidence_id' => $facts['rtc.export_evidence_id']];")
target = E / 'planned-partial-export-transaction.php'
with target.open('x', encoding='utf-8') as stream: stream.write(text)
with (E / 'partial-export-transaction-preparation.json').open('x', encoding='utf-8') as stream:
    json.dump({'status': 'DISTINCT_EXPORT_TRANSACTION_PROTOTYPE_REQUIRES_ORIGIN_ROLES_DOCUMENTS_AND_ALLOCATION',
               'before_sha256': hashlib.sha256(live.read_bytes()).hexdigest(),
               'prototype_sha256': hashlib.sha256(target.read_bytes()).hexdigest(),
               'app_installed': False, 'production_ready': False}, stream, indent=2)
print('PASS:export transaction prototype with mandatory origin and separate roles/documents prepared')
