<?php

declare(strict_types=1);

// B15 (4B.5.8): revalidates the active supplier-period credit policy (arts. 49-52) against the
// current compiled text of LC 214/2025 preserved from Planalto, without changing the policy.

function b15LawText(): string
{
    static $text = null;
    if ($text === null) {
        $raw = (string) file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/sources/official/lc214-planalto-observed-20261103.htm');
        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }
        $text = (string) preg_replace('/\s+/u', ' ', html_entity_decode((string) preg_replace('/<[^>]+>/', ' ', $raw), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    return $text;
}

const B15_EXCERPTS = [
    'art49' => 'As operações imunes, isentas ou sujeitas a alíquota zero, a diferimento ou a suspensão não permitirão a apropriação de créditos pelos adquirentes dos bens e serviços.',
    'art49_presumed' => 'O disposto no caput deste artigo não impede a apropriação dos créditos presumidos previstos expressamente nesta Lei Complementar.',
    'art51' => 'A imunidade e a isenção acarretarão a anulação dos créditos relativos às operações anteriores.',
    'art51_1' => 'A anulação dos créditos de que trata o caput deste artigo será proporcional ao valor das operações imunes e isentas sobre o valor de todas as operações do fornecedor.',
    'art51_2' => 'O disposto no caput e no § 1º deste artigo não se aplica às: I - exportações; e II - operações de que tratam os incisos IV e VI do caput do art. 9º desta Lei Complementar.',
    'art52' => 'No caso de operações sujeitas a alíquota zero, serão mantidos os créditos relativos às operações anteriores.',
    'art9_iv' => 'IV - de livros, jornais, periódicos e do papel destinado a sua impressão;',
    'art9_vi' => 'VI - de serviço de comunicação nas modalidades de radiodifusão sonora e de sons e imagens de recepção livre e gratuita;',
];

it('finds every relied-upon provision once in the current official text, not superseded', function (string $key): void {
    $text = b15LawText();
    $excerpt = B15_EXCERPTS[$key];
    expect(substr_count($text, $excerpt))->toBe(1, $key);
    $after = substr($text, strpos($text, $excerpt) + strlen($excerpt), 2500);
    $later = strpos($after, substr($excerpt, 0, 45));
    expect($later === false || ! str_contains(substr($after, $later, 900), 'Redação dada'))->toBeTrue("superseded: {$key}");
})->with(array_keys(B15_EXCERPTS));

it('keeps the active policy aligned with arts. 51-52: only exports and art. 9º IV/VI immunities preserve credit', function (): void {
    $catalog = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/rtc-codes-2026.10.27-141-draft1.json'), true, 512, JSON_THROW_ON_ERROR);
    $policies = $catalog['supplier_credit_period']['output_policies'];
    $actions = array_map(fn (array $p): string => $p['action'], $policies);
    expect($actions)->toBe(['STANDARD' => 'PRESERVE', 'REDUCED_30' => 'PRESERVE', 'REDUCED_60' => 'PRESERVE', 'ZERO_RATE' => 'PRESERVE',
        'IMMUNITY_EXPORT' => 'PRESERVE', 'IMMUNITY_BOOKS' => 'PRESERVE', 'IMMUNITY_FREE_BROADCAST' => 'PRESERVE',
        'IMMUNITY_OTHER' => 'ANNUL_PROPORTIONALLY', 'EXEMPT' => 'ANNUL_PROPORTIONALLY'])
        ->and($policies['ZERO_RATE']['source_article'])->toContain('art. 52')
        ->and($policies['IMMUNITY_EXPORT']['source_article'])->toContain('art. 51, §2º, I')
        ->and($policies['IMMUNITY_BOOKS']['source_article'])->toContain('art.9º, IV')
        ->and($policies['IMMUNITY_FREE_BROADCAST']['source_article'])->toContain('art.9º, VI')
        ->and($policies['IMMUNITY_OTHER']['source_article'])->toContain('art. 51, caput e §1º')
        ->and($policies['EXEMPT']['source_article'])->toContain('art. 51, caput e §1º');
});
