<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\DerePublicOfferDebtReturnXmlReader;
use Tests\TestCase;

uses(TestCase::class);

function noMovementNode57(DOMXPath $path, string $query): DOMElement
{
    $nodes = $path->query($query);
    $node = $nodes === false ? null : $nodes->item(0);
    if (! $node instanceof DOMElement) {
        throw new RuntimeException('Fixture element missing: '.$query);
    }

    return $node;
}

it('requires explicit zero D9121 total for native empty public-title origins', function (string $profile, string $case): void {
    $capture = json_decode((string) file_get_contents(storage_path('app/phase4b-dere9121-57-no-movement-preparation-20261013/'.$profile.'-no-movement-settlement.json')), true, 512, JSON_THROW_ON_ERROR);
    $origin = (string) file_get_contents(storage_path('app/phase4b-d2101-no-movement-20261006-21/'.$profile.'-candidate.xml.D2101.xml'));
    expect(hash('sha256', $origin))->toBe($capture['result']['composition']['d9121_reconciliation']['origin_xml_sha256']);
    $dom = new DOMDocument;
    $dom->loadXML($capture['input']['request']['current_public_offer_return']['xml']);
    $path = new DOMXPath($dom);
    $path->registerNamespace('d', 'http://www.dere.gov.br/schemas/evtRetornoTitPub/v0_0_2');
    if ($case === 'nonzero') {
        noMovementNode57($path, '//d:vApurTot')->nodeValue = '1.00';
    } elseif ($case === 'missing-total') {
        $node = noMovementNode57($path, '//d:infoTotTitOfPub');
        ($node->parentNode ?? throw new RuntimeException('Missing fixture parent'))->removeChild($node);
    } elseif ($case === 'excluded') {
        $source = new DOMDocument;
        $source->loadXML($origin);
        $sourcePath = new DOMXPath($source);
        $sourcePath->registerNamespace('d', 'http://www.dere.gov.br/schemas/evtDebOpOfPublic/v0_0_3');
        noMovementNode57($sourcePath, '//d:tpOper')->nodeValue = '3';
        $origin = (string) $source->saveXML();
        noMovementNode57($path, '//d:infoRecEv/d:hash')->nodeValue = base64_encode(hash('sha256', $origin, true));
    }
    $binding = $capture['input']['request']['current_public_offer_return'];
    $scope = ['synthetic' => true, 'supplier_id' => $capture['input']['request']['supplier_id'], 'period' => '2026-09',
        'origin_xml_sha256' => hash('sha256', $origin), 'expected_sequence' => '00', 'pgcc_receipt' => $binding['pgcc_receipt']];
    $result = (new DerePublicOfferDebtReturnXmlReader)->read((string) $dom->saveXML(), $origin, $scope, resource_path('fiscal/sources/official'));
    if ($case === 'valid') {
        expect($result['status'])->toBe('LOCAL_D9121_AGGREGATE_RECONCILED')
            ->and($result['title_count'])->toBe(0)->and($result['total_vApur'])->toBe('0.00');
    } else {
        $reason = match ($case) {
            'nonzero' => 'ORIGIN_AGGREGATE_AMOUNT_CONFLICT',
            'missing-total' => 'TOTALIZATION_REQUIRED',
            'excluded' => 'SANDBOX_NON_EXCLUSION_ORIGIN_REQUIRED',
            default => throw new RuntimeException('Unknown no-movement test case'),
        };
        expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason'])->toBe('RTC_DERE_D9121_'.$reason);
    }
    expect($result['production_ready'])->toBeFalse()->and($result['received_return_verified'])->toBeFalse();
})->with(['qualified-direct-only', 'qualified-fund-only', 'qualified-both-codes'])->with(['valid', 'nonzero', 'missing-total', 'excluded']);
