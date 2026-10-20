"""Read-only108 evidence checks, shared by preflight and independent terminal audit."""
from pathlib import Path
from datetime import datetime
from decimal import Decimal
import hashlib
import json
import re

ROOT = Path(__file__).resolve().parents[1]
E = Path(r'D:\ProjetoSaasExports\phase4b-dated108-integration-20261019')
C = E / 'cli-v1'
F = Path(r'D:\ProjetoSaasExports\phase4b-dated108-full-suite-20261019')
OLD = Path(r'D:\ProjetoSaasExports\phase4b-identity104-full-suite-20261019')
OLD_C = Path(r'D:\ProjetoSaasExports\phase4b-identity104-integration-20261019\cli-v2')
A104 = ROOT / 'storage/app/phase4b-identity104-development-20261019/identity104-development-validation.json'
D = r'D:\Docker\DockerDesktop\resources\bin\docker.exe'

def read(path):
    return json.loads(path.read_text(encoding='utf-8'))

def sha(path):
    with path.open('rb') as stream:
        return hashlib.file_digest(stream, 'sha256').hexdigest()

def save(path, value):
    with path.open('x', encoding='utf-8') as stream:
        json.dump(value, stream, ensure_ascii=False, indent=2)

def clean(path):
    return re.sub(r'\x1b\[[0-9;]*m', '', path.read_text(encoding='utf-8', errors='replace'))

def canonical(value):
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode()).hexdigest()

def body(result, kind):
    return result if kind == 'rtc' else {r['type']: r['payload'] for r in result['trace']}['CONSUMPTION_REFORM']

def gate(directory, label, expected=0):
    result = read(directory / (label + '.json'))
    assert result['exit_code'] == expected, label
    for channel in ['stdout', 'stderr']:
        assert sha(directory / (label + '.' + channel)) == result[channel + '_sha256'], label
    return result

def verify():
    assert sha(A104) == '8f3d57986e7fea2f87ca317914bc1ecac90e7cfd719607839e88fa5128262bd1'
    directed = read(E / 'dated108-directed-validation.json')
    runtime = directed['runtime_hashes']
    assert len(runtime) == 83
    for path, digest in runtime.items():
        assert sha(ROOT / path) == digest, path
    for label in ['directed-v4', 'phpstan-v3', 'pint-v4']:
        assert gate(E, label)['runtime_hashes'] == runtime
    assert '904 passed (12595 assertions)' in clean(E / 'directed-v4.stdout')
    assert '[OK] No errors' in clean(E / 'phpstan-v3.stdout')
    assert re.search(r'PASS.*572 files', clean(E / 'pint-v4.stdout'))
    corpus_path = C / 'dated108-cli-validation.json'
    corpus = read(corpus_path)
    assert corpus['status'] == 'DATED108_NATIVE_EXPANDED_CORPUS_VERIFIED_REGRESSION_PENDING'
    assert corpus['runtime_hashes'] == runtime
    manifests = [read(ROOT / 'tests/Fixtures' / n)['cases'] for n in [
        'dated108-scenario-expectations.json', 'dated108-upstream-expectations.json']]
    assert len(manifests[0]) == 22 and len(manifests[1]) == 36
    assert len(corpus['cases']) == 58 and corpus['native_decisions'] == corpus['exact_replays'] == 116
    assert len(corpus['historical_replays']) == corpus['historical_exact_replays'] == 427
    assert corpus['full_upstream_corpus_complete'] is True
    bundle_path = ROOT / 'resources/fiscal' / corpus['bundle']
    bundle = read(bundle_path)
    assert sha(bundle_path) == corpus['bundle_sha256'] == directed['bundle_sha256']
    previous = read(ROOT / 'resources/fiscal/bundles/consumption-phase4b-2026.10.19-104-draft1.json')
    assert bundle['status'] == 'SANDBOX_CANDIDATE'
    assert len(bundle['official_sources']) == 76 and bundle['official_sources'][:71] == previous['official_sources']
    for source in bundle['official_sources']:
        assert sha(ROOT / 'resources/fiscal/sources/official' / source['file']) == source['sha256']
    for role in ['rtc_rate_catalog', 'rtc_transition_catalog', 'municipal_parameters', 'service_catalog', 'rules']:
        assert bundle['resource_paths'][role] == previous['resource_paths'][role]
    for role in ['fact_definitions', 'questions', 'rtc_code_catalog', 'scenario_schema']:
        assert canonical(read(ROOT / 'resources/fiscal' / bundle['resource_paths'][role])) == bundle['content_hashes'][role]
    for role in ['fact_definitions', 'questions']:
        new = read(ROOT / 'resources/fiscal' / bundle['resource_paths'][role])
        old = read(ROOT / 'resources/fiscal' / previous['resource_paths'][role])
        assert new[:len(old)] == old and len(new) == len(old) + 6
    old_cases = {c['name']: c for c in read(OLD_C / 'identity104-cli-validation.json')['cases']}
    old_paths = {c['path']: c for c in old_cases.values()}
    decimals = {c['name']: c['independent_decimal'] for c in read(ROOT / 'storage/app/phase4b-partial-export-fx73-preparation-20261014/export-integrated-case-manifest.json')['cases'] if c['nominal_path']}
    expected_exits = {'production-assess108': 0, **{'historical-' + str(i): 0 for i in range(427)}}
    nominal_count = decimal_count = upstream_captured_count = 0
    new_keys = ['rtc.assess_export_dated_documents', 'rtc.export_iss_material_records', 'rtc.export_iss_dated_result_records',
        'rtc.export_dated_dps_identity_records', 'provider.iss_regime', 'provider.iss_regime_proof_records']
    for case, expected in zip(corpus['cases'], manifests[0] + manifests[1], strict=True):
        for key, value in expected.items():
            assert case[key] == value, (case['name'], key)
        current = read(ROOT / case['path'])
        assert sha(ROOT / case['path']) == case['scenario_sha256']
        if 'original_path' in case:
            assert sha(ROOT / case['original_path']) == case['original_sha256']
            original = read(ROOT / case['original_path'])
            assert datetime.fromisoformat(current['knowledge_at']) >= datetime.fromisoformat(original['knowledge_at'])
            restored = {k: v for k, v in current['facts'].items() if k not in new_keys}
            for key in ['rtc.assess_export_iss_results', 'rtc.assess_export_sp_results', 'rtc.assess_export_dps_identity']:
                if key in original['facts']:
                    restored[key] = original['facts'][key]
                else:
                    restored.pop(key, None)
            assert restored == original['facts']
        for kind in ['rtc', 'integrated']:
            label = case['name'] + '-' + kind
            expected_exits[label] = 1 if kind == 'rtc' and case['status'] == 'INVALID' else 0
            expected_exits[label + '-replay'] = 0
            snapshot = C / case[kind + '_snapshot']
            assert sha(snapshot) == case[kind + '_snapshot_sha256']
            result = read(C / (label + '.stdout'))
            assert read(snapshot)['result'] == result
            assert result['status'] == case['status'] and case['reason'] in result['reason_codes']
            replay = read(C / (label + '-replay.stdout'))
            assert replay['replay_matches'] is True
            if kind == 'integrated':
                assert replay['result'] == result
            rtc = body(result, kind)
            assert rtc['decision'] == []
            stages = {r['stage']: r for r in rtc['trace']}
            for old_stage in ['PARTIAL_EXPORT_ISS_RESULTS', 'PARTIAL_EXPORT_SP_RESULTS', 'PARTIAL_EXPORT_DPS_IDENTITY']:
                assert old_stage not in stages
            if case['dated_status'] is None:
                assert 'PARTIAL_EXPORT_DATED_DOCUMENTS' not in stages
            else:
                dated = stages['PARTIAL_EXPORT_DATED_DOCUMENTS']
                assert dated['status'] == case['dated_status'] and dated['reason'] == case['dated_reason']
                if case.get('dated_subreason') is not None:
                    assert dated['dated_reason'] == case['dated_subreason']
                for flag in ['dps_competence_legally_approved', 'legal_applicability_approved', 'fiscal_document_identifiers_qualified',
                    'government_evidence_authenticated', 'iss_supply_date_materially_qualified', 'documentary_currency_conversion_qualified',
                    'per_document_tax_amount_calculated', 'document_obligation_complete', 'xml_generated', 'posting_authorized', 'production_ready']:
                    assert dated[flag] is False
                if dated['status'] == 'LOCAL_DATED_DPS_IDENTITY_PAIR_BOUND':
                    assert dated['rtc_operation_date'] == current['facts']['rtc.operation_date']
                    assert [d['document_reference'] for d in dated['documents']] == [current['facts']['rtc.export_foreign_document_id'], current['facts']['rtc.export_domestic_document_id']]
                    for document in dated['documents']:
                        assert document['identity']['competence_date'] == document['iss_supply_on']
            nominal = stages.get('PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION')
            if case.get('fx_refusal_before_nominal'):
                assert nominal is None
            if nominal:
                if case.get('original_path') in old_paths:
                    prior_case = old_paths[case['original_path']]
                    captured = OLD_C / prior_case[kind + '_snapshot']
                    assert sha(captured) == prior_case[kind + '_snapshot_sha256']
                    captured_stages = {r['stage']: r for r in body(read(captured)['result'], kind)['trace']}
                    if 'PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION' in captured_stages:
                        assert nominal == captured_stages['PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION']
                        upstream_captured_count += 1
                old = old_cases.get(case['name'], old_cases['local-pair'] if 'original_path' not in case else None)
                if old:
                    prior = OLD_C / old[kind + '_snapshot']
                    assert sha(prior) == old[kind + '_snapshot_sha256']
                    prior_stages = {r['stage']: r for r in body(read(prior)['result'], kind)['trace']}
                    if 'PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION' in prior_stages:
                        assert nominal == prior_stages['PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION']
                        nominal_count += 1
                for key, value in decimals.get(case['name'].removeprefix('upstream-export-'), {}).items():
                    observed = str(Decimal(nominal['foreign_immune_gross_brl']) + Decimal(nominal['domestic_taxable_base_brl'])) if key == 'total_gross_brl' else nominal[key]
                    assert observed == value
                    decimal_count += 1
    assert nominal_count == corpus['nominal_controls_equal_to_captured104'] > 0
    assert decimal_count == corpus['independent_decimal_checks'] == 72
    assert upstream_captured_count > 0
    gates = {p.name.removesuffix('-execution.json'): p for p in C.glob('*-execution.json')}
    assert set(gates) == set(expected_exits)
    for label, path in gates.items():
        record = read(path)
        assert record['exit_code'] == record['expected_exit_code'] == expected_exits[label]
        for channel in ['stdout', 'stderr']:
            assert sha(C / (label + '.' + channel)) == record[channel + '_sha256']
    for i, prior in enumerate(corpus['historical_replays']):
        assert sha(Path(prior['path'])) == prior['sha256'] and prior['exact_replay'] is True
        replay = read(C / ('historical-' + str(i) + '.stdout'))
        assert replay['replay_matches'] is True
        if prior['command'] == 'fiscal:decision:replay':
            assert replay['result'] == read(Path(prior['path']))['result']
    assessment = read(C / 'production-assess108.stdout')
    assert assessment['ready'] is False and assessment['eligible_for_local_validation'] is True
    assert assessment['official_source_hashes_valid'] == assessment['official_sources_checked'] == 76
    assert len(assessment['blockers']) == 124
    assert set(assessment['blockers']) == set(read(OLD_C / 'production-assess104.stdout')['blockers']) | {'DATED_ISS_DPS_LOCAL_EVIDENCE_ONLY_NOT_LEGAL_OR_DOCUMENT_APPROVAL'}
    old_inputs = read(OLD / 'preflight.json')['input_hashes']
    allowed = {str(ROOT / p) for p in ['app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php',
        'app/Modules/Fiscal/Infrastructure/Production/Phase4bReadinessAssessor.php']}
    changed = {p for p, digest in old_inputs.items() if sha(Path(p)) != digest}
    assert changed == allowed and len(old_inputs) == 11133
    archive = read(E / 'before-integration-archive.json')
    for path, digest in archive['archived'].items():
        assert sha(E / 'before-integration' / path) == digest
    for path in changed:
        assert sha(E / 'before-integration' / Path(path).relative_to(ROOT)) == old_inputs[path]
    return {**corpus, 'independent_upstream_captured104_controls': upstream_captured_count}, bundle, runtime, old_inputs

def inputs(old_inputs, bundle):
    paths = {Path(p) for p in old_inputs}
    for directory, extensions in [('app', {'.php'}), ('tests', {'.php', '.json', '.xml', '.xsd'}),
        ('resources/fiscal', {'.json', '.xml', '.xsd'}), ('scripts', {'.php', '.py', '.ps1'})]:
        paths.update(p for p in (ROOT / directory).rglob('*') if p.is_file() and p.suffix in extensions)
    paths.update(ROOT / 'resources/fiscal/sources/official' / s['file'] for s in bundle['official_sources'])
    paths.update(p for p in E.rglob('*') if p.is_file())
    return {str(p): sha(p) for p in sorted(paths)}
