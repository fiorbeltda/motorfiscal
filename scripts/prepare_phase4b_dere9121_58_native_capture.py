"""Promote tested prototype capture driver to actual policy-selected implementation."""
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'storage/app/phase4b-dere9121-58-no-movement-integration-20261013'
source=(ROOT/'storage/app/phase4b-dere9121-57-no-movement-preparation-20261013/capture-no-movement-settlement.php').read_text(encoding='utf-8')
for name in ['planned-no-movement-reader.php','planned-no-movement-composition.php','planned-no-movement-settlement.php']:
    anchor="require __DIR__+'PLACEHOLDER';".replace("__DIR__+'PLACEHOLDER'","__DIR__+'/"+name+"'")
    anchor="require __DIR__.'/"+name+"';"
    assert source.count(anchor)==1
    source=source.replace(anchor+'\n','')
source=source.replace('DereFinancialPublicOfferNoMovementSettlementPrototype57','DereFinancialPublicOfferNegativeBaseSettlementPlanner')
source=source.replace('PASS: prototype57 native no-movement','PASS: installed58 native no-movement')
with (E/'capture-no-movement-settlement.php').open('x',encoding='utf-8') as stream:
    stream.write(source)
print('PASS:installed58 native capture driver prepared; prototypes remain preserved')
