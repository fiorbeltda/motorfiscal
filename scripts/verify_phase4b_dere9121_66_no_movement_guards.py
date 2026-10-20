"""Reject nonzero/missing aggregates, phantom titles and incomplete context for all no-movement profiles."""
from pathlib import Path
from xml.etree import ElementTree as ET
import base64,copy,hashlib,json,subprocess
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-dere9121-66-no-movement-versions-20261013'
P65=ROOT/'storage/app/phase4b-dere9121-65-version-corpus-20261013'
O='http://www.dere.gov.br/schemas/evtDebOpOfPublic/v0_0_3'
R='http://www.dere.gov.br/schemas/evtRetornoTitPub/v0_0_2'

def read(p):
    return json.loads(p.read_text(encoding='utf-8'))

def sha(p):
    return hashlib.sha256(p.read_bytes()).hexdigest()

def save(p,value):
    if p.exists():
        assert read(p)==value,p
    else:
        with p.open('x',encoding='utf-8') as f:
            json.dump(value,f,ensure_ascii=False,indent=2)

def node(tree,ns,name):
    result=tree.find('.//{'+ns+'}'+name)
    assert result is not None,name
    return result

def xml(tree,ns):
    ET.register_namespace('',ns)
    return ET.tostring(tree,encoding='unicode')

assert len(read(E/'no-movement-version-capture-validation.json')['cases'])==6
phantom=ET.fromstring(read(P65/'complete-sale-active03-request.json')['current_public_offer_return']['events'][3]['origin_xml'])
assert sum(float(n.text) for n in phantom.findall('.//{'+O+'}vApur'))==0
records=[]
for name in ['qualified-direct-only','qualified-fund-only','qualified-both-codes']:
    base=read(E/(name+'-active03-request.json'))
    for guard in ['nonzero','missing-total','phantom-title','excluded','pgcc-source','history-hash']:
        request=copy.deepcopy(base)
        binding=request['current_public_offer_return']
        active=binding['events'][3]
        if guard=='nonzero':
            response=ET.fromstring(active['return_xml'])
            node(response,R,'vApurTot').text='0.01'
            active['return_xml']=xml(response,R)
            expected='RTC_DERE_PUBLIC_VERSION_ACTIVE_RETURN_AGGREGATE_UNPROVEN'
        elif guard=='missing-total':
            response=ET.fromstring(active['return_xml'])
            group=node(response,R,'infoEvento')
            group.remove(node(response,R,'infoTotTitOfPub'))
            active['return_xml']=xml(response,R)
            expected='RTC_DERE_PUBLIC_VERSION_ACTIVE_RETURN_AGGREGATE_UNPROVEN'
        elif guard=='phantom-title':
            origin=ET.fromstring(active['origin_xml'])
            event=node(origin,O,'evtDebOpOfPublic')
            event.remove(node(origin,O,'infoDebOpOfPublic'))
            event.append(copy.deepcopy(node(phantom,O,'infoDebOpOfPublic')))
            active['origin_xml']=xml(origin,O)
            response=ET.fromstring(active['return_xml'])
            node(response,R,'hash').text=base64.b64encode(hashlib.sha256(active['origin_xml'].encode('utf-8')).digest()).decode('ascii')
            active['return_xml']=xml(response,R)
            expected='RTC_DERE_PUBLIC_BINDING_ACTIVE_PUBLIC_BUSINESS_DIFFERS_FROM_REBUILT_MONTH'
        elif guard=='excluded':
            binding['events']=binding['events'][:3]
            expected='RTC_DERE_PUBLIC_BINDING_ACTIVE_PUBLIC_EVENT_MISSING_AFTER_EXCLUSION'
        elif guard=='pgcc-source':
            binding['pgcc_source_hash']='0'*64
            expected='RTC_DERE_PUBLIC_BINDING_NATIVE_PUBLIC_PROJECTION_AND_PGCC_BINDING_REQUIRED'
        else:
            binding['period_history']['witness']['history_hash']='0'*64
            expected='RTC_DERE_PUBLIC_PERIOD_HISTORY_HASH_OR_KNOWLEDGE_CONFLICT'
        label=name+'-guard-v2-'+guard
        request_path=E/(label+'-request.json')
        candidate=E/(label+'-candidate.xml')
        report=E/(label+'-report.json')
        paths=[candidate,report,*[Path(str(candidate)+'.'+event+'.xml') for event in ['D1001','D1011','D1101','D1121','D2101']]]
        assert not any(path.exists() for path in paths)
        save(request_path,request)
        log=E/(label+'.log')
        record=E/(label+'.json')
        if record.exists():
            metadata=read(record)
            assert metadata['exit_code']==1 and metadata['log_sha256']==sha(log)
            output=read(log)
        else:
            assert not log.exists()
            command=['docker','compose','exec','-T','app','php','-d','memory_limit=512M','artisan','fiscal:dere:project-monthly-closing',
                (E/'financial-snapshot.json').relative_to(ROOT).as_posix(),(E/(name+'-month.json')).relative_to(ROOT).as_posix(),
                '--bundle-manifest=bundles/consumption-phase4b-2026.10.13-28-draft66.json',
                '--negative-base-settlement-request='+request_path.relative_to(ROOT).as_posix(),
                '--candidate-output='+candidate.relative_to(ROOT).as_posix(),'--report='+report.relative_to(ROOT).as_posix()]
            result=subprocess.run(command,cwd=ROOT,capture_output=True,encoding='utf-8')
            with log.open('x',encoding='utf-8') as f:
                f.write(result.stdout+result.stderr)
            save(record,{'command':command,'exit_code':result.returncode,'log_sha256':sha(log)})
            assert result.returncode==1,label
            output=json.loads(result.stdout)
        assert output['status']=='ACTION_REQUIRED' and output['reason']==expected,label+':'+str(output)
        assert not any(path.exists() for path in paths)
        records.append({'name':name,'guard':guard,'label':label,'expected_reason':expected,'request_sha256':sha(request_path),
            'log_sha256':sha(log),'no_xml_or_report_written':True,'production_ready':False})
        print('PASS:'+label+'; expected refusal before all outputs',flush=True)
save(E/'no-movement-guard-validation.json',{'status':'EIGHTEEN_NO_MOVEMENT_VERSION_COMMAND_GUARDS_REFUSED_BEFORE_WRITES',
    'cases':records,'guard_cases':18,'production_ready':False})
print('PASS:18 no-movement version guards refused before six XMLs or report')
