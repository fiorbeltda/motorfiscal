<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\FederalWithholding;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Shared\DecimalValue;

final readonly class PublicAdministrationRuleSet
{
    public function __construct(
        private PublicAnnexCatalog $catalog,
        private PublicPaymentBaseResolver $baseResolver = new PublicPaymentBaseResolver,
    ) {}

    /** @return array{irrf: FederalWithholdingDecision, pcc: PccWithholdingResult} */
    public function evaluate(FactBag $facts): array
    {
        if ($facts->value('payment.occurred') !== true) {
            return $this->unresolved(FederalWithholdingStatus::PENDING_TRIGGER, 'PUBLIC_PAYMENT_NOT_OCCURRED');
        }
        $missing = $facts->missing(['payment.date', 'payer.person_type', 'payer.public_entity_type', 'provider.person_type', 'provider.tax_regime', 'public.annex_category']);
        if ($missing !== []) {
            return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_PROFILE_FACTS_MISSING', $missing);
        }
        $date = (string) $facts->value('payment.date');
        $payer = (string) $facts->value('payer.person_type');
        $entity = (string) $facts->value('payer.public_entity_type');
        $federal = $payer === 'FEDERAL_PUBLIC_ADMINISTRATION';
        $subnational = in_array($payer, ['STATE_PUBLIC_ADMINISTRATION', 'DISTRICT_PUBLIC_ADMINISTRATION', 'MUNICIPAL_PUBLIC_ADMINISTRATION'], true);
        if (! $federal && ! $subnational) {
            return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_PAYER_SCOPE_CONFLICT');
        }
        if ($subnational && in_array($entity, ['PUBLIC_COMPANY', 'MIXED_CAPITAL_COMPANY', 'OTHER'], true)) {
            return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_SUBNATIONAL_ENTERPRISE_REQUIRES_PRIVATE_PROFILE');
        }
        if ($subnational && $entity === 'PUBLIC_FOUNDATION') {
            if (! $facts->hasDefinition('public.foundation_autarchic_nature')) {
                return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_SUBNATIONAL_FOUNDATION_ARTICLE2A_QUALIFICATION_REQUIRED');
            }
            $missing = $facts->missing(['public.foundation_autarchic_nature', 'public.foundation_qualification_source_id']);
            if ($missing !== []) {
                return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_FOUNDATION_QUALIFICATION_FACTS_MISSING', $missing);
            }
            if ($facts->value('public.foundation_autarchic_nature') !== true) {
                $missing = $facts->missing(['public.foundation_maintainer_public_revenue_share']);
                if ($missing !== []) {
                    return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_FOUNDATION_REVENUE_SHARE_MISSING', $missing);
                }
                $share = DecimalValue::fromString((string) $facts->value('public.foundation_maintainer_public_revenue_share'));
                if ($share->isNegative() || $share->compareTo(DecimalValue::fromString('100')) > 0) {
                    return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_FOUNDATION_REVENUE_SHARE_INVALID');
                }
                if ($share->compareTo(DecimalValue::fromString('50')) <= 0) {
                    return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_FOUNDATION_OUTSIDE_ARTICLE2A_SCOPE');
                }
            }
        }
        if ($federal && $entity === 'OTHER') {
            return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_FEDERAL_ENTITY_QUALIFICATION_REQUIRED');
        }
        $providerCooperative = $facts->value('provider.person_type') === 'COOPERATIVE';
        if (! $providerCooperative && $facts->value('provider.person_type') !== 'PRIVATE_LEGAL_ENTITY') {
            return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_BENEFICIARY_SPECIAL_PROFILE_REQUIRED');
        }
        if ($providerCooperative) {
            if (! $facts->hasDefinition('cooperative.act_type')) {
                return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_COOPERATIVE_SPECIAL_PROFILE_REQUIRED');
            }
            $missing = $facts->missing(['cooperative.provider_type', 'cooperative.act_type', 'cooperative.act_legal_basis']);
            if ($missing !== []) {
                return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_COOPERATIVE_CLASSIFICATION_MISSING', $missing);
            }
            if (in_array($facts->value('cooperative.provider_type'), ['WORK', 'MEDICAL'], true)) {
                return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_COOPERATIVE_COMPONENT_PROFILE_REQUIRED');
            }
        }
        if ($date >= '2027-01-01') {
            return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_LEGACY_PIS_COFINS_REVALIDATION_REQUIRED');
        }
        $category = (string) $facts->value('public.annex_category');
        if ($category === 'UNKNOWN') {
            return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_PAYMENT_ACTIVITY_SPLIT_REQUIRED', ['public.annex_category']);
        }
        $row = $category === 'MIXED' ? null : $this->catalog->row($category);
        if ($category !== 'MIXED' && $row === null) {
            return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_ANNEX_CATEGORY_NOT_FOUND', ['public.annex_category']);
        }
        if ($category === 'GOODS_MATERIALS_HOSPITAL_CARGO') {
            $missing = $facts->missing(['public.annex_basis_type']);
            if ($missing !== []) {
                return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_ANNEX_BASIS_TYPE_MISSING', $missing);
            }
        }
        if ($category === 'GOODS_MATERIALS_HOSPITAL_CARGO' && $facts->value('public.annex_basis_type') === 'QUALIFIED_MATERIAL_SERVICE') {
            $missing = $facts->missing(['public.materials_contract_qualified', 'public.materials_invoice_qualified']);
            if ($missing !== []) {
                return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_MATERIALS_EVIDENCE_MISSING', $missing);
            }
            if ($facts->value('public.materials_contract_qualified') !== true || $facts->value('public.materials_invoice_qualified') !== true) {
                return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_MATERIALS_CLASSIFICATION_UNPROVEN');
            }
        }
        $base = $this->baseResolver->resolve($facts);
        if ($base['base'] === null) {
            return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, (string) $base['reason'], $base['missing']);
        }
        $gross = $base['base'];
        /** @var list<array{line_id:string, document_id:?string, category:string, basis:DecimalValue, row:array{category:string, ir:string, csll:string, cofins:string, pis:string, total:string, revenue_code:string, locator:string}}> $lines */
        $lines = [];
        if ($category === 'MIXED') {
            $missing = $facts->missing(['public.payment_lines']);
            if ($missing !== []) {
                return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_PAYMENT_LINES_MISSING', $missing);
            }
            $rawLines = $facts->value('public.payment_lines');
            if (! is_array($rawLines) || $rawLines === []) {
                return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_PAYMENT_LINES_EMPTY');
            }
            $lineTotal = DecimalValue::fromString('0');
            $seen = [];
            foreach ($rawLines as $line) {
                if (! is_array($line) || ! is_string($line['line_id'] ?? null)
                    || $line['line_id'] === '' || isset($seen[$line['line_id']])
                    || ! is_string($line['document_id'] ?? null) || $line['document_id'] === ''
                    || ! is_string($line['category'] ?? null) || ! is_string($line['amount'] ?? null)
                    || preg_match('/^\d+(?:\.\d+)?$/', $line['amount']) !== 1) {
                    return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_PAYMENT_LINE_INVALID');
                }
                $lineRow = $this->catalog->row($line['category']);
                if ($lineRow === null) {
                    return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_PAYMENT_LINE_CATEGORY_UNKNOWN');
                }
                if ($line['category'] === 'GOODS_MATERIALS_HOSPITAL_CARGO') {
                    if (! in_array($line['annex_basis_type'] ?? null, ['GOODS', 'QUALIFIED_MATERIAL_SERVICE', 'HOSPITAL', 'DIAGNOSTIC', 'CARGO', 'FOOD', 'ENERGY'], true)) {
                        return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_PAYMENT_LINE_BASIS_TYPE_INVALID');
                    }
                    if ($line['annex_basis_type'] === 'QUALIFIED_MATERIAL_SERVICE'
                        && (($line['materials_contract_qualified'] ?? null) !== true || ($line['materials_invoice_qualified'] ?? null) !== true)) {
                        return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_PAYMENT_LINE_MATERIALS_UNPROVEN');
                    }
                }
                $seen[$line['line_id']] = true;
                $lineAmount = DecimalValue::fromString($line['amount'])->rounded(2);
                $lineTotal = $lineTotal->plus($lineAmount);
                $lines[] = ['line_id' => $line['line_id'], 'document_id' => $line['document_id'],
                    'category' => $line['category'], 'basis' => $lineAmount, 'row' => $lineRow];
            }
            if (count($lines) < 2 || $lineTotal->compareTo($gross) !== 0) {
                return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_PAYMENT_LINES_TOTAL_CONFLICT');
            }
        } else {
            if ($row === null) {
                return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_ANNEX_CATEGORY_NOT_FOUND', ['public.annex_category']);
            }
            $lines[] = ['line_id' => 'SINGLE', 'document_id' => null, 'category' => $category, 'basis' => $gross, 'row' => $row];
        }
        if (in_array($facts->value('provider.tax_regime'), ['SIMPLES_NACIONAL', 'MEI'], true)) {
            $missing = $facts->missing(['provider.revenue_is_own', 'provider.simples_declaration_received']);
            if ($missing !== []) {
                return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_SIMPLES_EVIDENCE_MISSING', $missing);
            }
            if ($facts->value('provider.revenue_is_own') === true && $facts->value('provider.simples_declaration_received') === true) {
                return $this->unresolved(FederalWithholdingStatus::EXEMPT, 'PUBLIC_SIMPLES_OWN_REVENUE_EXEMPT');
            }
        }
        $missing = $facts->missing(['public.beneficiary_treatment']);
        if ($missing !== []) {
            return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_BENEFICIARY_TREATMENT_MISSING', $missing);
        }
        $irException = null;
        if ($facts->value('public.beneficiary_treatment') !== 'STANDARD') {
            if (! $facts->hasDefinition('federal.irrf_status')) {
                return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_BENEFICIARY_EXCEPTION_RULE_REQUIRED');
            }
            $missing = $facts->missing(['federal.irrf_status', 'public.exception_disclosed_on_invoice']);
            if ($missing !== []) {
                return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_BENEFICIARY_EXCEPTION_FACTS_MISSING', $missing);
            }
            if ($facts->value('public.exception_disclosed_on_invoice') !== true) {
                return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_BENEFICIARY_EXCEPTION_NOT_DISCLOSED');
            }
            if ($facts->value('federal.irrf_status') !== 'APPLICABLE') {
                $missing = $facts->missing(['federal.irrf_exception_legal_basis']);
                if ($missing !== []) {
                    return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_IR_EXCEPTION_BASIS_MISSING', $missing);
                }
                $irException = ['status' => $facts->value('federal.irrf_status'),
                    'legal_basis' => $facts->value('federal.irrf_exception_legal_basis')];
            }
            if ($facts->value('public.beneficiary_treatment') === 'OTHER'
                && $irException === null) {
                return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_BENEFICIARY_OTHER_TREATMENT_REVIEW_REQUIRED');
            }
        }
        $convention = false;
        if ($subnational) {
            $missing = $facts->missing(['public.article33_convention_exists']);
            if ($missing !== []) {
                return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_ARTICLE33_CONVENTION_STATUS_MISSING', $missing);
            }
            $convention = $facts->value('public.article33_convention_exists') === true;
            if ($convention) {
                $missing = $facts->missing(['public.article33_convention_source_id', 'public.article33_convention_valid_from', 'public.article33_convention_valid_to']);
                if ($missing !== []) {
                    return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_ARTICLE33_CONVENTION_EVIDENCE_MISSING', $missing);
                }
                if ($date < $facts->value('public.article33_convention_valid_from') || $date > $facts->value('public.article33_convention_valid_to')) {
                    return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_ARTICLE33_CONVENTION_OUTSIDE_VALIDITY');
                }
            }
        }

        $withholdContributions = $federal || $convention;
        $componentExceptions = [];
        if ($withholdContributions) {
            $statusFacts = ['federal.pis_status', 'federal.cofins_status', 'federal.csll_status'];
            $missing = $facts->missing($statusFacts);
            if ($missing !== []) {
                return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_COMPONENT_STATUS_MISSING', $missing);
            }
            foreach ($statusFacts as $statusFact) {
                $status = (string) $facts->value($statusFact);
                if ($status !== 'APPLICABLE') {
                    $tax = strtoupper(str_replace('_status', '', explode('.', $statusFact)[1]));
                    $basisFact = $statusFact === 'federal.pis_status' ? 'federal.pis_exception_legal_basis'
                        : ($statusFact === 'federal.cofins_status' ? 'federal.cofins_exception_legal_basis' : 'federal.csll_exception_legal_basis');
                    $missing = $facts->missing([$basisFact, 'public.exception_disclosed_on_invoice']);
                    if ($missing !== []) {
                        return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_COMPONENT_EXCEPTION_EVIDENCE_MISSING', $missing);
                    }
                    if ($facts->value('public.exception_disclosed_on_invoice') !== true) {
                        return $this->unresolved(FederalWithholdingStatus::ACTION_REQUIRED, 'PUBLIC_COMPONENT_EXCEPTION_NOT_DISCLOSED');
                    }
                    $componentExceptions[$tax] = ['status' => $status, 'legal_basis' => $facts->value($basisFact)];
                }
            }
        }
        $rateKeys = ['IRRF' => 'ir', 'CSLL' => 'csll', 'COFINS' => 'cofins', 'PIS' => 'pis'];
        $amounts = array_fill_keys(array_keys($rateKeys), DecimalValue::fromString('0'));
        $lineCalculations = [];
        $total = DecimalValue::fromString('0');
        foreach ($lines as $line) {
            $lineResult = ['line_id' => $line['line_id'], 'document_id' => $line['document_id'],
                'category' => $line['category'], 'basis' => $line['basis']->value(),
                'revenue_code' => $line['row']['revenue_code'], 'locator' => $line['row']['locator'], 'taxes' => []];
            foreach ($rateKeys as $tax => $rateKey) {
                if (($tax === 'IRRF' && $irException !== null)
                    || ($tax !== 'IRRF' && (! $withholdContributions || isset($componentExceptions[$tax])))) {
                    continue;
                }
                $rate = $line['row'][$rateKey];
                $lineAmount = $line['basis']->multipliedBy(DecimalValue::fromString($rate))
                    ->dividedBy(DecimalValue::fromString('100'), 8)->rounded(2);
                $amounts[$tax] = $amounts[$tax]->plus($lineAmount);
                $total = $total->plus($lineAmount);
                $lineResult['taxes'][$tax] = ['rate' => $rate, 'amount' => $lineAmount->value()];
            }
            $lineCalculations[] = $lineResult;
        }
        $threshold = $facts->value('payer.siafi_electronic_collection') !== true
            && $total->compareTo(DecimalValue::fromString('10.00')) < 0;
        $details = [
            'catalog' => $this->catalog->provenance(),
            'payment_amount' => $facts->value('payment.gross_amount'),
            'withholding_basis' => $gross->value(),
            'original_invoice_amount' => $facts->value('invoice.gross_amount'),
            'glosa_amount' => $facts->value('public.glosa_amount'),
            'late_interest' => $facts->value('public.late_interest'),
            'late_penalties' => $facts->value('public.late_penalties'),
            'locator' => $row['locator'] ?? null,
            'line_calculations' => $lineCalculations,
            'collection_destination' => $federal ? 'FEDERAL_TREASURY' : 'LOCAL_TREASURY',
            'article33_convention_source_id' => $convention ? $facts->value('public.article33_convention_source_id') : null,
            'component_exceptions' => $componentExceptions,
        ];
        $ir = $this->decision(FederalTax::IRRF, $gross, $row['ir'] ?? null, $amounts['IRRF'],
            $federal ? ($componentExceptions === [] ? ($row['revenue_code'] ?? null) : '6256') : null, $date, $threshold, $details,
            $subnational ? 'PUBLIC_IR_LOCAL_COLLECTION_RULE_REQUIRED' : 'PUBLIC_ANNEX_I_IR');
        if ($irException !== null) {
            $ir = new FederalWithholdingDecision(FederalTax::IRRF,
                match ($irException['status']) {
                    'EXEMPT' => FederalWithholdingStatus::EXEMPT,
                    'IMMUNE' => FederalWithholdingStatus::IMMUNE,
                    'ZERO_RATE' => FederalWithholdingStatus::ZERO_RATE,
                    'SUSPENDED' => FederalWithholdingStatus::SUSPENDED,
                    default => FederalWithholdingStatus::ACTION_REQUIRED,
                }, $gross->value(), null, '0.00', null, 'PAYER', 'PAYMENT', $date,
                ['PUBLIC_IR_'.$irException['status']], [(string) $irException['legal_basis']], [], $details);
        }
        $decisions = [];
        foreach (['PIS', 'COFINS', 'CSLL'] as $tax) {
            if (isset($componentExceptions[$tax])) {
                $exception = $componentExceptions[$tax];
                $status = match ($exception['status']) {
                    'EXEMPT' => FederalWithholdingStatus::EXEMPT,
                    'ZERO_RATE' => FederalWithholdingStatus::ZERO_RATE,
                    'SUSPENDED' => FederalWithholdingStatus::SUSPENDED,
                    default => FederalWithholdingStatus::ACTION_REQUIRED,
                };
                $decisions[] = new FederalWithholdingDecision(FederalTax::from($tax), $status,
                    $gross->value(), null, '0.00', null, 'PAYER', 'PAYMENT', $date,
                    ['PUBLIC_COMPONENT_'.$exception['status']], [(string) $exception['legal_basis']], [], $details);

                continue;
            }
            if (! $withholdContributions) {
                $decisions[] = new FederalWithholdingDecision(FederalTax::from($tax), FederalWithholdingStatus::NOT_APPLICABLE,
                    $gross->value(), null, '0.00', null, 'PAYER', 'PAYMENT', $date,
                    ['PUBLIC_ARTICLE33_CONVENTION_ABSENT'], ['Lei 10.833/2003, art. 33'], [], $details);

                continue;
            }
            $componentDetails = $details;
            $componentDetails['collection_destination'] = 'FEDERAL_TREASURY';
            $decisions[] = $this->decision(FederalTax::from($tax), $gross, $row[$rateKeys[$tax]] ?? null, $amounts[$tax],
                $federal ? ($componentExceptions === [] ? ($row['revenue_code'] ?? null) : match ($tax) {
                    'CSLL' => '6228', 'COFINS' => '6243', 'PIS' => '6230'
                })
                    : match ($tax) {
                        'CSLL' => '4397', 'COFINS' => '4407', 'PIS' => '4409'
                    },
                $date, $threshold, $componentDetails, $convention ? 'PUBLIC_ARTICLE33_CONVENTION' : 'PUBLIC_ANNEX_I_CONTRIBUTION');
        }
        $contributionTotal = DecimalValue::fromString('0');
        foreach (['PIS', 'COFINS', 'CSLL'] as $tax) {
            if (isset($amounts[$tax]) && ! $threshold) {
                $contributionTotal = $contributionTotal->plus($amounts[$tax]);
            }
        }

        return [
            'irrf' => $ir,
            'pcc' => new PccWithholdingResult(
                $threshold ? 'DISPENSED_THRESHOLD' : (! $withholdContributions ? 'NOT_APPLICABLE'
                    : ($contributionTotal->compareTo(DecimalValue::fromString('0')) === 0 ? 'NO_WITHHOLDING_COMPONENTS' : 'WITHHOLD')),
                $decisions, (string) $facts->value('payment.gross_amount'), $contributionTotal->value(),
                DecimalValue::fromString((string) $facts->value('payment.gross_amount'))->minus($contributionTotal)->rounded(2)->value(),
                $threshold || ! $withholdContributions || $componentExceptions !== [] ? null : ($federal ? ($row['revenue_code'] ?? null) : '4085'),
                substr($date, 0, 7), 'PUBLIC_PAYMENT_DUE_RULE_REQUIRES_VERSION',
                [$threshold ? 'PUBLIC_TOTAL_BELOW_TEN' : 'PUBLIC_ANNEX_I_APPLIED'],
            ),
        ];
    }

    /** @param array<string, mixed> $details */
    private function decision(FederalTax $tax, DecimalValue $gross, ?string $rate, DecimalValue $amount,
        ?string $code, string $date, bool $threshold, array $details, string $reason): FederalWithholdingDecision
    {
        $zero = $amount->compareTo(DecimalValue::fromString('0')) === 0;
        $status = $zero ? FederalWithholdingStatus::ZERO_RATE : ($threshold ? FederalWithholdingStatus::DISPENSED_THRESHOLD : FederalWithholdingStatus::WITHHOLD);

        return new FederalWithholdingDecision($tax, $status, $gross->value(), $rate,
            $zero || $threshold ? '0.00' : $amount->value(), $zero || $threshold ? null : $code,
            'PAYER', 'PAYMENT', $date, [$reason], ['IN RFB 1.234/2012, Anexo I'], [], $details);
    }

    /**
     * @param  list<string>  $missing
     * @return array{irrf: FederalWithholdingDecision, pcc: PccWithholdingResult}
     */
    private function unresolved(FederalWithholdingStatus $status, string $reason, array $missing = []): array
    {
        $make = static fn (FederalTax $tax): FederalWithholdingDecision => new FederalWithholdingDecision(
            $tax, $status, null, null, null, null, 'PAYER', 'PAYMENT', null, [$reason],
            ['IN RFB 1.234/2012'], $missing);

        return [
            'irrf' => $make(FederalTax::IRRF),
            'pcc' => new PccWithholdingResult($status->value,
                [$make(FederalTax::PIS), $make(FederalTax::COFINS), $make(FederalTax::CSLL)],
                null, null, null, null, null, 'PUBLIC_PAYMENT_DUE_RULE_REQUIRES_VERSION', [$reason], $missing),
        ];
    }
}
