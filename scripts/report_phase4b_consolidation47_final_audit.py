"""Close the expanded local validation with preserved failed attempts and requirement limits."""
import json,hashlib,re
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];E=ROOT/'storage/app/phase4b-realization-consolidated-expansion47-draft-20261009-28';F=ROOT/'resources/fiscal';V='2026.10.09-28-draft47'
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
quality={name:read(E/(name+'.json')) for name in ['directed-tests','historical-directed-tests','guards','full-suite','phpstan','pint','reproduce','candidate27','candidate26','candidate25','expanded-artifact-audit','tax-audit']}
for name,value in quality.items():assert value['exit_code']==0 and value['log_sha256']==sha(E/(name+'.log'))
plain=re.sub(r'\x1b\[[0-9;]*m','',(E/'full-suite.log').read_text(encoding='utf-8'));counts=re.search(r'(\d+) passed.*?\((\d+) assertions\)',plain);assert counts and counts.groups()==('1697','42766')
corpus=read(E/'expanded-realization-corpus.json');assert len(corpus['cases'])==87 and corpus['financial_replays']==87 and corpus['xml_bodies']==520
audit=read(E/'existing-artifact-audit.json');assert audit['native_replay_count']==87 and audit['input_snapshot_and_report_identity_verified'] and audit['verifier_sha256']==sha(ROOT/audit['verifier_path'])
tax=read(E/'separate-tax-audit.json');assert tax['case_count']==87 and tax['separate_ibs_cbs_verified'];assert {v['case']:v['snapshot_sha256'] for v in tax['cases']}=={v['case']:v['snapshot_sha256'] for v in corpus['cases']}
baseline=read(E/'historical-before.json');changed=[p for p,h in baseline.items() if not (ROOT/p).is_file() or sha(ROOT/p)!=h];assert not changed,changed
for item in read(E/'implementation-hashes.json')+read(E/'final-audit-implementation-hashes.json'):assert sha(ROOT/item['path'])==item['sha256']
expansion=read(E/'expansion-cases.json');assert expansion['case_count']==78 and expansion['profiles']==12
for row in expansion['cases'].values():
 for p,h in [('fixture','fixture_sha256'),('original_financial_input','original_financial_input_sha256'),('original_month_input','original_month_input_sha256')]:assert sha(ROOT/row[p])==row[h]
for row in read(E/'input-compression-preservation.json')['files']:assert sha(ROOT/row['path'])==row['sha256']
research=read(E/'research.json');assert not research['normative_homologation_claimed'] and not research['new_public_return_at_sale_document_bridge_proven']
for p,h in [('manual_path','manual_sha256'),('rfb_article_path','rfb_article_sha256'),('rendered_page','rendered_page_sha256')]:assert sha(ROOT/research[p])==research[h]
gate=read(E/'production-assessment.json');assert not gate['ready'] and gate['official_source_hashes_valid']==51
for source in read(F/('bundles/consumption-phase4b-'+V+'.json'))['official_sources']:assert sha(F/'sources/official'/source['file'])==source['sha256']
assert read(E/'expanded-corpus.json')['exit_code']==1 and read(E/'expanded-corpus-retry1.json')['exit_code']==1
value={'status':'EXPANDED_HISTORICAL_REALIZATIONS_ONE_KNOWLEDGE_VALIDATED','bundle':V,'quality':quality,'full_suite_tests':1697,'full_suite_assertions':42766,'corpus':corpus,'separate_tax_audit':tax,'research':research,'historical_artifacts_checked':len(baseline),'historical_artifacts_changed':changed,'official_sources_verified':51,'historical_profiles':12,'historical_realization_cases_validated':78,'common_case_count':87,'prior_corpus_verifier_failures_preserved':True,'expanded_historical_corpus_validated':True,'unified_candidate_accepted':False,'scope28_completed':False,'phase4b_completed':False,'production_ready':False}
target=E/'consolidation-progress-report.json'
if target.exists():assert read(target)==value
else:
 with target.open('x',encoding='utf-8') as out:out.write(json.dumps(value,ensure_ascii=False,indent=2)+'\n')
print(json.dumps({'historical_preserved':len(baseline),'common_cases':87,'native_replays':87,'xml_bodies':520,'full_suite_tests':1697,'scope28_completed':False,'production_ready':False}))
