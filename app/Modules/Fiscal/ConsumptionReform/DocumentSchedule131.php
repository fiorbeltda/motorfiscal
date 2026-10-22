<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;

/** Ato4 dates and exceptions; neither document fulfilment nor municipal ISS obligations. */
final readonly class DocumentSchedule131
{
    public const VERSION = '2026.10.22-131-draft2';

    public const PRIOR_VERSION = '2026.10.22-131-draft1';

    public const AVAILABLE_AT = '2026-10-22T17:49:16+00:00';

    /** @return array<string, mixed> */
    public static function policy(): array
    {
        return ['contract' => 'DOCUMENT-SCHEDULE-131-V1', 'available_at' => self::AVAILABLE_AT,
            'source_id' => 'DERE-OBSERVED-67576256EA86',
            'source_sha256' => '67576256ea863adef5905896d6e7068682d993f3e36641ff16529ab66e4fc79e',
            'source_file' => 'dere-supplement-observed-20261002-ato-conjunto-rfb_cgibs-no-4-de-30-de-julho-de-2026.pdf',
            'source_observation' => '2026-10-02', 'published_on' => '2026-07-31',
            'legal_effective_from' => '2026-07-31', 'publication_locator' => 'DOU 31/07/2026 edição143 seção1 p25;art3',
            'rows' => self::rows(), 'simples_mandatory_from' => '2027-01-01',
            'dere_monthly' => ['first_period' => '2026-10', 'following_month_due_day' => 15,
                'source_article' => 'art1 §5', 'business_day_adjustment_assessed' => false],
            'scope' => 'RTC_DECLARATION_SCHEDULE_ONLY', 'document_obligation_complete' => false];
    }

    /** @return list<array{document: string, profile: string, mandatory_from: string, locator: string}> */
    public static function rows(): array
    {
        $rows = [];
        foreach ([
            ['NFE', 'GENERAL', '2026-08-03', 'I'], ['NFCE', 'GENERAL', '2026-08-03', 'II'],
            ['NFSE', 'PLATFORM_ART22', '2026-12-01', 'III a'],
            ['NFSE', 'ISS_0103_0105_0109', '2026-12-01', 'III b'],
            ['NFSE', 'ISS_1601', '2026-12-01', 'III c'], ['NFSE', 'ISS_GENERAL', '2026-10-01', 'III d'],
            ['NFSE', 'IMMATERIAL_NON_ISS_NON_ICMS', '2026-12-01', 'III e'],
            ['NFSE', 'CONDOMINIUM', '2026-12-01', 'III f'], ['NFSE', 'LEASE_EXCEPT_III_D', '2026-12-01', 'III g'],
            ['NFSE', 'OTHER', '2026-12-01', 'III h'], ['CTE', 'GENERAL', '2026-08-03', 'IV'],
            ['CTEOS', 'GENERAL', '2026-08-03', 'V'], ['BPE', 'SEMIURBAN_METROPOLITAN', '2026-12-01', 'VI a'],
            ['BPE', 'AIR', '2026-12-01', 'VI b'], ['BPE', 'OTHER', '2026-08-03', 'VI c'],
            ['MDFE', 'GENERAL', '2026-08-03', 'VII'], ['GTVE', 'GENERAL', '2026-08-03', 'VIII'],
            ['NF3E', 'GENERAL', '2026-08-03', 'IX'], ['NFCOM', 'GENERAL', '2026-10-01', 'X'],
            ['NFGAS', 'GENERAL', '2026-12-01', 'XI'], ['DCE', 'GENERAL', '2026-08-03', 'XII'],
            ['NFSE_VIA', 'GENERAL', '2026-08-03', 'XIII'], ['DUIMP', 'GENERAL', '2027-01-01', 'XIV'],
            ['DIR', 'GENERAL', '2026-10-01', 'XV'], ['NFAG', 'GENERAL', '2026-12-01', 'XVI'],
            ['DERE', 'TABLE', '2026-10-01', 'XVII a'], ['DERE', 'MONTHLY', '2026-11-15', 'XVII b'],
            ['DERE', 'OTHER', '2027-01-01', 'XVII c'], ['NFE_ABI', 'GENERAL', '2026-12-01', 'XVIII'],
            ['NFE', 'MONOPHASIC', '2027-01-01', '§2'], ['NFE', 'IMPORT_GOODS', '2027-01-01', '§3'],
            ['NFE', 'NON_ICMS_TAXPAYER', '2026-12-01', '§4'],
        ] as [$document, $profile, $date, $locator]) {
            $rows[] = ['document' => $document, 'profile' => $profile, 'mandatory_from' => $date, 'locator' => 'art1 '.$locator];
        }

        return $rows;
    }

    /** @param array<string, mixed> $codes */
    public static function required(array $codes): bool
    {
        return in_array($codes['version'] ?? null, [self::VERSION, self::PRIOR_VERSION, DocumentIssIntegration134::VERSION], true) || array_key_exists('document_schedule_policy131', $codes);
    }

    /** @param array<string, mixed> $codes
     * @return array<string, mixed>
     */
    public function qualify(array $codes, DateTimeImmutable $knownAt): array
    {
        $policy = $codes['document_schedule_policy131'] ?? null;
        if (! is_array($policy) || CanonicalJson::hash($policy) !== CanonicalJson::hash(self::policy())
            || $knownAt < new DateTimeImmutable(self::AVAILABLE_AT)) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DOCUMENT131_SOURCE_POLICY_NOT_BOUND'];
        }

        return ['status' => 'RESOLVED', 'reason' => 'RTC_DOCUMENT131_SOURCE_POLICY_BOUND',
            'policy_hash' => CanonicalJson::hash($policy), 'source_sha256' => $policy['source_sha256']];
    }

    /** @param array<string, mixed> $codes
     * @return array<string, mixed>
     */
    public function resolve(string $document, string $profile, string $regime, LocalDate $on, DateTimeImmutable $knownAt, array $codes): array
    {
        $qualification = $this->qualify($codes, $knownAt);
        if ($qualification['status'] !== 'RESOLVED') {
            return $qualification;
        }
        if ($on->value() < '2026-07-31') {
            return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DOCUMENT131_PRIOR_LEGISLATION_REQUIRED'];
        }
        $matches = array_values(array_filter(self::rows(), static fn (array $row): bool => $row['document'] === $document && $row['profile'] === $profile));
        if (count($matches) !== 1 || ! in_array($regime, ['REGULAR', 'FOREIGN', 'SIMPLES_NACIONAL', 'MEI', 'NON_TAXPAYER'], true)) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DOCUMENT131_EXPLICIT_PROFILE_REQUIRED'];
        }
        $row = $matches[0];
        $simples = in_array($regime, ['SIMPLES_NACIONAL', 'MEI'], true);
        $date = $simples ? '2027-01-01' : $row['mandatory_from'];

        return ['status' => 'RESOLVED', 'scope' => 'RTC_DECLARATION_SCHEDULE_ONLY',
            'document' => $document, 'profile' => $profile, 'required_by_schedule' => $on->value() >= $date,
            'mandatory_from' => $date, 'rule_id' => 'RTC.DOCUMENT131.'.$document.'.'.$profile,
            'source_id' => self::policy()['source_id'], 'source_hash' => self::policy()['source_sha256'],
            'source_article' => $row['locator'], 'exception_article' => $simples ? 'art1 §1' : null,
            'proof_kind' => 'PRIMARY_ACT_REVIEWED', 'full_act_review_required' => false,
            'iss_document_obligations' => 'INDEPENDENT', 'layout_deployment' => 'INDEPENDENT',
            'document_obligation_complete' => false, 'production_ready' => false,
            ...($document === 'DERE' && $profile === 'MONTHLY' ? ['monthly_schedule' => self::policy()['dere_monthly']] : [])];
    }

    /** @param array<string, mixed> $codes
     * @return array<string, mixed>
     */
    public function nfse(string $serviceCode, string $regime, LocalDate $on, DateTimeImmutable $knownAt, array $codes, bool $platformQualified = false): array
    {
        if (preg_match('/\A[0-9]{6}\z/', $serviceCode) !== 1) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DOCUMENT_SERVICE_CLASSIFICATION_REQUIRED'];
        }
        $subitem = substr($serviceCode, 0, 4);
        $profile = $platformQualified ? 'PLATFORM_ART22' : (in_array($subitem, ['0103', '0105', '0109'], true)
            ? 'ISS_0103_0105_0109' : ($subitem === '1601' ? 'ISS_1601' : 'ISS_GENERAL'));

        return $this->resolve('NFSE', $profile, $regime, $on, $knownAt, $codes);
    }
}
