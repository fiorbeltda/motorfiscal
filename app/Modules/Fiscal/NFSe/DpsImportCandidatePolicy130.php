<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\NFSe;

use App\Modules\Fiscal\ConsumptionReform\DpsLayoutPolicy127;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use DateTimeImmutable;

final readonly class DpsImportCandidatePolicy130
{
    public const CONTRACT = 'DPS-IMPORT-CANDIDATE-2026.10.22-130';

    public const AVAILABLE_AT = '2026-10-22T17:33:37.832938+00:00';

    /** @return array<string, mixed> */
    public static function policy(): array
    {
        return ['contract' => self::CONTRACT, 'available_at' => self::AVAILABLE_AT,
            'schema_sha256' => DpsLayoutPolicy127::SOURCE_SHA256,
            'root_sha256' => DpsLayoutPolicy127::ROOT_SHA256,
            'source_observations' => [
                ['file' => 'import130-decreto12955-observed20261022.html',
                    'url' => 'https://www2.camara.leg.br/legin/fed/decret/2026/decreto-12955-29-abril-2026-799019-normaatualizada-pe.html',
                    'sha256' => '8396d56d14fb30a386c7548047701a6c310cb3a60da1a0b6893b6df5b8d90235',
                    'observed_at' => '2026-10-22T17:33:35.810698+00:00'],
                ['file' => 'import130-nt009-notice-observed20261022.html',
                    'url' => 'https://www.gov.br/nfse/pt-br/noticias/publicado-o-ato-tecnico-conjunto-rfb-cgibs-no-7-aprovando-a-versao-1-01-da-nota-tecnica-009-nfs-e-e-a-nota-tecnica-010-da-nfs-e-via',
                    'sha256' => '4979189118ccc6f7f9d82a6e963b91942f80b216f913155a9c2f040d73d7d2ba',
                    'observed_at' => '2026-10-22T17:33:36.420768+00:00'],
                ['file' => 'import130-rtc-schedule-observed20261022.html',
                    'url' => 'https://www.gov.br/receitafederal/pt-br/acesso-a-informacao/acoes-e-programas/programas-e-atividades/reforma-tributaria-do-consumo/orientacoes-da-reforma-tributaria',
                    'sha256' => '9d560c4d280cddc01e3677facd16762307ebb5267d02dc0af4eb617dfb2a4116',
                    'observed_at' => self::AVAILABLE_AT],
            ],
            'scope' => 'LOCAL_IMPORT_CANDIDATE_FROM_REPLAYED_NONFINAL129',
            'unresolved' => ['IMPORT_DOCUMENT_OBLIGATION', 'MUNICIPAL_DOCUMENT_REQUIREMENTS',
                'NT009_NEW_INVOICE_AND_FX_FIELDS_NOT_IN_BOUND_XSD', 'OTHER_IMPORT_PROFILES_AND_DOCUMENT_BASE_FX'],
            'fiscal_decision_final' => false, 'document_obligation_complete' => false, 'production_ready' => false];
    }

    /** @param array<string, mixed> $codes
     * @return array<string, mixed>
     */
    public function qualify(array $codes, DateTimeImmutable $knownAt): array
    {
        $policy = $codes['import_candidate_policy130'] ?? null;
        $valid = is_array($policy) && CanonicalJson::hash($policy) === CanonicalJson::hash(self::policy())
            && ($codes['dps_xsd']['source_sha256'] ?? null) === DpsLayoutPolicy127::SOURCE_SHA256
            && $knownAt >= new DateTimeImmutable(self::AVAILABLE_AT);

        return ['status' => $valid ? 'RESOLVED' : 'ACTION_REQUIRED',
            'reason' => $valid ? 'RTC_IMPORT130_SOURCE_POLICY_BOUND' : 'RTC_IMPORT130_SOURCE_POLICY_NOT_BOUND',
            'fiscal_decision_final' => false, 'production_ready' => false];
    }
}
