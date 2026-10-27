<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\NFSe\DocumentFederalPayment137;
use App\Modules\Fiscal\NFSe\DocumentFederalPayment139;
use App\Modules\Fiscal\NFSe\PartialExportDpsPair135;
use App\Modules\Fiscal\NFSe\PartialExportFederalPair137;
use App\Modules\Fiscal\NFSe\PartialExportFederalPair139;
use Illuminate\Support\Facades\Artisan;

require_once dirname(__DIR__, 3).'/scripts/phase4b_public139_pair_fixture.php';

function public139PairCase(string $profile = 'federal-direct'): array
{
    static $cache = [];

    return $cache[$profile] ??= public139PairFixture($profile);
}

function public139PairProject(array $case): array
{
    $d = $case['declarations'];

    return (new PartialExportFederalPair139)->project($case['snapshot'],
        array_diff_key($d, ['payment_proofs' => true, 'own_selection_proofs' => true]),
        $d['payment_proofs'], $d['own_selection_proofs'], resource_path('fiscal'));
}

it('builds two complete XSD documents for each public profile without replacing own debits', function (string $profile, string $irrf, string $social, string $net, ?string $code): void {
    $case = public139PairCase($profile);
    $before = CanonicalJson::hash($case);
    $result = public139PairProject($case);
    expect($result['status'])->toBe('LOCAL_OWN_AND_WITHHELD_PAIR_XML_XSD_VALIDATED')
        ->and($result['document_payment_contract'])->toBe(DocumentFederalPayment139::CONTRACT)
        ->and($result['payment_binding']['prior_snapshot_replaced'])->toBeFalse()
        ->and($result['production_ready'])->toBeFalse();
    $rows = array_column($result['records'], null, 'role');
    $payments = array_column($result['payment_binding']['documents'], null, 'role');
    $domestic = $rows['RTC_DOMESTIC'];
    $federal = $payments['RTC_DOMESTIC']['federal_evaluation'];
    expect($domestic['own_fields']['vPis'])->toBe('4.05')
        ->and($domestic['own_fields']['vCofins'])->toBe('18.68')
        ->and($federal['irrf']['amount'])->toBe($irrf)
        ->and($federal['pcc']['total_withholding'])->toBe($social)
        ->and($federal['pcc']['collection_code'])->toBe($code)
        ->and($domestic['bank_net_brl'])->toBe($net)
        ->and($payments['RTC_DOMESTIC']['federal_snapshot']['knowledge']['rule_bundle_id'])->toBe('CONSUMPTION-PHASE4B-2026.10.26-139-draft1')
        ->and($case['snapshot']['knowledge']['rule_bundle_id'])->toBe('CONSUMPTION-PHASE4B-2026.10.22-134-draft1');
    $d = $case['declarations'];
    $prior = (new PartialExportDpsPair135)->project($case['snapshot'], array_diff_key($d, ['payment_proofs' => true, 'own_selection_proofs' => true]), resource_path('fiscal'));
    $priorRows = array_column($prior['records'], null, 'role');
    foreach ($rows as $role => $row) {
        expect($row['validation']['status'])->toBe('VALID');
        $xml = new DOMDocument;
        $xml->loadXML($row['xml'], LIBXML_NONET);
        $xp = new DOMXPath($xml);
        $xp->registerNamespace('n', 'http://www.sped.fazenda.gov.br/nfse');
        $node = $xp->query('/n:DPS/n:infDPS/n:valores/n:trib/n:tribFed')->item(0);
        $node->parentNode->removeChild($node);
        $old = new DOMDocument;
        $old->loadXML($priorRows[$role]['xml'], LIBXML_NONET);
        expect($xml->C14N())->toBe($old->C14N());
    }
    expect(isset($domestic['retention_fields']['vRetCSLL']))->toBe($social !== '0.00')
        ->and(isset($domestic['retention_fields']['vRetIRRF']))->toBe($irrf !== '0.00')
        ->and($domestic['own_fields']['tpRetPisCofins'])->toBe($social === '0.00' ? '0' : '3')
        ->and(CanonicalJson::hash($case))->toBe($before);
})->with([
    ['federal-direct', '30.78', '29.82', '580.73', '6190'],
    ['federal-mixed-company', '30.78', '29.82', '580.73', '6190'],
    ['municipal-no-convention', '30.78', '0.00', '610.55', null],
    ['municipal-with-convention', '30.78', '29.82', '580.73', '4085'],
    ['state-public-company', '0.00', '29.82', '611.51', '5952'],
]);

it('refuses contradictory freshly captured public payments before returning any XML', function (string $profile, string $mutation): void {
    $case = public139PairCase($profile);
    foreach ($case['declarations']['payment_proofs'] as &$proof) {
        if ($proof['value']['document']['role'] !== 'RTC_DOMESTIC') {
            continue;
        }
        $v = &$proof['value'];
        match ($mutation) {
            'entity missing' => $v['federal_facts'] = array_diff_key($v['federal_facts'], ['payer.public_entity_type' => true]),
            'unknown category' => $v['federal_facts']['public.annex_category'] = 'UNKNOWN',
            'foundation unqualified' => $v['federal_facts']['payer.public_entity_type'] = 'PUBLIC_FOUNDATION',
            'convention missing' => $v['federal_facts'] = array_diff_key($v['federal_facts'], ['public.article33_convention_exists' => true]),
            'convention source missing' => $v['federal_facts'] = array_diff_key($v['federal_facts'], ['public.article33_convention_source_id' => true]),
            'convention expired' => $v['federal_facts']['public.article33_convention_valid_to'] = '2025-12-31',
            'local rule missing' => $v['federal_facts'] = array_diff_key($v['federal_facts'], ['public.local_ir_collection_rule_id' => true]),
            'local rule expired' => $v['federal_facts']['public.local_ir_valid_to'] = '2025-12-31',
            'siafi wrong type' => $v['federal_facts']['payer.siafi_electronic_collection'] = 'false',
            'combined rate rounding' => $v['payment']['net_brl'] = '580.72',
            'old contract' => $proof['contract'] = DocumentFederalPayment137::CONTRACT,
            'old policy hash' => $v['source_policy_hash'] = CanonicalJson::hash(DocumentFederalPayment137::policy()),
        };
        $v['payment']['bank'] = payment137Bank($v);
        unset($v);
        $proof = own135Capture($proof);
    }
    unset($proof);
    $result = public139PairProject($case);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['records'])->toBe([]);
})->with([
    ['federal-direct', 'entity missing'], ['federal-direct', 'unknown category'],
    ['municipal-no-convention', 'foundation unqualified'], ['municipal-no-convention', 'convention missing'],
    ['municipal-with-convention', 'convention source missing'], ['municipal-with-convention', 'convention expired'],
    ['municipal-no-convention', 'local rule missing'], ['municipal-no-convention', 'local rule expired'],
    ['federal-direct', 'siafi wrong type'], ['federal-direct', 'combined rate rounding'],
    ['federal-direct', 'old contract'], ['federal-direct', 'old policy hash'],
]);

it('propagates the material Siafi question through the complete document coordinator', function (): void {
    $case = public139PairSiafiFixture(null);
    $result = public139PairProject($case);
    expect($result['status'])->toBe('ACTION_REQUIRED')
        ->and($result['records'])->toBe([])
        ->and($result['missing_facts'])->toContain('payer.siafi_electronic_collection')
        ->and(array_column($result['questions'], 'fact_key'))->toContain('payer.siafi_electronic_collection');
});

it('keeps own debits and mixed withholding codes distinct for explicit Siafi alternatives', function (bool $siafi, string $type, string $social, string $net): void {
    $result = public139PairProject(public139PairSiafiFixture($siafi));
    expect($result['status'])->toBe('LOCAL_OWN_AND_WITHHELD_PAIR_XML_XSD_VALIDATED');
    $row = array_column($result['records'], null, 'role')['RTC_DOMESTIC'];
    expect($row['own_fields']['vPis'])->toBe('4.05')
        ->and($row['own_fields']['vCofins'])->toBe('18.68')
        ->and($row['own_fields']['tpRetPisCofins'])->toBe($type)
        ->and($row['retention_fields']['vRetCSLL'] ?? '0.00')->toBe($social)
        ->and($row['bank_net_brl'])->toBe($net);
})->with([[false, '0', '0.00', '641.33'], [true, '5', '4.17', '637.16']]);

it('keeps new contracts unavailable to archived137 callers and rejects139 before availability', function (): void {
    $case = public139PairCase();
    $d = $case['declarations'];
    $prior = array_diff_key($d, ['payment_proofs' => true, 'own_selection_proofs' => true]);
    $legacy = (new PartialExportFederalPair137)->project($case['snapshot'], $prior, $d['payment_proofs'], $d['own_selection_proofs'], resource_path('fiscal'));
    expect($legacy['status'])->toBe('ACTION_REQUIRED')->and($legacy['records'])->toBe([]);
    $case['snapshot']['input']['knowledge_at'] = '2026-10-26T19:21:59+00:00';
    $early = public139PairProject($case);
    expect($early['status'])->toBe('ACTION_REQUIRED')
        ->and($early['records'])->toBe([])
        ->and($early['reason'])->toContain('DOCPAY139_SOURCE_NOT_YET_KNOWN');
});

it('uses the explicit139 CLI selection and refuses bad selections without output directories', function (): void {
    $case = public139PairCase('municipal-no-convention');
    $root = sys_get_temp_dir().'/public139-cli-'.bin2hex(random_bytes(5));
    mkdir($root, 0700);
    file_put_contents($root.'/snapshot.json', CanonicalJson::encode($case['snapshot']));
    file_put_contents($root.'/declarations.json', CanonicalJson::encode($case['declarations']));
    $args = ['snapshot' => $root.'/snapshot.json', 'declarations' => $root.'/declarations.json',
        '--public-payment-contract' => '139', '--output-dir' => $root.'/accepted'];
    expect(Artisan::call('fiscal:dps:project-export-pair-withholdings', $args))->toBe(0)
        ->and(is_file($root.'/accepted/RTC_DOMESTIC.xml'))->toBeTrue();
    $report = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
    expect($report['document_payment_contract'])->toBe(DocumentFederalPayment139::CONTRACT);
    $args['--public-payment-contract'] = 'UNKNOWN';
    $args['--output-dir'] = $root.'/refused';
    expect(Artisan::call('fiscal:dps:project-export-pair-withholdings', $args))->toBe(1)
        ->and(file_exists($root.'/refused'))->toBeFalse();
});
