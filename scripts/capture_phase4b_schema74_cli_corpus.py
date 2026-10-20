"""Exclusive native CLI corpus for the opt-in restricted schema; no transmission."""
from pathlib import Path
from datetime import datetime, timedelta
import copy
import hashlib
import json
import shutil
import subprocess

ROOT = Path(__file__).resolve().parents[1]
F = ROOT / 'resources/fiscal'
E = ROOT / 'storage/app/phase4b-schema74-cli-corpus-v2-20261014'
V = '2026.10.14-74-draft1'
MANIFEST = 'bundles/consumption-phase4b-' + V + '.json'
DOCKER = r'D:\Docker\DockerDesktop\resources\bin\docker.exe'


def read(path):
    return json.loads(path.read_text(encoding='utf-8'))


def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def save(path, data):
    with path.open('x', encoding='utf-8') as stream:
        json.dump(data, stream, ensure_ascii=False, indent=2)


def relative(path):
    return path.relative_to(ROOT).as_posix()


def run(label, args, expected_exit=0):
    command = [DOCKER, 'compose', 'exec', '-T', 'app', 'php', '-d',
               'memory_limit=512M', 'artisan', *args]
    result = subprocess.run(command, cwd=ROOT, capture_output=True, encoding='utf-8')
    with (E / (label + '.stdout')).open('x', encoding='utf-8') as stream:
        stream.write(result.stdout)
    with (E / (label + '.stderr')).open('x', encoding='utf-8') as stream:
        stream.write(result.stderr)
    record = {'command': command, 'exit_code': result.returncode,
              'stdout_sha256': sha(E / (label + '.stdout')),
              'stderr_sha256': sha(E / (label + '.stderr'))}
    save(E / (label + '-execution.json'), record)
    assert result.returncode == expected_exit, (label, result.stdout[-2000:], result.stderr[-2000:])
    payload = json.loads(result.stdout)
    return payload


assert shutil.disk_usage(ROOT).free > 100 * 1024 * 1024, 'Insufficient disk for small directed corpus'
E.mkdir(exist_ok=False)
bundle = read(F / MANIFEST)
codes = read(F / bundle['resource_paths']['rtc_code_catalog'])
known = bundle['knowledge_available_at']
observed = codes['local_xml_projection']['schema_selection']['source_observed_at']
assert datetime.fromisoformat(known) >= datetime.fromisoformat(observed)
inputs = [
    ('federal', 'storage/app/phase4b-dps-integrated-20261001-2-snapshot.json',
     'resources/fiscal/scenarios/dps-local-2026.10.01-2/federal-identity.json'),
    ('education', 'storage/app/phase4b-education-20261001-5-snapshot.json',
     'resources/fiscal/scenarios/dps-local-2026.10.01-5/presential-identity.json'),
    ('presential', 'storage/app/phase4b-integrated-education-presential-20261001-6-snapshot.json',
     'resources/fiscal/scenarios/dps-local-2026.10.01-6/presential-identity.json'),
]
records = []
for name, old_snapshot, old_identity in inputs:
    old_path = ROOT / old_snapshot
    before = sha(old_path)
    old = read(old_path)
    replay = run(name + '-historical-replay', ['fiscal:decision:replay', old_snapshot])
    assert replay['replay_matches'] and replay['result'] == old['result']
    scenario = copy.deepcopy(old['input'])
    scenario.update(scenario_id='SYNTHETIC-SCHEMA74-' + name, knowledge_at=known,
                    rule_bundle_id=bundle['bundle_id'], catalog_versions=bundle['catalog_versions'])
    scenario_path = E / (name + '-scenario.json')
    snapshot_path = E / (name + '-snapshot.json')
    save(scenario_path, scenario)
    decision = run(name + '-run', ['fiscal:scenario:run', relative(scenario_path),
        '--bundle-manifest=' + MANIFEST, '--snapshot=' + relative(snapshot_path)])
    assert decision['status'] in ['VALID', 'VALID_WITH_WARNINGS'], (name, decision.get('status'))
    replay_new = run(name + '-new-replay', ['fiscal:decision:replay', relative(snapshot_path)])
    assert replay_new['replay_matches'] and replay_new['result'] == decision
    identity = read(ROOT / old_identity)
    identity.update(issued_at=known, competence_date=scenario['competence_date'])
    projected = []
    for series in ['1', '00001', '89999']:
        identity_path = E / (name + '-identity-' + series + '.json')
        xml = E / (name + '-' + series + '.xml')
        save(identity_path, {**identity, 'series': series})
        args = ['fiscal:dps:project-xml', relative(snapshot_path), relative(identity_path),
                '--bundle-manifest=' + MANIFEST]
        report = run(name + '-project-' + series, [*args, '--output=' + relative(xml)])
        second = run(name + '-project-repeat-' + series, args)
        assert report['status'] == 'VALID' and report['schema_validation']['status'] == 'VALID'
        assert report['schema_selection']['status'] == 'VALID' and report['production_ready'] is False
        assert report['schema_selection']['remote_deployment_verified'] is False
        assert report['schema_selection']['document_obligation_qualified'] is False
        assert {k: v for k, v in report.items() if k != 'output'} == {k: v for k, v in second.items() if k != 'output'}
        assert report['schema_validation']['xml_sha256'] == sha(xml)
        projected.append({'series': series, 'xml': relative(xml), 'xml_sha256': sha(xml)})
    refusals = []
    for refusal, patch, reason in [
        ('production', {'environment': '1'}, 'PRODUCTION_ENVIRONMENT_FORBIDDEN'),
        ('series', {'series': '90000'}, 'SERIES_OUTSIDE_RESTRICTED_SCHEMA'),
        ('old-issuance', {'issued_at': old['input']['knowledge_at']}, 'ARTIFACT_NOT_YET_OBSERVED'),
        ('future-issuance', {'issued_at': (datetime.fromisoformat(known) + timedelta(seconds=1)).isoformat()}, 'ISSUANCE_AFTER_KNOWLEDGE'),
    ]:
        identity_path = E / (name + '-refusal-' + refusal + '.json')
        forbidden_xml = E / (name + '-forbidden-' + refusal + '.xml')
        save(identity_path, {**identity, **patch})
        response = run(name + '-refusal-' + refusal, ['fiscal:dps:project-xml',
            relative(snapshot_path), relative(identity_path), '--bundle-manifest=' + MANIFEST,
            '--output=' + relative(forbidden_xml)], 1)
        assert response['reason'] == 'RTC_DPS_RESTRICTED_SCHEMA_' + reason
        assert not forbidden_xml.exists()
        refusals.append({'case': refusal, 'reason': response['reason'], 'xml_not_written': True})
    assert sha(old_path) == before
    records.append({'name': name, 'historical_snapshot': old_snapshot, 'historical_snapshot_sha256': before,
        'historical_exact_replay': True, 'new_snapshot': relative(snapshot_path),
        'new_snapshot_sha256': sha(snapshot_path), 'new_exact_replay': True,
        'xml': projected, 'refusals': refusals})
    print('PASS:', name, 'three XSD-valid CLI documents, four refusals, old/new exact replay', flush=True)
assessment = run('local-production-assessment', ['fiscal:rtc:production-assess',
    '--bundle-manifest=' + MANIFEST, '--controls=resources/fiscal/production/phase4b-controls-' + V + '.json',
    '--target=local-validation'])
assert assessment['ready'] is False and assessment['eligible_for_local_validation'] is True
assert 'RTC_DPS_RESTRICTED_SCHEMA_LOCAL_ONLY' in assessment['blockers']
save(E / 'cli-corpus-validation.json', {'status': 'LOCAL_SCHEMA74_CLI_CORPUS_VERIFIED',
    'bundle': MANIFEST, 'bundle_sha256': sha(F / MANIFEST), 'cases': records,
    'source_observed_at': observed, 'production_blockers': assessment['blockers'],
    'nt009_migration_complete': False, 'full_suite_complete': False, 'phase4b_complete': False,
    'production_ready': False})
print('PASS: local eligibility proven;production explicitly refused')
