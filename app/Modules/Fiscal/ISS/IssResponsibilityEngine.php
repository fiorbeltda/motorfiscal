<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ISS;

use App\Modules\Fiscal\Classification\CanonicalService;
use App\Modules\Fiscal\Core\Facts\FactBag;

final class IssResponsibilityEngine
{
    private const NATIONAL_CUSTOMER_RESPONSIBILITY_CODES = [
        '03.05',
        '07.02',
        '07.04',
        '07.05',
        '07.09',
        '07.10',
        '07.12',
        '07.16',
        '07.17',
        '07.19',
        '11.02',
        '17.05',
        '17.10',
    ];

    private const LEGAL_ENTITY_CUSTOMER_TYPES = ['PJ', 'PJ_IMMUNE', 'PJ_EXEMPT', 'PUBLIC_ADMINISTRATION'];

    public function resolve(
        CanonicalService $service,
        FactBag $facts,
        MunicipalFiscalParametersResult $parameters,
        LocationIdentifier $taxLocation,
    ): IssResponsibilityDecision {
        $withholdingPerformed = $facts->has('operation.withholding_performed')
            ? $facts->value('operation.withholding_performed')
            : null;
        if (! is_bool($withholdingPerformed) && $withholdingPerformed !== null) {
            return new IssResponsibilityDecision(null, false, null, null, null, 'ISS_WITHHOLDING_PERFORMED_INVALID');
        }

        if ($facts->value('provider.tax_regime') === 'MEI') {
            return new IssResponsibilityDecision(
                responsibleParty: IssResponsibility::PROVIDER,
                withholdingRequired: false,
                tpRetIssqn: '1',
                nationalRule: 'SIMEI_WITHHOLDING_PROHIBITED',
                municipalRule: null,
                reasonCode: 'ISS_SIMEI_WITHHOLDING_PROHIBITED',
                withholdingPerformed: $withholdingPerformed,
                decision: IssWithholdingDecision::WITHHOLDING_PROHIBITED,
                ruleScope: 'TAX_REGIME',
                legalBasis: ['LC 123/2006', 'Resolução CGSN aplicável ao SIMEI'],
                confidence: 'DETERMINISTIC',
            );
        }

        if ($facts->value('provider.tax_regime') === 'SIMPLES_NACIONAL') {
            if (! $facts->has('provider.simples_iss_fixed_monthly')) {
                return new IssResponsibilityDecision(
                    null,
                    false,
                    null,
                    null,
                    null,
                    'ISS_SIMPLES_FIXED_MONTHLY_STATUS_MISSING',
                    ['provider.simples_iss_fixed_monthly'],
                    $withholdingPerformed,
                    IssWithholdingDecision::MANUAL_REVIEW,
                    'TAX_REGIME',
                    ['Resolução CGSN 140/2018, art. 27'],
                );
            }
            if ($facts->value('provider.simples_iss_fixed_monthly') === true
                && $facts->value('provider.municipality_ibge') === $taxLocation->ibgeCode) {
                return new IssResponsibilityDecision(
                    IssResponsibility::PROVIDER,
                    false,
                    '1',
                    'SIMPLES_FIXED_MONTHLY_SAME_MUNICIPALITY',
                    null,
                    'ISS_SIMPLES_FIXED_MONTHLY_WITHHOLDING_NOT_APPLICABLE',
                    withholdingPerformed: $withholdingPerformed,
                    decision: IssWithholdingDecision::DO_NOT_WITHHOLD,
                    ruleScope: 'TAX_REGIME',
                    legalBasis: ['Resolução CGSN 140/2018, art. 27'],
                    confidence: 'DETERMINISTIC',
                );
            }
        }

        if ($facts->value('operation.illegal_below_minimum_tax_burden') === true) {
            $roleDecision = $this->nationalResponsible($facts, true);
            if ($roleDecision instanceof IssResponsibilityDecision) {
                return $roleDecision;
            }
            if ($roleDecision === null) {
                return new IssResponsibilityDecision(
                    null, false, null, null, null,
                    'ISS_ILLEGAL_BELOW_MINIMUM_RESPONSIBLE_PARTY_UNCONFIRMED',
                    decision: IssWithholdingDecision::MANUAL_REVIEW,
                    ruleScope: 'NATIONAL',
                );
            }
            [$responsible, $tpRet] = $roleDecision;

            return new IssResponsibilityDecision(
                $responsible,
                true,
                $tpRet,
                'LC116_ART3_PAR4_ART6_PAR2_III',
                null,
                'ISS_ILLEGAL_BELOW_MINIMUM_RESPONSIBILITY',
                withholdingPerformed: $withholdingPerformed,
                decision: IssWithholdingDecision::WITHHOLD,
                ruleScope: 'NATIONAL',
                legalBasis: ['LC 116/2003 art. 3º, §4º', 'LC 116/2003 art. 6º, §2º, III', 'LC 116/2003 art. 8º-A'],
                confidence: 'DETERMINISTIC',
            );
        }

        if ($facts->value('operation.service_origin') === 'FOREIGN') {
            if (! $facts->has('operation.issuer_role')) {
                return new IssResponsibilityDecision(
                    null,
                    false,
                    null,
                    null,
                    null,
                    'ISS_IMPORT_RESPONSIBLE_ROLE_MISSING',
                    ['operation.issuer_role'],
                    $withholdingPerformed,
                    IssWithholdingDecision::MANUAL_REVIEW,
                    'NATIONAL',
                    ['LC 116/2003 art. 6º, §2º, I'],
                );
            }
            $responsible = match ($facts->value('operation.issuer_role')) {
                'CUSTOMER' => IssResponsibility::CUSTOMER,
                'INTERMEDIARY' => IssResponsibility::INTERMEDIARY,
                default => null,
            };
            if ($responsible === null) {
                return new IssResponsibilityDecision(null, false, null, null, null, 'ISS_IMPORT_RESPONSIBLE_ROLE_INVALID');
            }

            return new IssResponsibilityDecision(
                responsibleParty: $responsible,
                withholdingRequired: true,
                tpRetIssqn: $responsible === IssResponsibility::CUSTOMER ? '2' : '3',
                nationalRule: 'LC116_ART6_PAR2_I_IMPORT',
                municipalRule: null,
                reasonCode: 'ISS_NATIONAL_IMPORT_RESPONSIBILITY',
                withholdingPerformed: $withholdingPerformed,
                decision: IssWithholdingDecision::WITHHOLD,
                ruleScope: 'NATIONAL',
                legalBasis: ['LC 116/2003 art. 6º, §2º, I'],
                confidence: 'DETERMINISTIC',
            );
        }

        if (in_array($service->lc116Code, self::NATIONAL_CUSTOMER_RESPONSIBILITY_CODES, true)) {
            $roleDecision = $this->nationalResponsible($facts);
            if ($roleDecision instanceof IssResponsibilityDecision) {
                return $roleDecision;
            }
            if ($roleDecision !== null) {
                [$responsible, $tpRet] = $roleDecision;

                return new IssResponsibilityDecision(
                    responsibleParty: $responsible,
                    withholdingRequired: true,
                    tpRetIssqn: $tpRet,
                    nationalRule: 'LC116_ART6_PAR2_II_'.$service->lc116Code,
                    municipalRule: null,
                    reasonCode: 'ISS_NATIONAL_CUSTOMER_RESPONSIBILITY',
                    withholdingPerformed: $withholdingPerformed,
                    decision: IssWithholdingDecision::WITHHOLD,
                    ruleScope: 'NATIONAL',
                    legalBasis: ['LC 116/2003 art. 6º, §2º, II'],
                    confidence: 'DETERMINISTIC',
                );
            }
        }

        if ($service->lc116Code === '15.01') {
            if (! $facts->has('operation.card_arrangement_operation')) {
                return new IssResponsibilityDecision(
                    null,
                    false,
                    null,
                    null,
                    null,
                    'ISS_CARD_ARRANGEMENT_CLASSIFICATION_MISSING',
                    ['operation.card_arrangement_operation'],
                    $withholdingPerformed,
                    IssWithholdingDecision::MANUAL_REVIEW,
                    'NATIONAL_SPECIALIZED',
                    ['LC 116/2003, regras específicas do subitem 15.01'],
                );
            }
            if ($facts->value('operation.card_arrangement_operation') === true) {
                return new IssResponsibilityDecision(
                    null,
                    false,
                    null,
                    'LC116_CARD_ARRANGEMENT_SPECIALIZED',
                    null,
                    'ISS_CARD_ARRANGEMENT_SPECIAL_RULE_REVIEW_REQUIRED',
                    withholdingPerformed: $withholdingPerformed,
                    decision: IssWithholdingDecision::MANUAL_REVIEW,
                    ruleScope: 'NATIONAL_SPECIALIZED',
                    legalBasis: ['LC 116/2003, regras específicas do subitem 15.01', 'ADI 5835 — regra territorial de destino desabilitada'],
                    confidence: 'REVIEW_REQUIRED',
                );
            }
        }

        $responsibility = $parameters->responsibility;
        if ($responsibility === null) {
            return new IssResponsibilityDecision(
                responsibleParty: null,
                withholdingRequired: false,
                tpRetIssqn: null,
                nationalRule: null,
                municipalRule: null,
                reasonCode: 'ISS_RESPONSIBILITY_NOT_COVERED',
                withholdingPerformed: $withholdingPerformed,
                decision: IssWithholdingDecision::NEEDS_MUNICIPAL_RULE,
                ruleScope: 'MUNICIPAL',
            );
        }
        $tpRet = match ($responsibility) {
            IssResponsibility::PROVIDER => '1',
            IssResponsibility::CUSTOMER => '2',
            IssResponsibility::INTERMEDIARY => '3',
        };

        return new IssResponsibilityDecision(
            responsibleParty: $responsibility,
            withholdingRequired: $responsibility !== IssResponsibility::PROVIDER,
            tpRetIssqn: $tpRet,
            nationalRule: null,
            municipalRule: $parameters->ruleId ?? $parameters->sourceVersion,
            reasonCode: 'ISS_MUNICIPAL_RESPONSIBILITY_RESOLVED',
            withholdingPerformed: $withholdingPerformed,
            decision: $responsibility === IssResponsibility::PROVIDER
                ? IssWithholdingDecision::DO_NOT_WITHHOLD
                : IssWithholdingDecision::WITHHOLD,
            ruleScope: 'MUNICIPAL',
            legalBasis: array_values(array_filter([$parameters->legalBasis, $parameters->sourceId], 'is_string')),
            confidence: 'DETERMINISTIC',
        );
    }

    /**
     * @return array{IssResponsibility, string}|IssResponsibilityDecision|null
     */
    private function nationalResponsible(FactBag $facts, bool $roleRequired = false): array|IssResponsibilityDecision|null
    {
        $role = $facts->value('operation.responsible_role');
        if ($role === null && $roleRequired) {
            return new IssResponsibilityDecision(
                null,
                false,
                null,
                null,
                null,
                'ISS_NATIONAL_RESPONSIBLE_ROLE_MISSING',
                ['operation.responsible_role'],
                decision: IssWithholdingDecision::MANUAL_REVIEW,
                ruleScope: 'NATIONAL',
            );
        }
        $role ??= 'CUSTOMER';
        $personTypeFact = $role === 'INTERMEDIARY' ? 'intermediary.person_type' : 'customer.person_type';
        if (! $facts->has($personTypeFact)) {
            return new IssResponsibilityDecision(
                null,
                false,
                null,
                null,
                null,
                'ISS_RESPONSIBLE_PERSON_TYPE_MISSING',
                [$personTypeFact],
                decision: IssWithholdingDecision::MANUAL_REVIEW,
                ruleScope: 'NATIONAL',
            );
        }
        if (! in_array($facts->value($personTypeFact), self::LEGAL_ENTITY_CUSTOMER_TYPES, true)) {
            return null;
        }

        return $role === 'INTERMEDIARY'
            ? [IssResponsibility::INTERMEDIARY, '3']
            : [IssResponsibility::CUSTOMER, '2'];
    }
}
