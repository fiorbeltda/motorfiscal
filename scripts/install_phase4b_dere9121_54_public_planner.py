"""Install public composition and separate policy-selected settlement without changing accepted52/53 files."""
from pathlib import Path
import hashlib
import json
ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-dere9121-54-preparation-20261013'
APP = ROOT / 'app/Modules/Fiscal/ConsumptionReform'

def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()

composition_source = E / 'planned-DereFinancialPublicOfferCurrentBaseCompositionQualifier.php'
composition = composition_source.read_text(encoding='utf-8').replace(
    'DERE-FINANCIAL-PUBLIC-OFFER-COMPOSITION-PROTOTYPE-54', 'DERE-FINANCIAL-PUBLIC-OFFER-COMPOSITION-LOCAL-1.3.0-54')
composition_path = APP / 'DereFinancialPublicOfferCurrentBaseCompositionQualifier.php'
assert not composition_path.exists()
with composition_path.open('x', encoding='utf-8') as stream:
    stream.write(composition)
predecessor = APP / 'DereFinancialNegativeBaseSettlementPlanner.php'
source = predecessor.read_text(encoding='utf-8')
source = source.replace('final readonly class DereFinancialNegativeBaseSettlementPlanner',
    'final readonly class DereFinancialPublicOfferNegativeBaseSettlementPlanner')
source = source.replace('DERE-BCN-LOCAL-SETTLEMENT-1.3.0-52', 'DERE-PUBLIC-OFFER-BCN-LOCAL-SETTLEMENT-1.3.0-54')
anchor = "        $composition = (new DereFinancialCurrentBaseCompositionQualifier)->qualify($operation, $month, $grossTaxes, $knownAt,\n            $rules, $sources.'/dere-observed-20261005-annex-i.pdf');"
assert source.count(anchor) == 1
replacement = '''        $returnBinding = $request['current_public_offer_return'] ?? null;
        if (($policy['composition'] ?? null) !== 'INITIAL_SYNTHETIC_D2101_D9121_CODBC1010'
            || ($policy['return_processing_known_at_required'] ?? null) !== true
            || ! is_array($returnBinding) || ! is_string($returnBinding['xml'] ?? null)
            || ! is_file($sources.'/dere-observed-20261005-events.pdf')
            || ($policy['events_sha256'] ?? null) !== hash_file('sha256', $sources.'/dere-observed-20261005-events.pdf')) {
            return $this->stop('PUBLIC_RETURN_POLICY_OR_SOURCE_UNPROVEN');
        }
        if (! $this->returnKnownBy($returnBinding['xml'], $knownAt)) {
            return $this->stop('PUBLIC_RETURN_PROCESSING_NOT_KNOWN_OR_CHRONOLOGY_INVALID');
        }
        $composition = (new DereFinancialPublicOfferCurrentBaseCompositionQualifier)->qualify($operation, $month, $grossTaxes, $knownAt,
            $rules, $sources.'/dere-observed-20261005-annex-i.pdf', $returnBinding, $sources);'''
source = source.replace(anchor, replacement)
source = source.replace("'received_return_verified' => false, 'expiry_boundary_qualified' => false,",
    "'return_knowledge_cutoff_qualified' => true, 'received_return_verified' => false, 'expiry_boundary_qualified' => false,")
anchor = '    /** @return array<string, mixed> */\n    private function stop'
assert source.count(anchor) == 1
method = '''    private function returnKnownBy(string $xml, DateTimeImmutable $knownAt): bool
    {
        if (preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)) {
            return false;
        }
        $document = new \\DOMDocument;
        $document->resolveExternals = false;
        $document->substituteEntities = false;
        if (! @$document->loadXML($xml, LIBXML_NONET)) {
            return false;
        }
        $path = new \\DOMXPath($document);
        $path->registerNamespace('d', 'http://www.dere.gov.br/schemas/evtRetornoTitPub/v0_0_2');
        $times = [];
        foreach (['dhRecepcao', 'dhProcess'] as $tag) {
            $value = $path->evaluate('string(/d:DeRE/d:evtRetornoTitPub/d:infoRecEv/d:'.$tag.')');
            if (! is_string($value) || ! preg_match('/^(20[0-9]{2}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2})\\.([0-9]{7})([+-][0-9]{2}:[0-9]{2})$/', $value, $matches)) {
                return false;
            }
            $time = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i:sP', $matches[1].$matches[3]);
            if ($time === false) {
                return false;
            }
            $times[] = [$time->getTimestamp(), (int) $matches[2]];
        }
        $known = [$knownAt->getTimestamp(), (int) ($knownAt->format('u').'0')];

        return $times[0] <= $times[1] && $times[1] <= $known;
    }

'''
source = source.replace(anchor, method + anchor)
planner_path = APP / 'DereFinancialPublicOfferNegativeBaseSettlementPlanner.php'
assert not planner_path.exists()
with planner_path.open('x', encoding='utf-8') as stream:
    stream.write(source)
with (E / 'public-planner-source-installation.json').open('x', encoding='utf-8') as stream:
    json.dump({'status': 'PUBLIC_POLICY_PLANNER_INSTALLED_TESTS_AND_COMMAND_PENDING',
        'predecessor52_source_sha256': sha(predecessor), 'composition_prototype_sha256': sha(composition_source),
        'composition_installed_sha256': sha(composition_path), 'planner_installed_sha256': sha(planner_path),
        'native_monthly_command_integrated': False, 'production_ready': False}, stream, indent=2)
print('PASS: separate public composition/planner installed, processing cutoff guards; accepted52/53 unchanged')
