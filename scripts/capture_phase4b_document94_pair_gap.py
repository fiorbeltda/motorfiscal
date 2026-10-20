"""Observe both native runners for six existing export allocations; never infer per-document ISS."""
from pathlib import Path
from datetime import datetime, timezone
from decimal import Decimal
import hashlib
import json
import subprocess

ROOT = Path(__file__).resolve().parents[1]
E = Path(r'D:\ProjetoSaasExports\phase4b-document94-pair-binding-gap-20261016')
OLD = ROOT / 'storage/app/phase4b-partial-export-fx73-preparation-20261014'
D = r'D:\Docker\DockerDesktop\resources\bin\docker.exe'
TEMP = '/tmp/phase4b-document94-pair-binding-gap-20261016'
BUNDLE = 'bundles/consumption-phase4b-2026.10.14-73-draft1.json'

def sha(p):
    with p.open('rb') as stream:
        return hashlib.file_digest(stream, 'sha256').hexdigest()

def read(p):
    return json.loads(p.read_text(encoding='utf-8'))

def save(p, value):
    with p.open('x', encoding='utf-8') as stream:
        json.dump(value, stream, ensure_ascii=False, indent=2)

assert not E.exists(), 'Exclusive evidence directory'
E.mkdir()
container = subprocess.run([D, 'compose', 'ps', '-q', 'app'], cwd=ROOT, capture_output=True, text=True, check=True).stdout.strip()
assert container
subprocess.run([D, 'compose', 'exec', '-T', 'app', 'mkdir', TEMP], cwd=ROOT, check=True)
manifest = read(OLD / 'export-integrated-corpus-validation.json')
records = []
frozen = {str(OLD / 'export-integrated-corpus-validation.json'): sha(OLD / 'export-integrated-corpus-validation.json'),
          str(ROOT / 'resources/fiscal' / BUNDLE): sha(ROOT / 'resources/fiscal' / BUNDLE)}

def run(label, args):
    command = [D, 'compose', 'exec', '-T', 'app', 'php', '-d', 'memory_limit=512M', 'artisan', *args]
    started = datetime.now(timezone.utc).isoformat()
    proc = subprocess.run(command, cwd=ROOT, capture_output=True)
    execution = {'command': command, 'started_at': started, 'finished_at': datetime.now(timezone.utc).isoformat(), 'exit_code': proc.returncode}
    for channel in ['stdout', 'stderr']:
        raw = getattr(proc, channel)
        with (E / (label + '.' + channel)).open('xb') as stream:
            stream.write(raw)
        execution[channel + '_sha256'] = hashlib.sha256(raw).hexdigest()
    save(E / (label + '-execution.json'), execution)
    assert proc.returncode == 0, (label, proc.stdout.decode(errors='replace')[-1000:])
    return json.loads(proc.stdout)

for case in manifest['cases']:
    if not case['nominal_path']:
        continue
    name = case['name']
    scenario = read(ROOT / case['path'])
    frozen[str(ROOT / case['path'])] = sha(ROOT / case['path'])
    old_snapshot = OLD / (name + '-export-snapshot.json')
    assert sha(old_snapshot) == case['snapshot_sha256']
    frozen[str(old_snapshot)] = sha(old_snapshot)
    result_set = {}
    captures = {}
    for kind, command, replay in [('rtc', 'fiscal:rtc:run', 'fiscal:rtc:replay'),
                                  ('integrated', 'fiscal:scenario:run', 'fiscal:decision:replay')]:
        label = name + '-' + kind
        snapshot = TEMP + '/' + label + '-snapshot.json'
        result = run(label, [command, case['path'], '--bundle-manifest=' + BUNDLE, '--snapshot=' + snapshot])
        assert result['status'] == 'ACTION_REQUIRED' and case['expected_reason'] in result['reason_codes']
        assert run(label + '-replay', [replay, snapshot])['replay_matches'] is True
        destination = E / (label + '-snapshot.json')
        assert not destination.exists()
        subprocess.run([D, 'cp', container + ':' + snapshot, str(destination)], cwd=ROOT, check=True, capture_output=True)
        assert read(destination)['result'] == result
        captures[kind] = {'snapshot_file': destination.name, 'sha256': sha(destination), 'native_exact_replay': True}
        result_set[kind] = result
    rtc = {r['stage']: r for r in result_set['rtc']['trace']}
    integrated = {r['type']: r['payload'] for r in result_set['integrated']['trace']}
    integrated_rtc = {r['stage']: r for r in integrated['CONSUMPTION_REFORM']['trace']}
    assert rtc['PARTIAL_EXPORT_GROSS_ALLOCATION'] == integrated_rtc['PARTIAL_EXPORT_GROSS_ALLOCATION']
    amounts = case['independent_decimal']
    allocation = rtc['PARTIAL_EXPORT_GROSS_ALLOCATION']
    for key, expected in [('total_gross_brl', 'total_gross_brl'), ('foreign_gross_brl', 'foreign_immune_gross_brl'), ('domestic_gross_brl', 'domestic_taxable_base_brl')]:
        assert allocation[key] == amounts[expected]
    assert Decimal(allocation['foreign_gross_brl']) + Decimal(allocation['domestic_gross_brl']) == Decimal(allocation['total_gross_brl'])
    iss = integrated['ISS_CALCULATION']
    assert iss['valid'] is True and iss['currency'] == 'BRL'
    assert iss['gross_amount'] == scenario['facts']['operation.amount']
    assert iss['taxable_base'] == scenario['facts']['operation.amount']
    assert iss['deduction_amount'] == '0.00'
    facts = scenario['facts']
    identifiers = {key: facts.get(key) for key in ['rtc.document_id', 'rtc.export_foreign_document_id', 'rtc.export_domestic_document_id',
                                                  'rtc.export_transaction_evidence_id', 'rtc.acquirer_foreign_nif']}
    records.append({'name': name, 'native_captures': captures, 'competence_date': scenario['competence_date'],
                    'rtc_allocation': allocation, 'iss_full_operation_memory': iss,
                    'original_identifiers': identifiers, 'foreign_document_iss_qualification': None,
                    'domestic_document_iss_qualification': None, 'documentary_conversion_qualified': False,
                    'per_document_tax_amount_inferred': False, 'iss_zero_assumed_for_export': False,
                    'rtc_allocation_alone_proves_document_vserv': False,
                    'reason': 'NATIVE_ISS_IS_FULL_OPERATION_MEMORY_NOT_TWO_QUALIFIED_DOCUMENT_MEMORIES'})
    print('PASS:' + name + '; dual native runners/exact replays; independent ISS/RTC memories retained', flush=True)
assert len(records) == 6
assert all(sha(Path(p)) == digest for p, digest in frozen.items())
report = {'status': 'SIX_NATIVE_DUAL_RUNNER_DOCUMENT_PAIR_BINDING_GAPS_REPRODUCED',
          'created_at': datetime.now(timezone.utc).isoformat(), 'native_decisions': 12, 'native_exact_replays': 12,
          'source_input_hashes': frozen, 'cases': records,
          'document_obligation_complete': False, 'xml_generated': False, 'tax_recalculated': False,
          'phase4b_complete': False, 'production_ready': False,
          'next_required': 'Qualify ISS incidence, documentary conversion and bases independently for each document; do not reuse the full-operation ISS memory or allocate its tax by RTC share without proof.'}
save(E / 'document94-native-pair-binding-gap.json', report)
print(json.dumps({'status': report['status'], 'cases': len(records), 'native_decisions': 12, 'native_exact_replays': 12}), flush=True)
