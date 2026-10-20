"""Audit candidate21 acceptance with explicit fiscal and documentary limits."""
import hashlib
import json
import re
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
F = ROOT / 'resources/fiscal'
E = ROOT / 'storage/app/phase4b-d2101-no-movement-20261006-21'


def read(path):
    return json.loads(path.read_text(encoding='utf-8'))


def digest(value):
    return hashlib.sha256(json.dumps(value,ensure_ascii=False,sort_keys=True,separators=(',',':')).encode()).hexdigest()


def log(name):
    return re.sub(r'\x1b\[[0-9;]*m','',(E/name).read_text(encoding='utf-8'))


assert re.search(r'892 passed\s*\(2877 assertions\)',log('full-suite-final.log'))
assert '[OK] No errors' in log('phpstan-final.log')
assert re.search(r'PASS\s*\.+\s*360 files',log('pint.log'))
assert all(v==0 for v in read(E/'quality-result-final.json').values())
assert 'No security vulnerability advisories found' in log('composer-audit-final.log')
assert read(E/'additional-checks.json')['rules-validation.json'] == 0
assert log('diff-check-final.log').strip() == ''
assert 'Verified24 D2101 cases' in log('candidate-verifier-post-update.log')
manifest = read(F/'bundles/consumption-phase4b-2026.10.06-21.json')
assert manifest['status'] == 'SANDBOX_CANDIDATE'
for name,path in manifest['resource_paths'].items():
    p=F/path
    value=sorted([read(x) for x in p.rglob('*.json')],key=lambda r:r['rule_id']) if p.is_dir() else read(p)
    assert digest(value)==manifest['content_hashes'][name],name
assert digest(read(F/manifest['production_controls']['path']))==manifest['production_controls']['sha256']
for s in manifest['official_sources']:
    assert hashlib.sha256((F/'sources/official'/s['file']).read_bytes()).hexdigest()==s['sha256']
assert len(manifest['official_sources'])==43
codes=read(F/manifest['resource_paths']['rtc_code_catalog'])
closing=codes['dere_monthly_closing_projection']
ref=read(F/codes['dere_pgcc_projection']['reference_catalog_path'])
assert len(ref['accounts'])==10 and digest(ref)==closing['pgcc_qualification_policy']['reference_catalog_sha256']
assert closing['debt_no_movement_projection']['allowed_codes']==['110113001','110113002']
assert closing['pgcc_deduction_binding_required'] is True
schema=closing['debt_no_movement_projection']['xsd']
z=zipfile.ZipFile(F/'sources/official'/schema['source_file'])
assert 'evtDebOpOfPublic-v0_0_3.xsd' in z.namelist()
assert len(z.namelist())==32 and len([n for n in z.namelist() if n.endswith('.txt')])==1
assert b'vVarMensal' in z.read('evtDebOpOfPublic-v0_0_3.xsd')
corpus=read(E/'d2101-no-movement-corpus.json')
assert corpus['valid'] and len(corpus['cases'])==24 and corpus['financial_snapshots_replayed']==1
reports={}
for name in ['qualified-both-codes','qualified-direct-only','qualified-fund-only','qualified-no-obligation']:
    r=read(E/f'{name}-monthly-report.json')
    assert r['production_ready'] is False and r['projection']['period_closed'] is False
    debt=name!='qualified-no-obligation'
    assert ('D2101' in r['event_outputs'])==debt
    events={}
    for event,filename in r['event_outputs'].items():
        v=r['event_validations'][event]
        assert v['reason']=='RTC_DERE_UNSIGNED_LOCAL_CANDIDATE' and v['unsigned_candidate_structure_checked']
        assert len(v['errors'])==1
        assert hashlib.sha256((ROOT/filename).read_bytes()).hexdigest()==v['xml_sha256']
        events[event]={'xml_sha256':v['xml_sha256'],'schema_sha256':v['schema_sha256'],'unsigned_body_checked':True}
    reports[name]={'events':events,'snapshot_hash':r['snapshot_hash'],'decision_hash':r['decision_hash'],
        'monthly_input_hash':r['monthly_input_hash'],'debt_binding':r['projection'].get('debt_no_movement_projection')}
assert sum(len(r['events']) for r in reports.values())==23
gate=read(E/'production-assessment.json')
assert gate['ready'] is False and gate['eligible_for_local_validation'] is True
assert gate['official_sources_checked']==gate['official_source_hashes_valid']==43
regressions={}
for cut in ['21','20','19','18','17']:
    regressions[cut]={}
    for family,total in [('consumption-phase4b',15),('consumption-integrated',3)]:
        r=read(ROOT/f'storage/app/phase4b-financial-credit-{family}-corpus-20261002-{cut}.json')
        assert r['valid'] and r['passed']==r['total']==total
        regressions[cut][family]={'passed':total,'total':total}
baseline=read(E/'historical-before.json')
changed=[p for p,h in baseline.items() if not (ROOT/p).is_file() or hashlib.sha256((ROOT/p).read_bytes()).hexdigest()!=h]
assert changed==[]
before=read(E/'composer-lock-before.json');after=read(ROOT/'composer.lock')
old={p['name']:p for k in ['packages','packages-dev'] for p in before[k]}
new={p['name']:p for k in ['packages','packages-dev'] for p in after[k]}
assert set(old)==set(new)
assert [n for n in old if old[n]!=new[n]]==['league/commonmark']
assert old['league/commonmark']['version']=='2.10.1' and new['league/commonmark']['version']=='2.10.2'
for name in ['fork_phase4b_dere_d2101_no_movement_bundle.py','verify_phase4b_dere_d2101_no_movement_cut.py']:
    p=ROOT/'scripts'/name;compile(p.read_text(encoding='utf-8'),str(p),'exec')
report={'bundle_id':manifest['bundle_id'],'local_scope_validated':True,'phase4b_complete':False,
    'plan_scope':['4B.7.DOCUMENT_PARTIAL','4B.8.CORPUS_PARTIAL','5.4.COMPONENT','6.5.COMPONENT'],
    'tests':{'passed':892,'assertions':2877,'runtime':'DOCKER_PHP_8.5.10'},'phpstan':'PASS','pint':{'status':'PASS','files':360},
    'rules_validation':'PASS','composer_audit':'PASS','dependency_fix':{'package':'league/commonmark','from':'2.10.1','to':'2.10.2'},
    'documentary_corpus':corpus,'monthly_reports':reports,'integrated_regressions':regressions,
    'scope':{'codes':['110113001','110113002'],'debt_event':'D2101_ORIGINAL_SEMTITULOS_1',
        'empty_unmoved_accounts_only':True,'independent_positive_proofs':'SYNTHETIC',
        'title_movements_implemented':False,'mixed_schema_contracts':True,'full_monthly_1_3_0_homologation':False},
    'official_schema':{'file':schema['source_file'],'sha256':schema['source_sha256'],'entries':len(z.namelist()),
        'D2101_root':'evtDebOpOfPublic-v0_0_3.xsd','readme':'IGNORED_NOT_EXTRACTED_OR_INTERPRETED'},
    'historical_artifacts':{'checked':len(baseline),'changed':changed},
    'gate':{'ready':False,'eligible_for_local_validation':True,'sources_checked':43,'blockers':len(gate['blockers'])},
    'actions':{'signature_created':False,'transmission_performed':False,'period_closed':False,'collection_performed':False},
    'logs_sha256':{n:hashlib.sha256((E/n).read_bytes()).hexdigest() for n in
        ['full-suite-final.log','phpstan-final.log','pint.log','composer-audit-final.log','candidate-verifier-post-update.log']}}
p=E/'verification-report.json'
if p.exists():assert read(p)==report,'Refusing to overwrite different acceptance evidence.'
else:
    with p.open('x',encoding='utf-8') as stream:stream.write(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
print(f'Candidate21 audit PASS;892 tests/2877 assertions;{len(baseline)} historical artifacts preserved;production remains closed.')
