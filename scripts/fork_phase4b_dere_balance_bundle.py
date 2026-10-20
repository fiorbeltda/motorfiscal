"""Bind the D1101 local projection policy without changing predecessor resources."""
import copy
import hashlib
import json
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1] / 'resources/fiscal'
VERSION = '2026.10.02-15'


def read(path):
    return json.loads((ROOT / path).read_text(encoding='utf-8'))


def digest(value):
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode()).hexdigest()


def write(path, value):
    target = ROOT / path
    if target.exists():
        if read(path) != value:
            raise RuntimeError(f'Refusing to overwrite {target}')
        return
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(json.dumps(value, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')


previous = read('bundles/consumption-phase4b-2026.10.02-14.json')
target = f'bundles/consumption-phase4b-{VERSION}.json'
available = read(target)['knowledge_available_at'] if (ROOT / target).exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
bundle = copy.deepcopy(previous)
bundle.update(bundle_id=f'CONSUMPTION-PHASE4B-{VERSION}', version=VERSION,
              predecessor_bundle_id=previous['bundle_id'], knowledge_available_at=available)
codes = read(previous['resource_paths']['rtc_code_catalog'])
codes['version'] = VERSION
codes['dere_trial_balance_projection'] = {
    'strategy': 'DERE_ANALYTICAL_TRIAL_BALANCE_1_2_0',
    'status': 'ACTIVE', 'published_at': available,
    'source_hash': 'b52cb2cf96b176d303d64aeea0bab16e7d35bcc739478f6fc8a26b0512415feb',
    'contract': 'DERE-TRIAL-BALANCE-LOCAL-2026.10.02-1',
    'scope': 'LOCAL_UNSIGNED_D1101_NOT_FULL_MONTHLY_DECLARATION',
    'xsd': copy.deepcopy(codes['dere_local_projection']['xsd']),
}
path = f'catalogs/rtc-codes-{VERSION}.json'
write(path, codes)
bundle['resource_paths']['rtc_code_catalog'] = path
bundle['content_hashes']['rtc_code_catalog'] = digest(codes)
bundle['catalog_versions']['rtc_codes'] = VERSION
controls = read(previous['production_controls']['path'])
controls['version'] = VERSION
for control in controls['controls']:
    if control['id'] == '4B.7.DOCUMENT':
        control['gap'] += ' D1101 local com replay e vínculo ao livro qualificado; PGCC referencial completo, D1121 e D1199 continuam pendentes.'
path = f'production/phase4b-controls-{VERSION}.json'
write(path, controls)
bundle['production_controls'] = {'path': path, 'sha256': digest(controls)}
write(target, bundle)
for family in ['consumption-phase4b', 'consumption-integrated']:
    for source in sorted((ROOT / f'scenarios/{family}-2026.10.02-14').glob('*.json')):
        case = json.loads(source.read_text(encoding='utf-8'))
        case.update(rule_bundle_id=bundle['bundle_id'], catalog_versions=bundle['catalog_versions'], knowledge_at=available)
        write(f'scenarios/{family}-{VERSION}/{source.name}', case)
write(f'sources/official/phase4b-manifest-{VERSION}.json', {
    'version': VERSION, 'retrieved_on': '2026-10-02',
    'status': 'SANDBOX_EVIDENCE_REVIEW_REQUIRED', 'artifacts': bundle['official_sources'],
})
accounts = []
plan = []
for code, amount, nature, category in [('interest', '1000.00', 'C', '5'), ('principal', '10000.00', 'D', '1'), ('funding', '200.00', 'D', '4')]:
    accounts.append({'cCta': code, 'natSaldoInic': nature, 'vSaldoInic': '0.00',
                     'vMovDebt': amount if nature == 'D' else '0.00', 'vMovCred': amount if nature == 'C' else '0.00',
                     'natSaldoFinal': nature, 'vSaldoFinal': amount, 'natVApur': nature, 'vApur': amount,
                     'financial_entry_ids': [code],
                     'previous_balance': {'period': '2026-08', 'amount': '0.00', 'nature': nature, 'source_hash': 'c' * 64}})
    plan.append({'cCta': code, 'indCta': 'A', 'codNat': category, 'valid_from': '2026-01-01'})
write(f'scenarios/dere-local-{VERSION}/qualified-trial-balance.json', {
    'evidence_scope': 'SYNTHETIC_FIXTURE_NOT_REAL_BOOK_OR_PGCC_APPROVAL',
    'identity': {'generated_at': '2026-10-01T12:00:00.000-03:00', 'event_sequence': 1},
    'book': {'supplier_id': '11111111000191', 'document_id': 'dps-1', 'period_end': '2026-09-30', 'observed_on': '2026-10-01',
             'analytical_book_complete': True, 'pgcc_complete': True, 'pgcc_account_mapping_qualified': True,
             'opening_balances_qualified': True, 'book_source_hash': 'a' * 64, 'pgcc_source_hash': 'b' * 64,
             'book_evidence_id': 'FIXTURE.BOOK', 'pgcc_evidence_id': 'FIXTURE.PGCC',
             'first_reporting_period': False, 'closing_frequency': 'M', 'accounts': accounts, 'pgcc_accounts': plan},
})
print('Materialized candidate15; D1101 policy bound, production controls remain open.')
