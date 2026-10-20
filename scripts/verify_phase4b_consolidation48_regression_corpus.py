"""Native common-knowledge regression corpus with explicit proof rebinding."""
import json,hashlib,subprocess,gc,xml.etree.ElementTree as ET
from pathlib import Path
from decimal import Decimal,ROUND_HALF_EVEN
from dere_daily_memory_oracle import digest
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal'
V='2026.10.09-28-draft48';B='bundles/consumption-phase4b-'+V+'.json'
E=ROOT/'storage/app/phase4b-private-consolidation48-draft-20261009-28'
S=F/('scenarios/tvm-realization-consolidated-'+V)
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
def save(p,value):
 if p.exists():assert read(p)==value,p
 else:
  with p.open('x',encoding='utf-8') as out:out.write(json.dumps(value,ensure_ascii=False,indent=2)+'\n')
def run(args,code=0):
 r=subprocess.run(['docker','compose','exec','-T','app','php','-d','memory_limit=512M','artisan',*args],cwd=ROOT,capture_output=True,encoding='utf-8')
 assert r.returncode==code,r.stdout[:3000]+r.stderr
 return json.loads(r.stdout)
def compact(p):
 if p.stat().st_size>1000000:
  original=sha(p);r=subprocess.run(['compact.exe','/C','/F','/I','/Q','/EXE:LZX',str(p)],capture_output=True)
  assert r.returncode==0 and sha(p)==original
cases=[];count=0
previous=ROOT/'storage/app/phase4b-realization-consolidated-expansion47-draft-20261009-28'
assert sha(previous/'consolidation-progress-report.json')==read(E/'private-cases.json')['preceding_report_sha256']
frozen_inputs={row['path']:row['sha256'] for row in read(previous/'input-compression-preservation.json')['files']}
previous_cases=read(previous/'expanded-realization-corpus.json')['cases']
metadata_rows=[]
for row in previous_cases:
 folder=F/'scenarios/tvm-realization-consolidated-2026.10.09-28-draft47'
 metadata={'case':row['case'],'expected_base':row['base']}
 for kind,suffix in [('financial','financial'),('monthly','month')]:
  path=folder/(row['case']+'-'+suffix+'.json');metadata[kind+'_input']=path.relative_to(ROOT).as_posix();metadata[kind+'_input_sha256']=sha(path)
 assert metadata['financial_input_sha256']==frozen_inputs[metadata['financial_input']]
 assert digest(read(ROOT/metadata['monthly_input']))==read(previous/(row['case']+'-monthly-report.json'))['monthly_input_hash']
 metadata_rows.append(metadata)
def economic_ledger(ledger):
 ledger=json.loads(json.dumps(ledger))
 for entry in ledger['entries']:
  if 'securities_realization' in entry:entry['securities_realization'].pop('qualification_evidence',None)
  proof=entry.get('public_offer_debt',{}).get('sale_recognition',{}).get('independent_evidence')
  if proof:
   proof.pop('source_hash',None);proof['value'].pop('policy_hash',None)
 return ledger
for metadata in metadata_rows:
 case=metadata['case'];financial=S/(case+'-financial.json');monthly=S/(case+'-month.json')
 for kind in ['financial','monthly']:assert sha(ROOT/metadata[kind+'_input'])==metadata[kind+'_input_sha256']
 current=read(financial);original=read(ROOT/metadata['financial_input'])
 assert economic_ledger(current['facts']['rtc.financial_ledger'][0])==economic_ledger(original['facts']['rtc.financial_ledger'][0])
 assert read(monthly)['book']['accounts']==read(ROOT/metadata['monthly_input'])['book']['accounts']
 compact(financial);snapshot=E/(case+'-snapshot.json')
 args=['fiscal:rtc:run',financial.relative_to(ROOT).as_posix(),'--bundle-manifest='+B]
 if not snapshot.exists():args+=['--snapshot='+snapshot.relative_to(ROOT).as_posix()]
 result=run(args);assert result['reason_codes']==['RTC_FINANCIAL_DOCUMENT_PENDING'];compact(snapshot)
 stored=read(snapshot);assert stored['input']==current
 assert stored['knowledge']['content_hashes']['bundle_manifest']==digest(read(F/B))
 replay=run(['fiscal:rtc:replay',snapshot.relative_to(ROOT).as_posix()])
 assert replay['replay_matches'] and replay['original_hash']==result['decision_hash'];save(E/(case+'-replay.json'),replay)
 base=next(stage['base'] for stage in result['trace'] if stage['stage']=='FINANCIAL_OPERATION_AND_BASE')
 assert base['net_base']==metadata['expected_base']
 tax=next(stage for stage in result['trace'] if stage['stage']=='FINANCIAL_GROSS_DEBITS');assert tax['base']==base['net_base']
 for key,rate in [('ibs_nominal','.001'),('cbs_nominal','.009')]:
  assert Decimal(tax[key])==(Decimal(base['net_base'])*Decimal(rate)).quantize(Decimal('.01'),rounding=ROUND_HALF_EVEN)
 report=E/(case+'-monthly-report.json')
 args=['fiscal:dere:project-monthly-closing',snapshot.relative_to(ROOT).as_posix(),monthly.relative_to(ROOT).as_posix(),'--bundle-manifest='+B]
 if not report.exists():args+=['--report='+report.relative_to(ROOT).as_posix(),'--candidate-output='+(E/(case+'-candidate.xml')).relative_to(ROOT).as_posix()]
 projected=run(args);saved=read(report)
 assert saved['projection']['status']=='PROJECTED' and not saved['production_ready']
 for key in ['snapshot_hash','decision_hash','monthly_input_hash','projection','event_validations']:assert projected[key]==saved[key]
 accounts={a['cCta']:a for a in read(monthly)['book']['accounts']}
 for event,path in saved['event_outputs'].items():
  raw=(ROOT/path).read_bytes();validation=saved['event_validations'][event]
  assert validation['xml_sha256']==hashlib.sha256(raw).hexdigest()
  assert validation['reason']=='RTC_DERE_UNSIGNED_LOCAL_CANDIDATE' and len(validation['errors'])==1
  assert validation['schema_sha256']=='3d4de67215335e3b3186e92214ecbb8a55b501e846aba22eff59ca53e582dcdd'
  if event=='D1101':
   xml=ET.fromstring(raw);ns={'d':xml.tag.split('}')[0][1:]};rows={row.find('d:cCta',ns).text:row for row in xml.findall('.//d:infoConta',ns)}
   for code,account in accounts.items():
    for key in ['vSaldoInic','vMovDebt','vMovCred','vSaldoFinal','vApur']:assert rows[code].find('d:'+key,ns).text==account[key]
  if event=='D2101':
   xml=ET.fromstring(raw);ns={'d':xml.tag.split('}')[0][1:]}
   titles={(title.attrib['idTitulo'],title.attrib['cCta']):title for title in xml.findall('.//d:detTitulo',ns)}
   bindings=[entry['d2101_binding']['xml_title'] for entry in base['entries'] if entry.get('d2101_binding',{}).get('status')=='RESOLVED']
   assert len(titles)==len(bindings)
   for binding in bindings:
    title=titles[(binding['idTitulo'],binding['cCta'])]
    for key,value in binding.items():assert (title.get(key) if key in ['idTitulo','cCta'] else title.find('d:'+key,ns).text)==value
  count+=1
 cases.append({'case':case,'snapshot_sha256':sha(snapshot),'decision_hash':result['decision_hash'],'financial_input_sha256':sha(financial),'monthly_input_sha256':sha(monthly),'base':base['net_base'],'ibs_nominal':tax['ibs_nominal'],'cbs_nominal':tax['cbs_nominal'],'xml_count':len(saved['event_outputs'])})
 print(case,flush=True);del result,stored,base,projected,saved;gc.collect()
assert len(cases)==87 and count==520
save(E/'regression-native-corpus.json',{'bundle':V,'cases':cases,'financial_replays':87,'xml_bodies':count,'separate_ibs_cbs_verified':True,'source_ledger_economics_and_book_amounts_preserved':True,'independent_public_policy_and_monthly_proofs_recaptured':True,'scope28_completed':False,'phase4b_completed':False,'production_ready':False})
print(json.dumps({'regression_cases':87,'replays':87,'xml_bodies':count,'scope28_completed':False}))
