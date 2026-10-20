"""Verify lifecycle prototype evidence, explicit limits, primary sources and fixture arithmetic."""
from pathlib import Path
from decimal import Decimal
from xml.etree import ElementTree as ET
import hashlib,json
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-dere9121-59-version-preparation-20261013'

def read(path):
    return json.loads(path.read_text(encoding='utf-8'))

def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()

def ref(path):
    return {'path':path.relative_to(ROOT).as_posix(),'sha256':sha(path)}

assert not (ROOT/'app/Modules/Fiscal/ConsumptionReform/DerePublicOfferVersionChainPrototype59.php').exists()
research=read(E/'version-research-contract.json')
for source in research['sources']:
    assert sha(ROOT/source['source_path'])==source['source_sha256']
    assert sha(ROOT/source['excerpt_path'])==source['excerpt_sha256']
for schema in research['schemas']:
    assert sha(ROOT/schema['path'])==schema['sha256']
quality=[]
for label in ['version-native-v2-capture','version-native-v2-replay','version-native-v2-after-format-replay',
    'version-v2-prototype-phpstan','version-v2-format']:
    record=read(E/(label+'.json'))
    assert record['exit_code']==0 and record['log_sha256']==sha(E/(label+'.log'))
    quality.append(ref(E/(label+'.json')))
data=read(E/'version-native-v2-result.json')
assert len(data['results'])==12 and len(data['events'])==4
for index,amount in [(1,'98.17'),(2,'99.17'),(3,None),(4,'100.17')]:
    result=data['results']['prefix-'+str(index)]
    assert result['status']=='LOCAL_PUBLIC_EVENT_VERSION_CHAIN_RECONCILED'
    assert (result['active_event']['reconciliation']['total_vApur'] if result['active_event'] else None)==amount
    assert not result['active_event_business_reconciled'] and not result['eligible_for_composition']
    assert not result['government_return_authenticated'] and not result['production_ready']
for name,result in data['results'].items():
    if not name.startswith('prefix-'):
        assert result['status']=='ACTION_REQUIRED' and not result['production_ready']
for event in data['events']:
    root=ET.fromstring(event['origin_xml'])
    ns={'d':'http://www.dere.gov.br/schemas/evtDebOpOfPublic/v0_0_3'}
    for title in root.findall('.//d:detTitulo',ns):
        value=lambda tag: Decimal(title.find('d:'+tag,ns).text)
        assert value('vApur')==max(Decimal(0),min(value('vSelic'),value('vVarMensal'))-value('vPisCofins'))
files=[E/'planned-version-selector.php',E/'exercise-version-selector-v2.php',Path(__file__),
    ROOT/'scripts/capture_phase4b_dere9121_59_check.py',ROOT/'docs/fiscal/phase4b-next-scope-dere9121-public-versions59.md']
with (E/'version-preparation-validation.json').open('x',encoding='utf-8') as stream:
    json.dump({'status':'SYNTHETIC_PUBLIC_VERSION_LIFECYCLE_PROTOTYPE_VALIDATED_NOT_MONTHLY_BUSINESS_INTEGRATION',
        'positive_prefixes':4,'negative_cases':8,'exact_replay':True,'quality':quality,
        'primary_contract':ref(E/'version-research-contract.json'),
        'additional_hierarchy_page36':ref(E/'d2101-hierarchy-physical-page36.txt'),
        'implementation':[ref(path) for path in files], 'prototype_installed':False,
        'active_business_reconciled':False,'period_state_externally_verified':False,
        'D1021_proceeding_qualified':False,'monthly_integration_complete':False,
        'scope59_complete':False,'scope54_complete':False,'phase4b_complete':False,'production_ready':False},stream,indent=2)
print('PASS:4 lifecycle prefixes,8 refusals,active receipt/exclusion rules,exact replay and primary sources; business integration pending')
