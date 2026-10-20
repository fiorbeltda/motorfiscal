<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Facts\FactBag;

final readonly class PlatformResponsibilityEngine
{
    /** @return array<string, mixed> */
    public function resolve(FactBag $facts): array
    {
        if ($facts->value('rtc.platform_id') === null) {
            return ['status' => 'RESOLVED', 'responsibility' => 'NOT_APPLICABLE'];
        }

        $qualification = $facts->missing(['rtc.platform_remote_intermediation', 'rtc.platform_controlled_elements',
            'rtc.platform_activity_exclusion', 'rtc.platform_qualification_evidence_id']);
        if ($qualification !== []) {
            return $this->require('RTC_PLATFORM_QUALIFICATION_UNPROVEN', $qualification);
        }
        $controlled = $facts->value('rtc.platform_controlled_elements');
        if (! is_array($controlled) || ! array_is_list($controlled)
            || count(array_filter($controlled, 'is_string')) !== count($controlled)
            || count(array_diff($controlled, ['CHARGE', 'PAYMENT', 'TERMS', 'DELIVERY'])) > 0
            || count($controlled) !== count(array_unique($controlled))) {
            return ['status' => 'INVALID', 'reason' => 'RTC_PLATFORM_CONTROL_FACTS_INVALID', 'missing_facts' => []];
        }
        if ($facts->value('rtc.platform_activity_exclusion') === 'SEARCH_COMPARISON_ONLY'
            && $facts->value('rtc.platform_remote_intermediation') === true && $controlled !== []) {
            return $this->require('RTC_PLATFORM_SEARCH_FEE_BASIS_UNPROVEN', []);
        }
        if ($facts->value('rtc.platform_remote_intermediation') !== true || $controlled === []
            || $facts->value('rtc.platform_activity_exclusion') !== 'NONE') {
            return ['status' => 'RESOLVED', 'responsibility' => 'NOT_PLATFORM_UNDER_ART22',
                'source_article' => 'LC214 art. 22, §§1º-2º, 11'];
        }

        if ($facts->value('rtc.supplier_country') === null) {
            return $this->require('RTC_PLATFORM_SUPPLIER_RESIDENCE_UNPROVEN', ['rtc.supplier_country']);
        }
        if ($facts->value('rtc.supplier_country') !== 'BR') {
            return ['status' => 'RESOLVED', 'responsibility' => 'FOREIGN_SUPPLIER_SUBSTITUTIVE_SOLIDARY',
                'responsible_id' => $facts->value('rtc.platform_id'), 'joint_with' => 'ACQUIRER_OR_RECIPIENT',
                'source_article' => 'LC214 art. 22, I',
                'qualification_evidence_id' => $facts->value('rtc.platform_qualification_evidence_id')];
        }

        $operationProof = $facts->missing(['rtc.platform_reported_operation', 'rtc.platform_report_evidence_id',
            'rtc.supplier_document_full_value', 'rtc.supplier_document_evidence_id',
            'rtc.platform_document_registered', 'rtc.platform_document_evidence_id']);
        if ($operationProof !== []) {
            return $this->require('RTC_PLATFORM_OPERATION_EVIDENCE_MISSING', $operationProof);
        }
        $triggers = [];
        if ($facts->value('rtc.platform_reported_operation') === false) {
            $triggers[] = 'PLATFORM_INFORMATION_NOT_PROVIDED';
        }
        if ($facts->value('rtc.platform_document_registered') === false) {
            $triggers[] = 'OPERATION_NOT_REGISTERED_IN_FISCAL_DOCUMENT';
        }
        if ($facts->value('rtc.supplier_document_full_value') === false) {
            $supplierProof = $facts->missing(['rtc.supplier_is_taxpayer', 'rtc.supplier_taxpayer_evidence_id',
                'rtc.supplier_registered']);
            if ($supplierProof !== []) {
                return $this->require('RTC_PLATFORM_SUPPLIER_TAXPAYER_UNPROVEN', $supplierProof);
            }
            if ($facts->value('rtc.supplier_is_taxpayer') === true) {
                if ($facts->value('rtc.supplier_registered') === false
                    && $facts->value('rtc.supplier_taxpayer_authority_notice_id') === null) {
                    return $this->require('RTC_PLATFORM_UNREGISTERED_SUPPLIER_NOTICE_MISSING',
                        ['rtc.supplier_taxpayer_authority_notice_id']);
                }
                $triggers[] = 'SUPPLIER_DOCUMENT_MISSING_OR_UNDERVALUED';
            }
        }

        return ['status' => 'RESOLVED',
            'responsibility' => $triggers === [] ? 'NOT_TRIGGERED' : 'DOMESTIC_SUPPLIER_SOLIDARY',
            'responsible_id' => $triggers === [] ? null : $facts->value('rtc.platform_id'),
            'triggers' => $triggers,
            'source_article' => 'LC214 art. 22, II',
            'qualification_evidence_id' => $facts->value('rtc.platform_qualification_evidence_id'),
            'operation_evidence_ids' => [$facts->value('rtc.platform_report_evidence_id'),
                $facts->value('rtc.supplier_document_evidence_id'), $facts->value('rtc.platform_document_evidence_id')]];
    }

    /** @param list<string> $missing
     * @return array<string, mixed>
     */
    private function require(string $reason, array $missing): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => $reason, 'missing_facts' => $missing];
    }
}
