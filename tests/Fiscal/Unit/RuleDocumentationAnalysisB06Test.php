<?php

declare(strict_types=1);

function b06Artifact(): array
{
    $path = dirname(__DIR__, 3).'/storage/app/phase4b-b06-development-20261103/b06-rule-documentation-analysis.json';
    if (! is_file($path)) {
        test()->markTestSkipped('B06 artifact not generated in this checkout.');
    }

    return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
}

it('covers exactly the 83 divergent or uncorrelated engine rules reported by B04', function (): void {
    $root = dirname(__DIR__, 3);
    $b04 = json_decode((string) file_get_contents($root.'/storage/app/phase4b-b04-development-20261103/b04-reconciliation-report.json'), true, 512, JSON_THROW_ON_ERROR);
    $expected = [];
    foreach ($b04['report']['items'] as $item) {
        if (in_array($item['editions']['1.02.00']['status'], ['DIVERGENT_NO_LITERAL_MATCH', 'NO_OFFICIAL_ROW_IN_PREFIX'], true)) {
            $expected[] = $item['rule'];
        }
    }
    sort($expected);
    $artifact = b06Artifact();
    expect(array_column($artifact['results'], 'rule'))->toBe($expected)
        ->and($artifact['targets'])->toBe(83)
        ->and($artifact['summary'])->toBe(['CONTROVERSA_INTERPRETACAO_JURIDICA' => 40, 'RESOLVIDA_DOCUMENTALMENTE' => 37,
            'RESOLVIDA_DOCUMENTALMENTE_CONDICIONADA_A_FATOS' => 6])
        ->and($artifact['decision_authority'])->toBeFalse()->and($artifact['rules_changed'])->toBeFalse();
});

it('reduces every controversy to the single standard-on-annex question and keeps resolved rules fully checked', function (): void {
    $catalog = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/rtc-codes-2026.10.27-141-draft1.json'), true, 512, JSON_THROW_ON_ERROR);
    foreach (b06Artifact()['results'] as $r) {
        $rule = $catalog['engine_decision_rules'][$r['rule']];
        expect(array_filter($r['checks'], fn (mixed $v): bool => $v === false))->toBe([], $r['rule']);
        if (str_starts_with($r['classification'], 'CONTROVERSA')) {
            expect($r['flags'])->toBe(['PADRAO_DECLARADO_PARA_NBS_DE_ANEXO'], $r['rule'])
                ->and($rule['cclasstrib'])->toBe('000001')->and($rule['annex_service_category'])->toBeIn(['EDUCATION', 'HEALTH']);
        }
        if (in_array($rule['cclasstrib'], ['200028', '200029'], true)) {
            expect($r['annex'])->not->toBe('NBS_FORA_DO_ANEXO', $r['rule']);
        }
        expect($r['correlation_by_item']['1.02.00']['status'])->not->toBe('DIVERGENCIA_NAO_EXPLICADA_NO_ITEM', $r['rule']);
    }
});
