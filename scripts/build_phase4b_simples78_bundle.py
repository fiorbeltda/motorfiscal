"""Create an opt-in H1 Simples election bundle; retain old catalogs and H2 behavior."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json

ROOT = Path(__file__).resolve().parents[1]
F = ROOT / 'resources/fiscal'
E = ROOT / 'storage/app/phase4b-simples78-integration-preparation-20261015'
Q = ROOT / 'storage/app/phase4b-fx71-exact-quote-preparation-20261014'
S = Path(r'D:\ProjetoSaasExports\phase4b-simples78-native-acts-20261015')
V = '2026.10.15-78-draft1'


def read(path):
    return json.loads(path.read_text(encoding='utf-8'))


def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def canonical(value):
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode()).hexdigest()


def save(path, value):
    with path.open('x', encoding='utf-8') as stream:
        json.dump(value, stream, ensure_ascii=False, indent=2)


assert (E / 'before-integration-archive.json').exists()
for label in ['scope78-runtime-directed', 'scope78-runtime-phpstan', 'scope78-runtime-pint']:
    gate = read(Q / (label + '.json'))
    assert gate['exit_code'] == 0 and sha(Q / (label + '.log')) == gate['log_sha256'], label
source_validation = read(S / 'native-act-source-validation-v2.json')
assert source_validation['status'] == 'CGSN194_AND_CURRENT186_NATIVE_PUBLIC_SOURCES_VERIFIED_NOT_ACTIVATED'
policy = read(E / 'component-policy.json')
policy.pop('installed_in_engine')
bundle = read(F / 'bundles/consumption-phase4b-2026.10.15-76-draft1.json')
transitions = read(F / bundle['resource_paths']['rtc_transition_catalog'])
facts = read(F / bundle['resource_paths']['fact_definitions'])
questions = read(F / bundle['resource_paths']['questions'])
controls = read(F / bundle['production_controls']['path'])
original_h2 = canonical(transitions['simples_option_windows'][1])
transitions['version'] = V
h1 = transitions['simples_option_windows'][0]
assert h1['valid_from'] == '2027-01-01' and h1['valid_to'] == '2027-06-30'
h1.update(filing_to=policy['filing_through'], cancellation_from=policy['cancellation_from'],
          cancellation_through=policy['cancellation_through'], available_at=policy['available_at'],
          published_at='2026-09-28T00:00:00-03:00', qualification_policy=policy,
          source_id='CGSN194_NATIVE_VIGENTE_OBSERVED_20261015', source_hash=policy['source_hashes']['CGSN194'])
notice = transitions['simples_option_supersession_notices'][0]
assert notice['full_act_verified'] is False
notice.update(full_act_verified=True, full_act_verified_at=policy['available_at'],
              full_act_source_id=h1['source_id'], full_act_source_sha256=h1['source_hash'],
              resolution_scope='CGSN194_FULL_NATIVE_SOURCE_REVIEWED_H1_POLICY_ONLY')
assert canonical(transitions['simples_option_windows'][1]) == original_h2
added = [
    ('provider.cnpj_registered_on', 'DATE', 'Qual é a data comprovada de inscrição no CNPJ, separada do início de atividade?'),
    ('provider.cnpj_registration_evidence_id', 'STRING', 'Qual evidência identifica a inscrição no CNPJ e sua data?'),
    ('provider.initial_activity', 'BOOLEAN', 'A empresa está em início de atividade para a hipótese de inscrição no CNPJ?'),
    ('rtc.simples_election_route', 'ENUM', 'A opção regular IBS/CBS foi feita pela janela ordinária ou no ato de inscrição no CNPJ?'),
    ('rtc.simples_option_route_evidence_id', 'STRING', 'Qual evidência identifica a rota da opção regular IBS/CBS?'),
    ('rtc.simples_option_status_observed_on', 'DATE', 'Em que data a situação confirmada da opção foi comprovada para a operação?'),
]
rank = max(question['rank'] for question in questions)
for index, (key, kind, prompt) in enumerate(added, 1):
    assert not any(fact['key'] == key for fact in facts), key
    assert not any(question['fact_key'] == key for question in questions), key
    options = ['STANDARD_WINDOW', 'CNPJ_REGISTRATION'] if kind == 'ENUM' else None
    facts.append({'key': key, 'type': kind, 'material': True,
                  **({'min_length': 1} if kind == 'STRING' else {}),
                  **({'allowed_values': options} if options is not None else {})})
    questions.append({'fact_key': key, 'answer_type': kind, 'rank': rank + index, 'prompt': prompt,
                      **({'options': options} if options is not None else {})})
controls['version'] = V
targets = [F / ('catalogs/rtc-transition-' + V + '.json'),
           F / ('facts/consumption-phase4b-' + V + '.json'),
           F / ('questions/consumption-integrated-' + V + '.json'),
           F / ('production/phase4b-controls-' + V + '.json'),
           F / ('bundles/consumption-phase4b-' + V + '.json'), E / 'simples78-bundle-installation.json']
sources = []
for record in source_validation['records']:
    filename = 'cgsn' + record['number'] + '-native-vigente-observed-20261015.json'
    source_path = S / ('cgsn' + record['number'] + '-vigente-response.json')
    assert sha(source_path) == record['native_response_sha256']
    target = F / 'sources/official' / filename
    targets.append(target)
    sources.append((source_path, target, {'id': 'CGSN' + record['number'] + '_NATIVE_VIGENTE_OBSERVED_20261015',
        'url': record['source_url'], 'file': filename, 'sha256': record['native_response_sha256'],
        'version': 'NATIVE_JSON_VIGENTE_OBSERVED_2026.10.15', 'published_on': record['publication_date'],
        'effective_from': record['effective_date'], 'observed_at': policy['available_at'],
        'proven_effective_scope': 'H1 2027 regular IBS/CBS election, distinct registration exception and SIMEI exclusion;not government receipt authentication.'}))
assert not any(path.exists() for path in targets), 'Exclusive bundle/source installation target exists'
for role, value, relative in [
    ('rtc_transition_catalog', transitions, 'catalogs/rtc-transition-' + V + '.json'),
    ('fact_definitions', facts, 'facts/consumption-phase4b-' + V + '.json'),
    ('questions', questions, 'questions/consumption-integrated-' + V + '.json'),
]:
    save(F / relative, value)
    bundle['resource_paths'][role] = relative
    bundle['content_hashes'][role] = canonical(value)
control_path = 'production/phase4b-controls-' + V + '.json'
save(F / control_path, controls)
bundle['production_controls'] = {'path': control_path, 'sha256': canonical(controls)}
for source_path, target, source in sources:
    with target.open('xb') as stream:
        stream.write(source_path.read_bytes())
    assert sha(target) == source['sha256']
    bundle['official_sources'].append(source)
bundle.update(predecessor_bundle_id=bundle['bundle_id'], bundle_id='CONSUMPTION-PHASE4B-' + V,
              version=V, knowledge_available_at=datetime.now(timezone.utc).replace(microsecond=0).isoformat())
bundle['catalog_versions']['rtc_transition'] = V
bundle_path = F / ('bundles/consumption-phase4b-' + V + '.json')
save(bundle_path, bundle)
save(E / 'simples78-bundle-installation.json', {
    'status': 'SIMPLES78_OWN_H1_ELECTION_BUNDLE_CREATED_INTEGRATED_VALIDATION_PENDING',
    'bundle': bundle_path.relative_to(F).as_posix(), 'bundle_sha256': sha(bundle_path),
    'new_facts': [row[0] for row in added], 'official_source_count': len(bundle['official_sources']),
    'second_semester_row_unchanged_sha256': original_h2,
    'runtime_hashes': {path: sha(ROOT / path) for path in [
        'app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php',
        'app/Modules/Fiscal/ConsumptionReform/TransitionCatalog.php',
        'app/Modules/Fiscal/ConsumptionReform/SimplesRegularElectionQualifier78.php']},
    'full_suite_complete': False, 'phase4b_complete': False, 'production_ready': False})
print('PASS:own78 bundle/six facts/questions/two native sources;old catalogs and H2 unchanged;integration corpus pending')
