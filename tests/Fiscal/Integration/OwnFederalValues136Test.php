<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\NFSe\OwnFederalValues136;
use App\Modules\Fiscal\NFSe\PartialExportDpsPair136;
use Illuminate\Support\Facades\Artisan;

require_once dirname(__DIR__, 3).'/scripts/phase4b_own136_fixture.php';

function own136Case(): array
{
    static $case;

    return $case ??= own136Fixture();
}

function own136Qualify(array $case, ?array $proofs = null, ?array $policy = null, ?DateTimeImmutable $known = null, ?string $tableRoot = null): array
{
    return (new OwnFederalValues136)->qualify($case['iss'], $case['own_proofs'], $proofs ?? $case['declarations']['own_federal_proofs'],
        $policy ?? OwnFederalValues136::policy(), $known ?? new DateTimeImmutable($case['known_at']),
        $tableRoot ?? resource_path('fiscal/sources/official/own136-sped'), resource_path('fiscal'));
}

it('qualifies own debits independently of withheld amounts and preserves nonincidence null bases', function (): void {
    $case = own136Case();
    $before = CanonicalJson::hash($case);
    $result = own136Qualify($case);
    $rows = array_column($result['documents'], null, 'role');
    expect($result['status'])->toBe('LOCAL_OWN_FEDERAL_FIELDS_QUALIFIED')
        ->and($rows['RTC_DOMESTIC']['piscofins'])->toBe(['CST' => '01', 'vBCPisCofins' => '622.73', 'pAliqPis' => '0.65',
            'pAliqCofins' => '3.00', 'vPis' => '4.05', 'vCofins' => '18.68', 'tpRetPisCofins' => '0'])
        ->and($rows['RTC_FOREIGN']['piscofins'])->toBe(['CST' => '08', 'vPis' => '0.00', 'vCofins' => '0.00', 'tpRetPisCofins' => '0'])
        ->and($rows['RTC_DOMESTIC']['vRetCSLL'])->toBeNull()
        ->and($result['skipped_withholding_engine_used_as_negative_proof'])->toBeFalse()
        ->and($result['withheld_amount_used_as_own_debit'])->toBeFalse()
        ->and($result['production_ready'])->toBeFalse()
        ->and(CanonicalJson::hash($case))->toBe($before);
});

it('selects the own CST from the qualified revenue treatment independently of the RTC leg', function (): void {
    $case = own136Case();
    foreach ($case['own_proofs'] as &$proof) {
        if ($proof['value']['document']['role'] === 'RTC_FOREIGN') {
            $proof['value']['revenue']['treatment'] = 'ORDINARY_CUMULATIVE';
            $proof['value']['revenue']['bank_inflow'] = null;
            $proof = own135Capture($proof);
        }
    }
    unset($proof);
    $proofs = own136Proofs($case['iss'], $case['own_proofs'], new DateTimeImmutable($case['known_at']));
    $result = own136Qualify($case, $proofs);
    $foreign = array_column($result['documents'], null, 'role')['RTC_FOREIGN']['piscofins'];
    expect($foreign['CST'])->toBe('01')->and($foreign['vBCPisCofins'])->toBe('400.00')
        ->and($foreign['vPis'])->toBe('2.60')->and($foreign['vCofins'])->toBe('12.00');
});

it('rejects a resealed documentary conflict for its specific reason', function (string $name, string $suffix): void {
    $case = own136Case();
    $proofs = $case['declarations']['own_federal_proofs'];
    match ($name) {
        'domestic CST' => $proofs[0]['value']['selection']['pis_cst'] = '07',
        'foreign CST' => $proofs[1]['value']['selection']['cofins_cst'] = '07',
        'export code' => $proofs[1]['value']['selection']['export_receipt_code'] = '401',
        'review missing' => $proofs[0]['value']['selection']['review_reference'] = '',
        'CSLL unassessed' => $proofs[0]['value']['withholding']['CSLL'] = 'NOT_ASSESSED',
        'PIS retained' => $proofs[0]['value']['withholding']['PIS'] = 'WITHHELD',
        'party context' => $proofs[0]['value']['document']['customer_id'] = 'OTHER',
        'price context' => $proofs[0]['value']['document']['composed_price_brl'] = '617.92',
        'own result' => $proofs[0]['value']['own_revenue_result_hash'] = str_repeat('0', 64),
        'own proof' => $proofs[0]['value']['own_proof_hash'] = str_repeat('0', 64),
        'policy hash' => $proofs[0]['value']['source_policy_hash'] = str_repeat('0', 64),
        'date coverage' => $proofs[0]['valid_from'] = '2026-12-01',
        'revocation' => $proofs[0]['revoked_at'] = $proofs[0]['observed_at'],
        'supersession' => $proofs[0]['superseded_at'] = $proofs[0]['observed_at'],
        'duplicate evidence' => $proofs[1]['evidence_id'] = $proofs[0]['evidence_id'],
        'future proof' => $proofs[0]['observed_at'] = '2026-10-24T12:51:47+00:00',
        'predates own' => $proofs[0]['observed_at'] = (new DateTimeImmutable($case['known_at']))->modify('-1 second')->format('Y-m-d\TH:i:s.uP'),
        'extra field' => $proofs[0]['value']['approved'] = true,
    };
    $proofs = array_map(own135Capture(...), $proofs);
    expect(own136Qualify($case, $proofs)['reason'])->toBe('OWN136_'.$suffix);
})->with([
    ['domestic CST', 'EXPLICIT_REVIEWED_OWN_CST_SELECTION_REQUIRED'],
    ['foreign CST', 'EXPLICIT_REVIEWED_OWN_CST_SELECTION_REQUIRED'],
    ['export code', 'EXPLICIT_REVIEWED_OWN_CST_SELECTION_REQUIRED'],
    ['review missing', 'EXPLICIT_REVIEWED_OWN_CST_SELECTION_REQUIRED'],
    ['CSLL unassessed', 'EXPLICIT_THREE_TAX_NOT_WITHHELD_EVIDENCE_REQUIRED'],
    ['PIS retained', 'EXPLICIT_THREE_TAX_NOT_WITHHELD_EVIDENCE_REQUIRED'],
    ['party context', 'FRESH_DOCUMENT_CONTEXT_AND_COVERAGE_REQUIRED'],
    ['price context', 'FRESH_DOCUMENT_CONTEXT_AND_COVERAGE_REQUIRED'],
    ['own result', 'FRESH_DOCUMENT_CONTEXT_AND_COVERAGE_REQUIRED'],
    ['own proof', 'FRESH_DOCUMENT_CONTEXT_AND_COVERAGE_REQUIRED'],
    ['policy hash', 'FRESH_DOCUMENT_CONTEXT_AND_COVERAGE_REQUIRED'],
    ['date coverage', 'FRESH_DOCUMENT_CONTEXT_AND_COVERAGE_REQUIRED'],
    ['revocation', 'CURRENT_DISTINCT_CAPTURED_DECLARATIONS_REQUIRED'],
    ['supersession', 'CURRENT_DISTINCT_CAPTURED_DECLARATIONS_REQUIRED'],
    ['duplicate evidence', 'CURRENT_DISTINCT_CAPTURED_DECLARATIONS_REQUIRED'],
    ['future proof', 'CURRENT_DISTINCT_CAPTURED_DECLARATIONS_REQUIRED'],
    ['predates own', 'DECLARATION_PREDATES_OWN_ASSESSMENT'],
    ['extra field', 'FRESH_DOCUMENT_CONTEXT_AND_COVERAGE_REQUIRED'],
]);

it('requires exact source policy,captured bytes,two proofs and source knowledge', function (): void {
    $case = own136Case();
    $proofs = $case['declarations']['own_federal_proofs'];
    $bad = $proofs;
    $bad[0]['origin']['content'] .= ' ';
    expect(own136Qualify($case, $bad)['reason'])->toBe('OWN136_CURRENT_DISTINCT_CAPTURED_DECLARATIONS_REQUIRED')
        ->and(own136Qualify($case, [$proofs[0]])['reason'])->toBe('OWN136_TWO_CAPTURED_DOCUMENT_DECLARATIONS_REQUIRED')
        ->and(own136Qualify($case, policy: [...OwnFederalValues136::policy(), 'retention_code' => '2'])['reason'])->toBe('OWN136_EXACT_POLICY_REQUIRED')
        ->and(own136Qualify($case, known: new DateTimeImmutable('2026-10-23T12:51:44+00:00'))['reason'])->toBe('OWN136_SOURCE_NOT_YET_KNOWN')
        ->and(own136Qualify($case, tableRoot: '/not-a-source136')['reason'])->toBe('OWN136_ORIGINAL_CST_SOURCE_BYTES_REQUIRED');
});

it('reexecutes the export own assessment instead of accepting its cached declaration', function (): void {
    $case = own136Case();
    foreach ($case['own_proofs'] as &$proof) {
        if ($proof['value']['document']['role'] === 'RTC_FOREIGN') {
            $proof['value']['revenue']['bank_inflow'] = null;
            $proof = own135Capture($proof);
        }
    }
    unset($proof);
    expect(own136Qualify($case)['reason'])->toBe('OWN136_FRESH_OWN_CALCULATION_REQUIRED');
});

it('produces both full XMLs with own fields in official sequence and retains pending fiscal limits', function (): void {
    $case = own136Case();
    $result = (new PartialExportDpsPair136)->project($case['snapshot'], $case['declarations'], resource_path('fiscal'));
    expect($result['contract'])->toBe(PartialExportDpsPair136::CONTRACT)
        ->and($result['own_federal_sources_valid'])->toBe(4)
        ->and($result['additional_sources_valid'])->toBe(12)
        ->and($result['own_pis_cofins_projected_in_xml'])->toBeTrue()
        ->and($result['complete_fiscal_pair_qualified'])->toBeFalse()
        ->and($result['document_obligation_complete'])->toBeFalse()
        ->and($result['production_ready'])->toBeFalse();
    foreach ($result['records'] as $record) {
        $doc = new DOMDocument;
        $doc->loadXML($record['xml'], LIBXML_NONET);
        $xp = new DOMXPath($doc);
        $xp->registerNamespace('n', 'http://www.sped.fazenda.gov.br/nfse');
        $path = '/n:DPS/n:infDPS/n:valores/n:trib';
        $names = [];
        foreach ($xp->query($path.'/*') as $node) {
            $names[] = $node->localName;
        }
        expect($record['validation']['status'])->toBe('VALID')->and($names)->toBe(['tribMun', 'tribFed', 'totTrib'])
            ->and($xp->evaluate('string('.$path.'/n:tribFed/n:piscofins/n:CST)'))->toBe($record['role'] === 'RTC_FOREIGN' ? '08' : '01')
            ->and($xp->evaluate('count('.$path.'/n:tribFed/n:vRetCSLL)'))->toBe(0.0)
            ->and($xp->evaluate('count('.$path.'/n:tribFed/n:piscofins/n:vBCPisCofins)'))->toBe($record['role'] === 'RTC_FOREIGN' ? 0.0 : 1.0);
    }
    $case['declarations']['own_federal_proofs'] = array_reverse($case['declarations']['own_federal_proofs']);
    expect((new PartialExportDpsPair136)->project($case['snapshot'], $case['declarations'], resource_path('fiscal')))->toBe($result);
});

it('rejects a federal evidence identifier reused from the documentary envelope', function (): void {
    $case = own136Case();
    $case['declarations']['own_federal_proofs'][0]['evidence_id'] = $case['declarations']['documentary_proof']['evidence_id'];
    $case['declarations']['own_federal_proofs'][0] = own135Capture($case['declarations']['own_federal_proofs'][0]);
    expect(fn () => (new PartialExportDpsPair136)->project($case['snapshot'], $case['declarations'], resource_path('fiscal')))
        ->toThrow(RuntimeException::class, 'Distinct own,COMEX,documentary and federal evidence identifiers required.');
});

it('writes the own136 pair through its CLI and refuses overwrite and a contradictory retention claim before writing', function (): void {
    $case = own136Case();
    $parent = sys_get_temp_dir().'/pair136-cli-'.bin2hex(random_bytes(8));
    mkdir($parent, 0700);
    file_put_contents($parent.'/snapshot.json', CanonicalJson::encode($case['snapshot']));
    file_put_contents($parent.'/declarations.json', CanonicalJson::encode($case['declarations']));
    $args = ['snapshot' => $parent.'/snapshot.json', 'declarations' => $parent.'/declarations.json', '--output-dir' => $parent.'/valid'];
    expect(Artisan::call('fiscal:dps:project-export-pair-own', $args))->toBe(0);
    $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    expect($report['own_pis_cofins_projected_in_xml'])->toBeTrue()->and(is_file($parent.'/valid/report.json'))->toBeTrue();
    foreach ($report['records'] as $record) {
        expect(hash_file('sha256', $parent.'/valid/'.$record['role'].'.xml'))->toBe($record['xml_sha256']);
    }
    $before = hash_file('sha256', $parent.'/valid/report.json');
    expect(Artisan::call('fiscal:dps:project-export-pair-own', $args))->toBe(1)
        ->and(hash_file('sha256', $parent.'/valid/report.json'))->toBe($before);
    $case['declarations']['own_federal_proofs'][0]['value']['withholding']['CSLL'] = 'NOT_ASSESSED';
    $case['declarations']['own_federal_proofs'][0] = own135Capture($case['declarations']['own_federal_proofs'][0]);
    file_put_contents($parent.'/declarations.json', CanonicalJson::encode($case['declarations']));
    $args['--output-dir'] = $parent.'/refused';
    expect(Artisan::call('fiscal:dps:project-export-pair-own', $args))->toBe(1)->and(file_exists($parent.'/refused'))->toBeFalse();
});
