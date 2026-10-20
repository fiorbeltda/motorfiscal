"""Check integrated tax arithmetic, all monthly bodies, negative guards and historical hashes."""
import copy,hashlib,json,subprocess,xml.etree.ElementTree as ET
from decimal import Decimal,localcontext,ROUND_HALF_EVEN
from pathlib import Path
from dere_daily_memory_oracle import oracle
ROOT=Path(__file__).resolve().parents[1];F=ROOT/'resources/fiscal';E=ROOT/'storage/app/phase4b-variable-yield-20261006-27';V='2026.10.06-27';S=F/f'scenarios/dere-variable-yield-{V}';B=f'bundles/consumption-phase4b-{V}.json'
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def save(p,v):
 if p.exists():assert read(p)==v,p;return
 p.parent.mkdir(parents=True,exist_ok=True);p.write_text(json.dumps(v,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
def run(args,code=0):
 p=subprocess.run(['docker','compose','exec','-T','app','php','artisan',*args],cwd=ROOT,capture_output=True,encoding='utf-8');assert p.returncode in (code if isinstance(code,tuple) else (code,)),p.stdout+p.stderr;v=json.loads(p.stdout);assert 'error' not in v,v
 if p.returncode==1:assert v['status'] in ['INVALID','ACTION_REQUIRED'],v
 return v
b=read(F/B);c=read(F/b['resource_paths']['rtc_code_catalog']);rate=c['dere_debt_constant_cost_calculation']['series'];monthly=[];engine=[];bodies=0;replays=0
for p in sorted(S.glob('*-financial.json')):
 if 'facts' not in read(p):continue
 name=p.name.removesuffix('-financial.json');snap=E/f'{name}-snapshot.json';assert run(['fiscal:rtc:replay',snap.relative_to(ROOT).as_posix()])['replay_matches'];replays+=1
 result=run(['fiscal:rtc:run',p.relative_to(ROOT).as_posix(),f'--bundle-manifest={B}']);assert result['reason_codes']==['RTC_FINANCIAL_DOCUMENT_PENDING']
 stages={r['stage']:r for r in result['trace']};op=stages['FINANCIAL_OPERATION_AND_BASE'];expect=read(S/f'{name}.json')['corpus_expectation'];assert op['base']['net_base']==expect['base']
 tax=stages['FINANCIAL_GROSS_DEBITS'];assert tax['base']==expect['base']
 for key,percent in [('ibs_nominal','0.10'),('cbs_nominal','0.90')]:
  value=(Decimal(expect['base'])*Decimal(percent)/100).quantize(Decimal('.00000001'),rounding=ROUND_HALF_EVEN).quantize(Decimal('.01'),rounding=ROUND_HALF_EVEN);assert Decimal(tax[key])==value
 for entry in op['base']['entries']:
  if entry.get('fiscal_origin')!='D2101':continue
  q=entry['d2101_binding'];pos=entry['public_offer_debt']['position']
  recalculation=copy.deepcopy(pos);recalculation['appropriations']={row['date']:row['appropriation'] for row in pos['contract_curve']}
  curve,monthlyAmounts,_=oracle(recalculation,c['dere_debt_daily_memory_calculation']['series'])
  assert curve==pos['contract_curve'] and monthlyAmounts==pos['declared_monthly']
  assert q['xml_title']['vSelic']==monthlyAmounts['vSelic'] and q['taxable_amount']==monthlyAmounts['vApur'] and q['book_apur_amount']=='0.00'
  assert q['position_mode']=='DAILY_MEMORY' and not q['normative_method_homologated']

for p in sorted(S.glob('*.json')):
 v=read(p)
 if 'corpus_expectation' not in v:continue
 positive=v['corpus_expectation']['status']=='PROJECTED';snap=E/(v['financial_scenario'].removesuffix('-financial.json')+'-snapshot.json');report=E/f'{p.stem}-monthly-report-final.json'
 args=['fiscal:dere:project-monthly-closing',snap.relative_to(ROOT).as_posix(),p.relative_to(ROOT).as_posix(),f'--bundle-manifest={B}']
 if positive and not report.exists():args += ['--report='+report.relative_to(ROOT).as_posix(),'--candidate-output='+(E/(p.stem+'-candidate-final.xml')).relative_to(ROOT).as_posix()]
 r=run(args,0 if positive else 1);assert not r.get('production_ready',False)
 if positive:
  stored=read(report)
  for key in ['decision_hash','snapshot_hash','monthly_input_hash','projection','event_validations']:assert r[key]==stored[key]
  assert set(stored['event_outputs'])=={'D1001','D1011','D1101','D1121','D1199','D2101'}
  assert stored['projection']['debt_yield_projection']['total_vApur']==v['corpus_expectation']['auxiliary_base']
  assert stored['projection']['deduction_pgcc_binding']['reportable_amount']=='100.00'
  for ev,filename in stored['event_outputs'].items():
   data=(ROOT/filename).read_bytes();val=stored['event_validations'][ev];assert val['reason']=='RTC_DERE_UNSIGNED_LOCAL_CANDIDATE' and len(val['errors'])==1
   assert val['xml_sha256']==hashlib.sha256(data).hexdigest() and val['schema_sha256']=='3d4de67215335e3b3186e92214ecbb8a55b501e846aba22eff59ca53e582dcdd'
   xml=ET.fromstring(data);ns={'d':xml.tag.split('}')[0][1:]}
   if ev=='D2101':
    rows=xml.findall('.//d:detTitulo',ns);assert xml.find('.//d:semTitulos',ns) is None
    assert sum((Decimal(x.find('d:vApur',ns).text) for x in rows),Decimal('0.00'))==Decimal(v['corpus_expectation']['auxiliary_base'])
    expected=sorted((q['xml_title'] for q in stored['projection']['debt_yield_projection']['qualified_entries'].values()),key=lambda t:(t['cCta'],t['idTitulo']))
    assert len(rows)==len(expected)
    for row,title in zip(rows,expected):
     assert row.get('cCta')==title['cCta'] and row.get('idTitulo')==title['idTitulo']
     for field,amount in title.items():
      if field not in ['cCta','idTitulo']:assert row.find('d:'+field,ns).text==amount
   if ev=='D1101':
    rows=xml.findall('.//d:infoConta',ns)
    actual_rows={x.find('d:cCta',ns).text:x for x in rows}
    assert len(actual_rows)==len(v['book']['accounts'])
    for account in v['book']['accounts']:
     for field in ['vSaldoInic','vMovDebt','vMovCred','vSaldoFinal','vApur']:
      assert actual_rows[account['cCta']].find('d:'+field,ns).text==account[field]
    assert actual_rows['interest000'].find('d:vApur',ns).text=='1000.00'
   bodies+=1
 else:assert r['status'] in ['ACTION_REQUIRED','INVALID'] and r['reason']==v['corpus_expectation']['reason'],(p.name,r)
 monthly.append({'case':p.stem,'status':r['status'],'reason':r['reason']})
for p in sorted(S.glob('engine-*.json')):
 v=read(p);source=E/(p.stem+'-input.json');save(source,v['scenario']);r=run(['fiscal:rtc:run',source.relative_to(ROOT).as_posix(),f'--bundle-manifest={B}'],(0,1));assert r['decision']==[] and r['reason_codes']==[v['expectation']],(p.name,r);save(E/(p.stem+'-result.json'),r);engine.append({'case':p.stem,'status':r['status'],'reason':v['expectation']})
assert len(monthly)==27 and len(engine)==11 and bodies==120 and replays==20
save(E/'corpus-final.json',{'valid':True,'monthly_cases':monthly,'engine_cases':engine,'xml_bodies':bodies,'financial_replays':replays,'oracle':'DECIMAL_100_DIGIT_DERE_HALF_EVEN_INTERMEDIATE_8_FINAL_2'})
g=run(['fiscal:rtc:production-assess',f'--bundle-manifest={B}',f'--controls=resources/fiscal/production/phase4b-controls-{V}.json','--target=local-validation']);assert not g['ready'] and g['official_source_hashes_valid']==47;save(E/'production-assessment.json',g)
baseline=read(E/'historical-before.json');changed=[p for p,h in baseline.items() if not (ROOT/p).is_file() or hashlib.sha256((ROOT/p).read_bytes()).hexdigest()!=h];assert not changed;save(E/'historical-verification.json',{'checked':len(baseline),'changed':changed})
print(f'Candidate27 PASS:27 monthly/11 engine cases;120 XML bodies;20 financial replays;{len(baseline)} historical artifacts preserved.')
