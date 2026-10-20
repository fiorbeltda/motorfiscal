"""Archive accepted78 inputs and preserve final staged components before any temporal integration edits."""
from pathlib import Path
import hashlib
import json
import shutil

ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-temporal86-integration-preparation-20261015'
D=Path(r'D:\ProjetoSaasExports')
A=ROOT/'storage/app/phase4b-simples78-development-20261015'
def read(path):return json.loads(path.read_text(encoding='utf-8'))
def sha(path):return hashlib.sha256(path.read_bytes()).hexdigest()
accepted=read(A/'simples78-development-validation.json')
assert accepted['full_suite_complete'] and accepted['full_suite']['tests']==2440
for path,digest in accepted['runtime_hashes'].items():assert sha(ROOT/path)==digest,path
assert read(D/'phase4b-temporal85-composition-preparation-20261015/composition-preparation-validation.json')['status']=='TEMPORAL85_LOCAL_COMPOSITION_VERIFIED_NOT_INSTALLED'
bundle=read(ROOT/'resources/fiscal/bundles/consumption-phase4b-2026.10.15-78-draft1.json')
paths=['app/Modules/Fiscal/ConsumptionReform/ConsumptionReformEngine.php',
       'app/Modules/Fiscal/ConsumptionReform/TransitionCatalog.php',
       'resources/fiscal/bundles/consumption-phase4b-2026.10.15-78-draft1.json',
       *['resources/fiscal/'+bundle['resource_paths'][key] for key in ['rtc_transition_catalog','fact_definitions','questions']],
       'resources/fiscal/'+bundle['production_controls']['path']]
E.mkdir(exist_ok=False)
archived={}
for path in paths:
    target=E/'before-integration'/path
    target.parent.mkdir(parents=True,exist_ok=True)
    shutil.copy2(ROOT/path,target)
    assert sha(target)==sha(ROOT/path)
    archived[path]=sha(target)
components=[('TaxPointEventPlanner85.php','phase4b-temporal85-composition-preparation-20261015/TaxPointEventPlanner85.php'),
    ('SupplyDateEvidenceResolver83.php','phase4b-temporal83-presumption-preparation-20261015/SupplyDateEvidenceResolver83-formatted.php'),
    ('AdvanceTaxReconciler84.php','phase4b-temporal84-reconciliation-preparation-20261015/AdvanceTaxReconciler84-formatted.php')]
component_hashes={}
for name,path in components:
    shutil.copy2(D/path,E/name)
    assert sha(D/path)==sha(E/name)
    component_hashes[name]=sha(E/name)
fixtures=[('planner-cases.json','phase4b-temporal82-event-preparation-20261015/probe-v2/event-plan-cases.json'),
    ('presumption-cases.json','phase4b-temporal83-presumption-preparation-20261015/probe-v2/presumption-cases.json'),
    ('reconciliation-cases.json','phase4b-temporal84-reconciliation-preparation-20261015/probe-v2/reconciliation-cases.json'),
    ('composition-cases.json','phase4b-temporal85-composition-preparation-20261015/composition-cases.json')]
fixture_hashes={}
for name,path in fixtures:
    shutil.copy2(D/path,E/name)
    assert sha(D/path)==sha(E/name)
    fixture_hashes[name]=sha(E/name)
with (E/'before-integration-archive.json').open('x',encoding='utf-8') as stream:
    json.dump({'status':'ACCEPTED78_INPUTS_ARCHIVED_BEFORE_TEMPORAL86_EDITS','archived':archived,
        'staged_components':component_hashes,'fixtures':fixture_hashes,'accepted78_sha256':sha(A/'simples78-development-validation.json'),
        'full_suite86_complete':False,'phase4b_complete':False,'production_ready':False},stream,indent=2)
print('PASS:7 accepted inputs archived;3 staged components and4 immutable corpora preserved')
