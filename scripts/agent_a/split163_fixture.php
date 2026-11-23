<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\SplitDeadline163;

require_once __DIR__.'/split162_fixture.php';

/** Explicit synthetic days; this fixture is not an official holiday calendar. @return array<string, mixed> */
function split163Calendar(string $tax = 'CBS', string $purpose = 'PSP_OPERATIONS'): array
{
    $days = [];
    $on = new DateTimeImmutable('2026-09-01');
    while ($on->format('Y-m-d') <= '2026-11-30') {
        $days[$on->format('Y-m-d')] = (int) $on->format('N') <= 5 && ! in_array($on->format('Y-m-d'), ['2026-10-12', '2026-11-02', '2026-11-20'], true);
        $on = $on->modify('+1 day');
    }

    return ['id' => 'SYNTHETIC.CALENDAR.A163', 'version' => 'SYNTHETIC.V1', 'environment' => 'SANDBOX',
        'source_kind' => 'SYNTHETIC_TEST', 'status' => 'ACTIVE', 'published_at' => '2026-01-01T00:00:00Z',
        'valid_from' => '2026-01-01', 'valid_until' => '2027-01-01', 'revoked_at' => null, 'superseded_at' => null,
        'authority' => $purpose === 'PSP_OPERATIONS' ? 'PAYMENT_SYSTEM' : ($tax === 'CBS' ? 'RFB' : 'CGIBS'),
        'tax' => $tax, 'purpose' => $purpose, 'timezone' => 'Etc/GMT+3', 'coverage_start' => '2026-09-01',
        'coverage_end' => '2026-11-30', 'days' => $days, 'source_hash' => hash('sha256', 'A163 synthetic calendar'), 'evidence_id' => 'SYNTHETIC.CALENDAR.PROOF'];
}

/** @return array<string, string> */
function split163Event(string $id, string $at): array
{
    return ['at' => $at, 'source_hash' => hash('sha256', 'A163 synthetic event '.$id), 'evidence_id' => $id];
}

/** @return array<string, mixed> */
function split163Input(string $rule = 'PSP_REMITTANCE'): array
{
    $input = ['contract' => SplitDeadline163::CONTRACT, 'currency' => 'BRL', 'source_kind' => 'SYNTHETIC_TEST',
        'source_hash' => hash('sha256', 'A163 synthetic clock'), 'evidence_id' => 'SYNTHETIC.CLOCK.PROOF',
        'known_at' => '2026-11-23T16:00:00Z', 'as_of' => '2026-11-23T16:00:00Z', 'rule' => $rule, 'tax' => 'CBS',
        'reference_id' => 'SYNTHETIC.TX1', 'calendar_id' => 'SYNTHETIC.CALENDAR.A163',
        'synthetic_calendar' => split163Calendar('CBS', $rule === 'SIMPLIFIED_EXCESS_TRANSFER' ? 'TAX_AUTHORITY_EXPEDIENT' : 'PSP_OPERATIONS'),
        'events' => ['financial_settlement' => split163Event('SETTLEMENT.PROOF', '2026-10-09T15:00:00Z')],
        'performance' => null];
    if ($rule === 'SIMPLIFIED_EXCESS_TRANSFER') {
        $input['reference_id'] = 'EXCESS.CBS';
        $input['events'] = ['apuration_concluded' => split163Event('CLOSURE.PROOF', '2026-11-01T12:00:00Z'),
            'excess_determined' => split163Event('EXCESS.PROOF', '2026-11-10T12:00:00Z')];
    } elseif ($rule === 'PSP_REMITTANCE') {
        $input['events']['segregation_report'] = split163Event('REPORT.PROOF', '2026-10-14T16:00:00Z');
    } else {
        $input['events']['arrangement'] = 'BOLETO';
    }

    return seal158($input);
}
