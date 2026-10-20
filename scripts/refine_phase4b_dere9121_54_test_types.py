"""Preserve initial tests and add checked DOM helpers rather than type suppressions."""
from pathlib import Path
import re
ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-dere9121-54-preparation-20261013'
target = ROOT / 'tests/Fiscal/Unit/DerePublicOfferDebtReturn54Test.php'
with (E / 'before-checked-dom-test-helpers.php').open('xb') as stream:
    stream.write(target.read_bytes())
source = target.read_text(encoding='utf-8')
source = source.replace('file_get_contents(', '(string) file_get_contents(')
source = source.replace("foreach (glob(storage_path('app/phase4b-variable-yield-20261006-27/*-monthly-report-final.json')) as $path)",
    "foreach (glob(storage_path('app/phase4b-variable-yield-20261006-27/*-monthly-report-final.json')) ?: [] as $path)")
source = re.sub(r'(\$[A-Za-z]+)->query\(([^\n]+?)\)->item\(0\)', r'dere9121Element54(\1, \2)', source)
source = source.replace('$sourceDom->documentElement->firstElementChild', 'dere9121DocumentEvent54($sourceDom)')
source = re.sub(r'= (\$[A-Za-z]+)->saveXML\(\);', r'= (string) \1->saveXML();', source)
source = source.replace('$node->parentNode->removeChild($node);', "($node->parentNode ?? throw new RuntimeException('Fixture node has no parent.'))->removeChild($node);")
helpers = '''
function dere9121Element54(DOMXPath $xpath, string $path): DOMElement
{
    $nodes = $xpath->query($path);
    $element = $nodes === false ? null : $nodes->item(0);
    if (! $element instanceof DOMElement) {
        throw new RuntimeException('Fixture element missing: '.$path);
    }
    return $element;
}

function dere9121DocumentEvent54(DOMDocument $document): DOMElement
{
    $event = $document->documentElement?->firstElementChild;
    if (! $event instanceof DOMElement) {
        throw new RuntimeException('Fixture event missing.');
    }
    return $event;
}

'''
source = source.replace('/** @return array<string, mixed> */', helpers + '/** @return array<string, mixed> */', 1)
target.write_text(source, encoding='utf-8')
print('PASS: test reads and DOM accesses have explicit checked types; initial source preserved')
