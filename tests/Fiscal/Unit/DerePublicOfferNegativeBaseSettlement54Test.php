<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\DereFinancialPublicOfferNegativeBaseSettlementPlanner;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Tests\TestCase;

uses(TestCase::class);

/** @return array<string, mixed> */
function publicSettlement54(): array
{
    return json_decode((string) file_get_contents(storage_path('app/phase4b-dere9121-54-preparation-20261013/public-settlement-native-result.json')), true, 512, JSON_THROW_ON_ERROR)['input'];
}

/** @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function planPublicSettlement54(array $input): array
{
    return (new DereFinancialPublicOfferNegativeBaseSettlementPlanner)->plan($input['operation'], $input['month'], $input['gross'],
        new DateTimeImmutable($input['known_at']), $input['rules'], $input['request'], $input['policy'], resource_path('fiscal/sources/official'));
}

it('uses public return once and retains gross debits while proposing separate net IBS CBS taxes', function (): void {
    $input = publicSettlement54();
    $result = planPublicSettlement54($input);
    expect($result['status'])->toBe('LOCAL_BCN_TAX_SETTLEMENT_PROPOSED')
        ->and($result['composition']['calculation_input']['general_revenue'])->toBe('1000.00')
        ->and($result['composition']['calculation_input']['public_offer_debt_return'])->toBe('98.17')
        ->and($result['assessed_pools']['1010I']['positive_base'])->toBe('790.27')
        ->and($result['ibs_nominal_proposal'])->toBe('0.74')->and($result['cbs_nominal_proposal'])->toBe('7.02')
        ->and($result['original_gross_debits'])->toBe($input['gross'])
        ->and($result['return_knowledge_cutoff_qualified'])->toBeTrue()
        ->and($result['received_return_verified'])->toBeFalse()->and($result['production_ready'])->toBeFalse();
});

it('does not use public policy before publication or for partially covered competence', function (string $case): void {
    $input = publicSettlement54();
    if ($case === 'publication') {
        $input['known_at'] = (new DateTimeImmutable($input['known_at']))->modify('-1 second')->format(DATE_ATOM);
    } else {
        $input['policy']['valid_from'] = '2026-09-15';
        unset($input['policy']['source_hash']);
        $input['policy']['source_hash'] = CanonicalJson::hash($input['policy']);
    }
    expect(planPublicSettlement54($input)['reason'])->toBe('RTC_DERE_BCN_SETTLEMENT_POLICY_NOT_AVAILABLE');
})->with(['publication', 'competence']);

it('checks processing chronology and knowledge at seventh fractional digit', function (string $case): void {
    $input = publicSettlement54();
    $dom = new DOMDocument;
    $dom->loadXML($input['request']['current_public_offer_return']['xml']);
    $path = new DOMXPath($dom);
    $path->registerNamespace('d', 'http://www.dere.gov.br/schemas/evtRetornoTitPub/v0_0_2');
    $nodes = $path->query('//d:'.($case === 'reversed' ? 'dhRecepcao' : 'dhProcess'));
    $node = $nodes === false ? null : $nodes->item(0);
    if (! $node instanceof DOMElement) {
        throw new RuntimeException('Fixture processing timestamp missing.');
    }
    $known = new DateTimeImmutable($input['known_at']);
    $node->nodeValue = $case === 'fraction'
        ? $known->format('Y-m-d\TH:i:s').'.0000001+00:00'
        : $known->modify('+1 second')->format('Y-m-d\TH:i:s').'.0000000+00:00';
    $input['request']['current_public_offer_return']['xml'] = (string) $dom->saveXML();
    expect(planPublicSettlement54($input)['reason'])->toBe('RTC_DERE_BCN_SETTLEMENT_PUBLIC_RETURN_PROCESSING_NOT_KNOWN_OR_CHRONOLOGY_INVALID');
})->with(['future', 'fraction', 'reversed']);

it('refuses missing changed or successor public return bindings', function (string $case): void {
    $input = publicSettlement54();
    if ($case === 'missing') {
        unset($input['request']['current_public_offer_return']);
    } elseif ($case === 'sequence') {
        $input['request']['current_public_offer_return']['expected_sequence'] = '01';
    } elseif ($case === 'pgcc') {
        $input['request']['current_public_offer_return']['pgcc_source_hash'] = str_repeat('0', 64);
    } else {
        $input['request']['current_public_offer_return']['xml'] = str_replace('<vApurTot>98.17</vApurTot>', '<vApurTot>99.17</vApurTot>', $input['request']['current_public_offer_return']['xml']);
    }
    expect(planPublicSettlement54($input)['status'])->toBe('ACTION_REQUIRED');
})->with(['missing', 'sequence', 'pgcc', 'amount']);

it('keeps public gross down proposal without consuming stock when opted out', function (): void {
    $input = publicSettlement54();
    $input['request']['parameters'] = ['infoBCN' => [
        ['codBCNRaiz' => '1010I', 'usarBCNAcum' => '0'], ['codBCNRaiz' => '1010C', 'usarBCNAcum' => '0']], 'detBCNeg' => []];
    $result = planPublicSettlement54($input);
    expect($result['status'])->toBe('LOCAL_BCN_TAX_SETTLEMENT_PROPOSED')
        ->and($result['assessed_pools']['1010I']['deduction'])->toBe('0.00')
        ->and($result['ibs_nominal_proposal'])->toBe('0.79')->and($result['cbs_nominal_proposal'])->toBe('7.11');
});
