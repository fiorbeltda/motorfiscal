"""Capture two actual prior-period sources with distinct ISINs and the same asset account."""
import copy,json,hashlib,subprocess
from pathlib import Path
from datetime import datetime,timezone
from decimal import Decimal
from dere_daily_memory_oracle import digest,oracle,proof
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';V='2026.10.09-28-draft42';PV='2026.10.08-28-draft19';E=ROOT/'storage/app/phase4b-realization-shared-asset-sources-draft-20261009-28';S=F/('scenarios/tvm-shared-asset-sources-'+V)
E.mkdir(parents=True,exist_ok=True);S.mkdir(parents=True,exist_ok=True)
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
def save(p,value):
 if p.exists():assert read(p)==value,p
 else:
  with p.open('x',encoding='utf-8') as out:out.write(json.dumps(value,ensure_ascii=False,indent=2)+'\n')
  if p.stat().st_size>1000000:
   before=sha(p);r=subprocess.run(['compact.exe','/C','/F','/I','/Q','/EXE:LZX',str(p)],capture_output=True);assert r.returncode==0 and sha(p)==before
if not (E/'historical-before.json').exists():
 baseline={}
 for folder in [F,ROOT/'storage/app']:
  for p in folder.rglob('*'):
   if p.is_file() and E not in p.parents and V not in str(p) and p.suffix in ['.json','.xml','.pdf','.zip','.xsd']:baseline[p.relative_to(ROOT).as_posix()]=sha(p)
 save(E/'historical-before.json',baseline)
b=read(F/('bundles/consumption-phase4b-'+PV+'.json'));c=read(F/b['resource_paths']['rtc_code_catalog']);target=F/('bundles/consumption-phase4b-'+V+'.json');known=read(target)['knowledge_available_at'] if target.exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat();c['version']=V;q=c['financial_operation']['base']['public_offer_debt'];q['published_at']=known;c['dere_monthly_closing_projection']['debt_yield_projection']['qualification']=copy.deepcopy(q)
cp='catalogs/rtc-codes-'+V+'.json';save(F/cp,c);ctrl=read(F/b['production_controls']['path']);ctrl['version']=V;ct='production/phase4b-controls-'+V+'.json';save(F/ct,ctrl);b.update(predecessor_bundle_id=b['bundle_id'],bundle_id='CONSUMPTION-PHASE4B-'+V,version=V,knowledge_available_at=known);b['resource_paths']['rtc_code_catalog']=cp;b['content_hashes']['rtc_code_catalog']=digest(c);b['catalog_versions']['rtc_codes']=V;b['production_controls']={'path':ct,'sha256':digest(ctrl)};save(target,b)
def valid_isin(value):
 digits=''.join(str(ord(a)-55) if a.isalpha() else a for a in value)
 values=[int(a)*(2 if i%2 else 1) for i,a in enumerate(reversed(digits))]
 return sum(v//10+v%10 for v in values)%10==0
first='BRFIXTDEB019';assert valid_isin(first);second=next('BRFIXTDEB02'+str(d) for d in range(10) if valid_isin('BRFIXTDEB02'+str(d)));assert first!=second
policy=q['daily_memory_calculation'];sources=[]
for name,prototype,title_id,account in [('positive','net-negative',first,'debt000'),('negative','net-positive',second,'debt000')]:
 scenario=read(F/('scenarios/tvm-public-mixed-history-2026.10.08-28-draft21/'+prototype+'-2026-07-financial.json'));scenario.update(scenario_id='SYNTHETIC-shared-asset-source-'+name,knowledge_at=known,rule_bundle_id=b['bundle_id'],catalog_versions=b['catalog_versions']);ledger=scenario['facts']['rtc.financial_ledger'][0];entry=ledger['entries'][0];detail=entry['public_offer_debt'];title=detail['title'];title.update(idTitulo=title_id,cCta=account);position=detail['position'];position.update(idTitulo=title_id,cCta=account)
 position.pop('memory_evidence',None);position['appropriations']={day['date']:day['appropriation'] for day in position['contract_curve'] if Decimal(day['appropriation'])};curve,monthly,independent=oracle(position,policy['series']);position.pop('appropriations');position.update(contract_curve=curve,declared_monthly=monthly);position['method_evidence']=proof({'contract':policy['contract'],'method':position['method']},'SYNTHETIC.SHARED.ASSET.SOURCE.METHOD.'+name,'2026-07-01','2026-07-31');position['memory_evidence']=proof(position,'SYNTHETIC.SHARED.ASSET.SOURCE.MEMORY.'+name,'2026-07-01','2026-07-31');entry['document_id']='SYNTHETIC.SHARED.ASSET.SOURCE.JULY.'+name
 detail['eligibility_evidence']=proof({'supplier_id':ledger['supplier_id'],'period_end':ledger['period_end'],'title':title,'financial_entry_id':entry['id'],'document_id':entry['document_id'],'amount':entry['amount'],'result_account_code':detail['result_account_code'],'position_input_hash':digest(position),'position_mode':'DAILY_MEMORY'},'SYNTHETIC.SHARED.ASSET.SOURCE.ELIGIBILITY.'+name,'2026-07-01','2026-07-31')
 path=S/(name+'-financial.json');save(path,scenario);snapshot=E/(name+'-source-snapshot.json');args=['fiscal:rtc:run',path.relative_to(ROOT).as_posix(),'--bundle-manifest=bundles/consumption-phase4b-'+V+'.json']
 if not snapshot.exists():args.append('--snapshot='+snapshot.relative_to(ROOT).as_posix())
 cmd=['docker','compose','exec','-T','app','php','-d','memory_limit=512M','artisan',*args];r=subprocess.run(cmd,cwd=ROOT,capture_output=True,encoding='utf-8');assert r.returncode==0,r.stdout+r.stderr;result=json.loads(r.stdout);assert result['reason_codes']==['RTC_FINANCIAL_DOCUMENT_PENDING'];base=next(t['base'] for t in result['trace'] if t['stage']=='FINANCIAL_OPERATION_AND_BASE');binding=base['entries'][0]['d2101_binding'];actual=binding['calculation']
 for field,want in independent.items():
  if field!='daily_memory':assert actual[field]==want,(name,field)
  else:
   assert len(actual[field])==len(want)
   for day,expected in zip(actual[field],want):
    for key,value in expected.items():assert day[key]==value,(name,day['date'],key)
 assert base['net_base']==binding['taxable_amount'] and binding['unoffset_title_loss']==('150.00' if name=='negative' else '0.00')
 replay=subprocess.run(['docker','compose','exec','-T','app','php','-d','memory_limit=512M','artisan','fiscal:rtc:replay',snapshot.relative_to(ROOT).as_posix()],cwd=ROOT,capture_output=True,encoding='utf-8');assert replay.returncode==0,replay.stdout+replay.stderr;replayed=json.loads(replay.stdout);assert replayed['replay_matches'] and replayed['original_hash']==result['decision_hash'];save(E/(name+'-source-replay.json'),replayed);save(E/(name+'-independent-oracle.json'),independent)
 sources.append({'source':name,'idTitulo':title_id,'cCta':account,'snapshot':snapshot.relative_to(ROOT).as_posix(),'snapshot_sha256':sha(snapshot),'replay_matches':True,'taxable_amount':binding['taxable_amount'],'unoffset_title_loss':binding['unoffset_title_loss'],'closing_quantity':actual['closing_quantity'],'closing_book':actual['monthly']['vSaldoContFin'],'current_multiple_title_closing_accepted':False})
assert len({s['idTitulo'] for s in sources})==2 and len({s['cCta'] for s in sources})==1
save(E/'source-preparation.json',{'status':'TWO_DISTINCT_ISINS_IN_ONE_ASSET_ACCOUNT_SOURCES_CAPTURED_ONLY','bundle':V,'sources':sources,'independent_oracles_checked':True,'isin_assignment_claimed':False,'current_multiple_title_closing_accepted':False,'scope28_completed':False,'production_ready':False});print(json.dumps({'captured_sources':2,'current_multiple_title_closing_accepted':False,'production_ready':False}))
