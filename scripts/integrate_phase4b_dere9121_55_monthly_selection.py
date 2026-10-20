"""Archive accepted53 CLI and select public54 policy only for explicit D9121 request."""
from pathlib import Path
import hashlib
import json
ROOT = Path(__file__).resolve().parents[1]
P = ROOT / 'storage/app/phase4b-dere9121-54-preparation-20261013'
E = ROOT / 'storage/app/phase4b-dere9121-55-command-integration-20261013'
E.mkdir(exist_ok=True)
target = ROOT / 'app/Console/Commands/ProjectDereMonthlyClosingXmlCommand.php'

def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()

accepted = json.loads((ROOT / 'storage/app/phase4b-negative-base53-command-integration-20261009/command-development-validation.json').read_text(encoding='utf-8'))
item = next(i for i in accepted['implementation'] if i['path'] == target.relative_to(ROOT).as_posix())
assert sha(target) == item['sha256']
archive = E / 'before-public-selection-monthly-command.php'
with archive.open('xb') as stream:
    stream.write(target.read_bytes())
source = target.read_text(encoding='utf-8')
anchor = 'use App\\Modules\\Fiscal\\ConsumptionReform\\DereFinancialNegativeBaseSettlementPlanner;'
assert source.count(anchor) == 1
source = source.replace(anchor, anchor + '\nuse App\\Modules\\Fiscal\\ConsumptionReform\\DereFinancialPublicOfferNegativeBaseSettlementPlanner;')
old = "                $settlementPolicy = $knowledge['documents']['rtc_code_catalog']['dere_negative_base_local_settlement'] ?? null;"
new = """                $settlementRequest = json_decode((string) file_get_contents($settlementRequestPath), true, 512, JSON_THROW_ON_ERROR);
                $publicOffer = is_array($settlementRequest) && array_key_exists('current_public_offer_return', $settlementRequest);
                $settlementPolicyKey = $publicOffer ? 'dere_public_offer_negative_base_local_settlement' : 'dere_negative_base_local_settlement';
                $settlementPolicy = $knowledge['documents']['rtc_code_catalog'][$settlementPolicyKey] ?? null;"""
assert source.count(old) == 1
source = source.replace(old, new)
duplicate = "                $settlementRequest = json_decode((string) file_get_contents($settlementRequestPath), true, 512, JSON_THROW_ON_ERROR);"
assert source.count(duplicate) == 2
first = source.find(duplicate)
second = source.find(duplicate, first + len(duplicate))
source = source[:second] + source[second:].replace(duplicate + '\n', '', 1)
anchor = '                $settlement = (new DereFinancialNegativeBaseSettlementPlanner)->plan('
assert source.count(anchor) == 1
source = source.replace(anchor, "                $settlementPlanner = $publicOffer ? new DereFinancialPublicOfferNegativeBaseSettlementPlanner : new DereFinancialNegativeBaseSettlementPlanner;\n                $settlement = $settlementPlanner->plan(")
target.write_text(source, encoding='utf-8')
capture = json.loads((P / 'public-settlement-native-result.json').read_text(encoding='utf-8'))
for name, value in [('command-month-input.json', capture['input']['month']), ('command-settlement-request.json', capture['input']['request'])]:
    with (E / name).open('x', encoding='utf-8') as stream:
        json.dump(value, stream, ensure_ascii=False, indent=2)
with (E / 'source-selection-preparation.json').open('x', encoding='utf-8') as stream:
    json.dump({'status': 'PUBLIC54_SELECTION_IN_MONTHLY_COMMAND_APPLIED_VALIDATION_PENDING',
        'accepted53_source_sha256': sha(archive), 'applied_source_sha256': sha(target),
        'public_policy': 'dere_public_offer_negative_base_local_settlement',
        'old52_policy_selection_preserved': True, 'full_suite_after_selection_passed': False,
        'production_ready': False}, stream, indent=2)
print('PASS: public54 selection applied with accepted53 CLI archive; native command validation pending')
