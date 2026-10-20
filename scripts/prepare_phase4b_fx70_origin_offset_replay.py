"""Replay the nine material-origin cases against the offset-bounded successor without rewriting old driver."""
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-fx70-origin-preparation-20261014'
source=(E/'exercise-origin-transaction.php').read_text(encoding='utf-8')
source=source.replace("require __DIR__.'/planned-origin-transaction.php';","require __DIR__.'/planned-origin-transaction-offset-v2.php';")
with (E/'exercise-origin-successor-v2.php').open('x',encoding='utf-8') as f:f.write(source)
print('PASS:material-origin replay prepared against offset successor, previous driver unchanged')
