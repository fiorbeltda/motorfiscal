"""Prepare prototype capture over own54 financial replay and preserved21 no-movement months."""
from pathlib import Path
ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-dere9121-57-no-movement-preparation-20261013'
P = ROOT / 'storage/app/phase4b-dere9121-54-preparation-20261013'
source = (P / 'capture-public-settlement-v3.php').read_text(encoding='utf-8')
anchor = "$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();"
assert source.count(anchor) == 1
source = source.replace(anchor, anchor + "\nrequire __DIR__.'/planned-no-movement-reader.php';\nrequire __DIR__.'/planned-no-movement-composition.php';\nrequire __DIR__.'/planned-no-movement-settlement.php';\n$name=$argv[2]??'';\nif (!in_array($name,['qualified-direct-only','qualified-fund-only','qualified-both-codes'],true)) throw new RuntimeException('Unsupported prototype profile');")
source = source.replace("resource_path('fiscal/scenarios/dere-variable-yield-2026.10.06-27/constant.json')",
    "resource_path('fiscal/scenarios/dere-d2101-no-movement-2026.10.06-21/'.$name.'.json')")
source = source.replace("$month['debt_yield']", "$month['debt_no_movement']")
source = source.replace("$projection['debt_yield_projection']['xml']", "$projection['debt_no_movement_projection']['xml']")
source = source.replace("$projection['debt_yield_projection']['total_vApur']", "'0.00'")
source = source.replace("__DIR__.'/synthetic-d9121-v2.xml'", "storage_path('app/phase4b-dere9121-54-preparation-20261013/synthetic-d9121-v2.xml')")
source = source.replace('new App\\Modules\\Fiscal\\ConsumptionReform\\DereFinancialPublicOfferNegativeBaseSettlementPlanner',
    'new App\\Modules\\Fiscal\\ConsumptionReform\\DereFinancialPublicOfferNoMovementSettlementPrototype57')
source = source.replace("$path = __DIR__.'/public-settlement-native-result.json';", "$path = __DIR__.'/'.$name.'-no-movement-settlement.json';")
source = source.replace('PASS: own54 public D9121 -> current composition -> BCN -> IBS/CBS proposal;',
    'PASS: prototype57 native no-movement D2101 -> explicit zero D9121 -> composition -> BCN;')
with (E / 'capture-no-movement-settlement.php').open('x', encoding='utf-8') as stream:
    stream.write(source)
print('PASS: own replay, native empty-book proof, explicit return and prototype57 composition driver prepared')
