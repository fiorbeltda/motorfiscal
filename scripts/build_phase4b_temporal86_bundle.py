"""Create a source-bound temporal opt-in bundle, retaining every predecessor artifact and production gate."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json

ROOT=Path(__file__).resolve().parents[1]
F=ROOT/'resources/fiscal'
E=ROOT/'storage/app/phase4b-temporal86-integration-preparation-20261015'
D=Path(r'D:\ProjetoSaasExports')
V='2026.10.15-86-draft1'
def read(path):return json.loads(path.read_text(encoding='utf-8'))
def sha(path):return hashlib.sha256(path.read_bytes()).hexdigest()
def canonical(value):return hashlib.sha256(json.dumps(value,sort_keys=True,ensure_ascii=False,separators=(',',':')).encode()).hexdigest()
def save(path,value):
    with path.open('x',encoding='utf-8') as stream:json.dump(value,stream,ensure_ascii=False,indent=2)
assert (E/'before-integration-archive.json').exists()
bundle=read(F/'bundles/consumption-phase4b-2026.10.15-78-draft1.json')
transition=read(F/bundle['resource_paths']['rtc_transition_catalog'])
facts=read(F/bundle['resource_paths']['fact_definitions'])
questions=read(F/bundle['resource_paths']['questions'])
controls=read(F/bundle['production_controls']['path'])
source_specs=[('LC214','phase4b-temporal81-primary-research-20261015','lc214-current.html',
    'dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde','2026-01-16','2026-01-01'),
    ('CBS_REGULATION','phase4b-temporal81-primary-research-20261015','cbs-regulation.html',
    '8396d56d14fb30a386c7548047701a6c310cb3a60da1a0b6893b6df5b8d90235','2026-04-30','2026-04-30'),
    ('IBS_REGULATION','phase4b-temporal82-event-preparation-20261015','ibs-regulation-reobserved.pdf',
    '9ed7032ef9c25bbee51bdb5e90f60e1ad01a2bf56cb41a28221c3db7f61d419a','2026-04-30','2026-04-30'),
    ('IBS_AMENDMENT13','phase4b-temporal82-event-preparation-20261015','ibs-amendment13.pdf',
    '527f5b01b2c8effd984c0334ab1c3068d3926f4f77f90d023211758ee67f7222','2026-07-22','2026-07-22')]
policy={'contract':'RTC-TAX-POINT-EVENTS-2026.10.15-86','status':'ACTIVE',
    'valid_from':'2026-04-30','valid_to':'2033-12-31','available_at':'2026-10-15T14:11:36.200607+00:00',
    'source_hashes':{key:digest for key,_,_,digest,_,_ in source_specs},
    'scope':'LOCAL_RELEVANT_DATE_AND_ADVANCE_CANDIDATE_NOT_PRIOR_DEBIT_AUTHENTICATION_OR_COMPLETE_SETTLEMENT'}
paths=[F/f'catalogs/rtc-transition-{V}.json',F/f'facts/consumption-phase4b-{V}.json',
    F/f'questions/consumption-integrated-{V}.json',F/f'production/phase4b-controls-{V}.json',
    F/f'bundles/consumption-phase4b-{V}.json',E/'temporal86-bundle-installation.json',E/'component-policy.json']
source_rows=[]
for key,folder,name,digest,published,effective in source_specs:
    origin=D/folder/name
    assert sha(origin)==digest
    observation=read(D/folder/('primary-source-observation.json' if '81-' in folder else 'source-observation.json'))
    row=next(r for r in observation['sources'] if r['file']==name)
    assert row['http_status']==200 and row['sha256']==digest
    filename='temporal86-'+key.lower().replace('_','-')+origin.suffix
    target=F/'sources/official'/filename
    paths.append(target)
    source_rows.append((origin,target,{'id':'TEMPORAL86_'+key,'url':row['url'],'file':filename,'sha256':digest,
        'version':'OBSERVED_2026.10.15','published_on':published,'effective_from':effective,'observed_at':row['observed_at'],
        'proven_effective_scope':'Local art10/art11 tax point normalization and art617 amendment review;not future tax rates or prior-debit authentication.'}))
assert not any(p.exists() for p in paths),'Exclusive bundle or source target already exists'
added=[('rtc.assess_tax_point_events','BOOLEAN','Qualificar a data relevante por eventos e evidências temporais?'),
    ('rtc.tax_point_operation_id','STRING','Qual é a identidade da operação vinculada aos eventos temporais?'),
    ('rtc.tax_point_contexts','ARRAY','Informe a única operação, seus documentos, fornecimento, parcelas e evidências temporais.'),
    ('rtc.tax_point_installment_id','STRING','Qual parcela de execução continuada corresponde ao valor desta decisão?')]
rank=max(q['rank'] for q in questions)
for index,(key,kind,prompt) in enumerate(added,1):
    assert not any(row['key']==key for row in facts)
    facts.append({'key':key,'type':kind,'material':True,**({'min_length':1} if kind=='STRING' else {})})
    questions.append({'fact_key':key,'answer_type':kind,'rank':rank+index,'prompt':prompt})
transition['version']=V
transition['tax_point_event_policy']=policy
controls['version']=V
for row in controls['controls']:
    if row['id']=='4B.1.TEMPORALITY':row['gap']+=' Eventos86 opt-in: data local qualificada; débitos anteriores, conciliação documental e demais contextos continuam pendentes.'
    if row['id']=='4B.3.SIMPLES_MEI':row['gap']='Eleição regular H1/2027 aceita localmente78 com íntegra CGSN194/186. Sem autenticação governamental, alíquotas futuras, apuração Simples integral ou aprovação de produção.'
controls['controls'].append({'id':'4B.1.TEMPORAL_EVENT_SETTLEMENT','state':'PARTIAL',
    'source_ids':['TEMPORAL86_LC214','TEMPORAL86_CBS_REGULATION','TEMPORAL86_IBS_REGULATION','TEMPORAL86_IBS_AMENDMENT13'],
    'gap':'Prova dos débitos anteriores, período de apuração, cancelamento/pagamento a maior, documentos e demais espécies ainda não aceitos.'})
for role,value,relative in [('rtc_transition_catalog',transition,f'catalogs/rtc-transition-{V}.json'),
    ('fact_definitions',facts,f'facts/consumption-phase4b-{V}.json'),('questions',questions,f'questions/consumption-integrated-{V}.json')]:
    save(F/relative,value);bundle['resource_paths'][role]=relative;bundle['content_hashes'][role]=canonical(value)
control_path=f'production/phase4b-controls-{V}.json'
save(F/control_path,controls);bundle['production_controls']={'path':control_path,'sha256':canonical(controls)}
for origin,target,row in source_rows:
    with target.open('xb') as stream:stream.write(origin.read_bytes())
    assert sha(target)==row['sha256']
    bundle['official_sources'].append(row)
bundle.update(predecessor_bundle_id=bundle['bundle_id'],bundle_id='CONSUMPTION-PHASE4B-'+V,version=V,
    knowledge_available_at=datetime.now(timezone.utc).replace(microsecond=0).isoformat())
bundle['catalog_versions']['rtc_transition']=V
save(F/f'bundles/consumption-phase4b-{V}.json',bundle)
save(E/'component-policy.json',policy)
save(E/'temporal86-bundle-installation.json',{'status':'TEMPORAL86_BUNDLE_CREATED_INTEGRATION_VALIDATION_PENDING',
    'bundle':f'bundles/consumption-phase4b-{V}.json','bundle_sha256':sha(F/f'bundles/consumption-phase4b-{V}.json'),
    'new_facts':[r[0] for r in added],'official_sources':len(bundle['official_sources']),
    'old_rates_unchanged':bundle['content_hashes']['rtc_rate_catalog'],'full_suite_complete':False,'production_ready':False})
print('PASS:temporal86 own bundle,4 facts/questions,59 sources,extra production settlement gate;old rates unchanged')
