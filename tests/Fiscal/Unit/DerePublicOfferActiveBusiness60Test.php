<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\DerePublicOfferActiveEventBusinessBinder;
use Tests\TestCase;

uses(TestCase::class);

function businessNode60(DOMXPath $xpath, string $query): DOMElement
{
    $list = $xpath->query($query);
    $node = $list === false ? null : $list->item(0);
    if (! $node instanceof DOMElement) {
        throw new RuntimeException('Missing business fixture element.');
    }

    return $node;
}

/** @return array<string,mixed> */
function activeBusinessFixture60(bool $matching): array
{
    $input = json_decode((string) file_get_contents(storage_path('app/phase4b-dere9121-54-preparation-20261013/public-settlement-native-result.json')), true, 512, JSON_THROW_ON_ERROR)['input'];
    $events = json_decode((string) file_get_contents(storage_path('app/phase4b-dere9121-59-version-preparation-20261013/version-native-v2-result.json')), true, 512, JSON_THROW_ON_ERROR)['events'];
    if ($matching) {
        // Retain lifecycle identifiers/receipt references while restoring the independently captured native economics.
        $original = new DOMDocument;
        $original->loadXML($events[0]['origin_xml']);
        $originalPath = new DOMXPath($original);
        $originalPath->registerNamespace('d', 'http://www.dere.gov.br/schemas/evtDebOpOfPublic/v0_0_3');
        $business = businessNode60($originalPath, '//d:infoDebOpOfPublic');
        foreach ($events as &$event) {
            $dom = new DOMDocument;
            $dom->loadXML($event['origin_xml']);
            $path = new DOMXPath($dom);
            $path->registerNamespace('d', 'http://www.dere.gov.br/schemas/evtDebOpOfPublic/v0_0_3');
            if ($path->evaluate('string(//d:tpOper)') === '3') {
                continue;
            }
            $node = businessNode60($path, '//d:infoDebOpOfPublic');
            ($node->parentNode ?? throw new RuntimeException('No fixture parent'))->replaceChild($dom->importNode($business, true), $node);
            $event['origin_xml'] = (string) $dom->saveXML();
            $returned = new DOMDocument;
            $returned->loadXML($event['return_xml']);
            $returnPath = new DOMXPath($returned);
            $returnPath->registerNamespace('d', 'http://www.dere.gov.br/schemas/evtRetornoTitPub/v0_0_2');
            businessNode60($returnPath, '//d:infoRecEv/d:hash')->nodeValue = base64_encode(hash('sha256', $event['origin_xml'], true));
            businessNode60($returnPath, '//d:vApurTot')->nodeValue = '98.17';
            $event['return_xml'] = (string) $returned->saveXML();
        }
        unset($event);
    }
    $pgcc = $input['request']['current_public_offer_return'];

    return ['input' => $input, 'events' => $events, 'pgcc' => $pgcc];
}

it('binds only active public business that matches reconstructed monthly book and PGCC', function (string $case): void {
    $fixture = activeBusinessFixture60($case !== 'changed-economics');
    $input = $fixture['input'];
    if ($case === 'excluded') {
        $fixture['events'] = array_slice($fixture['events'], 0, 3);
    } elseif ($case === 'pgcc-receipt') {
        $fixture['pgcc']['pgcc_receipt'] = '1011-202609-'.str_repeat('Q', 19);
    } elseif ($case === 'pgcc-source') {
        $fixture['pgcc']['pgcc_source_hash'] = str_repeat('0', 64);
    } elseif ($case === 'closed') {
        $input['month']['prerequisites']['existing_closing'] = true;
    } elseif ($case === 'book') {
        $input['month']['book']['accounts'][0]['vApur'] = '9999.00';
    }
    $result = (new DerePublicOfferActiveEventBusinessBinder)->bind($input['operation'], $input['month'],
        new DateTimeImmutable($input['known_at']), $input['rules'], $fixture['events'], $fixture['pgcc'], resource_path('fiscal/sources/official'));
    if ($case === 'valid') {
        expect($result['status'])->toBe('LOCAL_ACTIVE_PUBLIC_BUSINESS_BOUND')->and($result['total_vApur'])->toBe('98.17')
            ->and($result['active_event']['sequence'])->toBe('03')
            ->and($result['active_event_business_locally_reconciled'])->toBeTrue()
            ->and($result['government_return_authenticated'])->toBeFalse()->and($result['production_ready'])->toBeFalse();
    } else {
        expect($result['status'])->not->toBe('LOCAL_ACTIVE_PUBLIC_BUSINESS_BOUND');
        if ($case === 'changed-economics') {
            expect($result['reason'])->toBe('RTC_DERE_PUBLIC_BINDING_ACTIVE_PUBLIC_BUSINESS_DIFFERS_FROM_REBUILT_MONTH');
        } elseif ($case === 'excluded') {
            expect($result['reason'])->toBe('RTC_DERE_PUBLIC_BINDING_ACTIVE_PUBLIC_EVENT_MISSING_AFTER_EXCLUSION');
        }
    }
})->with(['valid', 'changed-economics', 'excluded', 'pgcc-receipt', 'pgcc-source', 'closed', 'book']);
