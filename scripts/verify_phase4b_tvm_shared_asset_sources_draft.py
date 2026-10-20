"""Audit actual shared-asset prior sources without claiming a current monthly acceptance."""
import json,hashlib,subprocess,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';V='2026.10.09-28-draft42';E=ROOT/'storage/app/phase4b-realization-shared-asset-sources-draft-20261009-28'
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
def save(p,v):
 if p.exists():assert read(p)==v,p
 else:p.write_text(json.dumps(v,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
name=sys.argv[1]
if name=='reproduce':
 log=E/'reproduce-sources.log';assert not log.exists();command=[sys.executable,'scripts/build_phase4b_tvm_shared_asset_sources_draft.py'];r=subprocess.run(command,cwd=ROOT,capture_output=True,encoding='utf-8');log.write_text(r.stdout+r.stderr,encoding='utf-8');save(E/'reproduce-sources.json',{'command':command,'exit_code':r.returncode,'log_sha256':sha(log)});print(r.stdout+r.stderr);sys.exit(r.returncode)
assert name=='report';baseline=read(E/'historical-before.json');changed=[p for p,h in baseline.items() if not (ROOT/p).is_file() or sha(ROOT/p)!=h];assert not changed,changed
b=read(F/('bundles/consumption-phase4b-'+V+'.json'));assert len(b['official_sources'])==49
for item in b['official_sources']:assert sha(F/'sources/official'/item['file'])==item['sha256']
prep=read(E/'source-preparation.json');assert not prep['current_multiple_title_closing_accepted'];sources=prep['sources'];assert len(sources)==2 and len({s['idTitulo'] for s in sources})==2 and {s['cCta'] for s in sources}=={'debt000'}
for s in sources:
 assert sha(ROOT/s['snapshot'])==s['snapshot_sha256'] and s['replay_matches'];snap=read(ROOT/s['snapshot']);replay=read(E/(s['source']+'-source-replay.json'));assert replay['replay_matches'] and replay['original_hash']==snap['result']['decision_hash'];assert snap['input']['facts']['rtc.financial_ledger'][0]['entries'][0]['public_offer_debt']['title']['cCta']=='debt000'
assert sum(__import__('decimal').Decimal(s['closing_book']) for s in sources)==__import__('decimal').Decimal('19950.00')
reproduction=read(E/'reproduce-sources.json');assert reproduction['exit_code']==0 and sha(E/'reproduce-sources.log')==reproduction['log_sha256']
previous=ROOT/'storage/app/phase4b-realization-historical-multiple-complete-draft-20261009-28/multiple-progress-report.json';prior=read(previous);assert prior['current_multiple_title_closing_accepted'] and not prior['scope28_completed']
research=read(E/'research.json');assert not research['normative_homologation_claimed']
for pk,hk in [('manual_path','manual_sha256'),('rfb_article_path','rfb_article_sha256')]:assert sha(ROOT/research[pk])==research[hk]
files=['scripts/build_phase4b_tvm_shared_asset_sources_draft.py','scripts/verify_phase4b_tvm_shared_asset_sources_draft.py'];save(E/'implementation-hashes.json',[{'path':p,'sha256':sha(ROOT/p)} for p in files])
save(E/'source-preparation-audit.json',{'status':'SHARED_ASSET_DISTINCT_ISIN_SOURCE_PREPARATION_ONLY','source_preparation':prep,'historical_artifacts_checked':len(baseline),'historical_artifacts_changed':changed,'official_sources_verified':49,'reproduction':reproduction,'preceding_scope_report':previous.relative_to(ROOT).as_posix(),'preceding_scope_report_sha256':sha(previous),'aggregate_asset_opening':'19950.00','research':research,'current_multiple_title_closing_accepted':False,'scope28_completed':False,'production_ready':False,'pending':['Aggregate asset book and native binding policy','Current sale qualification, monthly projection, replays, XML and regressions']});print(json.dumps({'historical_preserved':len(baseline),'sources':2,'aggregate_asset_opening':'19950.00','current_monthly_accepted':False}))
