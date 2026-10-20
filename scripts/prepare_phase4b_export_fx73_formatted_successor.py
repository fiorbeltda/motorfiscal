"""Preserve frozen numeric prototype and make new driver generation for final formatting."""
from pathlib import Path
ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-partial-export-fx73-preparation-20261014'
with (E / 'planned-partial-export-quote-v2.php').open('xb') as stream:
    stream.write((E / 'planned-partial-export-quote.php').read_bytes())
for name in ['quote', 'transaction', 'engine']:
    source = E / ('exercise-partial-export-' + name + '.php')
    with (E / ('exercise-partial-export-' + name + '-v2.php')).open('x', encoding='utf-8') as stream:
        stream.write(source.read_text(encoding='utf-8').replace("'/planned-partial-export-quote.php'", "'/planned-partial-export-quote-v2.php'"))
print('PASS:new formatting generation created; frozen source and all recorded native results preserved')
