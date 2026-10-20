<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\DerePublicOfferEventVersionReconciler;
use Tests\TestCase;

uses(TestCase::class);

function publicVersionNode60(DOMXPath $xpath, string $query): DOMElement
{
    $list = $xpath->query($query);
    $node = $list === false ? null : $list->item(0);
    if (! $node instanceof DOMElement) {
        throw new RuntimeException('Missing version fixture node.');
    }

    return $node;
}

/** @return array<string,mixed> */
function publicVersionFixture60(): array
{
    return json_decode((string) file_get_contents(storage_path('app/phase4b-dere9121-59-version-preparation-20261013/version-native-v2-result.json')), true, 512, JSON_THROW_ON_ERROR);
}

it('selects one active public event and continues sequence after exclusion', function (int $length, ?string $amount): void {
    $input = publicVersionFixture60();
    $result = (new DerePublicOfferEventVersionReconciler)->select(array_slice($input['events'], 0, $length),
        '11111111000191', '2026-09', new DateTimeImmutable('2026-10-13T11:00:34+00:00'), resource_path('fiscal/sources/official'),
        ['synthetic' => true, 'period_open' => true]);
    expect($result['status'])->toBe('LOCAL_PUBLIC_EVENT_VERSION_CHAIN_RECONCILED')
        ->and($result['active_event']['reconciliation']['total_vApur'] ?? null)->toBe($amount)
        ->and(count($result['trace']))->toBe($length)
        ->and($result['eligible_for_composition'])->toBeFalse()->and($result['production_ready'])->toBeFalse();
})->with([[1, '98.17'], [2, '99.17'], [3, null], [4, '100.17']]);

it('refuses inconsistent public lifecycle references and unqualified scope', function (string $case): void {
    $input = publicVersionFixture60();
    $events = $input['events'];
    $scope = ['synthetic' => true, 'period_open' => true];
    if ($case === 'closed-period') {
        $scope['period_open'] = false;
    } elseif ($case === 'real-mode') {
        $scope['synthetic'] = false;
    } elseif ($case === 'gap') {
        array_splice($events, 1, 1);
    } elseif ($case === 'sequence-reset') {
        $events[3]['return_xml'] = str_replace('<seqEvento>03</seqEvento>', '<seqEvento>00</seqEvento>', $events[3]['return_xml']);
    } elseif ($case === 'wrong-hash') {
        $events[0]['return_xml'] = str_replace(base64_encode(hash('sha256', $events[0]['origin_xml'], true)), str_repeat('A', 43).'=', $events[0]['return_xml']);
    } else {
        $index = $case === 'missing-exclusion-reason' ? 2 : 1;
        $dom = new DOMDocument;
        $dom->loadXML($events[$index]['origin_xml']);
        $path = new DOMXPath($dom);
        $path->registerNamespace('d', 'http://www.dere.gov.br/schemas/evtDebOpOfPublic/v0_0_3');
        if ($case === 'duplicate-inclusion') {
            publicVersionNode60($path, '//d:tpOper')->nodeValue = '1';
        } elseif ($case === 'wrong-active-receipt') {
            publicVersionNode60($path, '//d:ideEvento/d:nrRecibo')->nodeValue = '2101-202609-'.str_repeat('9', 19);
        } else {
            $node = publicVersionNode60($path, '//d:motExcl');
            ($node->parentNode ?? throw new RuntimeException('No fixture parent'))->removeChild($node);
        }
        $events[$index]['origin_xml'] = (string) $dom->saveXML();
        $dom = new DOMDocument;
        $dom->loadXML($events[$index]['return_xml']);
        $path = new DOMXPath($dom);
        $path->registerNamespace('d', 'http://www.dere.gov.br/schemas/evtRetornoTitPub/v0_0_2');
        publicVersionNode60($path, '//d:infoRecEv/d:hash')->nodeValue = base64_encode(hash('sha256', $events[$index]['origin_xml'], true));
        $events[$index]['return_xml'] = (string) $dom->saveXML();
    }
    $result = (new DerePublicOfferEventVersionReconciler)->select($events, '11111111000191', '2026-09',
        new DateTimeImmutable('2026-10-13T11:00:34+00:00'), resource_path('fiscal/sources/official'), $scope);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['production_ready'])->toBeFalse()
        ->and($result['reason'])->toBe($input['results'][$case]['reason']);
})->with(['sequence-reset', 'duplicate-inclusion', 'gap', 'closed-period', 'real-mode', 'wrong-hash', 'wrong-active-receipt', 'missing-exclusion-reason']);
