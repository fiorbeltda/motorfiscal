"""Recapture wider public financial/monthly corpus under54 with immutable sources and explicit synthetic witnesses."""
from pathlib import Path
import hashlib
import json
import subprocess
from dere_daily_memory_oracle import digest

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-dere9121-56-corpus-preparation-20261013'
P = ROOT / 'storage/app/phase4b-dere9121-54-preparation-20261013'
manifest = json.loads((E / 'source-corpus-manifest.json').read_text(encoding='utf-8'))
bundle = json.loads((ROOT / 'resources/fiscal/bundles/consumption-phase4b-2026.10.13-28-draft54.json').read_text(encoding='utf-8'))

def read(path):
    return json.loads(path.read_text(encoding='utf-8'))

def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()

def save(path, value):
    if path.exists():
        assert read(path) == value, path
    else:
        with path.open('x', encoding='utf-8') as stream:
            json.dump(value, stream, ensure_ascii=False, indent=2)

def run(label, args, expected=None):
    log = E / (label + '.log')
    record = E / (label + '.json')
    if record.exists():
        metadata = read(record)
        assert metadata['exit_code'] == 0 and metadata['log_sha256'] == sha(log)
        stdout = log.read_text(encoding='utf-8')
    else:
        assert not log.exists()
        command = ['docker', 'compose', 'exec', '-T', 'app', 'php', '-d', 'memory_limit=512M', *args]
        result = subprocess.run(command, cwd=ROOT, capture_output=True, encoding='utf-8')
        with log.open('x', encoding='utf-8') as stream:
            stream.write(result.stdout + result.stderr)
        save(record, {'command': command, 'exit_code': result.returncode, 'log_sha256': sha(log)})
        assert result.returncode == 0, label + ': ' + result.stderr
        stdout = result.stdout
    if expected is None:
        assert 'PASS: own54 public D9121' in stdout, label
        return None
    data = json.loads(stdout)
    assert data['status'] == expected, label
    return data

source_driver = (P / 'capture-public-settlement-v3.php').read_text(encoding='utf-8')
records = []
for case in manifest['cases']:
    name = case['name']
    for key in ['source_month', 'source_financial_input', 'source_monthly_report', 'source_d2101']:
        assert sha(ROOT / case[key]['path']) == case[key]['sha256'], case[key]['path']
    financial = read(ROOT / case['source_financial_input']['path'])
    financial.update(scenario_id='SYNTHETIC-D9121-56-' + name, knowledge_at=bundle['knowledge_available_at'], rule_bundle_id=bundle['bundle_id'])
    financial['catalog_versions'] = bundle['catalog_versions']
    financial_path = E / (name + '-financial.json')
    snapshot = E / (name + '-snapshot.json')
    save(financial_path, financial)
    data = run(name + '-financial-check', ['artisan', 'fiscal:rtc:run', financial_path.relative_to(ROOT).as_posix(),
        '--bundle-manifest=bundles/consumption-phase4b-2026.10.13-28-draft54.json',
        '--snapshot=' + snapshot.relative_to(ROOT).as_posix()], 'ACTION_REQUIRED')
    assert data['reason_codes'] == ['RTC_FINANCIAL_DOCUMENT_PENDING'] and snapshot.exists()
    php = source_driver.replace("__DIR__.'/financial-snapshot.json'", "__DIR__+'/PLACEHOLDER'")
    php = php.replace("__DIR__+'/PLACEHOLDER'", "__DIR__+'/" + name + "-snapshot.json'")
    php = php.replace("__DIR__+'/" + name + "-snapshot.json'", "__DIR__.'/" + name + "-snapshot.json'")
    old_month = "resource_path('fiscal/scenarios/dere-variable-yield-2026.10.06-27/constant.json')"
    assert php.count(old_month) == 1
    php = php.replace(old_month, "resource_path('" + case['source_month']['path'].removeprefix('resources/') + "')")
    php = php.replace("__DIR__.'/synthetic-d9121-v2.xml'", "storage_path('app/phase4b-dere9121-54-preparation-20261013/synthetic-d9121-v2.xml')")
    php = php.replace("__DIR__.'/public-settlement-native-result.json'", "__DIR__.'/" + name + "-settlement.json'")
    marker = "$proof['value']['book_input_hash']"
    assert php.count(marker) == 1
    php = php.replace(marker, "$proof['value']['operation_hash'] = App\\Modules\\Fiscal\\Core\\Shared\\CanonicalJson::hash($stages['FINANCIAL_OPERATION_AND_BASE']);\n" + marker)
    driver = E / (name + '-capture.php')
    if driver.exists():
        assert driver.read_text(encoding='utf-8') == php
    else:
        with driver.open('x', encoding='utf-8') as stream:
            stream.write(php)
    run(name + '-settlement-capture-check', [driver.relative_to(ROOT).as_posix(), 'capture'])
    run(name + '-settlement-replay-check', [driver.relative_to(ROOT).as_posix(), 'replay'])
    settlement = read(E / (name + '-settlement.json'))
    assert settlement['result']['composition']['calculation_input']['public_offer_debt_return'] == case['original_public_total']
    month_path = E / (name + '-month.json')
    request_path = E / (name + '-request.json')
    save(month_path, settlement['input']['month'])
    save(request_path, settlement['input']['request'])
    report_path = E / (name + '-monthly-report.json')
    candidate = E / (name + '-monthly.xml')
    args = ['artisan', 'fiscal:dere:project-monthly-closing', snapshot.relative_to(ROOT).as_posix(), month_path.relative_to(ROOT).as_posix(),
        '--bundle-manifest=bundles/consumption-phase4b-2026.10.13-28-draft54.json',
        '--negative-base-settlement-request=' + request_path.relative_to(ROOT).as_posix()]
    report = run(name + '-monthly-capture-check', args + ['--report=' + report_path.relative_to(ROOT).as_posix(),
        '--candidate-output=' + candidate.relative_to(ROOT).as_posix()], 'ACTION_REQUIRED')
    assert report == read(report_path) and report['negative_base_settlement'] == settlement['result']
    replay = run(name + '-monthly-replay-check', args, 'ACTION_REQUIRED')
    keys = set(report) - {'event_outputs', 'candidate_output'}
    assert {k: report[k] for k in keys} == {k: replay[k] for k in keys}
    assert len(report['event_validations']) == 6
    for event, path in report['event_outputs'].items():
        assert sha(ROOT / path) == report['event_validations'][event]['xml_sha256']
        assert report['event_validations'][event]['reason'] == 'RTC_DERE_UNSIGNED_LOCAL_CANDIDATE'
    records.append({'name': name, 'snapshot_sha256': sha(snapshot), 'settlement_sha256': sha(E / (name + '-settlement.json')),
        'report_sha256': sha(report_path), 'public_total': case['original_public_total'], 'title_count': case['title_count'],
        'exact_settlement_replay': True, 'exact_monthly_replay': True, 'xml_hashes': {k: v['xml_sha256'] for k, v in report['event_validations'].items()}})
    print('PASS: ' + name + '; own54 composition, BCN, six XMLs and exact replays', flush=True)
save(E / 'corpus-capture-validation.json', {'status': 'PUBLIC_MONTHLY_CORPUS_UNDER54_CAPTURED_AND_REPLAYED',
    'cases': records, 'source_manifest_hash': digest(manifest), 'no_movement_path_complete': False,
    'public_version_chain_complete': False, 'production_ready': False})
print('PASS: '+str(len(records))+' public monthly cases; '+str(len(records)*6)+' XML hashes; source evidence preserved', flush=True)
