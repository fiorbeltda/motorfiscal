"""Extend preserved command53 scenarios to public55 selection and return failures."""
from pathlib import Path
ROOT = Path(__file__).resolve().parents[1]
source = (ROOT / 'tests/Fiscal/Integration/DereNegativeBaseMonthlyCommand53Test.php').read_text(encoding='utf-8')
source = source.replace('coordinates settlement and declaration through the native monthly command without output on invalid requests',
    'selects public D9121 settlement and rejects inconsistent requests before writing six monthly XMLs')
source = source.replace('phase4b-negative-base53-command-integration-20261009', 'phase4b-dere9121-55-command-integration-20261013')
source = source.replace("$options = json_decode((string) file_get_contents($e.'command-options-request.json'), true, 512, JSON_THROW_ON_ERROR);",
    "$options = $request;\n    unset($options['current_public_offer_return']);")
source = source.replace('phase4b-negative-base52-settlement-draft-20261009/command-financial-snapshot.json',
    'phase4b-dere9121-54-preparation-20261013/financial-snapshot.json')
source = source.replace('2026.10.09-28-draft52.json', '2026.10.13-28-draft54.json')
source = source.replace('bcn53-', 'd9121-55-')
anchor = "        } elseif ($case === 'unsupported-bundle') {"
assert source.count(anchor) == 2
new = """        } elseif ($case === 'future-return') {
            $request['current_public_offer_return']['xml'] = str_replace('2026-10-13T10:00:01.0000000+00:00', '2099-10-13T10:00:01.0000000+00:00', $request['current_public_offer_return']['xml']);
        } elseif ($case === 'return-pgcc') {
            $request['current_public_offer_return']['pgcc_source_hash'] = str_repeat('0', 64);
        } elseif ($case === 'return-sequence') {
            $request['current_public_offer_return']['expected_sequence'] = '01';
        } elseif ($case === 'return-total') {
            $request['current_public_offer_return']['xml'] = str_replace('<vApurTot>98.17</vApurTot>', '<vApurTot>99.17</vApurTot>', $request['current_public_offer_return']['xml']);
        } elseif ($case === 'missing-return-xml') {
            unset($request['current_public_offer_return']['xml']);
        } elseif ($case === 'missing-return') {
            unset($request['current_public_offer_return']);
        } elseif ($case === 'unsupported-bundle') {"""
source = source.replace(anchor, new, 1)
source = source.replace("->toBe(5)", "->toBe(6)")
source = source.replace("->toBe('0.84')", "->toBe('0.74')").replace("->toBe('7.93')", "->toBe('7.02')")
source = source.replace("$e.'before-options-report.json'", "$e.'monthly-report.json'")
source = source.replace("['D1101', 'D1121', 'D1011', 'D1001']", "['D1101', 'D1121', 'D1011', 'D1001', 'D2101']")
source = source.replace("$candidate.'.D1001.xml']", "$candidate.'.D1001.xml', $candidate.'.D2101.xml']")
source = source.replace("'events-mismatch', 'unsupported-bundle']);", "'events-mismatch', 'unsupported-bundle', 'future-return', 'return-pgcc', 'return-sequence', 'return-total', 'missing-return-xml', 'missing-return']);")
target = ROOT / 'tests/Fiscal/Integration/DerePublicOfferMonthlyCommand55Test.php'
with target.open('x', encoding='utf-8') as stream:
    stream.write(source)
print('PASS:19 specific public-command scenarios prepared; old tests unchanged')
