<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;
use InvalidArgumentException;

/** LC214 arts182-184: provider qualification and operation scope are independent. */
final readonly class FinancialRegimeQualifier
{
    /** @param array<string, mixed> $proof
     * @param  array<string, mixed>  $rule
     * @return array<string, mixed>
     */
    public function resolve(array $proof, string $supplierId, string $documentId, LocalDate $on, DateTimeImmutable $knownAt, array $rule): array
    {
        if (($rule['strategy'] ?? null) !== 'LC214_FINANCIAL_SCOPE_ART182_184' || ($rule['status'] ?? null) !== 'ACTIVE'
            || ! isset($rule['published_at'], $rule['valid_from']) || $knownAt < new DateTimeImmutable($rule['published_at'])
            || $on->value() < $rule['valid_from'] || (isset($rule['valid_to']) && $on->value() > $rule['valid_to'])
            || (isset($rule['revoked_at']) && $knownAt >= new DateTimeImmutable($rule['revoked_at']))
            || (isset($rule['superseded_at']) && $knownAt >= new DateTimeImmutable($rule['superseded_at']))
            || ! is_string($rule['source_hash'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/', $rule['source_hash'])) {
            return $this->stop('ACTION_REQUIRED', 'RULE_NOT_AVAILABLE');
        }
        if (($proof['supplier_id'] ?? null) !== $supplierId || ($proof['document_id'] ?? null) !== $documentId
            || $supplierId === '' || $documentId === '') {
            return $this->stop('INVALID', 'PARTIES_OR_DOCUMENT_CONFLICT');
        }
        foreach (['provider_scope_evidence_id', 'operation_scope_evidence_id'] as $key) {
            if (! $this->evidence($proof[$key] ?? null)) {
                return $this->stop('ACTION_REQUIRED', 'EVIDENCE_MISSING:'.$key);
            }
        }
        try {
            $from = LocalDate::fromString((string) ($proof['valid_from'] ?? ''))->value();
            $to = isset($proof['valid_to']) ? LocalDate::fromString((string) $proof['valid_to'])->value() : null;
            $observed = LocalDate::fromString((string) ($proof['observed_on'] ?? ''))->value();
            if (($to !== null && $to < $from) || $observed > $knownAt->format('Y-m-d') || $from > $observed) {
                return $this->stop('INVALID', 'PROOF_PERIOD_CONFLICT');
            }
            if ($on->value() < $from || ($to !== null && $on->value() > $to)) {
                return $this->stop('ACTION_REQUIRED', 'PROOF_OUTSIDE_PERIOD');
            }
        } catch (InvalidArgumentException) {
            return $this->stop('INVALID', 'PROOF_DATE_INVALID');
        }
        $role = $proof['provider_category'] ?? null;
        $supervised = ['BANK', 'ECONOMIC_SAVINGS_BANK', 'CREDIT_COOPERATIVE', 'FX_BROKER', 'SECURITIES_BROKER',
            'SECURITIES_DISTRIBUTOR', 'PORTFOLIO_MANAGER', 'INVESTMENT_ADVISOR', 'SECURITIES_CONSULTANT',
            'BCB_CORRESPONDENT', 'CONSORTIUM_ADMINISTRATOR', 'CONSORTIUM_INTERMEDIARY', 'DIRECT_CREDIT_COMPANY',
            'PERSON_TO_PERSON_LENDING_COMPANY', 'DEVELOPMENT_AGENCY', 'SAVINGS_AND_LOAN_ASSOCIATION', 'MORTGAGE_COMPANY',
            'CREDIT_FINANCING_INVESTMENT_COMPANY', 'REAL_ESTATE_CREDIT_COMPANY', 'LEASING_COMPANY', 'MICROENTERPRISE_CREDIT_COMPANY',
            'PAYMENT_INSTITUTION', 'MARKET_INFRASTRUCTURE_ENTITY', 'INSURER', 'REINSURER', 'PENSION_ENTITY',
            'CAPITALIZATION_COMPANY', 'INSURANCE_PENSION_CAPITALIZATION_INTERMEDIARY', 'VIRTUAL_ASSET_PROVIDER', 'NEWLY_SUPERVISED_PROVIDER'];
        $notSupervised = ['PAYMENT_OR_LOYALTY_PARTICIPANT', 'SECURITIZATION_COMPANY', 'FACTORING_COMPANY',
            'SIMPLE_CREDIT_COMPANY', 'BCB_CORRESPONDENT', 'OTHER_ECONOMIC_ACTIVITY_PROVIDER'];
        $supervision = $proof['supervision'] ?? null;
        if ($supervision === 'SFN_SUPERVISED') {
            if (! in_array($role, $supervised, true) || ($proof['supervision_effective'] ?? null) !== true
                || ! $this->evidence($proof['supervision_registry_evidence_id'] ?? null)) {
                return $this->stop('ACTION_REQUIRED', 'SUPERVISION_UNPROVEN');
            }
            $providerArticle = 'LC214 art183 paragraphs1/3';
        } elseif ($supervision === 'NOT_SFN_SUPERVISED') {
            if (! in_array($role, $notSupervised, true)) {
                return $this->stop('INVALID', 'PROVIDER_CATEGORY_CONFLICT');
            }
            if ($role === 'OTHER_ECONOMIC_ACTIVITY_PROVIDER'
                && (! in_array($proof['activity_basis'] ?? null, ['ECONOMIC_ACTIVITY', 'HABITUAL_OR_ECONOMIC_VOLUME', 'PROFESSIONAL_ACTIVITY'], true)
                    || ! $this->evidence($proof['activity_evidence_id'] ?? null))) {
                return $this->stop('ACTION_REQUIRED', 'ECONOMIC_ACTIVITY_UNPROVEN');
            }
            if ($role === 'BCB_CORRESPONDENT' && ! $this->evidence($proof['correspondent_registration_evidence_id'] ?? null)) {
                return $this->stop('ACTION_REQUIRED', 'CORRESPONDENT_REGISTRATION_UNPROVEN');
            }
            $providerArticle = 'LC214 art183 paragraph2';
        } else {
            return $this->stop('ACTION_REQUIRED', 'SUPERVISION_STATUS_UNPROVEN');
        }
        if ($role === 'PENSION_ENTITY' && ($proof['not_art26_paragraph9_entity'] ?? null) !== true) {
            return $this->stop('ACTION_REQUIRED', 'PENSION_TAXPAYER_STATUS_UNPROVEN');
        }
        $operation = $proof['operation_category'] ?? null;
        $family = null;
        if ($operation === 'BANK_EXCLUSIVE_FEE_SERVICE') {
            if ($supervision !== 'SFN_SUPERVISED' || ! in_array($role, ['BANK', 'ECONOMIC_SAVINGS_BANK'], true)
                || ($proof['bank_exclusive_by_regulation'] ?? null) !== true
                || ($proof['paid_by_fee_or_commission'] ?? null) !== true
                || ! $this->evidence($proof['bank_exclusivity_regulatory_evidence_id'] ?? null)) {
                return $this->stop('INVALID', 'BANK_GENERAL_EXCEPTION_CONFLICT');
            }
            $regime = 'GENERAL_ART184';
            $article = 'LC214 art184 caput/paragraph1';
        } elseif ($operation === 'PAYMENT_ACCOUNT_MAINTENANCE_OR_CLOSURE') {
            if ($supervision !== 'SFN_SUPERVISED' || $role !== 'PAYMENT_INSTITUTION'
                || ! in_array($proof['payment_account_kind'] ?? null, ['PREPAID', 'POSTPAID'], true)
                || ($proof['paid_by_fee_or_commission'] ?? null) !== true) {
                return $this->stop('INVALID', 'PAYMENT_ACCOUNT_GENERAL_EXCEPTION_CONFLICT');
            }
            $regime = 'GENERAL_ART184';
            $article = 'LC214 art184 paragraph2';
        } elseif ($operation === 'OTHER_NON_FINANCIAL_SERVICE') {
            if (($proof['not_art182_service'] ?? null) !== true || ! $this->evidence($proof['non_financial_scope_evidence_id'] ?? null)) {
                return $this->stop('ACTION_REQUIRED', 'NON_FINANCIAL_SCOPE_UNPROVEN');
            }
            $regime = 'GENERAL_ART184';
            $article = 'LC214 art184 paragraph3';
        } else {
            $family = match ($operation) {
                'CREDIT', 'FX', 'SECURITIES' => 'CREDIT_FX_SECURITIES_ART192',
                'SECURITIZATION', 'FACTORING' => 'SECURITIZATION_FACTORING_ART193',
                'LEASING' => 'LEASING', 'CONSORTIUM_ADMINISTRATION' => 'CONSORTIUM',
                'RESOURCE_MANAGEMENT' => 'RESOURCE_MANAGEMENT', 'PAYMENT_ARRANGEMENT' => 'PAYMENT_ARRANGEMENT',
                'MARKET_INFRASTRUCTURE' => 'MARKET_INFRASTRUCTURE', 'INSURANCE_OTHER_THAN_HEALTH' => 'INSURANCE',
                'REINSURANCE' => 'REINSURANCE', 'PRIVATE_PENSION' => 'PRIVATE_PENSION', 'CAPITALIZATION' => 'CAPITALIZATION',
                'FINANCIAL_INTERMEDIATION_ART182_XV' => 'FINANCIAL_INTERMEDIATION', 'VIRTUAL_ASSETS' => 'VIRTUAL_ASSETS',
                'MUTUAL_ASSET_PROTECTION' => 'MUTUAL_ASSET_PROTECTION', default => null,
            };
            if ($family === null) {
                return $this->stop('ACTION_REQUIRED', 'OPERATION_SCOPE_NOT_PROVEN');
            }
            if (($proof['provider_authorized_for_operation'] ?? null) !== true
                || ! $this->evidence($proof['operation_authorization_evidence_id'] ?? null)) {
                return $this->stop('ACTION_REQUIRED', 'OPERATION_AUTHORIZATION_UNPROVEN');
            }
            $regime = 'SPECIFIC_FINANCIAL_ART181';
            $article = 'LC214 art182';
        }

        return ['status' => 'RESOLVED', 'reason' => 'RTC_FINANCIAL_SCOPE_QUALIFIED', 'regime' => $regime,
            'base_family' => $family, 'supplier_id' => $supplierId, 'document_id' => $documentId,
            'provider_category' => $role, 'operation_category' => $operation, 'provider_source_article' => $providerArticle,
            'operation_source_article' => $article, 'source_hash' => $rule['source_hash'], 'proof' => $proof,
            'scope' => 'PROVIDER_AND_OPERATION_SCOPE_ONLY_NO_TAX_RATE_CREDIT_OR_DOCUMENT_APPROVAL'];
    }

    private function evidence(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    /** @return array<string, mixed> */
    private function stop(string $status, string $reason): array
    {
        return ['status' => $status, 'reason' => 'RTC_FINANCIAL_SCOPE_'.$reason];
    }
}
