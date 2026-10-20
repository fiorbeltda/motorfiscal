"""Add required immediately-prior competence to successor capture, preserving first rejection."""
from pathlib import Path
ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-dere9121-54-preparation-20261013'
source = (E / 'capture-public-settlement.php').read_text(encoding='utf-8')
anchor = "$request['current_public_offer_return'] = $binding;"
assert source.count(anchor) == 1
source = source.replace(anchor, "$request['prior_period'] = (new DateTimeImmutable($request['period'].'-01'))->modify('-1 month')->format('Y-m');\n" + anchor)
with (E / 'capture-public-settlement-v3.php').open('x', encoding='utf-8') as stream:
    stream.write(source)
print('PASS: immediately prior competence explicit; first rejected capture preserved')
