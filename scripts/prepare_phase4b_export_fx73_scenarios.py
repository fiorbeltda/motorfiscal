"""Create own native export cases after publishing the gated bundle."""
from pathlib import Path
from decimal import Decimal, ROUND_HALF_UP
import copy, json
from dere_daily_memory_oracle import digest
ROOT = Path(__file__).resolve().parents[1]
F = ROOT / 'resources/fiscal'
E = ROOT / 'storage/app/phase4b-partial-export-fx73-preparation-20261014'
def read(path): return json.loads(path.read_text(encoding='utf-8'))
def save(path, value):
    with path.open('x', encoding='utf-8') as stream: json.dump(value, stream, ensure_ascii=False, indent=2)
def money(value): return str(value.quantize(Decimal('0.01'), rounding=ROUND_HALF_UP))
bundle = read(F / 'bundles/consumption-phase4b-2026.10.14-73-draft1.json')
assert (E / 'export-fx-installation.json').exists()
native = read(E / 'partial-export-transaction-native-result.json')['cases']
gap = read(ROOT / 'storage/app/phase4b-original-requirements72-audit-v3-20261014/partial-export-gap-native-result.json')['cases']
directory = F / 'scenarios/export-fx-2026.10.14-73-draft1'
directory.mkdir(exist_ok=False)
cases = []
specs = [('usd-direct', 'valid', '2026-09-25', '40', '5.1795'),
         ('usd-weekend', 'valid', '2026-09-27', '40', '5.1991'),
         ('usd-quarter', 'valid', '2026-09-25', '25', '5.1795'),
         ('usd-mostly-foreign', 'valid', '2026-09-25', '99', '5.1795'),
         ('usd-mostly-domestic', 'valid', '2026-09-25', '1', '5.1795'),
         ('brl-control', 'BRL', '2026-09-25', '40', None)]
specs += [(name, name, '2026-09-25', '40', None) for name in
          ['proof-hash', 'proof-missing', 'wrong-document-proof', 'eur-unqualified', 'wrong-operation', 'unknown-source']]
for name, fixture, day, share, rate in specs:
    source = copy.deepcopy(gap['BRL' if fixture == 'BRL' else 'USD']['input'])
    nominal = fixture in ['valid', 'BRL']
    if fixture != 'BRL': source['facts'] = copy.deepcopy(native[fixture]['facts'])
    source.update(scenario_id='SYNTHETIC-EXPORT-FX73-' + name, competence_date=day,
                  knowledge_at=bundle['knowledge_available_at'], rule_bundle_id=bundle['bundle_id'], catalog_versions=bundle['catalog_versions'])
    facts = source['facts']
    facts['rtc.operation_date'] = day
    expected = None
    if rate is not None:
        gross = money(Decimal(facts['rtc.gross_amount']) * Decimal(rate))
        foreign = money(Decimal(gross) * Decimal(share) / Decimal('100'))
        domestic = money(Decimal(gross) - Decimal(foreign))
        facts['operation.amount'] = gross
        facts['rtc.export_share_percent'] = share
        proof = facts['rtc.export_fx_proof'][0]
        proof['value'].update(tax_point_date=day, converted_gross_brl=gross, operation_amount_brl=gross, foreign_share_percent=share)
        proof.pop('source_hash')
        proof['source_hash'] = digest(proof)
        expected = {'total_gross_brl': gross, 'foreign_immune_gross_brl': foreign, 'domestic_taxable_base_brl': domestic,
                    'ibs_state_nominal_brl': money(Decimal(domestic) * Decimal('0.001')),
                    'ibs_municipal_nominal_brl': '0.00', 'cbs_nominal_brl': money(Decimal(domestic) * Decimal('0.009'))}
    elif fixture == 'BRL':
        expected = {'total_gross_brl': '1000.00', 'foreign_immune_gross_brl': '400.00', 'domestic_taxable_base_brl': '600.00',
                    'ibs_state_nominal_brl': '0.60', 'ibs_municipal_nominal_brl': '0.00', 'cbs_nominal_brl': '5.40'}
    reason = 'RTC_PARTIAL_EXPORT_DOCUMENT_OBLIGATION_NOT_PROVEN' if nominal else native[fixture]['result']['reason']
    path = directory / (name + '.json')
    save(path, source)
    cases.append({'name': name, 'path': path.relative_to(ROOT).as_posix(), 'nominal_path': nominal,
                  'expected_reason': reason, 'independent_decimal': expected, 'currency': facts['rtc.gross_currency']})
save(E / 'export-integrated-case-manifest.json', {'status': 'TWELVE_OWN_EXPORT_NATIVE_CASES_PREPARED', 'cases': cases, 'production_ready': False})
print('PASS:twelve own export cases including USD weekend, varied shares, BRL and six refusals')
