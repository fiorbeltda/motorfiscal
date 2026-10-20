"""Archive86 before adding advance origin; preserve staged87 source and native proof."""
from pathlib import Path
import hashlib
import json
import shutil

ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-advance88-preparation-20261015'
S=Path(r'D:\ProjetoSaasExports\phase4b-temporal87-origin-preparation-20261015')
A=ROOT/'storage/app/phase4b-temporal86-development-20261015/temporal86-development-validation.json'
def read(p):return json.loads(p.read_text(encoding='utf-8'))
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
accepted=read(A)
assert accepted['full_suite_complete'] and accepted['full_suite']['tests']==2589
for p,h in accepted['runtime_hashes'].items():assert sha(ROOT/p)==h,p
proof=read(S/'probe-v2/advance-origin-native-execution.json')
assert proof['exit_code']==0
for p,h in proof['source_hashes'].items():assert sha(Path(p))==h
assert sha(S/'probe-v2/advance-origin-native.stdout')==proof['stdout_sha256']
native=read(S/'probe-v2/advance-origin-native.stdout')
assert len(native['cases'])==51 and all(r['passed'] for r in native['cases'])
for label in ['scope87-final-staged-phpstan','scope87-final-staged-pint']:
    path=ROOT/'storage/app/phase4b-fx71-exact-quote-preparation-20261014'/label
    gate=read(path.with_suffix('.json'));assert gate['exit_code']==0 and sha(path.with_suffix('.log'))==gate['log_sha256']
E.mkdir(exist_ok=False)
paths=['app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php',
    'app/Modules/Fiscal/ConsumptionReform/TaxPointContext86.php',
    'resources/monitoring/task-progress.json']
archive={}
for p in paths:
    target=E/'before-integration'/p;target.parent.mkdir(parents=True,exist_ok=True)
    shutil.copy2(ROOT/p,target);assert sha(target)==sha(ROOT/p);archive[p]=sha(target)
for name in ['AdvanceOnlyOrigin87','AdvanceOnlyNominal87']:
    source=S/(name+'-formatted.php');shutil.copy2(source,E/(name+'-staged.php'))
    code=source.read_text(encoding='utf-8')
    imports='use DateTimeImmutable;\n'+('use DateTimeZone;\n' if name=='AdvanceOnlyOrigin87' else 'use Throwable;\n')
    code=code.replace('declare(strict_types=1);','declare(strict_types=1);\n\nnamespace App\\Modules\\Fiscal\\ConsumptionReform;\n\n'+imports,1)
    target=ROOT/'app/Modules/Fiscal/ConsumptionReform'/(name+'.php')
    with target.open('x',encoding='utf-8') as stream:stream.write(code)
shutil.copy2(S/'probe-v2/advance-origin-cases.json',E/'advance-origin-cases.json')
with (E/'before-integration-archive.json').open('x',encoding='utf-8') as stream:
    json.dump({'status':'ACCEPTED86_ARCHIVED_STAGED87_ORIGIN_INSTALLED_FOR88','archived':archive,
        'accepted86_sha256':sha(A),'staged_native_execution':proof,'full_suite88_complete':False,'production_ready':False},stream,indent=2)
print('PASS:86 archived;two verified87 components installed;51-case corpus preserved')
