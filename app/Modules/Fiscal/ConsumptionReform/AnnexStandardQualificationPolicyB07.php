<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;

/**
 * B07 (decisão do usuário Q-B06-1, alternativa B, 03/11/2026): gate e contrato da
 * qualificação exigida quando se declara tributação integral para serviço cuja NBS
 * consta dos Anexos II/III da LC 214/2025 (arts. 129 e 130).
 *
 * Este arquivo só define o contrato e o gate. A seleção de alíquota é implementada
 * pelo Agente A em ConsumptionReformEngine.php. Nenhum catálogo publicado até o
 * checkpoint 143 carrega o bloco, logo o comportamento histórico é preservado.
 */
final readonly class AnnexStandardQualificationPolicyB07
{
    public const KEY = 'annex_standard_qualification_b07';

    public const POLICY = [
        'version' => 'B07',
        'legal_basis' => 'LC214 arts. 129 e 130; Anexos II e III (NBS e descrição do item)',
        'applies_when' => 'rtc.tax_treatment=STANDARD e rtc.service_nbs_code listado em item dos Anexos II/III',
        'required_facts' => ['rtc.annex_service_description_confirmed', 'rtc.annex_noncorrespondence_justification',
            'rtc.annex_noncorrespondence_evidence_id', 'rtc.annex_nbs_classification_confirmed',
            'rtc.service_nbs_code', 'rtc.service_nbs_evidence_id'],
        'outcomes' => [
            'RTC_ANNEX_SERVICE_NBS_REQUIRED' => 'ACTION_REQUIRED',
            'RTC_ANNEX_STANDARD_CONTRADICTS_CONFIRMED_DESCRIPTION' => 'INVALID',
            'RTC_ANNEX_NONCORRESPONDENCE_PROOF_MISSING' => 'ACTION_REQUIRED',
            'RTC_ANNEX_NONCORRESPONDENCE_JUSTIFICATION_INSUFFICIENT' => 'ACTION_REQUIRED',
            'RTC_ANNEX_NBS_RECLASSIFICATION_REQUIRED' => 'ACTION_REQUIRED',
            'RTC_ANNEX_NONCORRESPONDENCE_QUALIFIED' => 'RESOLVED_STANDARD',
        ],
        'never' => ['select STANDARD by default', 'presume REDUCED_60 without the annex proof'],
    ];

    /** @param array<string, mixed> $codes */
    public static function required(array $codes): bool
    {
        $policy = $codes[self::KEY] ?? null;

        return is_array($policy) && CanonicalJson::hash($policy) === CanonicalJson::hash(self::POLICY);
    }
}
