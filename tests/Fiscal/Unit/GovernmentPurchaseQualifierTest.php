<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\ConsumptionReformEngine;
use App\Modules\Fiscal\ConsumptionReform\GovernmentPurchaseQualifier;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\ConsumptionReform\TransitionCatalog;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

/** @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function governmentResult(string $sourceName, array $overrides = []): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge($root, 'bundles/consumption-phase4b-2026.09.28-12.json');
    $source = json_decode((string) file_get_contents($root.'/scenarios/consumption-phase4b-2026.09.28-12/'.$sourceName.'.json'),
        true, 512, JSON_THROW_ON_ERROR);
    foreach ($overrides as $key => $value) {
        if ($value === null) {
            unset($source['facts'][$key]);
        } else {
            $source['facts'][$key] = $value;
        }
    }
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($source);
    $documents = $knowledge['documents'];
    $engine = new ConsumptionReformEngine(new TemporalRateCatalog($documents['rtc_rate_catalog']),
        $documents['rtc_code_catalog'], new TransitionCatalog($documents['rtc_transition_catalog']));

    return $engine->evaluate($scenario->input->context->facts,
        $scenario->input->context->competenceDate, $scenario->input->context->knowledgeAt);
}

it('keeps government purchases in ordinary treatment in 2026', function (): void {
    $result = governmentResult('government-ordinary-2026');
    $treatment = array_values(array_filter($result['trace'],
        static fn (array $row): bool => ($row['stage'] ?? null) === 'GOVERNMENT_PURCHASE'))[0]['treatment'];

    expect($result['status'])->toBe('VALID')
        ->and($treatment['article_472_reductor'])->toBe('NOT_APPLICABLE')
        ->and($treatment['article_473_destination'])->toBe('NOT_APPLICABLE');
});

it('qualifies year-specific public revenue destination without inventing a reductor', function (): void {
    foreach (['government-reductor-2027' => '0', 'government-cbs-share-2029' => '10',
        'government-both-full-2033' => '100'] as $name => $cbsShare) {
        $result = governmentResult($name);
        $treatment = array_values(array_filter($result['trace'],
            static fn (array $row): bool => ($row['stage'] ?? null) === 'GOVERNMENT_PURCHASE'))[0]['treatment'];

        expect($result['status'])->toBe('ACTION_REQUIRED')
            ->and($result['reason_codes'])->toContain('RTC_GOVERNMENT_REDUCTOR_NOT_PROVEN')
            ->and($treatment['ibs_destination_share_percent'])->toBe('100')
            ->and($treatment['cbs_destination_share_percent'])->toBe($cbsShare);
    }
});

it('separates the in-person licitation exception and asks for its proof', function (): void {
    $exception = governmentResult('government-exception-in-person-waived-2027');
    $treatment = array_values(array_filter($exception['trace'],
        static fn (array $row): bool => ($row['stage'] ?? null) === 'GOVERNMENT_PURCHASE'))[0]['treatment'];
    expect($treatment['article_472_reductor'])->toBe('NOT_APPLICABLE')
        ->and($treatment['article_473_destination'])->toBe('NOT_APPLICABLE')
        ->and($exception['reason_codes'])->toContain('RTC_RATE_NOT_PROVEN:CBS');

    $missing = governmentResult('government-exception-in-person-waived-2027',
        ['rtc.government_procurement_evidence_id' => null]);
    expect($missing['status'])->toBe('ACTION_REQUIRED')
        ->and($missing['missing_facts'])->toContain('rtc.government_procurement_evidence_id');
});

it('rejects an unsupported uniform-rate assertion for SaaS and requires payment evidence', function (): void {
    $conflict = governmentResult('government-uniform-rate-exclusion-2029');
    $missing = governmentResult('government-payment-evidence-missing-2027');

    expect($conflict['status'])->toBe('INVALID')
        ->and($conflict['reason_codes'])->toContain('RTC_GOVERNMENT_UNIFORM_RATE_CLASSIFICATION_CONFLICT')
        ->and($missing['status'])->toBe('ACTION_REQUIRED')
        ->and($missing['missing_facts'])->toContain('rtc.government_payment_date', 'rtc.government_payment_evidence_id');
});

it('qualifies 2030 to 2032 CBS shares in the pure component', function (): void {
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge($root, 'bundles/consumption-phase4b-2026.09.28-12.json');
    $source = json_decode((string) file_get_contents($root.'/scenarios/consumption-phase4b-2026.09.28-12/government-cbs-share-2029.json'),
        true, 512, JSON_THROW_ON_ERROR);
    $facts = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($source)->input->context->facts;

    foreach (['2030-04-10' => '20', '2031-04-10' => '30', '2032-04-10' => '40'] as $date => $share) {
        $decision = (new GovernmentPurchaseQualifier)->resolve($facts, $date);
        expect($decision['status'])->toBe('RESOLVED')
            ->and($decision['treatment']['cbs_destination_share_percent'])->toBe($share);
    }
});
