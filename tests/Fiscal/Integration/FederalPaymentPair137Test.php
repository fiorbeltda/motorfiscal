<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\NFSe\DocumentFederalPayment137;
use App\Modules\Fiscal\NFSe\FederalRetention137;
use App\Modules\Fiscal\NFSe\OwnDebitTolerance137;
use App\Modules\Fiscal\NFSe\PartialExportDpsPair135;
use App\Modules\Fiscal\NFSe\PartialExportFederalPair137;
use Illuminate\Support\Facades\Artisan;

require_once dirname(__DIR__, 3).'/scripts/phase4b_retention137_fixture.php';

function retention137Case(): array
{
    static $case;

    return $case ??= retention137Fixture();
}

function retention137Project(array $case): array
{
    $declarations = $case['declarations'];

    return (new PartialExportFederalPair137)->project($case['snapshot'],
        array_diff_key($declarations, ['payment_proofs' => true, 'own_selection_proofs' => true]),
        $declarations['payment_proofs'], $declarations['own_selection_proofs'], resource_path('fiscal'));
}

function retention137Positive(): array
{
    static $result;

    return $result ??= retention137Project(retention137Case());
}

it('keeps own contributions distinct from social withholding in two complete XSD validated documents', function (): void {
    $case = retention137Case();
    $before = CanonicalJson::hash($case);
    $result = retention137Positive();
    $rows = array_column($result['records'], null, 'role');
    expect($result['status'])->toBe('LOCAL_OWN_AND_WITHHELD_PAIR_XML_XSD_VALIDATED')
        ->and($result['nt007_original_bytes_captured'])->toBeTrue()
        ->and($result['installed_in_application'])->toBeTrue()
        ->and($result['production_ready'])->toBeFalse()
        ->and($result['document_obligation_complete'])->toBeFalse()
        ->and($rows['RTC_DOMESTIC']['own_fields']['vPis'])->toBe('4.05')
        ->and($rows['RTC_DOMESTIC']['own_fields']['vCofins'])->toBe('18.68')
        ->and($rows['RTC_DOMESTIC']['retention_fields']['vRetCSLL'])->toBe('29.82')
        ->and($rows['RTC_DOMESTIC']['bank_net_brl'])->toBe('611.51')
        ->and($rows['RTC_FOREIGN']['retention_fields'])->toBe(['piscofins.tpRetPisCofins' => '0']);
    foreach ($rows as $row) {
        expect($row['validation']['status'])->toBe('VALID')
            ->and(hash('sha256', $row['xml']))->toBe($row['xml_sha256']);
    }
    expect(CanonicalJson::hash($case))->toBe($before);
});

it('keeps ordinary own debits when the foreign acquirer pays the domestic consumption document', function (): void {
    $case = retention137Case();
    foreach ($case['declarations']['payment_proofs'] as &$proof) {
        if ($proof['value']['document']['role'] !== 'RTC_DOMESTIC') {
            continue;
        }
        $facts = $case['snapshot']['input']['facts'];
        $proof['value']['payment'] = [...$proof['value']['payment'], 'payer_role' => 'ACQUIRER',
            'payer_id' => $facts['rtc.acquirer_id'], 'payer_country' => 'US', 'payer_person_type' => 'FOREIGN', 'net_brl' => '641.33'];
        $proof['value']['profile']['payer_tax_regime'] = 'NOT_APPLICABLE';
        $proof['value']['profile']['payer_head_office_tax_id'] = null;
        $proof['value']['payment']['bank'] = payment137Bank($proof['value']);
        $proof = own135Capture($proof);
    }
    unset($proof);
    $result = retention137Project($case);
    $row = array_column($result['records'], null, 'role')['RTC_DOMESTIC'];
    expect($row['own_fields']['CST'])->toBe('01')->and($row['own_fields']['vPis'])->toBe('4.05')
        ->and($row['own_fields']['vCofins'])->toBe('18.68')
        ->and($row['retention_fields'])->toBe(['piscofins.tpRetPisCofins' => '0'])
        ->and($row['bank_net_brl'])->toBe('641.33');
});

it('rejects an own customer country contradiction accepted by the historical projector', function (): void {
    $case = retention137Case();
    $decl = array_diff_key($case['declarations'], ['payment_proofs' => true, 'own_selection_proofs' => true]);
    foreach ($decl['own_tax_proofs'] as &$proof) {
        if ($proof['value']['document']['role'] === 'RTC_DOMESTIC') {
            $proof['value']['revenue']['customer_country'] = 'BR';
            $proof = own135Capture($proof);
        }
    }
    unset($proof);
    $decl = pair135Reseal($case['snapshot'], $decl);
    expect((new PartialExportDpsPair135)->project($case['snapshot'], $decl, resource_path('fiscal'))['complete_dps_built'])->toBeTrue();
    $result = (new DocumentFederalPayment137)->bind($case['snapshot'], $decl, $case['declarations']['payment_proofs'],
        DocumentFederalPayment137::policy(), resource_path('fiscal'));
    expect($result['reason'])->toBe('DOCPAY137_OWN_CUSTOMER_COUNTRY_CONFLICT_WITH_MATERIAL_ACQUIRER');
});

it('does not overwrite the material provider profile when building the federal scenario', function (): void {
    $case = retention137Case();
    $snapshot = $case['snapshot'];
    $snapshot['input']['facts']['provider.person_type'] = 'COOPERATIVE';
    $factory = new FiscalEngineFactory;
    $loaded = $factory->scenarioLoaderFromKnowledge($snapshot['knowledge'])->fromArray($snapshot['input']);
    $snapshot['result'] = $factory->makeFromKnowledge($snapshot['knowledge'])->evaluate($loaded->input)->toArray();
    $decl = array_diff_key($case['declarations'], ['payment_proofs' => true, 'own_selection_proofs' => true]);
    $decl = pair135Reseal($snapshot, $decl);
    $result = (new DocumentFederalPayment137)->bind($snapshot, $decl, $case['declarations']['payment_proofs'],
        DocumentFederalPayment137::policy(), resource_path('fiscal'));
    expect($result['reason'])->toBe('DOCPAY137_PRIOR_SNAPSHOT_AND_DOCUMENT_REPLAY_REQUIRED');
});

it('keeps the IRRF accounting credit date distinct from the PCC payment date', function (): void {
    $case = retention137Case();
    foreach ($case['declarations']['payment_proofs'] as &$proof) {
        if ($proof['value']['document']['role'] === 'RTC_DOMESTIC') {
            $proof['value']['payment']['payer_accounting_credit'] = ['status' => 'DATED_PAYER_ACCOUNTING_CREDIT',
                'credited_on' => '2026-10-22', 'evidence_reference' => 'SYNTHETIC-ACCOUNTING-ENTRY-137'];
            $proof = own135Capture($proof);
        }
    }
    unset($proof);
    $decl = array_diff_key($case['declarations'], ['payment_proofs' => true, 'own_selection_proofs' => true]);
    $result = (new DocumentFederalPayment137)->bind($case['snapshot'], $decl, $case['declarations']['payment_proofs'],
        DocumentFederalPayment137::policy(), resource_path('fiscal'));
    $federal = array_column($result['documents'], null, 'role')['RTC_DOMESTIC']['federal_evaluation'];
    expect($federal['irrf']['trigger_date'])->toBe('2026-10-22')
        ->and($federal['pcc']['decisions'][0]['trigger_date'])->toBe('2026-10-23');
});

it('rejects freshly resealed contradictory own selections and never returns partial XML', function (string $mutation): void {
    $case = retention137Case();
    $proof = &$case['declarations']['own_selection_proofs'][0];
    match ($mutation) {
        'classification' => $proof['value']['selection']['pis_cst'] = '07',
        'own amount hash' => $proof['value']['own_revenue_result_hash'] = str_repeat('0', 64),
        'false not withheld' => $proof['value']['withholding'] = ['PIS' => 'NOT_WITHHELD'],
        'duplicate evidence' => $proof['evidence_id'] = $case['declarations']['payment_proofs'][0]['evidence_id'],
    };
    $proof = own135Capture($proof);
    unset($proof);
    $result = retention137Project($case);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['records'])->toBe([]);
})->with(['classification', 'own amount hash', 'false not withheld', 'duplicate evidence']);

it('requires strictly positive withheld components even when all forged totals reconcile to zero', function (): void {
    $positive = retention137Positive();
    $federal = array_column($positive['payment_binding']['documents'], null, 'role')['RTC_DOMESTIC']['federal_evaluation'];
    foreach ($federal['pcc']['decisions'] as &$decision) {
        $decision['amount'] = '0.00';
    }
    unset($decision);
    $federal['pcc']['total_withholding'] = '0.00';
    $federal['pcc']['net_payment'] = $federal['pcc']['gross_payment'];
    $federal['total_withholding'] = '0.00';
    $federal['net_payment'] = $federal['gross_payment'];
    $result = (new FederalRetention137)->project($federal, FederalRetention137::policy(),
        new DateTimeImmutable(retention137Case()['known_at']), resource_path('fiscal'));
    expect($result['reason'])->toBe('RETENTION137_EXACT_WITHHELD_AMOUNT_REQUIRED');
});

it('requires preserved NT007 bytes independently of an intact layout workbook', function (): void {
    $root = sys_get_temp_dir().'/retention137-missing-source-'.bin2hex(random_bytes(5));
    mkdir($root.'/sources/official', 0700, true);
    copy(resource_path('fiscal/sources/official/export135-anexovi10401.xlsx'), $root.'/sources/official/export135-anexovi10401.xlsx');
    $result = (new FederalRetention137)->project([], FederalRetention137::policy(),
        new DateTimeImmutable(retention137Case()['known_at']), $root);
    expect($result['reason'])->toBe('RETENTION137_ORIGINAL_NT007_BYTES_REQUIRED');
});

it('preserves the own amount at half even ties within one cent and refuses larger divergence', function (string $amount, bool $accepted): void {
    $result = (new OwnDebitTolerance137)->compare('10.00', '0.65', $amount, 'TAXABLE');
    expect($result['status'] !== 'ACTION_REQUIRED')->toBe($accepted);
    if ($accepted) {
        expect($result['canonical_half_even_reference'])->toBe('0.06')
            ->and($result['value_preserved'])->toBe($amount)->and($result['amount_rewritten'])->toBeFalse();
    }
})->with([['0.05', true], ['0.06', true], ['0.07', true], ['0.08', false]]);

it('runs the real CLI and refuses an invalid payment before creating its output directory', function (): void {
    $case = retention137Case();
    $root = sys_get_temp_dir().'/retention137-cli-'.bin2hex(random_bytes(5));
    mkdir($root, 0700);
    file_put_contents($root.'/snapshot.json', CanonicalJson::encode($case['snapshot']));
    file_put_contents($root.'/declarations.json', CanonicalJson::encode($case['declarations']));
    expect(Artisan::call('fiscal:dps:project-export-pair-withholdings', ['snapshot' => $root.'/snapshot.json',
        'declarations' => $root.'/declarations.json', '--output-dir' => $root.'/accepted']))->toBe(0);
    $report = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
    expect($report['status'])->toBe('LOCAL_OWN_AND_WITHHELD_PAIR_XML_XSD_VALIDATED')
        ->and(is_file($root.'/accepted/RTC_DOMESTIC.xml'))->toBeTrue();
    $case['declarations']['payment_proofs'] = [];
    file_put_contents($root.'/invalid.json', CanonicalJson::encode($case['declarations']));
    expect(Artisan::call('fiscal:dps:project-export-pair-withholdings', ['snapshot' => $root.'/snapshot.json',
        'declarations' => $root.'/invalid.json', '--output-dir' => $root.'/refused']))->toBe(1)
        ->and(file_exists($root.'/refused'))->toBeFalse();
});
