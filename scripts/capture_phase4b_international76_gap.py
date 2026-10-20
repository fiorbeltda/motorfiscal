"""Reproduce integrated export and foreign-buyer gaps without editing fiscal runtime."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import subprocess

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-international76-gap-20261015'
F = ROOT / 'resources/fiscal'
M = 'bundles/consumption-phase4b-2026.10.14-74-draft1.json'
D = r'D:\Docker\DockerDesktop\resources\bin\docker.exe'


def read(path):
    return json.loads(path.read_text(encoding='utf-8'))


def save(name, value):
    with (E / name).open('x', encoding='utf-8') as stream:
        json.dump(value, stream, ensure_ascii=False, indent=2)


def run(label, args):
    command = [D, 'compose', 'exec', '-T', 'app', 'php', '-d', 'memory_limit=512M', 'artisan', *args]
    result = subprocess.run(command, cwd=ROOT, capture_output=True, encoding='utf-8')
    with (E / (label + '.log')).open('x', encoding='utf-8') as stream:
        stream.write(result.stdout + result.stderr)
    save(label + '-execution.json', {'command': command, 'exit_code': result.returncode,
        'log_sha256': hashlib.sha256((E / (label + '.log')).read_bytes()).hexdigest()})
    return result.returncode, json.loads(result.stdout)


E.mkdir(exist_ok=False)
bundle = read(F / M)
known = datetime.now(timezone.utc).replace(microsecond=0).isoformat()
records = []
for name, fixture in [('full-export', 'export-foreign-consumption-2026.json'),
                      ('foreign-buyer-domestic-consumption', 'regular-saas-foreign-acquirer-brazilian-recipient-2026.json')]:
    source_path = F / 'scenarios/consumption-phase4b-2026.09.28-1' / fixture
    source = read(source_path)
    source.update(scenario_id='SYNTHETIC-INTERNATIONAL76-' + name, knowledge_at=known,
        rule_bundle_id=bundle['bundle_id'], catalog_versions=bundle['catalog_versions'])
    save(name + '-input.json', source)
    snapshot_path = E / (name + '-snapshot.json')
    exit_code, result = run(name + '-run', ['fiscal:scenario:run', str((E / (name + '-input.json')).relative_to(ROOT)).replace('\\', '/'),
        '--bundle-manifest=' + M, '--snapshot=' + str(snapshot_path.relative_to(ROOT)).replace('\\', '/')])
    assert exit_code == 0 and result['status'] != 'ERROR', result
    replay_exit, replay = run(name + '-replay', ['fiscal:decision:replay', str(snapshot_path.relative_to(ROOT)).replace('\\', '/')])
    assert replay_exit == 0 and replay['replay_matches'] and replay['result'] == result
    identity = read(F / 'scenarios/dps-local-2026.10.01-6/presential-identity.json')
    identity.update(competence_date=source['competence_date'], issued_at=known, service_municipality='3509502')
    save(name + '-identity.json', identity)
    project_exit, projected = run(name + '-project', ['fiscal:dps:project-xml',
        str(snapshot_path.relative_to(ROOT)).replace('\\', '/'),
        str((E / (name + '-identity.json')).relative_to(ROOT)).replace('\\', '/'), '--bundle-manifest=' + M])
    records.append({'case': name, 'original_fixture': str(source_path.relative_to(ROOT)).replace('\\', '/'),
        'original_fixture_sha256': hashlib.sha256(source_path.read_bytes()).hexdigest(),
        'integrated_status': result['status'], 'integrated_reasons': result.get('reason_codes'),
        'decision': result.get('decision'), 'exact_replay': True,
        'projection_exit_code': project_exit, 'projection': projected,
        'snapshot_sha256': hashlib.sha256(snapshot_path.read_bytes()).hexdigest()})
    print(name + ': ' + result['status'] + ';projection=' + json.dumps(projected, ensure_ascii=False), flush=True)
save('international-gap-validation.json', {'status': 'TWO_INTEGRATED_INTERNATIONAL_ROUTES_REPRODUCED',
    'cases': records, 'runtime_changed': False, 'document_obligation_complete': False, 'production_ready': False})
