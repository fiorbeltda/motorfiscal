"""Freeze the actual integrated86 runtime and native evidence only after all directed gates are terminal."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import re

ROOT=Path(__file__).resolve().parents[1]
P=ROOT/'storage/app/phase4b-temporal86-integration-preparation-20261015'
E=ROOT/'storage/app/phase4b-temporal86-cli-20261015'
Q=ROOT/'storage/app/phase4b-fx71-exact-quote-preparation-20261014'
F=ROOT/'resources/fiscal'
def read(path):return json.loads(path.read_text(encoding='utf-8'))
def sha(path):return hashlib.sha256(path.read_bytes()).hexdigest()
def canonical(value):return hashlib.sha256(json.dumps(value,sort_keys=True,ensure_ascii=False,separators=(',',':')).encode()).hexdigest()
corpus=read(E/'temporal86-cli-corpus-validation.json')
assert corpus['status']=='TEMPORAL86_RTC_ISS_NATIVE_CORPUS_VERIFIED_FULL_REGRESSION_PENDING'
assert len(corpus['cases'])==14 and len(corpus['prior_compatibility'])==3
bundle=read(F/corpus['bundle'])
assert sha(F/corpus['bundle'])==corpus['bundle_sha256']
for role in ['rtc_transition_catalog','fact_definitions','questions']:
    assert canonical(read(F/bundle['resource_paths'][role]))==bundle['content_hashes'][role]
assert canonical(read(F/bundle['production_controls']['path']))==bundle['production_controls']['sha256']
assert len(bundle['official_sources'])==59
for source in bundle['official_sources']:assert sha(F/'sources/official'/source['file'])==source['sha256']
gates={}
for label in ['scope86-integrated-directed-v2','scope86-final-phpstan-all','scope86-final-pint-all']:
    record=read(Q/(label+'.json'))
    assert record['exit_code']==0 and sha(Q/(label+'.log'))==record['log_sha256']
    gates[label]=record
directed=re.sub(r'\x1b\[[0-9;]*m','',(Q/'scope86-integrated-directed-v2.log').read_text(encoding='utf-8'))
assert re.search(r'149 passed\s+\(885 assertions\)',directed)
archive=read(P/'before-integration-archive.json')
for path,digest in archive['archived'].items():assert sha(P/'before-integration'/path)==digest
for case in corpus['cases']:
    assert sha(ROOT/case['path'])==case['scenario_sha256']
    for kind in ['rtc','integrated']:
        snapshot=ROOT/case[kind+'_snapshot']
        assert sha(snapshot)==case[kind+'_snapshot_sha256']
        label=case['case']+'-'+kind
        record=read(E/(label+'-execution.json'))
        assert record['exit_code']==0
        assert sha(E/(label+'.stdout'))==record['stdout_sha256']
        assert read(snapshot)['result']==read(E/(label+'.stdout'))
        replay=read(E/(label+'-replay.stdout'))
        assert replay['replay_matches'] is True
        if kind=='integrated':assert replay['result']==read(snapshot)['result']
for row in corpus['prior_compatibility']:assert sha(ROOT/row['path'])==row['sha256']
runtime={p.relative_to(ROOT).as_posix():sha(p) for p in (ROOT/'app/Modules/Fiscal/ConsumptionReform').glob('*.php')}
evidence={}
old=read(ROOT/'storage/app/phase4b-simples78-cli-v2-20261015/simples78-integration-preflight.json')
for path in old['evidence_input_hashes']:evidence[path]=sha(ROOT/path)
for folder in [P,E]:
    for path in folder.rglob('*'):
        if path.is_file():evidence[path.relative_to(ROOT).as_posix()]=sha(path)
report={'status':'TEMPORAL86_LOCAL_INTEGRATION_PREFLIGHT_VERIFIED_FULL_SUITE_PENDING',
    'created_at':datetime.now(timezone.utc).isoformat(),'runtime_hashes':runtime,'evidence_input_hashes':evidence,
    'bundle_sha256':sha(F/corpus['bundle']),'gates':gates,'directed_tests':149,'directed_assertions':885,
    'corpus_sha256':sha(E/'temporal86-cli-corpus-validation.json'),'official_sources_valid':59,
    'production_blockers':corpus['production_blocker_count'],'full_suite_complete':False,
    'prior_advance_debits_proven':False,'document_settlement_complete':False,'phase4b_complete':False,'production_ready':False}
with (E/'temporal86-integration-preflight.json').open('x',encoding='utf-8') as stream:json.dump(report,stream,indent=2)
print('PASS:149/885 directed;14 native dual-runner scenarios/replays;59 sources;runtime/evidence freeze ready;full suite pending')
