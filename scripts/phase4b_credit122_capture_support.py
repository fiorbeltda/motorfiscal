"""Shared capture utilities and narrowly declared119 ->122 comparison rules."""
from pathlib import Path
from datetime import datetime
import copy
import hashlib
import json

R = Path(__file__).resolve().parents[1]
BASE = Path(r'D:\ProjetoSaasExports')
E = BASE / 'phase4b-credit122-preparation-20261021'
D = r'D:\Docker\DockerDesktop\resources\bin\docker.exe'
BUNDLE = 'bundles/consumption-phase4b-2026.10.21-122-draft1.json'


def read(path):
    return json.loads(Path(path).read_text(encoding='utf-8-sig'))


def sha(path):
    with Path(path).open('rb') as stream:
        return hashlib.file_digest(stream, 'sha256').hexdigest()


def canonical(value):
    return json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':'))


def canonical_hash(value):
    return hashlib.sha256(canonical(value).encode('utf-8')).hexdigest()


def save(path, value):
    with Path(path).open('x', encoding='utf-8') as stream:
        json.dump(value, stream, ensure_ascii=False, indent=2)


def core_inventory():
    paths = {path for folder in ['app', 'tests', 'resources/fiscal']
             for path in (R / folder).rglob('*') if path.is_file()}
    paths |= {R / 'composer.lock', R / 'phpunit.xml'}
    return {str(path): sha(path) for path in sorted(paths)}


def rtc_body(result, runner):
    if runner == 'rtc':
        return result
    return next((row['payload'] for row in result['trace'] if row['type'] == 'CONSUMPTION_REFORM'), None)


def compare_compatibility_result(old, new, runner, *, old_input, new_input):
    """Keep all fiscal fields/RTC trace; normalize declared bundle metadata and one resolved policy stage only.

    Cross-version outer execution-plan trace is NOT claimed identical. Current
    complete results/trace are independently checked by each exact own replay.
    """
    assert old['status'] == new['status']
    assert old['reason_codes'] == new['reason_codes']
    assert canonical(old['questions']) == canonical(new['questions'])
    assert canonical(old['missing_facts']) == canonical(new['missing_facts'])
    metadata_keys = {'scenario_id', 'knowledge_at', 'rule_bundle_id', 'catalog_versions'}
    assert set(old_input) == set(new_input)
    assert canonical({key: value for key, value in old_input.items() if key not in metadata_keys}) == canonical({key: value for key, value in new_input.items() if key not in metadata_keys})
    old_known_at, new_known_at = old_input['knowledge_at'], new_input['knowledge_at']
    manifests = [read(R / 'resources/fiscal/bundles/consumption-phase4b-2026.10.20-119-draft1.json'),
                 read(R / 'resources/fiscal' / BUNDLE)]
    catalogs = [read(R / 'resources/fiscal' / item['resource_paths']['rtc_code_catalog']) for item in manifests]
    assert manifests[0]['bundle_id'] == 'CONSUMPTION-PHASE4B-2026.10.20-119-draft1'
    assert manifests[1]['bundle_id'] == 'CONSUMPTION-PHASE4B-2026.10.21-122-draft1'
    changes = []

    def normalize_tax_decision(a, b, path):
        if isinstance(a, dict) and 'catalog_versions' in a:
            assert a['catalog_versions']['codes'] == '2026.10.20-119-draft1'
            assert b['catalog_versions']['codes'] == '2026.10.21-122-draft1'
            changes.append(path + '.catalog_versions.codes')
            b['catalog_versions']['codes'] = a['catalog_versions']['codes']
        assert canonical(a) == canonical(b), 'Unapproved fiscal decision change: ' + path

    def normalize_assessment(a, b, path):
        assert (a is None) == (b is None), path
        if a is None:
            return
        old_temporal = [row for row in a['trace'] if row.get('stage') == 'TEMPORAL']
        assert len(old_temporal) == 1
        assert datetime.fromisoformat(old_temporal[0]['known_at']) == datetime.fromisoformat(old_known_at)
        filtered = []
        for stage in b['trace']:
            if stage.get('stage') == 'CREDIT_INPUT_POLICY122':
                expected = {'stage': 'CREDIT_INPUT_POLICY122', 'status': 'RESOLVED',
                            'reason': 'RTC_CREDIT122_SOURCE_POLICY_BOUND', 'missing_facts': [],
                            'policy_hash': canonical_hash(catalogs[1]['credit_input_policy122']),
                            'credit_granted': False, 'document_authenticated': False, 'extinction_proven': False}
                assert canonical(stage) == canonical(expected), 'New credit policy did not qualify exactly'
                changes.append(path + '.trace.CREDIT_INPUT_POLICY122')
            else:
                if stage.get('stage') == 'TEMPORAL':
                    assert datetime.fromisoformat(stage['known_at']) == datetime.fromisoformat(new_known_at)
                    stage['known_at'] = old_temporal[0]['known_at']
                    changes.append(path + '.trace.TEMPORAL.known_at')
                filtered.append(stage)
        b['trace'] = filtered
        normalize_tax_decision(a['decision'], b['decision'], path + '.decision')
        for key in ['status', 'reason_codes', 'missing_facts', 'trace']:
            assert canonical(a[key]) == canonical(b[key]), 'Unapproved RTC assessment change: ' + path + '.' + key

    a_decision, b_decision = copy.deepcopy(old['decision']), copy.deepcopy(new['decision'])
    normalize_assessment(copy.deepcopy(rtc_body(old, runner)), copy.deepcopy(rtc_body(new, runner)), 'rtc_assessment')
    if runner == 'rtc':
        normalize_tax_decision(a_decision, b_decision, 'decision')
    else:
        if isinstance(a_decision, dict) and 'versions' in a_decision:
            a, b = a_decision['versions'], b_decision['versions']
            assert a['rule_bundle_id'] == manifests[0]['bundle_id'] and b['rule_bundle_id'] == manifests[1]['bundle_id']
            b['rule_bundle_id'] = a['rule_bundle_id']
            changes.append('decision.versions.rule_bundle_id')
            assert a['catalogs']['rtc_codes'] == catalogs[0]['version'] and b['catalogs']['rtc_codes'] == catalogs[1]['version']
            b['catalogs']['rtc_codes'] = a['catalogs']['rtc_codes']
            changes.append('decision.versions.catalogs.rtc_codes')
            for key, old_value, new_value in [('bundle_manifest', manifests[0], manifests[1]),
                                               ('rtc_code_catalog', catalogs[0], catalogs[1])]:
                assert a['content_hashes'][key] == canonical_hash(old_value)
                assert b['content_hashes'][key] == canonical_hash(new_value)
                b['content_hashes'][key] = a['content_hashes'][key]
                changes.append('decision.versions.content_hashes.' + key)
        if isinstance(a_decision, dict) and 'facts_used' in a_decision:
            old_facts, new_facts = a_decision['facts_used'], b_decision['facts_used']
            assert len(old_facts) == len(new_facts)
            for old_fact, new_fact in zip(old_facts, new_facts, strict=True):
                assert old_fact['key'] == new_fact['key']
                if old_fact['recorded_at'] != new_fact['recorded_at']:
                    supplied = old_input['facts'].get(old_fact['key'])
                    explicit = isinstance(supplied, dict) and 'value' in supplied and 'recorded_at' in supplied
                    assert not explicit, 'Literal provided recording timestamp must never be rewritten'
                    assert datetime.fromisoformat(old_fact['recorded_at']) == datetime.fromisoformat(old_known_at)
                    assert datetime.fromisoformat(new_fact['recorded_at']) == datetime.fromisoformat(new_known_at)
                    new_fact['recorded_at'] = old_fact['recorded_at']
                    changes.append('decision.facts_used.' + old_fact['key'] + '.implicit_recorded_at')
                assert canonical(old_fact) == canonical(new_fact), 'Unapproved fact value/provenance change'
        if isinstance(a_decision, dict) and 'consumption_reform' in a_decision:
            normalize_assessment(a_decision['consumption_reform'], b_decision['consumption_reform'], 'decision.consumption_reform')
        assert canonical(a_decision) == canonical(b_decision), 'Unapproved integrated business decision change'
    return sorted(set(changes))
