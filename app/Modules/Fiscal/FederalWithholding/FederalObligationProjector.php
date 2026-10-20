<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\FederalWithholding;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use InvalidArgumentException;

final readonly class FederalObligationProjector
{
    /** @param array<string, mixed> $catalog */
    public function __construct(private array $catalog, private ?FederalBankingCalendar $calendar = null)
    {
        if (($catalog['catalog_id'] ?? null) !== 'FEDERAL.OBLIGATIONS.2026'
            || ($catalog['status'] ?? null) !== 'SANDBOX_CANDIDATE') {
            throw new InvalidArgumentException('Invalid federal obligation catalog.');
        }
    }

    /**
     * @param  list<FederalWithholdingDecision>  $additionalDecisions
     * @return array<string, mixed>
     */
    public function project(FactBag $facts, FederalWithholdingDecision $irrf,
        PccWithholdingResult $pcc, FederalWithholdingDecision $inss, array $additionalDecisions = []): array
    {
        $items = [];
        $periods = [];
        $decisions = [$irrf, ...$pcc->decisions, $inss, ...$additionalDecisions];
        foreach ($decisions as $decision) {
            if ($decision->status !== FederalWithholdingStatus::WITHHOLD || $decision->triggerDate === null) {
                continue;
            }
            if (($decision->details['collection_destination'] ?? null) === 'LOCAL_TREASURY') {
                $required = ['public.local_ir_collection_rule_id', 'public.local_ir_collection_code',
                    'public.local_ir_due_rule', 'public.local_ir_valid_from', 'public.local_ir_valid_to'];
                $missing = $facts->missing($required);
                $valid = $missing === []
                    && $decision->triggerDate >= $facts->value('public.local_ir_valid_from')
                    && $decision->triggerDate <= $facts->value('public.local_ir_valid_to');
                $items[] = [
                    'status' => $valid ? 'PROJECTED_PENDING_CALENDAR' : 'ACTION_REQUIRED',
                    'tax' => $decision->tax->value,
                    'event' => 'LOCAL_IR_COLLECTION',
                    'trigger_date' => $decision->triggerDate,
                    'tax_period' => substr($decision->triggerDate, 0, 7),
                    'responsible_party' => 'PAYER',
                    'revenue_code' => $valid ? $facts->value('public.local_ir_collection_code') : null,
                    'due_rule' => $valid ? $facts->value('public.local_ir_due_rule') : 'LOCAL_COLLECTION_RULE_REQUIRED',
                    'due_date' => null,
                    'collection_rule_id' => $valid ? $facts->value('public.local_ir_collection_rule_id') : null,
                    'calendar_status' => 'LOCAL_BANKING_CALENDAR_REQUIRED',
                    'reason_code' => $valid ? 'LOCAL_IR_COLLECTION_PROJECTED' : 'LOCAL_IR_COLLECTION_RULE_MISSING_OR_EXPIRED',
                    'missing_facts' => $missing,
                ];

                continue;
            }
            if ($decision->triggerDate < $this->catalog['effective_from']
                || $decision->triggerDate > $this->catalog['effective_to']) {
                $items[] = [
                    'status' => 'ACTION_REQUIRED',
                    'tax' => $decision->tax->value,
                    'reason_code' => 'OBLIGATION_CATALOG_OUTSIDE_VALIDITY',
                    'trigger_date' => $decision->triggerDate,
                ];

                continue;
            }
            $event = $this->eventFor($facts, $decision);
            $collection = $this->collectionDue($facts, $decision);
            $period = substr($decision->triggerDate, 0, 7);
            $reportingDate = $decision->triggerDate;
            if (in_array($this->catalog['version'], ['2026-sandbox-2', '2026-sandbox-3'], true)
                && $decision->tax === FederalTax::INSS
                && str_contains((string) $facts->value('payer.person_type'), 'PUBLIC_ADMINISTRATION')
                && $facts->value('payer.siafi_electronic_collection') === false) {
                $liquidationDate = $collection['collection_basis_date'] ?? null;
                $reportingDate = $liquidationDate === null ? null
                    : (substr($liquidationDate, 0, 7) === substr($decision->triggerDate, 0, 7)
                        ? $decision->triggerDate : $liquidationDate);
                $period = $reportingDate === null ? null : substr($reportingDate, 0, 7);
            }
            $dueRule = $event === 'ESOCIAL'
                ? ($this->catalog['due_rules']['ESOCIAL_S1210'] ?? 'ESOCIAL_EVENT_SPECIFIC')
                : $this->catalog['due_rules']['EFD_REINF'];
            $due = $period === null ? null : $this->calendar?->resolve($period, $dueRule);
            $selfWithholding = $decision->responsibleParty === 'BENEFICIARY';
            $establishmentFact = $selfWithholding ? 'provider.head_office_tax_id' : 'payer.head_office_tax_id';
            $establishment = $facts->value($establishmentFact);
            if ($period !== null) {
                $periods[$period.'|'.$establishmentFact] = ['period' => $period, 'establishment_fact' => $establishmentFact];
            }
            $missing = [];
            if ($facts->hasDefinition($establishmentFact) && ! $facts->has($establishmentFact)) {
                $missing[] = $establishmentFact;
            }
            if (str_contains((string) $facts->value('payer.person_type'), 'PUBLIC_ADMINISTRATION')
                && $decision->tax !== FederalTax::INSS
                && $facts->hasDefinition('federal.income_nature_code')
                && ! $facts->has('federal.income_nature_code')) {
                $missing[] = 'federal.income_nature_code';
            }
            if ($this->catalog['version'] === '2026-sandbox-3'
                && $facts->value('provider.person_type') === 'FOREIGN_LEGAL_ENTITY'
                && $facts->hasDefinition('provider.foreign_tax_id')
                && ! $facts->has('provider.foreign_tax_id')) {
                $missing[] = 'provider.foreign_tax_id';
            }
            foreach ($this->reportingSplits($decision) as $split) {
                $items[] = [
                    'status' => $event === null || $missing !== [] || ($split['revenue_code'] === null && $decision->tax !== FederalTax::INSS)
                        || ($collection['collection_missing_facts'] ?? []) !== [] ? 'ACTION_REQUIRED'
                        : (($due['due_date'] ?? null) === null ? 'PROJECTED_PENDING_CALENDAR' : 'PROJECTED'),
                    'tax' => $decision->tax->value,
                    'event' => $event,
                    ...($event === 'ESOCIAL' ? ['event_subtype' => 'S-1210'] : []),
                    'trigger_date' => $decision->triggerDate,
                    'tax_period' => $period,
                    ...(in_array($this->catalog['version'], ['2026-sandbox-2', '2026-sandbox-3'], true) && $decision->tax === FederalTax::INSS
                        ? ['reinf_invoice_date' => $reportingDate, 'original_invoice_date' => $decision->triggerDate]
                        : []),
                    'responsible_party' => $decision->responsibleParty,
                    'reporting_establishment' => $establishment,
                    ...($this->catalog['version'] === '2026-sandbox-3'
                        ? ['beneficiary_tax_id' => $facts->value('provider.person_type') === 'FOREIGN_LEGAL_ENTITY'
                            ? null : ($decision->details['beneficiary_tax_id'] ?? $facts->value('provider.head_office_tax_id')),
                            'beneficiary_foreign_tax_id' => $facts->value('provider.person_type') === 'FOREIGN_LEGAL_ENTITY'
                                ? $facts->value('provider.foreign_tax_id') : null]
                        : []),
                    'work_cno' => $decision->tax === FederalTax::INSS ? ($decision->details['construction']['details']['cno'] ?? null) : null,
                    'work_cno_holder' => $decision->tax === FederalTax::INSS ? ($decision->details['construction']['details']['cno_holder'] ?? null) : null,
                    'revenue_code' => $split['revenue_code'],
                    'revenue_code_status' => $decision->tax === FederalTax::INSS ? 'DERIVED_IN_DCTFWEB_FROM_R2010'
                        : ($split['revenue_code'] === null ? 'REQUIRED' : 'RESOLVED'),
                    'withholding_amount' => $split['amount'],
                    'line_id' => $split['line_id'],
                    'source_document_id' => $split['document_id'],
                    'income_nature_code' => $facts->value('federal.income_nature_code'),
                    'due_rule' => $dueRule,
                    'due_date' => $due['due_date'] ?? null,
                    'calendar_status' => $due['status'] ?? 'BANKING_CALENDAR_REQUIRED',
                    'calendar_id' => $due['calendar_id'] ?? null,
                    'calendar_version' => $due['calendar_version'] ?? null,
                    ...$collection,
                    'missing_facts' => $missing,
                    'reason_code' => $event === null ? 'OBLIGATION_EVENT_ROUTING_REQUIRED'
                        : ($split['revenue_code'] === null && $decision->tax !== FederalTax::INSS
                            ? 'OBLIGATION_REVENUE_CODE_REQUIRED' : 'FEDERAL_WITHHOLDING_EVENT_PROJECTED'),
                    'source_id' => 'SOURCE.RFB.EFD_REINF.FAQ.2026',
                ];
            }
            if ($decision->tax === FederalTax::INSS) {
                $providerPeriod = substr($decision->triggerDate, 0, 7);
                $providerDue = $this->calendar?->resolve($providerPeriod, $this->catalog['due_rules']['EFD_REINF']);
                $providerMissing = $facts->has('provider.head_office_tax_id') ? [] : ['provider.head_office_tax_id'];
                $items[] = [
                    'status' => $providerMissing !== [] ? 'ACTION_REQUIRED'
                        : (($providerDue['due_date'] ?? null) === null ? 'PROJECTED_PROVIDER_SIDE_PENDING_CALENDAR' : 'PROJECTED_PROVIDER_SIDE'),
                    'tax' => 'INSS',
                    'event' => 'R-2020',
                    'tax_period' => $providerPeriod,
                    'responsible_party' => 'PROVIDER',
                    'reporting_establishment' => $facts->value('provider.head_office_tax_id'),
                    'work_cno' => $decision->details['construction']['details']['cno'] ?? null,
                    'work_cno_holder' => $decision->details['construction']['details']['cno_holder'] ?? null,
                    'due_rule' => $this->catalog['due_rules']['EFD_REINF'],
                    'due_date' => $providerDue['due_date'] ?? null,
                    'calendar_status' => $providerDue['status'] ?? 'BANKING_CALENDAR_REQUIRED',
                    'calendar_id' => $providerDue['calendar_id'] ?? null,
                    'calendar_version' => $providerDue['calendar_version'] ?? null,
                    'missing_facts' => $providerMissing,
                    'reason_code' => $providerMissing === [] ? 'PROVIDER_R2020_PROJECTED' : 'PROVIDER_HEAD_OFFICE_REQUIRED',
                    'source_id' => 'SOURCE.RFB.EFD_REINF.FAQ.2026',
                ];
            }
        }
        foreach ($periods as $projection) {
            $period = $projection['period'];
            $establishmentFact = $projection['establishment_fact'];
            $selfWithholding = $establishmentFact === 'provider.head_office_tax_id';
            $due = $this->calendar?->resolve($period, $this->catalog['due_rules']['DCTFWEB']);
            $items[] = [
                'status' => ! $facts->has($establishmentFact) ? 'ACTION_REQUIRED'
                    : (($due['due_date'] ?? null) === null ? 'PROJECTED_PENDING_CALENDAR' : 'PROJECTED'),
                'event' => 'DCTFWEB',
                'tax_period' => $period,
                'responsible_party' => $selfWithholding ? 'BENEFICIARY_HEAD_OFFICE' : 'PAYER_HEAD_OFFICE',
                'reporting_establishment' => $facts->value($establishmentFact),
                'due_rule' => $this->catalog['due_rules']['DCTFWEB'],
                'due_date' => $due['due_date'] ?? null,
                'calendar_status' => $due['status'] ?? 'BANKING_CALENDAR_REQUIRED',
                'calendar_id' => $due['calendar_id'] ?? null,
                'calendar_version' => $due['calendar_version'] ?? null,
                'missing_facts' => $facts->has($establishmentFact) ? [] : [$establishmentFact],
                'source_id' => 'SOURCE.RFB.DCTFWEB.MANUAL.2025',
            ];
        }

        $items = $this->consolidateIncomeEvents($items);

        return [
            'catalog_id' => $this->catalog['catalog_id'],
            'version' => $this->catalog['version'],
            'status' => $items === [] ? 'NO_WITHHOLDING_EVENT'
                : (in_array('ACTION_REQUIRED', array_column($items, 'status'), true) ? 'ACTION_REQUIRED'
                    : (in_array(null, array_column($items, 'due_date'), true) ? 'PROJECTED_WITH_OPEN_DUE_DATES' : 'PROJECTED')),
            'items' => $items,
            'transmission_performed' => false,
        ];
    }

    /** @return array<string, mixed> */
    private function collectionDue(FactBag $facts, FederalWithholdingDecision $decision): array
    {
        if (! in_array($this->catalog['version'], ['2026-sandbox-2', '2026-sandbox-3'], true)) {
            return [];
        }
        if ($this->catalog['version'] === '2026-sandbox-3'
            && $facts->value('provider.person_type') === 'FOREIGN_LEGAL_ENTITY'
            && $decision->tax === FederalTax::IRRF) {
            return $decision->revenueCode === '0422'
                ? ['collection_basis_date' => $decision->triggerDate,
                    'collection_due_rule' => 'DAILY_SAME_DAY',
                    'collection_due_date' => $decision->triggerDate,
                    'collection_source_id' => 'SOURCE.RFB.AGENDA.2026.DAILY',
                    'collection_calendar_status' => 'TAX_AGENDA_DAILY',
                    'collection_missing_facts' => []]
                : ['collection_due_rule' => 'FOREIGN_REVENUE_CODE_SPECIFIC_RULE_REQUIRED',
                    'collection_due_date' => null,
                    'collection_calendar_status' => 'TAX_SPECIFIC_DUE_RULE_REQUIRED',
                    'collection_missing_facts' => []];
        }
        $public = str_contains((string) $facts->value('payer.person_type'), 'PUBLIC_ADMINISTRATION');
        $rule = null;
        $basisDate = $decision->triggerDate;
        $missing = [];
        if ($decision->tax === FederalTax::INSS) {
            $rule = $this->catalog['due_rules']['INSS_PAYMENT'];
            if ($public) {
                if (! $facts->has('payer.siafi_electronic_collection')) {
                    $missing[] = 'payer.siafi_electronic_collection';
                    $basisDate = null;
                } elseif ($facts->value('payer.siafi_electronic_collection') !== true) {
                    if (! $facts->has('public.expenditure_liquidation_date')) {
                        $missing[] = 'public.expenditure_liquidation_date';
                        $basisDate = null;
                    } else {
                        $basisDate = (string) $facts->value('public.expenditure_liquidation_date');
                        if ($basisDate < $decision->triggerDate) {
                            $missing[] = 'public.expenditure_liquidation_date';
                            $basisDate = null;
                        }
                    }
                }
            }
        } elseif ($public && $decision->responsibleParty === 'PAYER') {
            $rule = $decision->tax === FederalTax::IRRF && $decision->revenueCode === '3280'
                ? ($this->catalog['due_rules']['PUBLIC_COOPERATIVE_3280_PAYMENT'] ?? null)
                : $this->catalog['due_rules']['PUBLIC_FEDERAL_IN_1234_PAYMENT'];
            $basisDate = is_string($facts->value('payment.date')) ? $facts->value('payment.date') : null;
            if ($basisDate === null) {
                $missing[] = 'payment.date';
            }
        } elseif ($this->catalog['version'] === '2026-sandbox-3'
            && in_array($facts->value('payer.person_type'), ['PRIVATE_LEGAL_ENTITY', 'COOPERATIVE'], true)
            && in_array($facts->value('provider.person_type'), ['PRIVATE_LEGAL_ENTITY', 'COOPERATIVE'], true)
            && in_array($decision->tax, [FederalTax::IRRF, FederalTax::PIS, FederalTax::COFINS, FederalTax::CSLL], true)) {
            $rule = $decision->tax === FederalTax::IRRF
                ? $this->catalog['due_rules']['PRIVATE_DOMESTIC_IRRF_PAYMENT']
                : $this->catalog['due_rules']['PRIVATE_DOMESTIC_PCC_PAYMENT'];
            $basisDate = $decision->triggerDate;
        }
        if ($rule === null) {
            return ['collection_due_rule' => $this->catalog['due_rules']['FEDERAL_WITHHOLDING_PAYMENT'],
                'collection_due_date' => null, 'collection_calendar_status' => 'TAX_SPECIFIC_DUE_RULE_REQUIRED',
                'collection_missing_facts' => []];
        }
        $due = $basisDate === null ? null : $this->calendar?->resolve(substr($basisDate, 0, 7), $rule);

        return [
            'collection_basis_date' => $basisDate,
            'collection_due_rule' => $rule,
            'collection_due_date' => $due['due_date'] ?? null,
            'collection_source_id' => $decision->tax === FederalTax::INSS
                ? ($public ? 'SOURCE.RFB.COSIT171.2026' : 'SOURCE.RFB.IN2110.2022')
                : ($public && $decision->tax === FederalTax::IRRF && $decision->revenueCode === '3280'
                    ? 'SOURCE.RFB.IN1234.ART26'
                    : ($public ? 'SOURCE.RFB.IN1234.ART7' : 'SOURCE.RFB.MAFON.2025')),
            'collection_calendar_status' => $due['status'] ?? 'BANKING_CALENDAR_REQUIRED',
            'collection_calendar_id' => $due['calendar_id'] ?? null,
            'collection_calendar_version' => $due['calendar_version'] ?? null,
            'collection_missing_facts' => $missing,
        ];
    }

    private function eventFor(FactBag $facts, FederalWithholdingDecision $decision): ?string
    {
        if ($decision->tax === FederalTax::INSS) {
            return 'R-2010';
        }
        if ($decision->responsibleParty === 'BENEFICIARY') {
            return 'R-4080';
        }
        if (($decision->details['beneficiary_person_type'] ?? null) === 'INDIVIDUAL') {
            return ($decision->details['reporting']['event'] ?? null) === 'ESOCIAL' ? 'ESOCIAL' : 'R-4010';
        }
        $providerType = $facts->value('provider.person_type');
        if ($providerType === 'INDIVIDUAL') {
            if (! $facts->has('federal.pf_service_relationship')) {
                return null;
            }

            return $facts->value('federal.pf_service_relationship') === 'EMPLOYMENT_OR_WORK_RELATIONSHIP'
                ? 'ESOCIAL' : 'R-4010';
        }
        if (in_array($providerType, ['PRIVATE_LEGAL_ENTITY', 'COOPERATIVE', 'FOREIGN_LEGAL_ENTITY'], true)) {
            return 'R-4020';
        }

        return null;
    }

    /** @return list<array{amount: ?string, revenue_code: ?string, line_id: ?string, document_id: ?string}> */
    private function reportingSplits(FederalWithholdingDecision $decision): array
    {
        $lines = $decision->details['line_calculations'] ?? null;
        if (! is_array($lines) || count($lines) < 2) {
            return [['amount' => $decision->amount, 'revenue_code' => $decision->revenueCode,
                'line_id' => null, 'document_id' => null]];
        }
        $splits = [];
        foreach ($lines as $line) {
            $amount = $line['taxes'][$decision->tax->value]['amount'] ?? null;
            if (! is_string($amount) || $amount === '0.00') {
                continue;
            }
            $splits[] = [
                'amount' => $amount,
                'revenue_code' => $decision->revenueCode ?? ($line['revenue_code'] ?? null),
                'line_id' => $line['line_id'] ?? null,
                'document_id' => $line['document_id'] ?? null,
            ];
        }

        return $splits;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function consolidateIncomeEvents(array $items): array
    {
        $consolidated = [];
        $indexes = [];
        foreach ($items as $item) {
            if (($item['event'] ?? null) !== 'R-4020' || ! is_string($item['revenue_code'] ?? null)) {
                $consolidated[] = $item;

                continue;
            }
            $key = implode('|', array_map(static fn (mixed $value): string => (string) $value, [
                $item['tax_period'] ?? null, $item['trigger_date'] ?? null,
                $item['reporting_establishment'] ?? null, $item['beneficiary_tax_id'] ?? null, $item['revenue_code'],
                $item['income_nature_code'] ?? null, $item['line_id'] ?? null,
                $item['source_document_id'] ?? null, $item['responsible_party'] ?? null,
            ]));
            if (! isset($indexes[$key])) {
                $item['tax_components'] = [$item['tax'] => $item['withholding_amount']];
                $indexes[$key] = count($consolidated);
                $consolidated[] = $item;

                continue;
            }
            $index = $indexes[$key];
            $current = $consolidated[$index];
            $current['tax'] = 'COMBINED';
            $current['tax_components'][$item['tax']] = $item['withholding_amount'];
            $current['withholding_amount'] = DecimalValue::fromString((string) $current['withholding_amount'])
                ->plus(DecimalValue::fromString((string) $item['withholding_amount']))->rounded(2)->value();
            $consolidated[$index] = $current;
        }

        return $consolidated;
    }
}
