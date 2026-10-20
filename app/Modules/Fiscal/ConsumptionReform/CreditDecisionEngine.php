<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Shared\DecimalValue;

final readonly class CreditDecisionEngine
{
    /** @param array<string, mixed>|null $treatmentDecision
     * @return array<string, mixed>
     */
    public function resolve(FactBag $facts, string $market, bool $qualifyImport = false, ?array $treatmentDecision = null): array
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
}
