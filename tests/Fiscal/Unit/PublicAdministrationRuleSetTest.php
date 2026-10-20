<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Facts\FactSourceType;
use App\Modules\Fiscal\Core\Facts\FactValue;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use App\Modules\Fiscal\FederalWithholding\ConstructionPrevidenciaryProfile;
use App\Modules\Fiscal\FederalWithholding\FederalBankingCalendar;
use App\Modules\Fiscal\FederalWithholding\FederalObligationProjector;
use App\Modules\Fiscal\FederalWithholding\FederalTax;
use App\Modules\Fiscal\FederalWithholding\FederalWithholdingDecision;
use App\Modules\Fiscal\FederalWithholding\FederalWithholdingEngine;
use App\Modules\Fiscal\FederalWithholding\FederalWithholdingStatus;
use App\Modules\Fiscal\FederalWithholding\InssWithholdingEngine;
use App\Modules\Fiscal\FederalWithholding\IrrfWithholdingEngine;
use App\Modules\Fiscal\FederalWithholding\PccWithholdingEngine;
use App\Modules\Fiscal\FederalWithholding\PccWithholdingResult;
use App\Modules\Fiscal\FederalWithholding\PublicAdministrationRuleSet;
use App\Modules\Fiscal\FederalWithholding\PublicAnnexCatalog;
use App\Modules\Fiscal\Infrastructure\Files\FactDefinitionFileLoader;

function publicRuleSet(): PublicAdministrationRuleSet
{
    $document = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/public-annex-i-rfb-41766.json'), true, 512, JSON_THROW_ON_ERROR);

    return new PublicAdministrationRuleSet(new PublicAnnexCatalog($document));
}

/** @param array<string, mixed> $values */
function publicFacts(array $values, string $version = 'v2'): FactBag
{
    $registry = (new FactDefinitionFileLoader)->load(dirname(__DIR__, 3).'/resources/fiscal/facts/federal-phase4a-public-'.$version.'.json');
    $facts = new FactBag($registry);
    $effective = LocalDate::fromString('2026-09-24');
    $knownAt = new DateTimeImmutable('2026-09-24T07:32:59-03:00');
    foreach ($values as $key => $value) {
        $facts->put(new FactValue($key, $value, FactSourceType::FIXTURE, $effective, null, $knownAt));
    }

    return $facts;
}

function publicBaseFacts(string $payer, string $amount): array
{
    return [
        'payment.occurred' => true,
        'payment.date' => '2026-09-24',
        'payment.gross_amount' => $amount,
        'payer.person_type' => $payer,
        'payer.public_entity_type' => 'DIRECT',
        'provider.person_type' => 'PRIVATE_LEGAL_ENTITY',
        'provider.tax_regime' => 'LUCRO_REAL',
        'public.annex_category' => 'GENERAL_SERVICES',
        'public.glosa_without_new_invoice' => false,
        'public.has_late_additions' => false,
        'public.beneficiary_treatment' => 'STANDARD',
        'federal.pis_status' => 'APPLICABLE',
        'federal.cofins_status' => 'APPLICABLE',
        'federal.csll_status' => 'APPLICABLE',
    ];
}

it('applies the private IRRF ten-real dispense only through ten reais', function (): void {
    $base = [
        'payment.occurred' => true,
        'payment.date' => '2026-09-24',
        'payer.person_type' => 'PRIVATE_LEGAL_ENTITY',
        'provider.person_type' => 'PRIVATE_LEGAL_ENTITY',
        'provider.tax_regime' => 'LUCRO_REAL',
        'federal.service_category' => 'CLEANING',
    ];
    $atTen = (new IrrfWithholdingEngine)->evaluate(publicFacts([...$base, 'payment.gross_amount' => '1000.00']));
    $aboveTen = (new IrrfWithholdingEngine)->evaluate(publicFacts([...$base, 'payment.gross_amount' => '1001.00']));

    expect($atTen->status)->toBe(FederalWithholdingStatus::DISPENSED_THRESHOLD)
        ->and($atTen->amount)->toBe('0.00')
        ->and($aboveTen->status)->toBe(FederalWithholdingStatus::WITHHOLD)
        ->and($aboveTen->amount)->toBe('10.01');
});

it('limits the individual monthly IRRF table to 2026', function (): void {
    $base = [
        'payment.occurred' => true,
        'payment.gross_amount' => '6000.00',
        'payer.person_type' => 'PRIVATE_LEGAL_ENTITY',
        'provider.person_type' => 'INDIVIDUAL',
        'federal.pf_monthly_taxable_income' => '6000.00',
        'federal.pf_monthly_taxable_base' => '6000.00',
        'federal.pf_service_relationship' => 'NO_WORK_RELATIONSHIP',
    ];
    $current = (new IrrfWithholdingEngine)->evaluate(publicFacts([...$base, 'payment.date' => '2026-09-24']));
    $future = (new IrrfWithholdingEngine)->evaluate(publicFacts([...$base, 'payment.date' => '2027-01-01']));

    expect($current->status)->toBe(FederalWithholdingStatus::WITHHOLD)
        ->and($current->revenueCode)->toBe('0588')
        ->and($current->details['reporting']['event'])->toBe('R-4010')
        ->and($future->status)->toBe(FederalWithholdingStatus::ACTION_REQUIRED)
        ->and($future->reasonCodes)->toContain('IRRF_PF_PROGRESSIVE_TABLE_VERSION_MISSING');
});

it('keeps foreign technical service and favored-jurisdiction rates distinct', function (): void {
    $base = [
        'payment.occurred' => true,
        'payment.date' => '2026-09-24',
        'payer.person_type' => 'PRIVATE_LEGAL_ENTITY',
        'provider.person_type' => 'FOREIGN_LEGAL_ENTITY',
        'federal.foreign_income_category' => 'TECHNICAL_OR_ADMINISTRATIVE_ASSISTANCE',
        'federal.foreign_taxable_base' => '1000.00',
        'federal.foreign_treaty_applicable' => false,
        'federal.foreign_revenue_code' => '0422',
    ];
    $standard = (new IrrfWithholdingEngine)->evaluate(publicFacts([...$base, 'provider.favored_tax_jurisdiction' => false]));
    $favored = (new IrrfWithholdingEngine)->evaluate(publicFacts([...$base, 'provider.favored_tax_jurisdiction' => true]));

    expect($standard->amount)->toBe('150.00')
        ->and($favored->amount)->toBe('250.00')
        ->and($favored->reasonCodes)->toContain('IRRF_FOREIGN_FAVORED_JURISDICTION_25');
});

it('ends the CPRB 3.5 percent transition after 2027', function (): void {
    $base = [
        'invoice.gross_amount' => '1000.00',
        'payer.person_type' => 'PRIVATE_LEGAL_ENTITY',
        'provider.person_type' => 'PRIVATE_LEGAL_ENTITY',
        'provider.tax_regime' => 'LUCRO_REAL',
        'inss.service_group' => 'ARTICLE_111',
        'inss.service_category' => 'CLEANING_CONSERVATION_JANITORIAL',
        'inss.execution_mode' => 'CONTRACT_FOR_RESULT',
        'inss.statutory_dispense_claimed' => false,
        'inss.material_equipment_profile' => 'NONE',
        'provider.cprb_applicable' => true,
    ];
    $transition = (new InssWithholdingEngine)->evaluate(publicFacts([...$base, 'invoice.issue_date' => '2026-09-24']));
    $after = (new InssWithholdingEngine)->evaluate(publicFacts([...$base, 'invoice.issue_date' => '2028-01-02']));

    expect($transition->status)->toBe(FederalWithholdingStatus::WITHHOLD)
        ->and($transition->amount)->toBe('35.00')
        ->and($after->status)->toBe(FederalWithholdingStatus::WITHHOLD)
        ->and($after->amount)->toBe('110.00');
});

it('separates federal and municipal collection of the Annex I rates', function (): void {
    $federal = publicRuleSet()->evaluate(publicFacts(publicBaseFacts('FEDERAL_PUBLIC_ADMINISTRATION', '1000.00')));
    expect($federal['irrf']->amount)->toBe('48.00')
        ->and($federal['irrf']->revenueCode)->toBe('6190')
        ->and($federal['pcc']->totalWithholding)->toBe('46.50');

    $local = publicRuleSet()->evaluate(publicFacts([
        ...publicBaseFacts('MUNICIPAL_PUBLIC_ADMINISTRATION', '1000.00'),
        'public.article33_convention_exists' => false,
    ]));
    expect($local['irrf']->amount)->toBe('48.00')
        ->and($local['irrf']->revenueCode)->toBeNull()
        ->and($local['pcc']->totalWithholding)->toBe('0')
        ->and($local['pcc']->decisions[0]->status)->toBe(FederalWithholdingStatus::NOT_APPLICABLE);
});

it('requires article 2-A qualification for a subnational public foundation', function (): void {
    $result = publicRuleSet()->evaluate(publicFacts([
        ...publicBaseFacts('MUNICIPAL_PUBLIC_ADMINISTRATION', '1000.00'),
        'payer.public_entity_type' => 'PUBLIC_FOUNDATION',
    ]));

    expect($result['irrf']->status)->toBe(FederalWithholdingStatus::ACTION_REQUIRED)
        ->and($result['irrf']->reasonCodes)->toContain('PUBLIC_SUBNATIONAL_FOUNDATION_ARTICLE2A_QUALIFICATION_REQUIRED');
});

it('qualifies a subnational foundation by autarchic nature or more than half public funding', function (): void {
    $base = [
        ...publicBaseFacts('MUNICIPAL_PUBLIC_ADMINISTRATION', '1000.00'),
        'payer.public_entity_type' => 'PUBLIC_FOUNDATION',
        'public.article33_convention_exists' => false,
        'public.foundation_qualification_source_id' => 'OFFICIAL_FOUNDATION_DOCUMENT',
    ];
    $autarchic = publicRuleSet()->evaluate(publicFacts([...$base,
        'public.foundation_autarchic_nature' => true], 'v4'));
    $funded = publicRuleSet()->evaluate(publicFacts([...$base,
        'public.foundation_autarchic_nature' => false,
        'public.foundation_maintainer_public_revenue_share' => '50.01'], 'v4'));
    $half = publicRuleSet()->evaluate(publicFacts([...$base,
        'public.foundation_autarchic_nature' => false,
        'public.foundation_maintainer_public_revenue_share' => '50.00'], 'v4'));
    expect($autarchic['irrf']->status)->toBe(FederalWithholdingStatus::WITHHOLD)
        ->and($funded['irrf']->status)->toBe(FederalWithholdingStatus::WITHHOLD)
        ->and($half['irrf']->status)->toBe(FederalWithholdingStatus::ACTION_REQUIRED)
        ->and($half['irrf']->reasonCodes)->toContain('PUBLIC_FOUNDATION_OUTSIDE_ARTICLE2A_SCOPE');
});

it('retains exactly ten reais under the public threshold', function (): void {
    $below = publicRuleSet()->evaluate(publicFacts([
        ...publicBaseFacts('MUNICIPAL_PUBLIC_ADMINISTRATION', '208.22'),
        'public.article33_convention_exists' => false,
    ]));
    $exact = publicRuleSet()->evaluate(publicFacts([
        ...publicBaseFacts('MUNICIPAL_PUBLIC_ADMINISTRATION', '208.33'),
        'public.article33_convention_exists' => false,
    ]));
    expect($below['irrf']->status)->toBe(FederalWithholdingStatus::DISPENSED_THRESHOLD)
        ->and($exact['irrf']->status)->toBe(FederalWithholdingStatus::WITHHOLD)
        ->and($exact['irrf']->amount)->toBe('10.00');
});

it('blocks an unproven convention and the legacy contribution table in 2027', function (): void {
    $convention = publicRuleSet()->evaluate(publicFacts([
        ...publicBaseFacts('STATE_PUBLIC_ADMINISTRATION', '1000.00'),
        'public.article33_convention_exists' => true,
    ]));
    $future = publicRuleSet()->evaluate(publicFacts([
        ...publicBaseFacts('FEDERAL_PUBLIC_ADMINISTRATION', '1000.00'),
        'payment.date' => '2027-01-01',
    ]));
    expect($convention['irrf']->status)->toBe(FederalWithholdingStatus::ACTION_REQUIRED)
        ->and($convention['irrf']->missingFacts)->toContain('public.article33_convention_source_id')
        ->and($future['pcc']->status)->toBe('ACTION_REQUIRED');
});

it('keeps the construction CNO holder separate from ISS classification', function (): void {
    $base = [
        'inss.execution_mode' => 'TOTAL_CONSTRUCTION',
        'construction.previdenciary_object_type' => 'WORK',
        'construction.cno_required' => true,
        'construction.cno' => '123456789012',
    ];
    $profile = new ConstructionPrevidenciaryProfile;
    $invalid = $profile->classify(publicFacts([...$base, 'construction.cno_holder' => 'CONTRACTING_PARTY']));
    $valid = $profile->classify(publicFacts([...$base, 'construction.cno_holder' => 'PROVIDER']));
    expect($invalid['status'])->toBe('ACTION_REQUIRED')
        ->and($valid['status'])->toBe('RESOLVED')
        ->and($valid['details']['cno_holder'])->toBe('PROVIDER');
});

it('excludes cooperative CSLL while calculating evidenced PIS and Cofins bases', function (): void {
    $result = (new PccWithholdingEngine)->evaluate(publicFacts([
        'payment.occurred' => true,
        'payment.date' => '2026-09-24',
        'payment.gross_amount' => '1000.00',
        'payer.person_type' => 'COOPERATIVE',
        'payer.tax_regime' => 'LUCRO_REAL',
        'provider.person_type' => 'COOPERATIVE',
        'provider.tax_regime' => 'LUCRO_REAL',
        'cooperative.provider_type' => 'WORK',
        'cooperative.pcc_taxable_base' => '300.00',
        'cooperative.pcc_basis_evidence_confirmed' => true,
        'federal.service_category' => 'COOPERATIVE_PERSONAL_SERVICE',
        'federal.pis_status' => 'APPLICABLE',
        'federal.cofins_status' => 'APPLICABLE',
    ]));
    expect($result->status)->toBe('WITHHOLD')
        ->and($result->totalWithholding)->toBe('10.95')
        ->and($result->decisions[0]->status)->toBe(FederalWithholdingStatus::NOT_APPLICABLE)
        ->and($result->decisions[0]->reasonCodes)->toContain('PCC_COOPERATIVE_CSLL_EXCLUDED');
});

it('blocks medical cooperative invoice taxation until its component profile is classified', function (): void {
    $facts = publicFacts([
        'payment.occurred' => true,
        'payment.date' => '2026-09-24',
        'payment.gross_amount' => '1000.00',
        'payer.person_type' => 'PRIVATE_LEGAL_ENTITY',
        'payer.tax_regime' => 'LUCRO_REAL',
        'provider.person_type' => 'COOPERATIVE',
        'provider.tax_regime' => 'LUCRO_REAL',
        'cooperative.provider_type' => 'MEDICAL',
        'cooperative.pcc_taxable_base' => '300.00',
        'cooperative.pcc_basis_evidence_confirmed' => true,
        'federal.service_category' => 'COOPERATIVE_PERSONAL_SERVICE',
        'federal.irrf_taxable_base' => '300.00',
    ]);
    $pcc = (new PccWithholdingEngine)->evaluate($facts);
    $ir = (new IrrfWithholdingEngine)->evaluate($facts);
    expect($pcc->status)->toBe('ACTION_REQUIRED')
        ->and($pcc->reasonCodes)->toContain('PCC_COOPERATIVE_TYPE_RULE_REQUIRED')
        ->and($ir->status)->toBe(FederalWithholdingStatus::ACTION_REQUIRED)
        ->and($ir->reasonCodes)->toContain('IRRF_COOPERATIVE_TYPE_RULE_REQUIRED');
});

it('uses the documented MAFON bases for cooperative cargo and passenger transport', function (): void {
    $base = [
        'payment.occurred' => true,
        'payment.date' => '2026-09-24',
        'payment.gross_amount' => '100000.00',
        'payer.person_type' => 'PRIVATE_LEGAL_ENTITY',
        'provider.person_type' => 'COOPERATIVE',
        'provider.tax_regime' => 'LUCRO_REAL',
        'cooperative.provider_type' => 'TRANSPORT',
        'cooperative.transport_service_amount' => '100000.00',
        'cooperative.transport_invoice_split_confirmed' => true,
        'federal.service_category' => 'COOPERATIVE_PERSONAL_SERVICE',
    ];
    $cargo = (new IrrfWithholdingEngine)->evaluate(publicFacts([...$base, 'cooperative.transport_kind' => 'CARGO'], 'v4'));
    $passenger = (new IrrfWithholdingEngine)->evaluate(publicFacts([...$base, 'cooperative.transport_kind' => 'PASSENGER'], 'v4'));
    expect($cargo->status)->toBe(FederalWithholdingStatus::WITHHOLD)
        ->and($cargo->basis)->toBe('10000.00')
        ->and($cargo->amount)->toBe('150.00')
        ->and($cargo->revenueCode)->toBe('3280')
        ->and($passenger->basis)->toBe('60000.00')
        ->and($passenger->amount)->toBe('900.00');

    $unproven = (new IrrfWithholdingEngine)->evaluate(publicFacts([...$base, 'cooperative.transport_kind' => 'CARGO',
        'cooperative.transport_invoice_split_confirmed' => false], 'v4'));
    expect($unproven->status)->toBe(FederalWithholdingStatus::ACTION_REQUIRED);
});

it('projects beneficiary self withholding without transmitting or inventing a due date', function (): void {
    $catalog = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/federal-obligations-2026-sandbox.json'), true, 512, JSON_THROW_ON_ERROR);
    $irrf = new FederalWithholdingDecision(FederalTax::IRRF, FederalWithholdingStatus::WITHHOLD,
        '1000.00', '1.50', '15.00', '8045', 'BENEFICIARY', 'PAYMENT', '2026-09-24', ['IRRF_COMMISSION_15'], ['RIR/2018']);
    $inss = new FederalWithholdingDecision(FederalTax::INSS, FederalWithholdingStatus::NOT_APPLICABLE,
        null, null, '0.00', null, 'PAYER', 'INVOICE_ISSUANCE', null, [], []);
    $pcc = new PccWithholdingResult('NOT_APPLICABLE', [], null, null, null, null, null, 'NONE');
    $result = (new FederalObligationProjector($catalog))->project(publicFacts([
        'payer.person_type' => 'PRIVATE_LEGAL_ENTITY',
        'payer.head_office_tax_id' => '12345678000199',
        'provider.person_type' => 'PRIVATE_LEGAL_ENTITY',
        'provider.head_office_tax_id' => '98765432000111',
    ]), $irrf, $pcc, $inss);
    expect($result['items'][0]['event'])->toBe('R-4080')
        ->and($result['items'][0]['reporting_establishment'])->toBe('98765432000111')
        ->and($result['items'][0]['due_date'])->toBeNull()
        ->and($result['items'][1]['event'])->toBe('DCTFWEB')
        ->and($result['items'][1]['responsible_party'])->toBe('BENEFICIARY_HEAD_OFFICE')
        ->and($result['transmission_performed'])->toBeFalse();
});

it('calculates mixed public contracts by documented payment line', function (): void {
    $facts = publicFacts([
        ...publicBaseFacts('FEDERAL_PUBLIC_ADMINISTRATION', '1000.00'),
        'public.annex_category' => 'MIXED',
        'public.payment_lines' => [
            ['line_id' => 'A', 'document_id' => 'NF-1', 'category' => 'GENERAL_SERVICES', 'amount' => '500.00'],
            ['line_id' => 'B', 'document_id' => 'NF-1', 'category' => 'GOODS_MATERIALS_HOSPITAL_CARGO', 'annex_basis_type' => 'GOODS', 'amount' => '500.00'],
        ],
    ]);
    $result = publicRuleSet()->evaluate($facts);
    expect($result['irrf']->amount)->toBe('30.00')
        ->and($result['irrf']->rate)->toBeNull()
        ->and($result['irrf']->revenueCode)->toBeNull()
        ->and($result['irrf']->details['line_calculations'])->toHaveCount(2);

    $catalog = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/federal-obligations-2026-sandbox.json'), true, 512, JSON_THROW_ON_ERROR);
    $inss = new FederalWithholdingDecision(FederalTax::INSS, FederalWithholdingStatus::NOT_APPLICABLE,
        null, null, '0.00', null, 'PAYER', 'INVOICE_ISSUANCE', null, [], []);
    $projection = (new FederalObligationProjector($catalog))->project(publicFacts([
        ...$facts->canonicalValues(),
        'payer.head_office_tax_id' => '12345678000199',
        'federal.income_nature_code' => '15001',
    ]), $result['irrf'], $result['pcc'], $inss);
    $irItems = array_values(array_filter($projection['items'], static fn (array $item): bool => isset($item['tax_components']['IRRF'])));
    expect($irItems)->toHaveCount(2)
        ->and($irItems[0]['revenue_code'])->toBe('6190')
        ->and($irItems[1]['revenue_code'])->toBe('6147')
        ->and($irItems[0]['tax_components']['IRRF'])->toBe('24.00')
        ->and($irItems[1]['tax_components']['IRRF'])->toBe('6.00')
        ->and($projection['status'])->toBe('PROJECTED_WITH_OPEN_DUE_DATES');
});

it('requires evidence before applying a public component exception', function (): void {
    $result = publicRuleSet()->evaluate(publicFacts([
        ...publicBaseFacts('FEDERAL_PUBLIC_ADMINISTRATION', '1000.00'),
        'federal.pis_status' => 'EXEMPT',
    ]));
    expect($result['irrf']->status)->toBe(FederalWithholdingStatus::ACTION_REQUIRED)
        ->and($result['irrf']->reasonCodes)->toContain('PUBLIC_COMPONENT_EXCEPTION_EVIDENCE_MISSING');
});

it('retains the other public components under differential codes when PIS is exempt', function (): void {
    $result = publicRuleSet()->evaluate(publicFacts([
        ...publicBaseFacts('FEDERAL_PUBLIC_ADMINISTRATION', '1000.00'),
        'federal.pis_status' => 'EXEMPT',
        'federal.pis_exception_legal_basis' => 'DOCUMENTED_LEGAL_BASIS',
        'public.exception_disclosed_on_invoice' => true,
    ]));
    expect($result['irrf']->revenueCode)->toBe('6256')
        ->and($result['pcc']->decisions[0]->status)->toBe(FederalWithholdingStatus::EXEMPT)
        ->and($result['pcc']->decisions[1]->revenueCode)->toBe('6243')
        ->and($result['pcc']->decisions[2]->revenueCode)->toBe('6228')
        ->and($result['pcc']->totalWithholding)->toBe('40.00')
        ->and($result['pcc']->collectionCode)->toBeNull();
});

it('keeps municipal IR outside federal reporting until the local collection rule is supplied', function (): void {
    $facts = publicFacts([
        ...publicBaseFacts('MUNICIPAL_PUBLIC_ADMINISTRATION', '1000.00'),
        'public.article33_convention_exists' => false,
    ]);
    $tax = publicRuleSet()->evaluate($facts);
    $inss = new FederalWithholdingDecision(FederalTax::INSS, FederalWithholdingStatus::NOT_APPLICABLE,
        null, null, '0.00', null, 'PAYER', 'INVOICE_ISSUANCE', null, [], []);
    $catalog = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/federal-obligations-2026-sandbox.json'), true, 512, JSON_THROW_ON_ERROR);
    $projection = (new FederalObligationProjector($catalog))->project($facts, $tax['irrf'], $tax['pcc'], $inss);
    expect($projection['status'])->toBe('ACTION_REQUIRED')
        ->and($projection['items'])->toHaveCount(1)
        ->and($projection['items'][0]['event'])->toBe('LOCAL_IR_COLLECTION')
        ->and($projection['items'][0]['missing_facts'])->toContain('public.local_ir_collection_rule_id');
});

it('uses the original invoice for glosa without a new invoice and reconciles the payment', function (): void {
    $facts = [
        ...publicBaseFacts('FEDERAL_PUBLIC_ADMINISTRATION', '900.00'),
        'public.glosa_without_new_invoice' => true,
        'invoice.gross_amount' => '1000.00',
        'public.glosa_amount' => '100.00',
    ];
    $result = publicRuleSet()->evaluate(publicFacts($facts));
    expect($result['irrf']->amount)->toBe('48.00')
        ->and($result['irrf']->details['withholding_basis'])->toBe('1000.00')
        ->and($result['irrf']->details['payment_amount'])->toBe('900.00')
        ->and($result['pcc']->netPayment)->toBe('853.50');

    $conflict = publicRuleSet()->evaluate(publicFacts([...$facts, 'payment.gross_amount' => '899.00']));
    expect($conflict['irrf']->status)->toBe(FederalWithholdingStatus::ACTION_REQUIRED)
        ->and($conflict['irrf']->reasonCodes)->toContain('PUBLIC_INVOICE_PAYMENT_RECONCILIATION_REQUIRED');
});

it('includes evidenced late interest and penalties in the public withholding base', function (): void {
    $result = publicRuleSet()->evaluate(publicFacts([
        ...publicBaseFacts('FEDERAL_PUBLIC_ADMINISTRATION', '1050.00'),
        'public.has_late_additions' => true,
        'invoice.gross_amount' => '1000.00',
        'public.late_interest' => '30.00',
        'public.late_penalties' => '20.00',
    ]));
    expect($result['irrf']->amount)->toBe('50.40')
        ->and($result['irrf']->details['withholding_basis'])->toBe('1050.00');
});

it('resolves national filing dates from the versioned banking calendar and leaves 2027 open', function (): void {
    $calendar = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/federal-banking-calendar-2026-national.json'), true, 512, JSON_THROW_ON_ERROR);
    $resolver = new FederalBankingCalendar($calendar);
    expect($resolver->resolve('2026-10', 'DAY_15_NEXT_MONTH_PREVIOUS_BANKING_DAY')['due_date'])->toBe('2026-11-13')
        ->and($resolver->resolve('2026-10', 'LAST_BUSINESS_DAY_NEXT_MONTH')['due_date'])->toBe('2026-11-30')
        ->and($resolver->resolve('2026-12', 'DAY_15_NEXT_MONTH_PREVIOUS_BANKING_DAY')['due_date'])->toBeNull();
});

it('separates federal public collection from filing and uses liquidation for non Siafi INSS', function (): void {
    $catalog = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/federal-obligations-2026-sandbox-v2.json'), true, 512, JSON_THROW_ON_ERROR);
    $calendar = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/federal-banking-calendar-2026-national.json'), true, 512, JSON_THROW_ON_ERROR);
    $projector = new FederalObligationProjector($catalog, new FederalBankingCalendar($calendar));
    $ir = new FederalWithholdingDecision(FederalTax::IRRF, FederalWithholdingStatus::WITHHOLD,
        '1000.00', '4.80', '48.00', '6190', 'PAYER', 'PAYMENT', '2026-05-20', [], []);
    $inss = new FederalWithholdingDecision(FederalTax::INSS, FederalWithholdingStatus::WITHHOLD,
        '1000.00', '11.00', '110.00', null, 'PAYER', 'INVOICE_ISSUANCE', '2026-04-15', [], []);
    $pcc = new PccWithholdingResult('NOT_APPLICABLE', [], null, null, null, null, null, 'NONE');
    $base = [
        'payer.person_type' => 'FEDERAL_PUBLIC_ADMINISTRATION',
        'payer.head_office_tax_id' => '12345678000199',
        'provider.person_type' => 'PRIVATE_LEGAL_ENTITY',
        'provider.head_office_tax_id' => '98765432000111',
        'federal.income_nature_code' => '15001',
        'payment.date' => '2026-05-20',
        'payer.siafi_electronic_collection' => false,
    ];
    $pending = $projector->project(publicFacts($base, 'v3'), $ir, $pcc, $inss);
    expect($pending['items'][0]['collection_due_date'])->toBe('2026-06-19')
        ->and($pending['items'][0]['due_date'])->toBe('2026-06-15')
        ->and($pending['items'][1]['status'])->toBe('ACTION_REQUIRED')
        ->and($pending['items'][1]['collection_missing_facts'])->toContain('public.expenditure_liquidation_date');

    $resolved = $projector->project(publicFacts([...$base, 'public.expenditure_liquidation_date' => '2026-05-21'], 'v3'), $ir, $pcc, $inss);
    expect($resolved['items'][1]['collection_basis_date'])->toBe('2026-05-21')
        ->and($resolved['items'][1]['collection_due_date'])->toBe('2026-06-19')
        ->and($resolved['items'][1]['tax_period'])->toBe('2026-05')
        ->and($resolved['items'][1]['reinf_invoice_date'])->toBe('2026-05-21')
        ->and($resolved['items'][2]['tax_period'])->toBe('2026-04');

    $sameMonth = $projector->project(publicFacts([...$base, 'public.expenditure_liquidation_date' => '2026-04-21'], 'v3'), $ir, $pcc, $inss);
    expect($sameMonth['items'][1]['reinf_invoice_date'])->toBe('2026-04-15')
        ->and($sameMonth['items'][1]['tax_period'])->toBe('2026-04');

    $siafi = $projector->project(publicFacts([...$base, 'payer.siafi_electronic_collection' => true], 'v3'), $ir, $pcc, $inss);
    expect($siafi['items'][1]['collection_basis_date'])->toBe('2026-04-15')
        ->and($siafi['items'][1]['collection_due_date'])->toBe('2026-05-20');
});

it('carries the construction CNO and its holder into R-2010', function (): void {
    $catalog = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/federal-obligations-2026-sandbox.json'), true, 512, JSON_THROW_ON_ERROR);
    $ir = new FederalWithholdingDecision(FederalTax::IRRF, FederalWithholdingStatus::NOT_APPLICABLE,
        null, null, '0.00', null, 'PAYER', 'PAYMENT', null, [], []);
    $inss = new FederalWithholdingDecision(FederalTax::INSS, FederalWithholdingStatus::WITHHOLD,
        '1000.00', '11.00', '110.00', '2631', 'PAYER', 'INVOICE_ISSUANCE', '2026-09-24',
        ['INSS_RETENTION_11'], ['Lei 8.212/1991, art. 31'], [],
        ['construction' => ['status' => 'RESOLVED', 'details' => ['cno' => '123456789012', 'cno_holder' => 'CONTRACTING_PARTY']]]);
    $pcc = new PccWithholdingResult('NOT_APPLICABLE', [], null, null, null, null, null, 'NONE');
    $projection = (new FederalObligationProjector($catalog))->project(publicFacts([
        'payer.person_type' => 'PRIVATE_LEGAL_ENTITY',
        'payer.head_office_tax_id' => '12345678000199',
        'provider.person_type' => 'PRIVATE_LEGAL_ENTITY',
    ]), $ir, $pcc, $inss);
    expect($projection['items'][0]['event'])->toBe('R-2010')
        ->and($projection['items'][0]['status'])->toBe('PROJECTED_PENDING_CALENDAR')
        ->and($projection['items'][0]['revenue_code_status'])->toBe('DERIVED_IN_DCTFWEB_FROM_R2010')
        ->and($projection['items'][0]['work_cno'])->toBe('123456789012')
        ->and($projection['items'][0]['work_cno_holder'])->toBe('CONTRACTING_PARTY')
        ->and($projection['items'][1]['status'])->toBe('ACTION_REQUIRED')
        ->and($projection['items'][1]['missing_facts'])->toContain('provider.head_office_tax_id');
});

it('routes a foreign legal beneficiary through R-4020', function (): void {
    $catalog = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/federal-obligations-2026-sandbox.json'), true, 512, JSON_THROW_ON_ERROR);
    $ir = new FederalWithholdingDecision(FederalTax::IRRF, FederalWithholdingStatus::WITHHOLD,
        '1000.00', '15.00', '150.00', '0422', 'PAYER', 'PAYMENT_CREDIT_DELIVERY_EMPLOYMENT_OR_REMITTANCE',
        '2026-09-24', ['IRRF_FOREIGN_TECHNICAL_SERVICE_15'], ['RIR/2018']);
    $inss = new FederalWithholdingDecision(FederalTax::INSS, FederalWithholdingStatus::NOT_APPLICABLE,
        null, null, '0.00', null, 'PAYER', 'INVOICE_ISSUANCE', null, [], []);
    $pcc = new PccWithholdingResult('NOT_APPLICABLE', [], null, null, null, null, null, 'NONE');
    $projection = (new FederalObligationProjector($catalog))->project(publicFacts([
        'payer.person_type' => 'PRIVATE_LEGAL_ENTITY',
        'payer.head_office_tax_id' => '12345678000199',
        'provider.person_type' => 'FOREIGN_LEGAL_ENTITY',
    ]), $ir, $pcc, $inss);
    expect($projection['items'][0]['event'])->toBe('R-4020')
        ->and($projection['items'][0]['status'])->toBe('PROJECTED_PENDING_CALENDAR');
});

it('requires total-contract qualification and parent evidence for v4 construction', function (): void {
    $profile = new ConstructionPrevidenciaryProfile;
    $base = ['construction.previdenciary_object_type' => 'WORK', 'construction.cno_required' => true,
        'construction.cno' => '123456789012', 'construction.cno_holder' => 'PROVIDER',
        'inss.execution_mode' => 'TOTAL_CONSTRUCTION'];
    $missing = $profile->classify(publicFacts($base, 'v4'));
    $total = $profile->classify(publicFacts([...$base,
        'construction.total_contract_qualified' => true, 'construction.total_scope_confirmed' => true], 'v4'));
    $partial = $profile->classify(publicFacts([...$base,
        'inss.execution_mode' => 'PARTIAL_CONSTRUCTION',
        'construction.work_responsible_party' => 'PROVIDER'], 'v4'));
    $subcontract = $profile->classify(publicFacts([...$base,
        'inss.execution_mode' => 'SUBCONTRACT',
        'construction.work_responsible_party' => 'PROVIDER'], 'v4'));

    expect($missing['missing'])->toContain('construction.total_contract_qualified')
        ->and($total['status'])->toBe('RESOLVED')
        ->and($partial['status'])->toBe('RESOLVED')
        ->and($subcontract['missing'])->toContain('construction.parent_contract_reference');
});

it('uses the first decade of the next month for public cooperative IR 3280', function (): void {
    $calendarCatalog = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/federal-banking-calendar-2026-national.json'), true, 512, JSON_THROW_ON_ERROR);
    $catalog = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/federal-obligations-2026-sandbox-v3.json'), true, 512, JSON_THROW_ON_ERROR);
    $ir = new FederalWithholdingDecision(FederalTax::IRRF, FederalWithholdingStatus::WITHHOLD,
        '1000.00', '1.50', '15.00', '3280', 'PAYER', 'PAYMENT', '2026-09-24',
        ['PUBLIC_COOPERATIVE_PERSONAL_SERVICE'], ['IN RFB 1.234/2012, art. 26']);
    $inss = new FederalWithholdingDecision(FederalTax::INSS, FederalWithholdingStatus::NOT_APPLICABLE,
        null, null, '0.00', null, 'PAYER', 'INVOICE_ISSUANCE', null, [], []);
    $pcc = new PccWithholdingResult('NOT_APPLICABLE', [], null, null, null, null, null, 'NONE');
    $projection = (new FederalObligationProjector($catalog, new FederalBankingCalendar($calendarCatalog)))->project(publicFacts([
        'payer.person_type' => 'FEDERAL_PUBLIC_ADMINISTRATION',
        'payer.head_office_tax_id' => '12345678000199',
        'provider.person_type' => 'COOPERATIVE',
        'payment.date' => '2026-09-24',
        'federal.income_nature_code' => '12345',
    ], 'v4'), $ir, $pcc, $inss);

    expect($projection['items'][0]['collection_due_rule'])->toBe('DAY_10_NEXT_MONTH_PREVIOUS_BANKING_DAY')
        ->and($projection['items'][0]['collection_due_date'])->toBe('2026-10-09');
});

it('reconciles the private work cooperative PCC base to legally classified invoice components', function (): void {
    $base = [
        'payment.occurred' => true, 'payment.date' => '2026-09-24', 'payment.gross_amount' => '1000.00',
        'payer.person_type' => 'PRIVATE_LEGAL_ENTITY', 'payer.tax_regime' => 'LUCRO_REAL',
        'provider.person_type' => 'COOPERATIVE', 'provider.tax_regime' => 'LUCRO_REAL',
        'cooperative.provider_type' => 'WORK', 'cooperative.act_type' => 'MIXED',
        'cooperative.act_legal_basis' => 'Contrato e Lei 5.764/1971',
        'cooperative.pcc_taxable_base' => '700.00', 'cooperative.pcc_basis_evidence_confirmed' => true,
        'cooperative.invoice_components_evidence_confirmed' => true,
        'cooperative.invoice_components' => [
            ['line_id' => 'service', 'document_id' => 'nf-1', 'amount' => '700.00',
                'pcc_taxable' => true, 'pcc_treatment_legal_basis' => 'Lei 10.833/2003, art. 30'],
            ['line_id' => 'repass', 'document_id' => 'nf-2', 'amount' => '300.00',
                'pcc_taxable' => false, 'pcc_treatment_legal_basis' => 'Parecer jurídico documentado'],
        ],
        'federal.service_category' => 'COOPERATIVE_PERSONAL_SERVICE',
        'federal.pis_status' => 'APPLICABLE', 'federal.cofins_status' => 'APPLICABLE',
    ];
    $valid = (new PccWithholdingEngine)->evaluate(publicFacts($base, 'v4'));
    $invalid = (new PccWithholdingEngine)->evaluate(publicFacts([...$base,
        'cooperative.pcc_taxable_base' => '1000.00'], 'v4'));

    expect($valid->totalWithholding)->toBe('25.55')
        ->and($invalid->reasonCodes)->toContain('PCC_COOPERATIVE_COMPONENT_RECONCILIATION_FAILED');
});

it('retains applicable public contributions while honoring a documented IR exemption', function (): void {
    $facts = publicFacts([...publicBaseFacts('FEDERAL_PUBLIC_ADMINISTRATION', '1000.00'),
        'public.beneficiary_treatment' => 'EXEMPT',
        'federal.irrf_status' => 'EXEMPT',
        'federal.irrf_exception_legal_basis' => 'Ato normativo individual identificado',
        'public.exception_disclosed_on_invoice' => true,
    ], 'v4');
    $result = publicRuleSet()->evaluate($facts);

    expect($result['irrf']->status)->toBe(FederalWithholdingStatus::EXEMPT)
        ->and($result['irrf']->amount)->toBe('0.00')
        ->and($result['pcc']->totalWithholding)->toBe('46.50');
});

it('routes subnational public companies to the private withholding profile', function (): void {
    $facts = publicFacts([
        'payer.person_type' => 'STATE_PUBLIC_ADMINISTRATION',
        'payer.public_entity_type' => 'PUBLIC_COMPANY',
        'payer.tax_regime' => 'PUBLIC_ENTITY',
        'provider.person_type' => 'PRIVATE_LEGAL_ENTITY',
        'provider.tax_regime' => 'LUCRO_REAL',
        'payment.occurred' => true, 'payment.date' => '2026-09-24',
        'payment.gross_amount' => '1000.00', 'federal.service_category' => 'CLEANING',
        'federal.pis_status' => 'APPLICABLE', 'federal.cofins_status' => 'APPLICABLE',
        'federal.csll_status' => 'APPLICABLE',
        'invoice.issue_date' => '2026-09-24', 'inss.service_group' => 'OUTSIDE_EXHAUSTIVE_LIST',
        'inss.service_category' => 'CLEANING_CONSERVATION_JANITORIAL',
        'inss.execution_mode' => 'CONTRACT_FOR_RESULT',
    ], 'v4');
    $result = (new FederalWithholdingEngine)->evaluate($facts);

    expect($result->irrf->status)->toBe(FederalWithholdingStatus::DISPENSED_THRESHOLD)
        ->and($result->pcc->totalWithholding)->toBe('46.50')
        ->and($result->reasonCodes)->toContain('PUBLIC_SUBNATIONAL_ENTERPRISE_PRIVATE_WITHHOLDING_PROFILE');
});

it('keeps production cooperative goods outside private service retention', function (): void {
    $facts = publicFacts([
        'payment.occurred' => true, 'payment.date' => '2026-09-24', 'payment.gross_amount' => '1000.00',
        'payer.person_type' => 'PRIVATE_LEGAL_ENTITY', 'payer.tax_regime' => 'LUCRO_REAL',
        'provider.person_type' => 'COOPERATIVE', 'provider.tax_regime' => 'LUCRO_REAL',
        'cooperative.provider_type' => 'PRODUCTION', 'cooperative.act_type' => 'COOPERATIVE_ACT',
        'cooperative.act_legal_basis' => 'Lei 5.764/1971, documento fiscal de produtos de associados',
        'federal.service_category' => 'OTHER',
        'invoice.issue_date' => '2026-09-24', 'inss.service_group' => 'OUTSIDE_EXHAUSTIVE_LIST',
        'inss.service_category' => 'GOODS', 'inss.execution_mode' => 'CONTRACT_FOR_RESULT',
    ], 'v4');

    expect((new IrrfWithholdingEngine)->evaluate($facts)->status)->toBe(FederalWithholdingStatus::NOT_APPLICABLE)
        ->and((new PccWithholdingEngine)->evaluate($facts)->status)->toBe('NOT_APPLICABLE')
        ->and((new InssWithholdingEngine)->evaluate($facts)->status)->toBe(FederalWithholdingStatus::NOT_APPLICABLE);
});

it('moves the eSocial S-1210 deadline forward when the fifteenth is not a banking day', function (): void {
    $calendarCatalog = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/federal-banking-calendar-2026-national.json'), true, 512, JSON_THROW_ON_ERROR);
    $calendar = new FederalBankingCalendar($calendarCatalog);

    expect($calendar->resolve('2026-07', 'DAY_15_NEXT_MONTH_NEXT_BANKING_DAY')['due_date'])
        ->toBe('2026-08-17');
});

it('routes a public payment to a foreign beneficiary through art 35 and daily IR collection', function (): void {
    $calendarCatalog = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/federal-banking-calendar-2026-national.json'), true, 512, JSON_THROW_ON_ERROR);
    $catalog = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/federal-obligations-2026-sandbox-v3.json'), true, 512, JSON_THROW_ON_ERROR);
    $facts = publicFacts([
        'payer.person_type' => 'FEDERAL_PUBLIC_ADMINISTRATION', 'payer.public_entity_type' => 'DIRECT',
        'payer.head_office_tax_id' => '12345678000199',
        'provider.person_type' => 'FOREIGN_LEGAL_ENTITY', 'provider.foreign_tax_id' => 'US-ABC-123',
        'provider.favored_tax_jurisdiction' => false,
        'payment.occurred' => true, 'payment.date' => '2026-09-24',
        'payment.gross_amount' => '1000.00', 'federal.foreign_income_category' => 'TECHNICAL_OR_ADMINISTRATIVE_ASSISTANCE',
        'federal.foreign_taxable_base' => '1000.00', 'federal.foreign_treaty_applicable' => false,
        'federal.foreign_revenue_code' => '0422', 'federal.income_nature_code' => '17001',
        'invoice.issue_date' => '2026-09-24', 'inss.service_group' => 'OUTSIDE_EXHAUSTIVE_LIST',
        'inss.service_category' => 'TECHNICAL_SERVICE', 'inss.execution_mode' => 'CONTRACT_FOR_RESULT',
    ], 'v4');
    $engine = new FederalWithholdingEngine(public: publicRuleSet(),
        obligations: new FederalObligationProjector($catalog, new FederalBankingCalendar($calendarCatalog)));
    $result = $engine->evaluate($facts);

    expect($result->status)->toBe('RESOLVED')
        ->and($result->irrf->amount)->toBe('150.00')
        ->and($result->pcc->status)->toBe('NOT_APPLICABLE')
        ->and($result->obligations['items'][0]['beneficiary_foreign_tax_id'])->toBe('US-ABC-123')
        ->and($result->obligations['items'][0]['collection_due_date'])->toBe('2026-09-24');
});
