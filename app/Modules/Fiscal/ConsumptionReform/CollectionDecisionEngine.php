<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Facts\FactBag;

final readonly class CollectionDecisionEngine
{
    /** @return array<string, mixed> */
    public function resolve(FactBag $facts, string $market, bool $splitOperational): array
    {
        $method = (string) $facts->value('rtc.collection_method');
        if ($method === 'SUPPLIER') {
            return ['status' => 'RESOLVED', 'method' => $method, 'collector' => $facts->value('rtc.supplier_id'),
                'extinction_status' => 'NOT_ASSESSED', 'source_article' => 'LC214 art. 27, II'];
        }
        if (! in_array($method, ['ACQUIRER', 'SPLIT_PAYMENT'], true) || $market === 'IMPORT') {
            return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_COLLECTION_STRATEGY_NOT_MATERIALIZED:'.$method,
                'missing_facts' => []];
        }
        if ($facts->value('rtc.collection_legal_basis') === null) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_COLLECTION_BASIS_MISSING',
                'missing_facts' => ['rtc.collection_legal_basis']];
        }
        $missing = $facts->missing(['rtc.payment_instrument_split_capable', 'rtc.payment_instrument_evidence_id',
            'rtc.payment_transaction_id']);
        if ($missing !== []) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_PAYMENT_INSTRUMENT_UNPROVEN', 'missing_facts' => $missing];
        }
        if ($method === 'ACQUIRER') {
            if ($facts->value('rtc.acquirer_regime') === null) {
                return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_ACQUIRER_REGIME_UNPROVEN',
                    'missing_facts' => ['rtc.acquirer_regime']];
            }
            if ($facts->value('rtc.acquirer_regime') !== 'REGULAR'
                || $facts->value('rtc.payment_instrument_split_capable') !== false) {
                return ['status' => 'INVALID', 'reason' => 'RTC_ACQUIRER_COLLECTION_CONDITIONS_CONFLICT',
                    'missing_facts' => []];
            }

            return ['status' => 'RESOLVED', 'method' => $method, 'collector' => $facts->value('rtc.supplier_id'),
                'eligible_payer' => $facts->value('rtc.acquirer_id'),
                'extinction_status' => 'UNCONFIRMED', 'source_article' => 'LC214 art. 36',
                'payment_transaction_id' => $facts->value('rtc.payment_transaction_id'),
                'instrument_evidence_id' => $facts->value('rtc.payment_instrument_evidence_id')];
        }
        if ($facts->value('rtc.payment_instrument_split_capable') !== true) {
            return ['status' => 'INVALID', 'reason' => 'RTC_SPLIT_INSTRUMENT_CONFLICT', 'missing_facts' => []];
        }
        if (! $splitOperational) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_SPLIT_OFFICIAL_DEPLOYMENT_NOT_PROVEN',
                'missing_facts' => []];
        }
        if ($facts->value('rtc.split_deployment_evidence_id') === null) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_SPLIT_DEPLOYMENT_UNPROVEN',
                'missing_facts' => ['rtc.split_deployment_evidence_id']];
        }

        return ['status' => 'RESOLVED', 'method' => $method, 'collector' => 'PAYMENT_SERVICE_PROVIDER',
            'extinction_status' => 'UNCONFIRMED', 'source_article' => 'LC214 arts. 31-35',
            'payment_transaction_id' => $facts->value('rtc.payment_transaction_id'),
            'instrument_evidence_id' => $facts->value('rtc.payment_instrument_evidence_id'),
            'deployment_evidence_id' => $facts->value('rtc.split_deployment_evidence_id')];
    }
}
