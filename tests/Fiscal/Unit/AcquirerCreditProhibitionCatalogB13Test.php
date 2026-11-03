<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\AcquirerCreditProhibitionCatalogB13;

/** Normalized text of the preserved official Planalto snapshot (independent of the class). */
function b13LawText(): string
{
    static $text = null;
    if ($text === null) {
        $path = dirname(__DIR__, 3).'/resources/fiscal/sources/official/'.AcquirerCreditProhibitionCatalogB13::SOURCE_FILE;
        $raw = (string) file_get_contents($path);
        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }
        $text = (string) preg_replace('/\s+/u', ' ', html_entity_decode((string) preg_replace('/<[^>]+>/', ' ', $raw), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    return $text;
}

it('binds every prohibition to a unique, current literal excerpt of the preserved official law', function (): void {
    $path = dirname(__DIR__, 3).'/resources/fiscal/sources/official/'.AcquirerCreditProhibitionCatalogB13::SOURCE_FILE;
    expect(hash_file('sha256', $path))->toBe(AcquirerCreditProhibitionCatalogB13::SOURCE_SHA256);
    $excerpts = array_map(fn (array $e): string => $e[2], AcquirerCreditProhibitionCatalogB13::ENTRIES);
    $excerpts['HEALTH_PLAN_EXCEPTION'] = AcquirerCreditProhibitionCatalogB13::HEALTH_PLAN_EXCEPTION[1];
    $excerpts['HEALTH_PLAN_EXCEPTION_ART57'] = AcquirerCreditProhibitionCatalogB13::HEALTH_PLAN_EXCEPTION[2];
    $text = b13LawText();
    foreach ($excerpts as $key => $excerpt) {
        expect(substr_count($text, $excerpt))->toBe(1, $key);
        $after = substr($text, strpos($text, $excerpt) + strlen($excerpt), 2500);
        $head = substr($excerpt, 0, 45);
        $later = strpos($after, $head);
        expect($later === false || ! str_contains(substr($after, $later, 900), 'Redação dada'))->toBeTrue("superseded: {$key}");
    }
    expect($excerpts)->toHaveCount(16);
});

it('prohibits the acquirer credit for every statutory regime and never grants credit', function (string $regime, string $basis): void {
    $operation = ['acquired_regime' => $regime, 'role' => $regime === 'URBAN_TRANSPORT_SUPPLIER_INPUT' ? 'TRANSPORT_SUPPLIER' : 'ACQUIRER',
        'fuel_destination' => 'RESALE'];
    $result = (new AcquirerCreditProhibitionCatalogB13)->qualify($operation);
    expect($result['status'])->toBe('CREDIT_PROHIBITED')->and($result['legal_basis'])->toBe($basis)
        ->and($result['excerpt_sha256'])->toBe(hash('sha256', AcquirerCreditProhibitionCatalogB13::ENTRIES[$regime][2]))
        ->and($result['credit_granted'])->toBeFalse();
})->with(fn (): array => array_map(fn (array $e, string $k): array => [$k, $e[0]],
    AcquirerCreditProhibitionCatalogB13::ENTRIES, array_keys(AcquirerCreditProhibitionCatalogB13::ENTRIES)));

it('applies only the literal exceptions, with evidence', function (array $operation, string $status, string $reason): void {
    $result = (new AcquirerCreditProhibitionCatalogB13)->qualify($operation);
    expect($result['status'])->toBe($status)->and($result['reason'])->toBe($reason)->and($result['credit_granted'])->toBeFalse();
})->with([
    'employee health plan, regular acquirer' => [['acquired_regime' => 'HEALTH_PLAN', 'employee_collective_agreement_plan' => true, 'acquirer_regime' => 'REGULAR', 'exception_evidence_id' => 'CCT-2026'], 'CREDIT_LIMITED_TO_SUPPLIER_EXTINGUISHED_DEBITS', 'B13_EMPLOYEE_HEALTH_PLAN_EXCEPTION'],
    'employee health plan, Simples acquirer' => [['acquired_regime' => 'HEALTH_PLAN', 'employee_collective_agreement_plan' => true, 'acquirer_regime' => 'SIMPLES', 'exception_evidence_id' => 'CCT-2026'], 'ACTION_REQUIRED', 'B13_HEALTH_PLAN_EXCEPTION_REQUIRES_REGULAR_ACQUIRER_AND_AGREEMENT_EVIDENCE'],
    'employee health plan without evidence' => [['acquired_regime' => 'HEALTH_PLAN', 'employee_collective_agreement_plan' => true, 'acquirer_regime' => 'REGULAR'], 'ACTION_REQUIRED', 'B13_HEALTH_PLAN_EXCEPTION_REQUIRES_REGULAR_ACQUIRER_AND_AGREEMENT_EVIDENCE'],
    'individual health plan' => [['acquired_regime' => 'HEALTH_PLAN'], 'CREDIT_PROHIBITED', 'B13_STATUTORY_CREDIT_PROHIBITION'],
    'pet health plan has no exception' => [['acquired_regime' => 'PET_HEALTH_PLAN', 'employee_collective_agreement_plan' => true, 'acquirer_regime' => 'REGULAR', 'exception_evidence_id' => 'X'], 'CREDIT_PROHIBITED', 'B13_STATUTORY_CREDIT_PROHIBITION'],
    'SAF sports rights' => [['acquired_regime' => 'SAF_SUPPLY', 'saf_sports_rights_acquisition' => true, 'exception_evidence_id' => 'CONTRATO-ATLETA'], 'NOT_PROHIBITED_BY_STATUTORY_CATALOGUE', 'B13_SAF_SPORTS_RIGHTS_EXCEPTION'],
    'SAF sports rights without evidence' => [['acquired_regime' => 'SAF_SUPPLY', 'saf_sports_rights_acquisition' => true], 'ACTION_REQUIRED', 'B13_EXCEPTION_EVIDENCE_REQUIRED'],
    'financial service expressly permitted' => [['acquired_regime' => 'FINANCIAL_SERVICE', 'expressly_permitted_article' => '196', 'exception_evidence_id' => 'CONTRATO-1'], 'NOT_PROHIBITED_BY_STATUTORY_CATALOGUE', 'B13_FINANCIAL_SERVICE_EXPRESSLY_PERMITTED_ARTS_194_198'],
    'financial service, article outside 194-198' => [['acquired_regime' => 'FINANCIAL_SERVICE', 'expressly_permitted_article' => '199', 'exception_evidence_id' => 'X'], 'ACTION_REQUIRED', 'B13_FINANCIAL_PERMISSION_ARTICLE_AND_EVIDENCE_REQUIRED'],
    'fuel for own use' => [['acquired_regime' => 'MONOPHASIC_FUEL_FOR_RESALE', 'fuel_destination' => 'OWN_USE'], 'NOT_PROHIBITED_BY_STATUTORY_CATALOGUE', 'B13_FUEL_NOT_FOR_RESALE_SEE_ART180_PARAGRAPHS'],
    'fuel destination missing' => [['acquired_regime' => 'MONOPHASIC_FUEL_FOR_RESALE'], 'ACTION_REQUIRED', 'B13_FUEL_DESTINATION_REQUIRED'],
    'transport supplier rule does not hit acquirer' => [['acquired_regime' => 'URBAN_TRANSPORT_SUPPLIER_INPUT', 'role' => 'ACQUIRER'], 'NOT_PROHIBITED_BY_STATUTORY_CATALOGUE', 'B13_PROHIBITION_SCOPE_DOES_NOT_MATCH_ROLE'],
    'general operation' => [['acquired_regime' => 'GENERAL'], 'NOT_PROHIBITED_BY_STATUTORY_CATALOGUE', 'B13_NO_STATUTORY_PROHIBITION_IN_CATALOGUE'],
    'unknown regime' => [['acquired_regime' => 'LUXURY_CARS'], 'INVALID', 'B13_REGIME_NOT_IN_CATALOGUE'],
    'missing regime' => [[], 'INVALID', 'B13_REGIME_NOT_IN_CATALOGUE'],
]);

it('is deterministic', function (): void {
    $op = ['acquired_regime' => 'BAR_RESTAURANT_FOOD'];
    expect((new AcquirerCreditProhibitionCatalogB13)->qualify($op))->toBe((new AcquirerCreditProhibitionCatalogB13)->qualify($op));
});
