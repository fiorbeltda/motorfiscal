<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Shared\DecimalValue;

final readonly class CreditDecisionEngine
{
    /**
     * B02: with $explainZero every zero-valued outcome also carries a distinct
     * cause and amount semantics (4B.5.10). The default keeps the historical
     * output byte-identical, so accepted replays and decision hashes remain valid;
     * activation belongs to a separately versioned caller.
     *
     * @param  array<string, mixed>|null  $treatmentDecision
     * @return array<string, mixed>
     */
    public function resolve(FactBag $facts, string $market, bool $qualifyImport = false, ?array $treatmentDecision = null, bool $explainZero = false): array
    {
        $result = $this->decide($facts, $market, $qualifyImport, $treatmentDecision);

        return $explainZero ? $this->explainZero($result, $facts, $treatmentDecision) : $result;
    }

    /**
     * @param  array<string, mixed>|null  $treatmentDecision
     * @return array<string, mixed>
     */
    private function decide(FactBag $facts, string $market, bool $qualifyImport, ?array $treatmentDecision): array
    {
        $context = (string) $facts->value('rtc.credit_context');
        if ($context === 'OUTPUT') {
            return ['status' => 'RESOLVED', 'ibs' => ['status' => 'NOT_ASSESSED_ON_OUTPUT'], 'cbs' => ['status' => 'NOT_ASSESSED_ON_OUTPUT']];
        }
        if ($context === 'PERSONAL_USE') {
            return ['status' => 'RESOLVED', 'ibs' => ['status' => 'DENIED', 'amount' => '0.00'], 'cbs' => ['status' => 'DENIED', 'amount' => '0.00']];
        }
        if ($market === 'IMPORT' && $qualifyImport
            && ($facts->value('rtc.acquirer_country') !== 'BR'
                || $facts->value('rtc.importer_id') !== $facts->value('rtc.acquirer_id'))) {
            return ['status' => 'RESOLVED', 'source_article' => 'LC214 art. 64, §5º, VII',
                'ibs' => ['status' => 'DENIED', 'amount' => '0.00', 'reason' => 'CLAIMANT_NOT_DOMESTIC_ACQUIRER'],
                'cbs' => ['status' => 'DENIED', 'amount' => '0.00', 'reason' => 'CLAIMANT_NOT_DOMESTIC_ACQUIRER']];
        }
        if ($facts->value('rtc.acquirer_regime') === null) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_CREDIT_CLAIMANT_REGIME_MISSING', 'missing_facts' => ['rtc.acquirer_regime']];
        }
        if ($facts->value('rtc.acquirer_regime') !== 'REGULAR') {
            return ['status' => 'RESOLVED', 'ibs' => ['status' => 'DENIED', 'amount' => '0.00', 'reason' => 'CLAIMANT_NOT_REGULAR'],
                'cbs' => ['status' => 'DENIED', 'amount' => '0.00', 'reason' => 'CLAIMANT_NOT_REGULAR']];
        }
        $missing = $facts->missing(['rtc.input_document_id', 'rtc.input_document_valid', 'rtc.prior_tax_extinguished',
            'rtc.input_ibs_amount', 'rtc.input_cbs_amount', 'rtc.credit_adjustments']);
        if ($missing !== []) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_CREDIT_EVIDENCE_MISSING', 'missing_facts' => $missing];
        }
        if ($facts->value('rtc.input_document_valid') !== true
            || ($facts->value('rtc.prior_tax_extinguished') !== true && $treatmentDecision === null)) {
            return ['status' => 'RESOLVED', 'ibs' => ['status' => 'CONDITIONAL', 'amount' => '0.00'], 'cbs' => ['status' => 'CONDITIONAL', 'amount' => '0.00']];
        }
        if ($market === 'IMPORT' && $facts->value('rtc.import_document_id') === null) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_IMPORT_CREDIT_DOCUMENT_MISSING', 'missing_facts' => ['rtc.import_document_id']];
        }
        if ($market === 'IMPORT' && $qualifyImport) {
            $proofKeys = ['rtc.import_credit_fiscal_document_id'];
            if ($treatmentDecision === null || array_filter($treatmentDecision['components'],
                static fn (array $component): bool => in_array($component['status'], ['ORDINARY', 'PRINCIPAL_ONLY'], true)) !== []) {
                $proofKeys[] = 'rtc.import_credit_extinction_evidence_id';
            }
            $importMissing = $facts->missing($proofKeys);
            if ($importMissing !== []) {
                return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_IMPORT_CREDIT_PROOF_MISSING',
                    'missing_facts' => $importMissing];
            }
            if ($facts->value('rtc.input_document_id') !== $facts->value('rtc.import_credit_fiscal_document_id')) {
                return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_IMPORT_CREDIT_DOCUMENT_LINK_CONFLICT',
                    'missing_facts' => ['rtc.input_document_id', 'rtc.import_credit_fiscal_document_id']];
            }
        }

        $ibs = DecimalValue::fromString((string) $facts->value('rtc.input_ibs_amount'));
        $cbs = DecimalValue::fromString((string) $facts->value('rtc.input_cbs_amount'));
        if ($ibs->isNegative() || $cbs->isNegative()) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_NEGATIVE_INPUT_CREDIT', 'missing_facts' => []];
        }
        foreach ($facts->value('rtc.credit_adjustments') as $adjustment) {
            if (! is_array($adjustment) || ! isset($adjustment['tax'], $adjustment['kind'], $adjustment['amount'], $adjustment['evidence_id'])
                || ! in_array($adjustment['kind'], ['REVERSAL', 'DISALLOWANCE'], true)) {
                return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_CREDIT_ADJUSTMENT_UNPROVEN', 'missing_facts' => []];
            }
            $amount = DecimalValue::fromString((string) $adjustment['amount']);
            if ($amount->isNegative()) {
                return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_NEGATIVE_CREDIT_ADJUSTMENT', 'missing_facts' => []];
            }
            if ($adjustment['tax'] === 'IBS') {
                $ibs = $ibs->minus($amount);
            } elseif ($adjustment['tax'] === 'CBS') {
                $cbs = $cbs->minus($amount);
            } else {
                return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_CREDIT_TAX_UNKNOWN', 'missing_facts' => []];
            }
        }
        if ($ibs->isNegative() || $cbs->isNegative()) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_CREDIT_ADJUSTMENT_EXCEEDS_AVAILABLE', 'missing_facts' => []];
        }

        $result = ['status' => 'RESOLVED',
            ...($market === 'IMPORT' && $qualifyImport ? ['source_article' => 'LC214 art. 64, §5º, VII; art. 47, §1º'] : []),
            'ibs' => ['status' => 'ALLOWED', 'amount' => $ibs->rounded(2)->value(), 'document_id' => $facts->value('rtc.input_document_id')],
            'cbs' => ['status' => 'ALLOWED', 'amount' => $cbs->rounded(2)->value(), 'document_id' => $facts->value('rtc.input_document_id')]];
        if ($treatmentDecision !== null) {
            foreach (['IBS' => 'ibs', 'CBS' => 'cbs'] as $tax => $key) {
                $component = $treatmentDecision['components'][$tax];
                if ($component['status'] === 'DENIED') {
                    $result[$key] = ['status' => 'DENIED', 'amount' => '0.00', 'reason' => 'INPUT_TREATMENT_PRECLUDES_CREDIT'];
                } elseif ($component['status'] === 'CONDITIONAL' || $facts->value('rtc.prior_tax_extinguished') !== true) {
                    $result[$key] = ['status' => 'CONDITIONAL', 'amount' => '0.00'];
                }
                $result[$key]['treatment_qualification'] = $component;
            }
        }

        return $result;
    }

    /**
     * Annotates without changing status or amount. Only RESOLVED tax components are
     * annotated; ACTION_REQUIRED stops already carry their own reason.
     *
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>|null  $treatmentDecision
     * @return array<string, mixed>
     */
    private function explainZero(array $result, FactBag $facts, ?array $treatmentDecision): array
    {
        if ($result['status'] !== 'RESOLVED') {
            return $result;
        }
        $context = (string) $facts->value('rtc.credit_context');
        foreach (['IBS' => 'ibs', 'CBS' => 'cbs'] as $tax => $key) {
            $component = $result[$key];
            $component['zero_semantics_version'] = 'B02';
            switch ($component['status']) {
                case 'NOT_ASSESSED_ON_OUTPUT':
                    $component['zero_causes'] = ['OUTPUT_CONTEXT_NO_INPUT_CREDIT'];
                    $component['amount_semantics'] = 'NOT_ASSESSED_NO_AMOUNT';
                    break;
                case 'DENIED':
                    $component['zero_causes'] = [$component['reason'] ?? ($context === 'PERSONAL_USE' ? 'PERSONAL_USE_OR_CONSUMPTION' : 'DENIED_UNSPECIFIED')];
                    $component['amount_semantics'] = 'FINAL_DENIAL_ZERO';
                    break;
                case 'CONDITIONAL':
                    $causes = [];
                    if ($facts->value('rtc.input_document_valid') !== true) {
                        $causes[] = 'INPUT_DOCUMENT_NOT_VALID';
                    }
                    if ($facts->value('rtc.prior_tax_extinguished') !== true) {
                        $causes[] = 'PRIOR_TAX_NOT_EXTINGUISHED';
                    }
                    if (($treatmentDecision['components'][$tax]['status'] ?? null) === 'CONDITIONAL') {
                        $causes[] = 'INPUT_TREATMENT_CONDITIONAL';
                    }
                    $component['zero_causes'] = $causes === [] ? ['CONDITIONAL_UNSPECIFIED'] : $causes;
                    $component['amount_semantics'] = 'CONTINGENT_NOT_YET_APPROPRIABLE';
                    break;
                case 'ALLOWED':
                    $zero = DecimalValue::fromString('0');
                    $amount = DecimalValue::fromString((string) $component['amount']);
                    if ($amount->compareTo($zero) === 0) {
                        $input = DecimalValue::fromString((string) $facts->value($tax === 'IBS' ? 'rtc.input_ibs_amount' : 'rtc.input_cbs_amount'));
                        $adjusted = $zero;
                        foreach ($facts->value('rtc.credit_adjustments') as $adjustment) {
                            if ($adjustment['tax'] === $tax) {
                                $adjusted = $adjusted->plus(DecimalValue::fromString((string) $adjustment['amount']));
                            }
                        }
                        $component['zero_causes'] = [match (true) {
                            $input->compareTo($zero) === 0 => 'INPUT_TAX_AMOUNT_ZERO',
                            $adjusted->compareTo($zero) > 0 => 'FULLY_REVERSED_OR_DISALLOWED',
                            default => 'ROUNDED_TO_ZERO',
                        }];
                        $component['amount_semantics'] = 'ALLOWED_ZERO_AMOUNT';
                    } else {
                        $component['zero_causes'] = [];
                        $component['amount_semantics'] = 'ALLOWED_AMOUNT';
                    }
                    break;
            }
            $result[$key] = $component;
        }

        return $result;
    }
}
