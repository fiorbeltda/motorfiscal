<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use DateTimeImmutable;

/** Import taxpayer/reference qualification; collection, discharge and documents remain separate. */
final readonly class ImportResponsibility129
{
    public const VERSION = '2026.10.22-129-draft1';

    public const OBSERVED_AT = '2026-10-22T16:40:39.031505+00:00';

    public const SOURCE_SHA256 = '027797fa2a1f5ce9ebe34c7236766be4ef850127beb510eedb3b8315cf18d6ed';

    /** @return array<string, mixed> */
    public static function policy(): array
    {
        return ['contract' => 'IMPORT-RESPONSIBILITY-129-V1', 'available_at' => self::OBSERVED_AT,
            'source' => ['id' => 'IMPORT129_LC214_OBSERVED20261022',
                'url' => 'https://www.planalto.gov.br/ccivil_03/leis/lcp/lcp214compilado.htm',
                'sha256' => self::SOURCE_SHA256, 'observed_at' => self::OBSERVED_AT],
            'references' => self::references(), 'scope' => 'QUALIFIED_IMPORT_TAXPAYER_AND_DECLARED_COLLECTION_ROUTE',
            'payment_proven' => false, 'document_obligation_complete' => false];
    }

    /** @return array<string, array{article: string, aliases: list<string>}> */
    public static function references(): array
    {
        return [
            'ACQUIRER' => ['article' => 'LC214 art. 64, §5º, V',
                'aliases' => ['LC214-ART64-V', 'LC214 art. 64, §5º, V', 'LC214.2025.ART64.PAR5.V']],
            'RECIPIENT' => ['article' => 'LC214 art. 64, §5º, VI',
                'aliases' => ['LC214-ART64-VI', 'LC214 art. 64, §5º, VI', 'LC214.2025.ART64.PAR5.VI']],
        ];
    }

    /** @param array<string, mixed> $codes */
    public static function required(array $codes): bool
    {
        return in_array($codes['version'] ?? null, [self::VERSION, DocumentSchedule131::VERSION, DocumentSchedule131::PRIOR_VERSION, DocumentIssIntegration134::VERSION], true) || array_key_exists('import_responsibility_policy129', $codes);
    }

    /** @param array<string, mixed> $codes
     * @return array<string, mixed>
     */
    public function qualify(array $codes, DateTimeImmutable $knownAt): array
    {
        $policy = $codes['import_responsibility_policy129'] ?? null;
        if (! is_array($policy) || CanonicalJson::hash($policy) !== CanonicalJson::hash(self::policy())) {
            return $this->stop('SOURCE_POLICY_NOT_BOUND');
        }
        if ($knownAt < new DateTimeImmutable(self::OBSERVED_AT)) {
            return $this->stop('SOURCE_NOT_YET_OBSERVED');
        }

        return ['status' => 'RESOLVED', 'reason' => 'RTC_IMPORT129_SOURCE_POLICY_BOUND',
            'policy_hash' => CanonicalJson::hash(self::policy()), ...$this->limits()];
    }

    /** Already qualified import incidence/location are prerequisites; this component never infers them.
     * @param  array<string, mixed>|null  $platformDecision
     * @return array<string, mixed>
     */
    public function resolve(FactBag $facts, ?array $platformDecision = null): array
    {
        if ($facts->value('rtc.market') !== 'IMPORT' || $facts->value('rtc.supplier_regime') !== 'FOREIGN') {
            return $this->stop('IMPORT_CONTEXT_REQUIRED', 'INVALID');
        }
        $missing = [];
        foreach (['supplier_id', 'acquirer_id', 'recipient_id', 'importer_id'] as $role) {
            $value = $facts->value('rtc.'.$role);
            if (! is_string($value) || trim($value) === '') {
                $missing[] = 'rtc.'.$role;
            } elseif ($value !== trim($value)) {
                return $this->stop('PARTY_IDENTIFIER_INVALID', 'INVALID', ['rtc.'.$role]);
            }
        }
        foreach (['supplier_country', 'acquirer_country', 'recipient_country'] as $country) {
            $value = $facts->value('rtc.'.$country);
            if (! is_string($value) || preg_match('/\A[A-Z]{2}\z/', $value) !== 1) {
                $missing[] = 'rtc.'.$country;
            }
        }
        if ($missing !== []) {
            return $this->stop('PARTY_FACTS_MISSING', missing: $missing);
        }
        if ($facts->value('rtc.supplier_country') === 'BR') {
            return $this->stop('SUPPLIER_NOT_FOREIGN', 'INVALID');
        }
        $role = $facts->value('rtc.acquirer_country') === 'BR' ? 'ACQUIRER' : 'RECIPIENT';
        if ($role === 'RECIPIENT' && $facts->value('rtc.recipient_country') !== 'BR') {
            return $this->stop('NO_DOMESTIC_TAXPAYER', 'INVALID');
        }
        $taxpayer = $facts->value($role === 'ACQUIRER' ? 'rtc.acquirer_id' : 'rtc.recipient_id');
        if ($facts->value('rtc.importer_id') !== $taxpayer
            || ($facts->has('rtc.taxpayer_id') && $facts->value('rtc.taxpayer_id') !== $taxpayer)) {
            return $this->stop('TAXPAYER_CONFLICT', 'INVALID', ['rtc.importer_id', 'rtc.taxpayer_id']);
        }
        if ($taxpayer === $facts->value('rtc.supplier_id')
            || ($facts->value('rtc.acquirer_id') === $facts->value('rtc.recipient_id')
                && $facts->value('rtc.acquirer_country') !== $facts->value('rtc.recipient_country'))) {
            return $this->stop('PARTY_RESIDENCE_CONFLICT', 'INVALID');
        }
        if ($facts->value('rtc.collection_method') !== $role) {
            return $this->stop('COLLECTION_ROUTE_CONFLICT', 'INVALID', ['rtc.collection_method']);
        }
        $declared = $facts->value('rtc.collection_legal_basis');
        if (! is_string($declared) || trim($declared) === '') {
            return $this->stop('BASIS_MISSING', missing: ['rtc.collection_legal_basis']);
        }
        foreach (self::references() as $referenceRole => $reference) {
            if (in_array($declared, $reference['aliases'], true) && $referenceRole !== $role) {
                return $this->stop('BASIS_ROLE_CONFLICT', 'INVALID', ['rtc.collection_legal_basis']);
            }
        }
        if (! in_array($declared, self::references()[$role]['aliases'], true)) {
            return $this->stop('BASIS_NOT_QUALIFIED', missing: ['rtc.collection_legal_basis']);
        }
        if ($facts->has('rtc.platform_id') && ($platformDecision['status'] ?? null) !== 'RESOLVED') {
            return $this->stop('PLATFORM_QUALIFICATION_REQUIRED', missing: ['rtc.platform_qualification_evidence_id']);
        }

        return ['status' => 'RESOLVED', 'reason' => 'RTC_IMPORT129_RESPONSIBILITY_QUALIFIED',
            'taxpayer' => $taxpayer, 'taxpayer_role' => $role, 'importer' => $facts->value('rtc.importer_id'),
            'declared_collection_route' => $role, 'source_article' => self::references()[$role]['article'],
            'canonical_reference' => self::references()[$role]['aliases'][2],
            'source_sha256' => self::SOURCE_SHA256, 'source_observed_at' => self::OBSERVED_AT,
            'foreign_supplier_responsibility' => ['party_id' => $facts->value('rtc.supplier_id'),
                'type' => 'SOLIDARY_SUBJECT_TO_ARTS21_23', 'source_article' => 'LC214 art. 64, §5º, VIII',
                'registration_and_enforcement_assessed' => false],
            'platform_responsibility' => $platformDecision, 'extinction_status' => 'NOT_PROVEN',
            ...$this->limits()];
    }

    /** @return array<string, bool> */
    private function limits(): array
    {
        return ['payment_proven' => false, 'document_obligation_complete' => false, 'production_ready' => false];
    }

    /** @param list<string> $missing
     * @return array<string, mixed>
     */
    private function stop(string $reason, string $status = 'ACTION_REQUIRED', array $missing = []): array
    {
        return ['status' => $status, 'reason' => 'RTC_IMPORT129_'.$reason, 'missing_facts' => $missing, ...$this->limits()];
    }
}
