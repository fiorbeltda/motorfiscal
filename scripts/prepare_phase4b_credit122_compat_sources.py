"""Bind all16 geographic and45 contract119 sources to the known1441 replay receipt."""
from pathlib import Path
from datetime import datetime, timezone
import collections
import hashlib
import json

repo = Path(__file__).resolve().parents[1]
base = Path(r'D:\ProjetoSaasExports')
root = base / 'phase4b-credit122-preparation-20261021'
old = base / 'phase4b-material-input119-integration-20261020'


def read(path):
    return json.loads(path.read_text(encoding='utf-8-sig'))


def sha(path):
    with path.open('rb') as stream:
        return hashlib.file_digest(stream, 'sha256').hexdigest()


historical = root / 'historical-replay-v1/credit122-historical-validation.json'
assert sha(historical) == 'ddffca53a90188148c06c90d8b3cedb12ba24a2201e5198f823fb6505b349520'
historical_hashes = {Path(row['path']): row['sha256'] for row in read(historical)['records']}
accepted = repo / 'storage/app/phase4b-material119-development-20261020/material119-development-validation.json'
assert sha(accepted) == '98cb2e92afe2281cc88d94c3a49f155152cc146071a12a5e70cf7511d303cc19'
records = []
contexts = collections.Counter()
decision_shapes = {}
for family, folder, filename, count in [
    ('geography', 'cli-v1', 'material119-cli-validation.json', 16),
    ('contracts', 'contract-cli-v2', 'material119-contract-cli-validation.json', 45),
]:
    directory = old / folder
    report_path = directory / filename
    report = read(report_path)
    assert len(report['cases']) == count
    actual_paths = set()
    for row in report['cases']:
        name = row['name'] if family == 'contracts' else row['branch'] + '-' + row['destination_case']
        entry = {'family': family, 'name': name, 'runners': {}}
        for runner in ['rtc', 'integrated']:
            if runner + '_snapshot' in row:
                path = directory / row[runner + '_snapshot']
                digest = row[runner + '_snapshot_sha256']
            else:
                path = directory / row[runner]['snapshot']
                digest = row[runner]['snapshot_sha256']
            assert historical_hashes[path] == digest == sha(path)
            snapshot = read(path)
            actual_paths.add(path)
            result = snapshot['result']
            entry['runners'][runner] = {'snapshot': str(path), 'snapshot_sha256': digest,
                                         'status': result['status'], 'reason_codes': result['reason_codes']}
            if result['decision'] and runner not in decision_shapes:
                decision_shapes[runner] = {'keys': list(result['decision']),
                                           'catalog_versions': result['decision'].get('catalog_versions')}
            if runner == 'rtc':
                facts = snapshot['input']['facts']
                contexts[(family, facts.get('rtc.credit_context', 'ABSENT'))] += 1
                entry['source_credit_context'] = facts.get('rtc.credit_context')
                entry['source_document_price_optin'] = facts.get('rtc.assess_export_document_prices') is True
        records.append(entry)
    assert actual_paths == {path for path in historical_hashes if path.parent == directory}
assert len(records) == 61 and len({(row['family'], row['name']) for row in records}) == 61
target = root / 'credit122-compatibility119-source-index-v1.json'
with target.open('x', encoding='utf-8') as stream:
    json.dump({'created_at': datetime.now(timezone.utc).isoformat(), 'cases': records,
               'source119_acceptance_sha256': sha(accepted),
               'source1441_receipt_sha256': sha(historical),
               'geographic_cases': 16, 'contract_cases': 45,
               'current122_execution_complete': False, 'production_ready': False}, stream, ensure_ascii=False, indent=2)
print(json.dumps({'source_cases': 61, 'source_snapshot_hashes_verified': 122,
                  'credit_context_counts': {str(key): value for key, value in contexts.items()},
                  'document_price_optins': sum(row['source_document_price_optin'] for row in records),
                  'decision_shapes': decision_shapes, 'index_sha256': sha(target)}, ensure_ascii=False), flush=True)
