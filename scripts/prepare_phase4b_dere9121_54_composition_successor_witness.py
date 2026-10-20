"""Create separate driver with a new synthetic witness and explicit exception failure."""
from pathlib import Path
ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-dere9121-54-preparation-20261013'
source = (E / 'exercise-planned-composition.php').read_text(encoding='utf-8')
anchor = "$stages = array_column($replay['trace'], null, 'stage');"
assert source.count(anchor) == 1
insert = '''
$closing = $rules['closing'];
$qualifiedPgcc = (new App\\Modules\\Fiscal\\ConsumptionReform\\DerePgccQualifier)->resolve($month['pgcc'],
    $closing['pgcc_catalogs']['reference'], $closing['pgcc_catalogs']['tax_codes'], $known, false, $closing['pgcc_qualification_policy']);
$proof = $month['debt_yield']['evidence'];
$proof['evidence_id'] .= '.D9121.54.EXPLICIT.ISS.SUCCESSOR';
$proof['value']['book_input_hash'] = App\\Modules\\Fiscal\\Core\\Shared\\CanonicalJson::hash($month['book']);
$proof['value']['pgcc_source_hash'] = $qualifiedPgcc['source_hash'];
unset($proof['source_hash']);
$proof['source_hash'] = App\\Modules\\Fiscal\\Core\\Shared\\CanonicalJson::hash($proof);
$month['debt_yield']['evidence'] = $proof;
'''
source = source.replace(anchor, anchor + insert)
source = source.replace('declare(strict_types=1);', 'declare(strict_types=1);\ntry {', 1)
source += '''
} catch (Throwable $exception) {
    fwrite(STDERR, json_encode(['status' => 'ERROR', 'error' => $exception->getMessage()], JSON_THROW_ON_ERROR));
    exit(1);
}
'''
with (E / 'exercise-planned-composition-v2.php').open('x', encoding='utf-8') as stream:
    stream.write(source)
print('PASS: successor synthetic D2101 witness prepared; initial failing driver preserved')
