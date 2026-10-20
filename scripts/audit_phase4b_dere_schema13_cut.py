"""Accept only the evidenced local monthly subset under the official 1.3.0 archive."""
import hashlib,json,re
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
F=ROOT/'resources/fiscal';E=ROOT/'storage/app/phase4b-schema13-20261006-22'
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def digest(v):return hashlib.sha256(json.dumps(v,ensure_ascii=False,sort_keys=True,separators=(',',':')).encode()).hexdigest()
def log(n):return re.sub(r'\x1b\[[0-9;]*m','',(E/n).read_text(encoding='utf-8'))
assert re.search(r'898 passed\s*\(2901 assertions\)',log('tests-final.log'))
assert '[OK] No errors' in log('phpstan.log')
assert re.search(r'PASS\s*\.+\s*361 files',log('pint.log'))
assert all(v==0 for v in read(E/'quality-final.json').values())
assert 'No security vulnerability advisories found' in log('composer-audit-final.log')
assert 'Verified13 partial RTC/3 general integration cases/8 replays/3 XML candidates' in log('general-regressions.log')
assert 'Verified24 D2101 cases' in log('predecessor-regression.log')
b=read(F/'bundles/consumption-phase4b-2026.10.06-22.json')
for k,path in b['resource_paths'].items():
 p=F/path;v=sorted([read(x) for x in p.rglob('*.json')],key=lambda x:x['rule_id']) if p.is_dir() else read(p)
 assert digest(v)==b['content_hashes'][k],k
assert digest(read(F/b['production_controls']['path']))==b['production_controls']['sha256']
for s in b['official_sources']:assert hashlib.sha256((F/'sources/official'/s['file']).read_bytes()).hexdigest()==s['sha256']
c=read(F/b['resource_paths']['rtc_code_catalog'])
for k in ['dere_monthly_closing_projection','dere_trial_balance_projection','dere_deduction_projection','dere_pgcc_projection','dere_contributor_projection']:
 assert c[k]['xsd']['source_sha256']=='3d4de67215335e3b3186e92214ecbb8a55b501e846aba22eff59ca53e582dcdd'
assert c['dere_pgcc_projection']['schema_version']=='v1_0_4'
assert c['dere_deduction_projection']['schema_version']=='v0_0_2'
corpus=read(E/'corpus.json');assert corpus['valid'] and len(corpus['cases'])==46 and corpus['xml_bodies']==41 and corpus['financial_replays']==4
regressions={}
for cut in ['22','21']:
 regressions[cut]={}
 for family,total in [('consumption-phase4b',15),('consumption-integrated',3)]:
  r=read(ROOT/f'storage/app/phase4b-financial-credit-{family}-corpus-20261002-{cut}.json')
  assert r['valid'] and r['passed']==r['total']==total
  regressions[cut][family]=total
baseline=read(E/'historical-before.json')
changed=[p for p,h in baseline.items() if not (ROOT/p).is_file() or hashlib.sha256((ROOT/p).read_bytes()).hexdigest()!=h];assert not changed
reports={}
for p in E.glob('*-monthly-report.json'):
 r=read(p);assert r['production_ready'] is False and r['projection']['period_closed'] is False
 reports[p.stem]={'decision_hash':r['decision_hash'],'monthly_input_hash':r['monthly_input_hash'],'event_hashes':{}}
 for ev,path in r['event_outputs'].items():
  v=r['event_validations'][ev];assert v['reason']=='RTC_DERE_UNSIGNED_LOCAL_CANDIDATE' and len(v['errors'])==1
  assert v['xml_sha256']==hashlib.sha256((ROOT/path).read_bytes()).hexdigest()
  reports[p.stem]['event_hashes'][ev]=v['xml_sha256']
assert len(reports)==8
g=read(E/'production-assessment.json');assert not g['ready'] and g['official_source_hashes_valid']==43
report={'bundle_id':b['bundle_id'],'local_scope_validated':True,'phase4b_complete':False,
 'tests':{'passed':898,'assertions':2901},'phpstan':'PASS','pint':{'files':361,'status':'PASS'},'composer_audit':'PASS',
 'corpus':corpus,'monthly_reports':reports,'integrated_regressions':regressions,
 'historical_artifacts':{'checked':len(baseline),'changed':changed},
 'scope':'SINGLE_1_3_0_ARCHIVE_SUPPORTED_MONTHLY_SUBSET_SINGLE_ACCOUNT_CORRESPONDENT_ONLY',
 'limitations':['SYNTHETIC_POSITIVE_EVIDENCE','NO_GOVERNMENT_RECEPTION','NO_NEW_PGCC_REFERENCE_PLANS_OR_REGIMES','NO_FULL_D1121_MULTIPLE_ACCOUNT_ALLOCATION','D2101_NO_MOVEMENT_ONLY'],
 'gate':{'ready':False,'sources_valid':43,'blockers':len(g['blockers'])},
 'actions':{'signature_created':False,'transmission_performed':False,'period_closed':False},
 'logs_sha256':{n:hashlib.sha256((E/n).read_bytes()).hexdigest() for n in ['tests-final.log','phpstan.log','pint.log','composer-audit-final.log','general-regressions.log','predecessor-regression.log','verifier.log']}}
p=E/'verification-report.json'
if p.exists():assert read(p)==report
else:p.write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
print(f'Candidate22 acceptance PASS:898 tests/2901 assertions;46 cases;41 XML;{len(baseline)} historical artifacts preserved.')
