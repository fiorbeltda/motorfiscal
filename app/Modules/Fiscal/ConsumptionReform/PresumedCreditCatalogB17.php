<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\DecimalValue;

/**
 * B17: hipóteses de crédito presumido da LC 214/2025 (4B.5.1), com trechos vigentes do texto compilado
 * do Planalto (resources/fiscal/sources/official/lc214-planalto-observed-20261103.htm).
 * Percentuais legais literais (arts. 170, 447, 465) são calculados; percentuais definidos por ato
 * (arts. 168, 169) ou por alíquota de referência (art. 171) exigem parâmetro com fonte. Parâmetro
 * sintético é calculado só como teste e marcado como não oficial. O catálogo não apropria crédito.
 */
final readonly class PresumedCreditCatalogB17
{
    public const VERSION = 'presumed-credit-catalog-b17';

    public const SOURCE_SHA256 = '0a0d190bc39689f50b199646b51dd84d01f6d7392c179287b46909c3d4f4673c';

    /** hypothesis => [basis, taxes, rate mode, literal excerpt, usage restriction] */
    public const HYPOTHESES = [
        'ZFM_NATIONAL_INDUSTRIALIZED_GOOD' => ['LC214 art. 447', ['IBS'], 'REGION_7_5_OR_13_5',
            'crédito presumido de IBS relativo à aquisição de bem material industrializado de origem nacional contemplado pela redução a zero da alíquota do IBS nos termos do art. 445 desta Lei Complementar.',
            'LC214 art. 452: só compensação com IBS/CBS devidos pelo contribuinte; vedado ressarcimento; prazo de 5 anos'],
        'ALC_NATIONAL_INDUSTRIALIZED_GOOD' => ['LC214 art. 465', ['IBS'], 'REGION_7_5_OR_13_5',
            'crédito presumido de IBS relativo à aquisição de bem material industrializado de origem nacional contemplado pela redução a zero da alíquota do IBS nos termos do art. 463 desta Lei Complementar.',
            'LC214 art. 468: só compensação com IBS/CBS devidos pelo contribuinte'],
        'ZFM_INTERMEDIATE_GOOD_INCENTIVIZED_INDUSTRY' => ['LC214 art. 449', ['IBS'], 'LITERAL_7_5',
            'Fica concedido à indústria incentivada na Zona Franca de Manaus, sujeita ao regime regular do IBS e da CBS, crédito presumido de IBS relativo à aquisição de bem intermediário produzido na referida área',
            'LC214 art. 452: só compensação com IBS/CBS devidos pelo contribuinte; vedado ressarcimento; prazo de 5 anos'],
        'ZFM_IMPORT_FOR_PRESENTIAL_RESALE' => ['LC214 art. 444', ['IBS'], 'HALF_IMPORT_IBS_RATE',
            'crédito presumido de IBS relativo à importação de bem material para revenda presencial na Zona Franca de Manaus.',
            'LC214 art. 444, §2º: deduzido do IBS devido na importação; art. 452'],
        'ALC_IMPORT_FOR_PRESENTIAL_RESALE' => ['LC214 art. 462', ['IBS'], 'HALF_IMPORT_IBS_RATE',
            'crédito presumido de IBS relativo à importação de bem material para revenda presencial na Área de Livre Comércio.',
            'LC214 art. 462, §2º: deduzido do IBS devido na importação; art. 468'],
        'ALC_INDUSTRY_OUTPUT_CBS' => ['LC214 art. 467', ['CBS'], 'ALC_INDUSTRY_6',
            'créditos presumidos de CBS relativo à operação que destine ao território nacional bem material produzido pela própria indústria na referida área nos termos do projeto econômico aprovado.',
            'LC214 art. 468: só compensação com IBS/CBS devidos pelo contribuinte'],
        'ZFM_INCENTIVIZED_INDUSTRY_OUTPUT' => ['LC214 art. 450 (redação LC 227/2026)', ['IBS', 'CBS'], 'ZFM_INDUSTRY_450',
            'São concedidos à indústria incentivada na Zona Franca de Manaus créditos presumidos de IBS e de CBS relativos à operação que destine ao território nacional',
            'LC214 art. 452: só compensação com IBS/CBS devidos pelo contribuinte; vedado ressarcimento'],
        'AUTOMOTIVE_PROJECT_ART11C_LAW9440' => ['LC214 arts. 309 e 311', ['CBS'], 'AUTOMOTIVE_311',
            'o crédito presumido de que trata o art. 309 desta Lei Complementar será calculado mediante a aplicação dos seguintes percentuais sobre o valor das vendas no mercado interno, em cada mês',
            'LC214 art. 310: não cumulável com outros benefícios federais da CBS'],
        'AUTOMOTIVE_PROJECT_LAW9826' => ['LC214 arts. 309 e 312', ['CBS'], 'EXTERNAL_FORMULA_FACTORS',
            'o crédito presumido de que trata o art. 309 desta Lei Complementar corresponderá ao produto da multiplicação dos seguintes fatores',
            'LC214 art. 310'],
        'TRANSITION_STOCK_2027' => ['LC214 art. 381', ['CBS'], 'EXECUTIVE_ACT_REQUIRED',
            'O contribuinte sujeito ao regime regular da CBS poderá apropriar crédito presumido sobre o estoque de bens materiais existente em 1º de janeiro de 2027',
            'LC214 art. 381, §2º: ato do Poder Executivo disciplinará a verificação do estoque'],
        'TRANSITION_DEPRECIATION_PIS_COFINS' => ['LC214 art. 380', ['CBS'], 'LEGACY_DATA_REQUIRED',
            'deverão permanecer sendo apropriados, como créditos presumidos da CBS',
            'Saldo de créditos de PIS/Cofins em apropriação por depreciação/amortização (dado legado)'],
        'SOLID_WASTE_FROM_INCENTIVIZED_COLLECTOR' => ['LC214 art. 170', ['IBS', 'CBS'], 'ART170_SCHEDULE',
            'I - para o crédito presumido de IBS: a) em 2029, 1,3% (um inteiro e três décimos por cento); b) em 2030, 2,6% (dois inteiros e seis décimos por cento); c) em 2031, 3,9% (três inteiros e nove décimos por cento); d) em 2032, 5,2% (cinco inteiros e dois décimos por cento); e) a partir de 2033, 13% (treze por cento); e II - para o crédito presumido de CBS, 7% (sete por cento).',
            'Dedução do IBS/CBS devidos pelo contribuinte'],
        'RURAL_PRODUCER_NON_CONTRIBUTOR' => ['LC214 art. 168', ['IBS', 'CBS'], 'ANNUAL_JOINT_ACT_PARAMETER',
            'O contribuinte de IBS e de CBS sujeito ao regime regular poderá apropriar créditos presumidos dos referidos tributos relativos às aquisições de bens e serviços de produtor rural ou de produtor rural integrado, não contribuintes',
            'Conforme arts. 47 a 56'],
        'AUTONOMOUS_CARGO_CARRIER_INDIVIDUAL' => ['LC214 art. 169', ['IBS', 'CBS'], 'ANNUAL_JOINT_ACT_PARAMETER',
            'O contribuinte de IBS e de CBS sujeito ao regime regular poderá apropriar créditos presumidos dos referidos tributos relativos às aquisições de serviço de transporte de carga de transportador autônomo pessoa física',
            'Conforme arts. 47 a 56'],
        'USED_MOVABLE_GOOD_FOR_RESALE' => ['LC214 art. 171', ['IBS', 'CBS'], 'REFERENCE_RATE_PARAMETER',
            'O contribuinte de IBS e de CBS sujeito ao regime regular poderá apropriar créditos presumidos dos referidos tributos relativos às aquisições, para revenda, de bem móvel usado de pessoa física que não seja contribuinte dos referidos tributos ou que seja inscrita como MEI.',
            'Conforme arts. 47 a 56'],
    ];

    /** LC214 art. 170, §3º: acquisitions excluded from the solid-waste presumed credit. */
    public const SOLID_WASTE_EXCLUSIONS = ['PESTICIDES', 'HOME_MEDICINES', 'BATTERIES', 'TIRES', 'HOUSEHOLD_ELECTRONICS',
        'LUBRICATING_OILS', 'FLUORESCENT_LAMPS', 'COPPER_SCRAP'];

    /**
     * @param  array<string, mixed>  $in
     * @return array<string, mixed>
     */
    public function qualify(array $in): array
    {
        $key = $in['hypothesis'] ?? null;
        if (! is_string($key) || ! isset(self::HYPOTHESES[$key])) {
            return $this->out('INVALID', 'B17_HYPOTHESIS_NOT_IN_CATALOGUE', null);
        }
        [, $taxes, $mode] = self::HYPOTHESES[$key];
        $allowedRegimes = $mode === 'HALF_IMPORT_IBS_RATE' ? ['REGULAR', 'SIMPLES'] : ['REGULAR'];
        if (! in_array($in['acquirer_regime'] ?? null, $allowedRegimes, true)) {
            return $this->out('NOT_APPLICABLE', 'B17_ACQUIRER_NOT_REGULAR_REGIME', $key);
        }
        if (! is_string($in['base_amount'] ?? null) || preg_match('/\A\d{1,15}(\.\d{1,2})?\z/', $in['base_amount']) !== 1
            || ! is_string($in['acquisition_date'] ?? null) || preg_match('/\A(\d{4})-\d{2}-\d{2}\z/', $in['acquisition_date'], $m) !== 1) {
            return $this->out('INVALID', 'B17_BASE_OR_DATE_INVALID', $key);
        }
        if (! is_string($in['document_id'] ?? null) || trim($in['document_id']) === '') {
            return $this->out('ACTION_REQUIRED', 'B17_DOCUMENT_REQUIRED', $key);
        }
        $year = (int) $m[1];
        $rates = [];
        $preset = [];
        $synthetic = false;
        switch ($mode) {
            case 'REGION_7_5_OR_13_5':
                if (($in['habilitated'] ?? false) !== true || ($in['zero_rate_operation'] ?? false) !== true
                    || ! is_string($in['habilitation_evidence_id'] ?? null) || trim($in['habilitation_evidence_id']) === '') {
                    return $this->out('ACTION_REQUIRED', 'B17_HABILITATION_AND_ZERO_RATE_PROOF_REQUIRED', $key);
                }
                $rates['IBS'] = match ($in['origin_region'] ?? null) {
                    'SOUTH_SOUTHEAST_EXCEPT_ES' => '7.5',
                    'NORTH_NORTHEAST_CENTERWEST_OR_ES' => '13.5',
                    default => null,
                };
                if ($rates['IBS'] === null) {
                    return $this->out('ACTION_REQUIRED', 'B17_ORIGIN_REGION_REQUIRED', $key);
                }
                break;
            case 'LITERAL_7_5':
                if (($in['habilitated'] ?? false) !== true || ($in['zero_rate_operation'] ?? false) !== true
                    || ($in['used_in_final_goods_production'] ?? false) !== true
                    || ! is_string($in['habilitation_evidence_id'] ?? null) || trim($in['habilitation_evidence_id']) === '') {
                    return $this->out('ACTION_REQUIRED', 'B17_INCENTIVIZED_INDUSTRY_PROOF_REQUIRED', $key);
                }
                if (($in['industrialization_by_order_return'] ?? false) === true) {
                    // Art. 449, §2º: on return of goods industrialized by order, only the value added.
                    if (! is_string($in['value_added_amount'] ?? null) || preg_match('/\A\d{1,15}(\.\d{1,2})?\z/', $in['value_added_amount']) !== 1) {
                        return $this->out('ACTION_REQUIRED', 'B17_VALUE_ADDED_REQUIRED_ART449_2', $key);
                    }
                    $in['base_amount'] = $in['value_added_amount'];
                }
                $rates['IBS'] = '7.5';
                break;
            case 'HALF_IMPORT_IBS_RATE':
                $param = $in['rate_parameters'] ?? null;
                if (($in['habilitated'] ?? false) !== true || ! is_string($in['habilitation_evidence_id'] ?? null) || trim($in['habilitation_evidence_id']) === '') {
                    return $this->out('ACTION_REQUIRED', 'B17_HABILITATION_AND_ZERO_RATE_PROOF_REQUIRED', $key);
                }
                if (! is_array($param) || ! is_string($param['source_id'] ?? null) || trim($param['source_id']) === ''
                    || ! in_array($param['source_kind'] ?? null, ['OFFICIAL', 'SYNTHETIC_TEST'], true)
                    || ! is_string($param['import_ibs_percent'] ?? null) || preg_match('/\A\d{1,3}(\.\d{1,4})?\z/', $param['import_ibs_percent']) !== 1) {
                    return $this->out('ACTION_REQUIRED', 'B17_IMPORT_IBS_RATE_PARAMETER_REQUIRED', $key);
                }
                $rates['IBS'] = DecimalValue::fromString($param['import_ibs_percent'])->dividedBy(DecimalValue::fromString('2'), 6)->value();
                $synthetic = $param['source_kind'] === 'SYNTHETIC_TEST';
                break;
            case 'ALC_INDUSTRY_6':
            case 'ZFM_INDUSTRY_450':
                if (($in['habilitated'] ?? false) !== true || ! is_string($in['habilitation_evidence_id'] ?? null) || trim($in['habilitation_evidence_id']) === '') {
                    return $this->out('ACTION_REQUIRED', 'B17_HABILITATION_AND_ZERO_RATE_PROOF_REQUIRED', $key);
                }
                // Arts. 450, §3º and 467, §2º: not for non-incident, exempt, zero-rate, suspended or deferred operations, nor art. 441 goods.
                if (($in['operation_standard_incidence'] ?? null) !== true || ($in['art441_excluded_good'] ?? null) !== false) {
                    return $this->out('NOT_APPLICABLE', 'B17_OPERATION_EXCLUDED_ART450_3_OR_467_2', $key);
                }
                if ($mode === 'ALC_INDUSTRY_6') {
                    $rates['CBS'] = '6';
                    break;
                }
                if (($in['sale_to_union_art473'] ?? false) === true) {
                    return $this->out('ACTION_REQUIRED', 'B17_ART450_5_UNION_SALE_NOT_MODELED', $key);
                }
                $ibsPercent = match ($in['good_category'] ?? null) {
                    'FINAL_CONSUMER_GOOD' => '55', 'CAPITAL_GOOD' => '75', 'INTERMEDIATE_GOOD' => '90.25', 'IT_GOOD_OR_ICMS_100' => '100', default => null,
                };
                if ($ibsPercent === null || ! is_string($in['ibs_debit_balance'] ?? null) || preg_match('/\A\d{1,15}(\.\d{1,2})?\z/', $in['ibs_debit_balance']) !== 1) {
                    return $this->out('ACTION_REQUIRED', 'B17_ART450_CATEGORY_AND_IBS_DEBIT_BALANCE_REQUIRED', $key);
                }
                $ibs = DecimalValue::fromString($in['ibs_debit_balance'])->multipliedBy(DecimalValue::fromString($ibsPercent))
                    ->dividedBy(DecimalValue::fromString('100'), 10)->rounded(2)->value();
                $rates['CBS'] = ($in['art454_product'] ?? false) === true ? '6' : '2';
                $preset = ['IBS' => $ibs];
                $rates['IBS'] = $ibsPercent.'%_OF_IBS_DEBIT_BALANCE';
                break;
            case 'AUTOMOTIVE_311':
                $month = $in['fruition_month'] ?? null;
                if (($in['habilitated'] ?? false) !== true || ($in['eligible_electric_or_biofuel_hybrid'] ?? false) !== true
                    || ! is_string($in['habilitation_evidence_id'] ?? null) || trim($in['habilitation_evidence_id']) === '' || ! is_int($month) || $month < 1) {
                    return $this->out('ACTION_REQUIRED', 'B17_AUTOMOTIVE_HABILITATION_ELIGIBILITY_AND_MONTH_REQUIRED', $key);
                }
                if (($in['full_cbs_domestic_sale'] ?? null) !== true) {
                    return $this->out('NOT_APPLICABLE', 'B17_AUTOMOTIVE_SALE_EXCLUDED_ART311_2', $key);
                }
                if ($year > 2032 || $month > 60) {
                    return $this->out('NOT_APPLICABLE', 'B17_AUTOMOTIVE_CREDIT_EXTINCT_OR_PERIOD_EXCEEDED', $key);
                }
                $initial = $month <= 12 ? '11.60' : ($month <= 48 ? '10' : '8.70');
                // Art. 311, §3º: reduced by 20% of the initial percentage per year between 2029 and 2032 (engineering reading).
                $factor = ['2029' => '0.8', '2030' => '0.6', '2031' => '0.4', '2032' => '0.2'][(string) $year] ?? '1';
                $rates['CBS'] = DecimalValue::fromString($initial)->multipliedBy(DecimalValue::fromString($factor))->value();
                break;
            case 'EXTERNAL_FORMULA_FACTORS':
                return $this->out('ACTION_REQUIRED', 'B17_ART312_FACTORS_FROM_EXTERNAL_LEGISLATION_REQUIRED', $key);
            case 'EXECUTIVE_ACT_REQUIRED':
                return $this->out('ACTION_REQUIRED', 'B17_ART381_EXECUTIVE_ACT_FOR_STOCK_VERIFICATION_REQUIRED', $key);
            case 'LEGACY_DATA_REQUIRED':
                return $this->out('ACTION_REQUIRED', 'B17_ART380_LEGACY_PIS_COFINS_SCHEDULE_REQUIRED', $key);
            case 'ART170_SCHEDULE':
                $material = $in['waste_category'] ?? null;
                if (in_array($material, self::SOLID_WASTE_EXCLUSIONS, true)
                    && ! ($material === 'LUBRICATING_OILS' && ($in['anp_authorized_rerefiner_or_collector'] ?? false) === true)) {
                    return $this->out('NOT_APPLICABLE', 'B17_SOLID_WASTE_EXCLUDED_ART170_3', $key);
                }
                if (! is_string($material) || trim($material) === '') {
                    return $this->out('ACTION_REQUIRED', 'B17_WASTE_CATEGORY_REQUIRED', $key);
                }
                if ($year < 2027) {
                    return $this->out('ACTION_REQUIRED', 'B17_ART170_PRODUCTION_OF_EFFECTS_NOT_QUALIFIED_BEFORE_2027', $key);
                }
                $rates['CBS'] = '7';
                $rates['IBS'] = match (true) {
                    $year >= 2033 => '13', $year === 2032 => '5.2', $year === 2031 => '3.9', $year === 2030 => '2.6', $year === 2029 => '1.3',
                    default => null,
                };
                break;
            default:
                $param = $in['rate_parameters'] ?? null;
                if (! is_array($param) || ! is_string($param['source_id'] ?? null) || trim($param['source_id']) === ''
                    || ! in_array($param['source_kind'] ?? null, ['OFFICIAL', 'SYNTHETIC_TEST'], true)) {
                    return $this->out('ACTION_REQUIRED', $mode === 'ANNUAL_JOINT_ACT_PARAMETER'
                        ? 'B17_ANNUAL_JOINT_ACT_PERCENT_REQUIRED' : 'B17_REFERENCE_RATE_PARAMETER_REQUIRED', $key);
                }
                foreach ($taxes as $tax) {
                    $value = $param[strtolower($tax).'_percent'] ?? null;
                    if (! is_string($value) || preg_match('/\A\d{1,3}(\.\d{1,4})?\z/', $value) !== 1) {
                        return $this->out('ACTION_REQUIRED', 'B17_RATE_PARAMETER_INVALID', $key);
                    }
                    $rates[$tax] = $value;
                }
                $synthetic = $param['source_kind'] === 'SYNTHETIC_TEST';
        }
        $amounts = [];
        foreach ($taxes as $tax) {
            if (isset($preset[$tax])) {
                $amounts[$tax] = $preset[$tax];

                continue;
            }
            $amounts[$tax] = ($rates[$tax] ?? null) === null ? null : DecimalValue::fromString($in['base_amount'])
                ->multipliedBy(DecimalValue::fromString($rates[$tax]))->dividedBy(DecimalValue::fromString('100'), 10)->rounded(2)->value();
        }

        return $this->out($synthetic ? 'PRESUMED_CREDIT_SYNTHETIC_TEST_ONLY' : 'PRESUMED_CREDIT_HYPOTHESIS_QUALIFIED',
            'B17_PRESUMED_CREDIT_COMPUTED', $key, ['percent' => $rates, 'amounts' => $amounts,
                'not_available_taxes' => array_keys(array_filter($amounts, fn ($a) => $a === null)),
                'usage_restriction' => self::HYPOTHESES[$key][4], 'official_decision' => ! $synthetic]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function out(string $status, string $reason, ?string $key, array $extra = []): array
    {
        $h = $key === null ? null : self::HYPOTHESES[$key];
        $o = ['status' => $status, 'reason' => $reason, 'version' => self::VERSION, 'hypothesis' => $key,
            'legal_basis' => $h[0] ?? null, 'excerpt_sha256' => $h === null ? null : hash('sha256', $h[3]),
            'source_sha256' => self::SOURCE_SHA256, 'official_decision' => false, ...$extra,
            'credit_appropriated' => false, 'production_ready' => false];

        return [...$o, 'result_hash' => CanonicalJson::hash($o)];
    }
}
