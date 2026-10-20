"""Reuse the verified native case harnesses against installed namespaces; keep snapshots out of Pest data providers."""
from pathlib import Path
import hashlib
import json

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-advance91-integration-20261015'
D = Path(r'D:\ProjetoSaasExports')
P89 = D / 'phase4b-advance89-record-binding-preparation-20261015'
P90 = D / 'phase4b-advance90-final-reconciliation-preparation-20261015'
def read(p): return json.loads(p.read_text(encoding='utf-8'))
def sha(p): return hashlib.sha256(p.read_bytes()).hexdigest()
def save(p, text):
    with p.open('x', encoding='utf-8') as stream: stream.write(text)
targets = [E / 'native89-installed.php', E / 'native90-installed.php', E / 'decimal90-independent-oracle.json', E / 'component-harness-installation.json']
assert not any(p.exists() for p in targets)
proof89 = read(P89 / 'staged89-record-binding-validation.json')
proof90 = read(P90 / 'staged90-captured-reconciliation-validation-v2.json')
assert sha(P89 / 'probe89.php') == proof89['native_execution']['source_hashes']['probe89.php']
assert sha(P90 / 'probe90.php') == proof90['native_execution']['source_hashes'][str(P90 / 'probe90.php')]
source89 = (P89 / 'probe89.php').read_text(encoding='utf-8')
assert source89.count("require '/tmp/PriorAdvanceRecordSet89.php';") == 1
source89 = source89.replace("require '/tmp/PriorAdvanceRecordSet89.php';", '')
source89 = source89.replace('use Staged89\\PriorAdvanceRecordSet89;', 'use App\\Modules\\Fiscal\\ConsumptionReform\\PriorAdvanceRecordSet89;')
# These payloads were useful in staging, but retaining four 3.6MB snapshots in parent Pest is unnecessary.
assert source89.count("    'additional_native_snapshots' => [$second, ...$numeric],\n") == 1
source89 = source89.replace("    'additional_native_snapshots' => [$second, ...$numeric],\n", '')
source90 = (P90 / 'probe90.php').read_text(encoding='utf-8')
assert source90.count("require '/tmp/probe89.php';") == source90.count("require '/tmp/CapturedAdvanceFinalReconciler90.php';") == 1
source90 = source90.replace("require '/tmp/probe89.php';", "require __DIR__.'/native89-installed.php';")
source90 = source90.replace("require '/tmp/CapturedAdvanceFinalReconciler90.php';", '')
source90 = source90.replace('use Staged90\\CapturedAdvanceFinalReconciler90;', 'use App\\Modules\\Fiscal\\ConsumptionReform\\CapturedAdvanceFinalReconciler90;')
save(targets[0], source89); save(targets[1], source90)
save(targets[2], json.dumps(proof90['native_execution']['oracle'], ensure_ascii=False, indent=2))
save(targets[3], json.dumps({'status': 'INSTALLED_NAMESPACE_NATIVE_CASE_HARNESSES_CREATED',
    'record_binding_cases': 43, 'final_reconciliation_cases': 28, 'independent_decimal_cases': 14,
    'source89_sha256': sha(P89 / 'probe89.php'), 'source90_sha256': sha(P90 / 'probe90.php'),
    'artifacts': {p.name: sha(p) for p in targets[:3]},
    'pest_wrapper_tests': 2, 'note': '43/28 native cases remain explicit;two subprocess wrappers are Pest tests, not71 new Pest tests.',
    'production_ready': False}, ensure_ascii=False, indent=2))
print('PASS:43 and28 native case harnesses,14 independent decimal answers;two permanent Pest wrappers')
