"""Install origin/offset and exact quote parsing together after predecessor acceptance."""
from pathlib import Path
import hashlib, json, runpy

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-fx71-exact-quote-preparation-20261014'
def sha(path): return hashlib.sha256(path.read_bytes()).hexdigest()
accepted = json.loads((E / 'exact-quote-preparation-validation.json').read_text(encoding='utf-8'))
for item in accepted['implementation']:
    assert sha(ROOT / item['path']) == item['sha256']
live = ROOT / 'app/Modules/Fiscal/ConsumptionReform/ImportFxQuoteConverter.php'
assert sha(live) == accepted['current_converter_sha256']
archive = E / 'before-combined-install-converter.php'
assert not archive.exists() and not (E / 'combined-successor-installation.json').exists()
runpy.run_path(str(ROOT / 'scripts/activate_phase4b_fx70_origin_offset_v2.py'), run_name='__main__')
with archive.open('xb') as stream: stream.write(live.read_bytes())
source = E / 'planned-exact-quote-converter.php'
live.write_text(source.read_text(encoding='utf-8').replace('ImportFxExactQuotePrototype71', 'ImportFxQuoteConverter'), encoding='utf-8')
with (E / 'combined-successor-installation.json').open('x', encoding='utf-8') as stream:
    json.dump({'status': 'ORIGIN_OFFSET_AND_EXACT_TOKEN_INSTALLED_VALIDATION_PENDING',
               'before_converter_sha256': sha(archive), 'installed_converter_sha256': sha(live),
               'prototype_sha256': sha(source), 'policy_activated': False,
               'full_scope68_complete': False, 'production_ready': False}, stream, indent=2)
print('PASS:combined successor installed; both predecessor sources archived; validation pending')
