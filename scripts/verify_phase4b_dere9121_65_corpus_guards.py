"""Reject economic and excluded-active mutations for every case, plus four distinct contextual command guards."""
from pathlib import Path
from decimal import Decimal
from xml.etree import ElementTree as ET
import base64,copy,hashlib,json,subprocess,sys
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-dere9121-65-version-corpus-20261013'
O='http://www.dere.gov.br/schemas/evtDebOpOfPublic/v0_0_3'
R='http://www.dere.gov.br/schemas/evtRetornoTitPub/v0_0_2'

def read(p):
    return json.loads(p.read_text(encoding='utf-8'))

def sha(p):
    return hashlib.sha256(p.read_bytes()).hexdigest()

def save(p,v):
    if p.exists():
        assert read(p)==v,p
    else:
        with p.open('x',encoding='utf-8') as f:
            json.dump(v,f,ensure_ascii=False,indent=2)

def node(tree,ns,name):
    found=tree.find('.//{'+ns+'}'+name)
    assert found is not None,name
    return found

def xml(tree,ns):
    ET.register_namespace('',ns)
    return ET.tostring(tree,encoding='unicode')

def change_origin(event,tree):
    event['origin_xml']=xml(tree,O)
    response=ET.fromstring(event['return_xml'])
    node(response,R,'hash').text=base64.b64encode(hashlib.sha256(event['origin_xml'].encode('utf-8')).digest()).decode('ascii')
    event['return_xml']=xml(response,R)

complete=(E/'corpus-capture-validation.json').exists()
if complete:
    assert len(read(E/'corpus-capture-validation.json')['cases'])==40
else:
    assert '--completed-only' in sys.argv,'Full corpus capture required'
records=[]
names=[c['name'] for c in read(E/'source-corpus-manifest.json')['cases']]
for name in names:
    if not complete:
        replay_path=E/(name+'-active03-monthly-replay.json')
        if not replay_path.exists():
            continue
        replay=read(replay_path)
        assert replay['exit_code']==0 and replay['log_sha256']==sha(replay_path.with_suffix('.log'))
    base=read(E/(name+'-active03-request.json'))
    guards=['economic','excluded']+(['stale-receipt','pgcc-source','wrong-period','history-hash'] if name=='constant' else [])
    for guard in guards:
        request=copy.deepcopy(base)
        binding=request['current_public_offer_return']
        if guard=='economic':
            active=binding['events'][3]
            origin=ET.fromstring(active['origin_xml'])
            value=node(origin,O,'vApur')
            value.text=format(Decimal(value.text)+Decimal('.01'),'.2f')
            change_origin(active,origin)
            response=ET.fromstring(active['return_xml'])
            total=sum((Decimal(v.text) for v in origin.findall('.//{'+O+'}vApur')),Decimal(0))
            node(response,R,'vApurTot').text=format(total,'.2f')
            active['return_xml']=xml(response,R)
            expected='RTC_DERE_PUBLIC_BINDING_ACTIVE_PUBLIC_BUSINESS_DIFFERS_FROM_REBUILT_MONTH'
        elif guard=='excluded':
            binding['events']=binding['events'][:3]
            expected='RTC_DERE_PUBLIC_BINDING_ACTIVE_PUBLIC_EVENT_MISSING_AFTER_EXCLUSION'
        elif guard=='stale-receipt':
            active=binding['events'][1]
            origin=ET.fromstring(active['origin_xml'])
            node(origin,O,'nrRecibo').text='2101-202609-'+('9'*19)
            change_origin(active,origin)
            expected='RTC_DERE_PUBLIC_VERSION_ACTIVE_RECEIPT_REFERENCE_CONFLICT'
        elif guard=='pgcc-source':
            binding['pgcc_source_hash']='0'*64
            expected='RTC_DERE_PUBLIC_BINDING_NATIVE_PUBLIC_PROJECTION_AND_PGCC_BINDING_REQUIRED'
        elif guard=='wrong-period':
            active=binding['events'][3]
            response=ET.fromstring(active['return_xml'])
            node(response,R,'perApur').text='2026-08'
            active['return_xml']=xml(response,R)
            expected='RTC_DERE_PUBLIC_VERSION_EVENT_PAIR_CONTEXT_SEQUENCE_OR_ID_CONFLICT'
        else:
            binding['period_history']['witness']['history_hash']='0'*64
            expected='RTC_DERE_PUBLIC_PERIOD_HISTORY_HASH_OR_KNOWLEDGE_CONFLICT'
        label=name+'-guard-'+guard
        request_path=E/(label+'-request.json')
        candidate=E/(label+'-candidate.xml')
        report=E/(label+'-report.json')
        outputs=[candidate,report,*[Path(str(candidate)+'.'+event+'.xml') for event in ['D1001','D1011','D1101','D1121','D2101']]]
        assert not any(p.exists() for p in outputs),'Guard outputs already exist'
        save(request_path,request)
        log=E/(label+'.log')
        record=E/(label+'.json')
        if record.exists():
            metadata=read(record)
            assert metadata['exit_code']==1 and metadata['log_sha256']==sha(log)
            result=json.loads(log.read_text(encoding='utf-8'))
        else:
            assert not log.exists()
            command=['docker','compose','exec','-T','app','php','-d','memory_limit=512M','artisan','fiscal:dere:project-monthly-closing',
                (E/(name+'-snapshot.json')).relative_to(ROOT).as_posix(),(E/(name+'-month.json')).relative_to(ROOT).as_posix(),
                '--bundle-manifest=bundles/consumption-phase4b-2026.10.13-28-draft65.json',
                '--negative-base-settlement-request='+request_path.relative_to(ROOT).as_posix(),
                '--candidate-output='+candidate.relative_to(ROOT).as_posix(),'--report='+report.relative_to(ROOT).as_posix()]
            executed=subprocess.run(command,cwd=ROOT,capture_output=True,encoding='utf-8')
            with log.open('x',encoding='utf-8') as f:
                f.write(executed.stdout+executed.stderr)
            save(record,{'command':command,'exit_code':executed.returncode,'log_sha256':sha(log)})
            assert executed.returncode==1,label+':'+executed.stderr
            result=json.loads(executed.stdout)
        assert result['status']=='ACTION_REQUIRED' and result['reason']==expected,label+':'+str(result)
        assert not any(p.exists() for p in outputs),label+':writes occurred'
        records.append({'name':name,'guard':guard,'expected_reason':expected,'request_sha256':sha(request_path),
            'log_sha256':sha(log),'no_xml_or_report_written':True,'production_ready':False})
        print('PASS:'+label+'; explicit expected refusal before all outputs',flush=True)
if complete:
    assert len(records)==44
    target=E/'corpus-guard-validation.json'
    status='FORTY_ECONOMIC_OR_EXCLUDED_ACTIVE_GUARDS_AND_FOUR_CONTEXT_GUARDS_REFUSED_BEFORE_WRITES'
else:
    target=E/('corpus-guard-progress-'+str(len(records))+'.json')
    status='COMPLETED_CASE_COMMAND_GUARDS_REFUSED_BEFORE_WRITES_WIDER_CORPUS_PENDING'
save(target,{'status':status,'cases':records,'guard_cases':len(records),'full_corpus_guard_complete':complete,'production_ready':False})
print('PASS:'+str(len(records))+' command guard cases refused before any of six XMLs or report; '+('full corpus' if complete else 'corpus pending'))
