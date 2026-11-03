<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;

/**
 * B13: vedações legais de crédito de IBS/CBS por regime da operação adquirida (4B.5.7).
 *
 * Cada entrada traz o trecho literal vigente da LC 214/2025 no texto compilado do Planalto
 * (resources/fiscal/sources/official/lc214-planalto-observed-20261103.htm), conferido como único e
 * não substituído por redação posterior (LC 227/2026). O resultado nunca concede crédito: ausência de
 * vedação neste catálogo não dispensa as demais condições (arts. 47 a 56).
 */
final readonly class AcquirerCreditProhibitionCatalogB13
{
    public const VERSION = 'acquirer-credit-prohibitions-b13';

    public const SOURCE_FILE = 'lc214-planalto-observed-20261103.htm';

    public const SOURCE_SHA256 = '0a0d190bc39689f50b199646b51dd84d01f6d7392c179287b46909c3d4f4673c';

    /** regime => [article, scope, literal excerpt] */
    public const ENTRIES = [
        'PERSONAL_USE' => ['LC214 art. 57, §5º', 'ACQUIRER', 'Em relação aos bens e serviços de uso ou consumo pessoal de que trata este artigo, fica vedada a apropriação de créditos.'],
        'MONOPHASIC_FUEL_FOR_RESALE' => ['LC214 art. 180', 'ACQUIRER', 'É vedada a apropriação de créditos em relação às aquisições de combustíveis sujeitos à incidência única do IBS e da CBS, quando destinadas à distribuição, à comercialização ou à revenda.'],
        'FINANCIAL_SERVICE' => ['LC214 art. 199', 'ACQUIRER', 'Fica vedada a apropriação de créditos do IBS e da CBS na aquisição dos serviços financeiros de que tratam os incisos I a V do caput do art. 182 da Lei Complementar que não estiverem permitidos expressamente nos arts. 194 a 198.'],
        'INVESTOR_LEVEL_FUND_SERVICE' => ['LC214 art. 211', 'ACQUIRER', 'ficam sujeitos ao IBS e à CBS pelas alíquotas previstas no art. 189 desta Lei Complementar, vedado o crédito do IBS e da CBS para o adquirente dos serviços.'],
        'PRIVATE_PENSION' => ['LC214 art. 226', 'ACQUIRER', 'Fica vedado o crédito de IBS e de CBS na aquisição de serviços de previdência complementar.'],
        'HEALTH_PLAN' => ['LC214 art. 238', 'ACQUIRER', 'Fica vedado o crédito de IBS e de CBS para os adquirentes de planos de assistência à saúde.'],
        'PET_HEALTH_PLAN' => ['LC214 art. 243', 'ACQUIRER', 'Os planos de assistência à saúde de animais domésticos ficam sujeitos ao disposto nos arts. 234 a 242 desta Lei Complementar, com exceção das alíquotas aplicáveis, que serão nacionalmente uniformes e corresponderão à soma das alíquotas de referência de cada esfera federativa, reduzidas em 30% (trinta por cento), vedado o crédito ao adquirente.'],
        'BETTING' => ['LC214 art. 247', 'ACQUIRER', 'Fica vedado o crédito de IBS e de CBS aos apostadores dos concursos de prognósticos.'],
        'BAR_RESTAURANT_FOOD' => ['LC214 art. 276', 'ACQUIRER', 'Fica vedada a apropriação de créditos do IBS e da CBS pelos adquirentes de alimentação e bebidas fornecidas pelos bares e restaurantes, inclusive lanchonetes.'],
        'HOTEL_AMUSEMENT_PARK' => ['LC214 art. 283', 'ACQUIRER', 'Fica vedada a apropriação de créditos de IBS e de CBS pelo adquirente dos serviços de hotelaria, parques de diversão e parques temáticos.'],
        'URBAN_COLLECTIVE_TRANSPORT' => ['LC214 art. 285, III', 'ACQUIRER', 'III - fica vedada a apropriação de créditos de IBS e de CBS pelo adquirente dos serviços de transporte.'],
        'URBAN_TRANSPORT_SUPPLIER_INPUT' => ['LC214 art. 285, II', 'TRANSPORT_SUPPLIER', 'II - fica vedada a apropriação de créditos de IBS e de CBS nas aquisições pelo fornecedor do serviço de transporte;'],
        'SAF_SUPPLY' => ['LC214 art. 293, §6º', 'ACQUIRER', '§ 6º Fica vedada a apropriação de créditos do IBS e da CBS para os adquirentes de bens e serviços da SAF, com exceção da aquisição de direitos desportivos de atletas'],
        'SPECIFIC_REGIME_DEDUCTED_VALUE' => ['LC214 art. 303', 'ACQUIRER', 'Fica vedada a apropriação de crédito de IBS e CBS sobre os valores que forem deduzidos da base de cálculo do IBS e da CBS nos regimes específicos, assim como a dedução em duplicidade de qualquer valor.'],
    ];

    public const HEALTH_PLAN_EXCEPTION = [
        'LC214 art. 238, parágrafo único; art. 57, §3º, IV, "f" (redação da LC 227/2026)',
        'O disposto no caput deste artigo não se aplica à hipótese de que trata a alínea “f” do inciso IV do § 3º do art. 57 desta Lei Complementar',
        'f) serviços de planos de assistência à saúde destinados a empregados e seus dependentes em decorrência de acordo ou convenção coletiva de trabalho, sendo os créditos na aquisição desses serviços equivalentes aos respectivos débitos do fornecedor apurados e extintos de acordo com o disposto no regime específico de planos de assistência à saúde;',
    ];

    /**
     * @param  array<string, mixed>  $operation  acquired_regime, role, destination and exception claims
     * @return array<string, mixed>
     */
    public function qualify(array $operation): array
    {
        $regime = $operation['acquired_regime'] ?? null;
        if ($regime === 'GENERAL') {
            return $this->result('NOT_PROHIBITED_BY_STATUTORY_CATALOGUE', 'B13_NO_STATUTORY_PROHIBITION_IN_CATALOGUE', null);
        }
        if (! is_string($regime) || ! isset(self::ENTRIES[$regime])) {
            return $this->result('INVALID', 'B13_REGIME_NOT_IN_CATALOGUE', null);
        }
        [$basis, $scope] = self::ENTRIES[$regime];
        $role = $operation['role'] ?? 'ACQUIRER';
        if ($scope !== $role) {
            return $this->result('NOT_PROHIBITED_BY_STATUTORY_CATALOGUE', 'B13_PROHIBITION_SCOPE_DOES_NOT_MATCH_ROLE', null);
        }
        if ($regime === 'MONOPHASIC_FUEL_FOR_RESALE') {
            $destination = $operation['fuel_destination'] ?? null;
            if (! in_array($destination, ['DISTRIBUTION', 'COMMERCIALIZATION', 'RESALE', 'OWN_USE'], true)) {
                return $this->result('ACTION_REQUIRED', 'B13_FUEL_DESTINATION_REQUIRED', null, ['missing_facts' => ['fuel_destination']]);
            }
            if ($destination === 'OWN_USE') {
                return $this->result('NOT_PROHIBITED_BY_STATUTORY_CATALOGUE', 'B13_FUEL_NOT_FOR_RESALE_SEE_ART180_PARAGRAPHS', null);
            }
        }
        if ($regime === 'SAF_SUPPLY' && ($operation['saf_sports_rights_acquisition'] ?? false) === true) {
            if (! $this->evidence($operation)) {
                return $this->result('ACTION_REQUIRED', 'B13_EXCEPTION_EVIDENCE_REQUIRED', $regime);
            }

            return $this->result('NOT_PROHIBITED_BY_STATUTORY_CATALOGUE', 'B13_SAF_SPORTS_RIGHTS_EXCEPTION', $regime);
        }
        if ($regime === 'FINANCIAL_SERVICE' && isset($operation['expressly_permitted_article'])) {
            if (! in_array($operation['expressly_permitted_article'], ['194', '195', '196', '197', '198'], true) || ! $this->evidence($operation)) {
                return $this->result('ACTION_REQUIRED', 'B13_FINANCIAL_PERMISSION_ARTICLE_AND_EVIDENCE_REQUIRED', $regime);
            }

            return $this->result('NOT_PROHIBITED_BY_STATUTORY_CATALOGUE', 'B13_FINANCIAL_SERVICE_EXPRESSLY_PERMITTED_ARTS_194_198', $regime,
                ['permitted_by' => 'LC214 art. '.$operation['expressly_permitted_article']]);
        }
        if ($regime === 'HEALTH_PLAN' && ($operation['employee_collective_agreement_plan'] ?? false) === true) {
            if (($operation['acquirer_regime'] ?? null) !== 'REGULAR' || ! $this->evidence($operation)) {
                return $this->result('ACTION_REQUIRED', 'B13_HEALTH_PLAN_EXCEPTION_REQUIRES_REGULAR_ACQUIRER_AND_AGREEMENT_EVIDENCE', $regime);
            }

            return $this->result('CREDIT_LIMITED_TO_SUPPLIER_EXTINGUISHED_DEBITS', 'B13_EMPLOYEE_HEALTH_PLAN_EXCEPTION', $regime,
                ['exception_basis' => self::HEALTH_PLAN_EXCEPTION[0], 'exception_excerpt_sha256' => hash('sha256', self::HEALTH_PLAN_EXCEPTION[1])]);
        }

        return $this->result('CREDIT_PROHIBITED', 'B13_STATUTORY_CREDIT_PROHIBITION', $regime);
    }

    /** @param array<string, mixed> $operation */
    private function evidence(array $operation): bool
    {
        return is_string($operation['exception_evidence_id'] ?? null) && trim($operation['exception_evidence_id']) !== '';
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function result(string $status, string $reason, ?string $regime, array $extra = []): array
    {
        $entry = $regime === null ? null : self::ENTRIES[$regime];
        $out = ['status' => $status, 'reason' => $reason, 'version' => self::VERSION, 'regime' => $regime,
            'legal_basis' => $entry[0] ?? null, 'excerpt_sha256' => $entry === null ? null : hash('sha256', $entry[2]),
            'source_sha256' => self::SOURCE_SHA256, ...$extra,
            'credit_granted' => false, 'other_conditions_still_apply' => true, 'production_ready' => false];

        return [...$out, 'result_hash' => CanonicalJson::hash($out)];
    }
}
