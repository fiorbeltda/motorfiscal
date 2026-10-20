"""Verify synthetic contextual period prototype and preserve the unactivated scope boundary."""
from pathlib import Path
import hashlib,json
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-dere9121-62-period-preparation-20261013'
P=ROOT/'storage/app/phase4b-dere9121-61-version-integration-20261013'

def read(p):
    return json.loads(p.read_text(encoding='utf-8'))

def sha(p):
    return hashlib.sha256(p.read_bytes()).hexdigest()

def ref(p):
    return {'path':p.relative_to(ROOT).as_posix(),'sha256':sha(p)}

sources=read(E/'period-research-sources.json')
for item in sources['sources']:
    assert sha(ROOT/item['path'])==item['sha256']
    assert sha(ROOT/item['excerpt_path'])==item['excerpt_sha256']
for item in sources['schemas']:
    assert sha(ROOT/item['path'])==item['sha256']
mod=(E/'dere-observed-20261005-mod.pdf.period-excerpt.txt').read_text(encoding='utf-8')
events=(E/'dere-observed-20261005-events.pdf.period-excerpt.txt').read_text(encoding='utf-8')
assert 'Contador Contínuo de Ciclos' in mod and 'Primeira Reabertura' in mod and 'PERAPUR_FECHADO' in mod
assert 'EXISTE_RECIBO_FECH_ATIVO' in events and 'exclusivamente a operação de Inclusão' in events
labels=['period62-v2-capture','period62-final-formatted-replay','period62-post-format-phpstan','period62-final-pint2']
for label in labels:
    record=read(E/(label+'.json'))
    assert record['exit_code']==0 and record['log_sha256']==sha(E/(label+'.log'))
    if label in ['period62-v2-capture','period62-final-formatted-replay']:
        assert (E/(label+'.log')).read_text(encoding='utf-8').count('PASS:')==22
native=read(E/'period-native-v2-result.json')
assert len(native['cases'])==22
positive=[]
for name,case in native['cases'].items():
    result=case['result']
    assert result['production_ready'] is False
    if case['expected_reason'] is None:
        positive.append(name)
        assert result['status']=='LOCAL_PUBLIC_PERIOD_HISTORY_QUALIFIED'
        assert result['current_state']=='OPEN' and result['period_state_locally_reconciled']
        assert result['period_state_externally_verified'] is False
        assert result['closing_origin_economics_reconciled'] is False
        assert result['government_return_authenticated'] is False
    else:
        assert result['status']=='ACTION_REQUIRED' and result['reason']=='RTC_DERE_PUBLIC_PERIOD_'+case['expected_reason']
assert positive==['initial-open','one-reopening','two-reopenings']
assert native['cases']['two-reopenings']['result']['closing_count']==2
assert native['cases']['two-reopenings']['result']['reopening_count']==2
for item in read(P/'integration-progress-validation.json')['implementation']:
    assert sha(ROOT/item['path'])==item['sha256'],item['path']
files=[E/'planned-period-qualifier.php',E/'exercise-period-qualifier.php',
    ROOT/'scripts/prepare_phase4b_dere9121_62_period_research.py',ROOT/'scripts/capture_phase4b_dere9121_62_check.py',
    ROOT/'docs/fiscal/phase4b-next-scope-dere9121-period-state62.md',Path(__file__)]
with (E/'period-preparation-validation.json').open('x',encoding='utf-8') as f:
    json.dump({'status':'CONTEXTUAL_SYNTHETIC_PERIOD_HISTORY_PROTOTYPE_VALIDATED_NOT_ACTIVATED',
        'positive_cases':3,'negative_cases':19,'exact_replay':True,'native_capture':ref(E/'period-native-v2-result.json'),
        'quality':[ref(E/(label+'.json')) for label in labels],'primary_evidence':ref(E/'period-research-sources.json'),
        'implementation':[ref(p) for p in files],'accepted61_sources_preserved':True,'app_installed':False,
        'policy_activated':False,'monthly_integrated':False,'period_state_externally_verified':False,
        'closing_origin_economics_reconciled':False,'scope62_complete':False,'scope59_complete':False,
        'phase4b_complete':False,'production_ready':False},f,ensure_ascii=False,indent=2)
print('PASS:22 contextual period cases, exact replay, primary hashes and unchanged61 implementation; activation pending')
