<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Facts\FactSourceType;
use App\Modules\Fiscal\Core\Facts\FactValue;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use App\Modules\Fiscal\FederalWithholding\FederalWithholdingEngine;
use App\Modules\Fiscal\FederalWithholding\FederalWithholdingStatus;
use App\Modules\Fiscal\FederalWithholding\MedicalCooperativeWithholdingProfile;
use App\Modules\Fiscal\Infrastructure\Files\FactDefinitionFileLoader;

/** @param array<string, mixed> $overrides */
function medicalCooperativeFacts(array $overrides): FactBag
{
    $registry = (new FactDefinitionFileLoader)->load(dirname(__DIR__, 3).'/resources/fiscal/facts/federal-phase4a-public-v4.json');
    $facts = new FactBag($registry);
    $values = [
        'payer.person_type' => 'PRIVATE_LEGAL_ENTITY',
        'provider.person_type' => 'COOPERATIVE',
        'cooperative.provider_type' => 'MEDICAL',
        'payment.occurred' => true,
        'payment.date' => '2026-09-24',
        'payment.gross_amount' => '1000.00',
        'cooperative.medical_role' => 'PLAN_OPERATOR',
        'cooperative.health_plan_price_model' => 'PREESTABLISHED',
        'cooperative.invoice_components_evidence_confirmed' => true,
        'cooperative.invoice_components' => [
            ['line_id' => 'plan', 'document_id' => 'nf-1', 'type' => 'PREESTABLISHED_PLAN', 'amount' => '1000.00'],
        ],
        'invoice.issue_date' => '2026-09-24',
        'inss.service_group' => 'OUTSIDE_EXHAUSTIVE_LIST',
        'inss.service_category' => 'HEALTH',
        'inss.execution_mode' => 'CONTRACT_FOR_RESULT',
    ];
    foreach ([...$values, ...$overrides] as $key => $value) {
        $facts->put(new FactValue($key, $value, FactSourceType::FIXTURE,
            LocalDate::fromString('2026-09-24'), null, new DateTimeImmutable('2026-09-24T15:07:42-03:00')));
    }

    return $facts;
}

it('does not retain the preestablished medical plan component', function (): void {
    $result = (new FederalWithholdingEngine)->evaluate(medicalCooperativeFacts([]));

    expect($result->status)->toBe('RESOLVED')
        ->and($result->irrf->status)->toBe(FederalWithholdingStatus::NOT_APPLICABLE)
        ->and($result->pcc->totalWithholding)->toBe('0')
        ->and($result->inss->status)->toBe(FederalWithholdingStatus::NOT_APPLICABLE);
});

it('separates the operator personal medical service from preestablished plan value', function (): void {
    $facts = medicalCooperativeFacts([
        'cooperative.health_plan_price_model' => 'MIXED',
        'cooperative.invoice_components' => [
            ['line_id' => 'plan', 'document_id' => 'nf-2', 'type' => 'PREESTABLISHED_PLAN', 'amount' => '300.00'],
            ['line_id' => 'member', 'document_id' => 'nf-2', 'type' => 'POSTESTABLISHED_MEMBER_PF_SERVICE', 'amount' => '700.00'],
        ],
    ]);
    $result = (new MedicalCooperativeWithholdingProfile)->evaluate($facts);

    expect($result['irrf']->basis)->toBe('700.00')
        ->and($result['irrf']->amount)->toBe('10.50')
        ->and($result['pcc']->totalWithholding)->toBe('32.55');
});

it('attributes singular cooperative PJ components to each beneficiary', function (): void {
    $facts = medicalCooperativeFacts([
        'cooperative.medical_role' => 'SINGULAR_INTERMEDIARY',
        'cooperative.health_plan_price_model' => 'NONE',
        'cooperative.invoice_components' => [
            ['line_id' => 'pj', 'document_id' => 'nf-3', 'type' => 'MEMBER_PJ_SERVICE', 'amount' => '900.00',
                'beneficiary_tax_id' => '12345678000199', 'beneficiary_tax_regime' => 'LUCRO_REAL'],
            ['line_id' => 'fee', 'document_id' => 'nf-3', 'type' => 'ADMINISTRATION_FEE', 'amount' => '100.00'],
        ],
    ]);
    $result = (new FederalWithholdingEngine)->evaluate($facts);

    expect($result->status)->toBe('RESOLVED')
        ->and($result->irrf->status)->toBe(FederalWithholdingStatus::DISPENSED_THRESHOLD)
        ->and($result->additionalDecisions)->toHaveCount(4)
        ->and($result->additionalDecisions[0]->details['beneficiary_tax_id'])->toBe('12345678000199')
        ->and($result->totalWithholding)->toBe('55.35');
});

it('blocks unproven or unreconciled invoice components', function (): void {
    $unproven = (new MedicalCooperativeWithholdingProfile)->evaluate(medicalCooperativeFacts([
        'cooperative.invoice_components_evidence_confirmed' => false,
    ]));
    $unreconciled = (new MedicalCooperativeWithholdingProfile)->evaluate(medicalCooperativeFacts([
        'payment.gross_amount' => '999.99',
    ]));

    expect($unproven['irrf']->status)->toBe(FederalWithholdingStatus::ACTION_REQUIRED)
        ->and($unreconciled['irrf']->reasonCodes)->toContain('MEDICAL_COOPERATIVE_INVOICE_PAYMENT_CONFLICT');
});

it('separates public cooperative personal service, third-party materials and fee', function (): void {
    $facts = medicalCooperativeFacts([
        'payer.person_type' => 'FEDERAL_PUBLIC_ADMINISTRATION',
        'provider.head_office_tax_id' => '11111111000191',
        'cooperative.provider_type' => 'WORK',
        'payment.gross_amount' => '3000.00',
        'cooperative.invoice_components' => [
            ['line_id' => 'pf', 'document_id' => 'nf-10', 'type' => 'MEMBER_PF_SERVICE', 'amount' => '1000.00'],
            ['line_id' => 'pj', 'document_id' => 'nf-11', 'type' => 'THIRD_PARTY_PJ_MATERIAL_SERVICE',
                'amount' => '1500.00', 'beneficiary_tax_id' => '22222222000191',
                'qualified_material_or_hospital_service_confirmed' => true],
            ['line_id' => 'fee', 'document_id' => 'nf-12', 'type' => 'ADMINISTRATION_FEE', 'amount' => '500.00'],
        ],
    ]);
    $result = (new FederalWithholdingEngine)->evaluate($facts);

    expect($result->status)->toBe('RESOLVED')
        ->and($result->irrf->revenueCode)->toBe('3280')
        ->and($result->irrf->amount)->toBe('15.00')
        ->and($result->pcc->totalWithholding)->toBe('46.50')
        ->and($result->additionalDecisions)->toHaveCount(8)
        ->and($result->additionalDecisions[0]->revenueCode)->toBe('6147')
        ->and($result->additionalDecisions[0]->details['beneficiary_tax_id'])->toBe('22222222000191')
        ->and($result->totalWithholding)->toBe('196.50');
});

it('requires beneficiary and legal evidence for public cooperative service with materials', function (): void {
    $facts = medicalCooperativeFacts([
        'payer.person_type' => 'FEDERAL_PUBLIC_ADMINISTRATION',
        'provider.head_office_tax_id' => '11111111000191',
        'cooperative.provider_type' => 'WORK',
        'cooperative.invoice_components' => [
            ['line_id' => 'pj', 'document_id' => 'nf-13', 'type' => 'THIRD_PARTY_PJ_MATERIAL_SERVICE',
                'amount' => '1000.00', 'beneficiary_tax_id' => '22222222000191'],
        ],
    ]);
    $result = (new FederalWithholdingEngine)->evaluate($facts);

    expect($result->status)->toBe('ACTION_REQUIRED')
        ->and($result->irrf->reasonCodes)->toContain('PUBLIC_COOPERATIVE_6147_EVIDENCE_REQUIRED');
});

it('keeps subnational cooperative IR local and requires a convention for federal contributions', function (): void {
    $facts = medicalCooperativeFacts([
        'payer.person_type' => 'MUNICIPAL_PUBLIC_ADMINISTRATION',
        'payer.public_entity_type' => 'DIRECT',
        'provider.head_office_tax_id' => '11111111000191',
        'cooperative.provider_type' => 'WORK',
        'public.article33_convention_exists' => false,
        'cooperative.invoice_components' => [
            ['line_id' => 'pf', 'document_id' => 'nf-14', 'type' => 'MEMBER_PF_SERVICE', 'amount' => '1000.00'],
        ],
    ]);
    $result = (new FederalWithholdingEngine)->evaluate($facts);

    expect($result->irrf->status)->toBe(FederalWithholdingStatus::WITHHOLD)
        ->and($result->irrf->revenueCode)->toBeNull()
        ->and($result->irrf->details['collection_destination'])->toBe('LOCAL_TREASURY')
        ->and($result->pcc->status)->toBe('NOT_APPLICABLE')
        ->and($result->totalWithholding)->toBe('15.00');
});

it('requires a new legal version for cooperative component decisions in 2027', function (): void {
    $private = (new FederalWithholdingEngine)->evaluate(medicalCooperativeFacts([
        'payment.date' => '2027-01-15',
    ]));
    $public = (new FederalWithholdingEngine)->evaluate(medicalCooperativeFacts([
        'payer.person_type' => 'FEDERAL_PUBLIC_ADMINISTRATION',
        'provider.head_office_tax_id' => '11111111000191',
        'payment.date' => '2027-01-15',
    ]));

    expect($private->status)->toBe('ACTION_REQUIRED')
        ->and($private->reasonCodes)->toContain('MEDICAL_COOPERATIVE_2027_COMPONENT_RULES_REQUIRED')
        ->and($public->status)->toBe('ACTION_REQUIRED')
        ->and($public->reasonCodes)->toContain('PUBLIC_COOPERATIVE_2027_REVALIDATION_REQUIRED');
});

it('projects a nonmember individual on the progressive IR table with the individual beneficiary', function (): void {
    $facts = medicalCooperativeFacts([
        'payer.person_type' => 'FEDERAL_PUBLIC_ADMINISTRATION',
        'provider.head_office_tax_id' => '11111111000191',
        'cooperative.provider_type' => 'WORK',
        'payment.gross_amount' => '6000.00',
        'cooperative.invoice_components' => [
            ['line_id' => 'pf-third', 'document_id' => 'receipt-1', 'type' => 'NONMEMBER_PF_SERVICE',
                'amount' => '6000.00', 'beneficiary_tax_id' => '12345678901',
                'monthly_taxable_income' => '6000.00', 'monthly_taxable_base' => '6000.00',
                'relationship' => 'NO_WORK_RELATIONSHIP'],
        ],
    ]);
    $result = (new FederalWithholdingEngine)->evaluate($facts);

    expect($result->status)->toBe('RESOLVED')
        ->and($result->additionalDecisions)->toHaveCount(1)
        ->and($result->additionalDecisions[0]->revenueCode)->toBe('0588')
        ->and($result->additionalDecisions[0]->details['beneficiary_tax_id'])->toBe('12345678901')
        ->and($result->additionalDecisions[0]->details['reporting']['event'])->toBe('R-4010');
});

it('classifies a public medical reimbursement by its documented underlying hospital service', function (): void {
    $facts = medicalCooperativeFacts([
        'payer.person_type' => 'FEDERAL_PUBLIC_ADMINISTRATION',
        'provider.head_office_tax_id' => '11111111000191',
        'cooperative.invoice_components' => [
            ['line_id' => 'hospital-reimbursement', 'document_id' => 'receipt-2',
                'type' => 'REIMBURSEMENT', 'underlying_service_type' => 'HOSPITAL_INDEPENDENT',
                'underlying_service_legal_basis' => 'IN RFB 1.234/2012, art. 27',
                'amount' => '1000.00', 'beneficiary_tax_id' => '22222222000191',
                'qualified_material_or_hospital_service_confirmed' => true],
        ],
    ]);
    $result = (new FederalWithholdingEngine)->evaluate($facts);

    expect($result->status)->toBe('RESOLVED')
        ->and($result->additionalDecisions)->toHaveCount(4)
        ->and($result->additionalDecisions[0]->revenueCode)->toBe('6147')
        ->and($result->totalWithholding)->toBe('58.50');
});

it('uses an earlier unconditional credit for medical cooperative IR while PCC follows payment', function (): void {
    $facts = medicalCooperativeFacts([
        'payment.accounting_credit_date' => '2026-08-31',
        'cooperative.health_plan_price_model' => 'POSTESTABLISHED',
        'cooperative.invoice_components' => [
            ['line_id' => 'personal', 'document_id' => 'nf-20',
                'type' => 'POSTESTABLISHED_MEMBER_PF_SERVICE', 'amount' => '1000.00'],
        ],
    ]);
    $result = (new FederalWithholdingEngine)->evaluate($facts);

    expect($result->irrf->triggerDate)->toBe('2026-08-31')
        ->and($result->pcc->decisions[0]->triggerDate)->toBe('2026-09-24');
});

it('keeps a nonmember individual tax separate from the public PJ ten-real threshold', function (): void {
    $facts = medicalCooperativeFacts([
        'payer.person_type' => 'FEDERAL_PUBLIC_ADMINISTRATION',
        'provider.head_office_tax_id' => '11111111000191',
        'cooperative.provider_type' => 'WORK',
        'payment.gross_amount' => '5001.00',
        'cooperative.invoice_components' => [
            ['line_id' => 'individual', 'document_id' => 'receipt-3', 'type' => 'NONMEMBER_PF_SERVICE',
                'amount' => '5001.00', 'beneficiary_tax_id' => '12345678901',
                'monthly_taxable_income' => '5001.00', 'monthly_taxable_base' => '4393.80',
                'relationship' => 'NO_WORK_RELATIONSHIP'],
        ],
    ]);
    $result = (new FederalWithholdingEngine)->evaluate($facts);

    expect($result->additionalDecisions[0]->status)->toBe(FederalWithholdingStatus::WITHHOLD)
        ->and($result->totalWithholding)->toBe($result->additionalDecisions[0]->amount);
});
