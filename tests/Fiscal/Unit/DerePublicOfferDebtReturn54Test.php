<?php

declare(strict_types=1);
use App\Modules\Fiscal\ConsumptionReform\DerePublicOfferDebtReturnXmlReader;
use Tests\TestCase;

uses(TestCase::class);

function dere9121Element54(DOMXPath $xpath, string $path): DOMElement
{
    $nodes = $xpath->query($path);
    $element = $nodes === false ? null : $nodes->item(0);
    if (! $element instanceof DOMElement) {
        throw new RuntimeException('Fixture element missing: '.$path);
    }

    return $element;
}

function dere9121DocumentEvent54(DOMDocument $document): DOMElement
{
    $event = $document->documentElement?->firstElementChild;
    if (! $event instanceof DOMElement) {
        throw new RuntimeException('Fixture event missing.');
    }

    return $event;
}

/** @return array<string, mixed> */
function dere9121Fixture54(): array
{
    $context = json_decode((string) file_get_contents(storage_path('app/phase4b-dere9121-54-preparation-20261013').'/synthetic-d9121-context.json'), true, 512, JSON_THROW_ON_ERROR);
    $origin = (string) file_get_contents(base_path($context['source_d2101_path']));
    $return = (string) file_get_contents(storage_path('app/phase4b-dere9121-54-preparation-20261013').'/synthetic-d9121-v2.xml');
    $scope = ['synthetic' => true, 'supplier_id' => '11111111000191', 'period' => $context['period'],
        'origin_xml_sha256' => hash('sha256', $origin), 'expected_sequence' => '00', 'pgcc_receipt' => $context['synthetic_pgcc_receipt']];
    $reader = new DerePublicOfferDebtReturnXmlReader;
    $sources = resource_path('fiscal/sources/official');
    $positiveReport = null;
    foreach (glob(storage_path('app/phase4b-variable-yield-20261006-27/*-monthly-report-final.json')) ?: [] as $path) {
        $report = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (isset($report['projection']['debt_yield_projection']['total_vApur'])
            && (float) $report['projection']['debt_yield_projection']['total_vApur'] > 0) {
            $positiveReport = $report;
            break;
        }
    }
    if ($positiveReport === null) {
        throw new RuntimeException('Positive preserved D2101 fixture required.');
    }
    $positiveOrigin = (string) file_get_contents(base_path($positiveReport['event_outputs']['D2101']));
    if (hash('sha256', $positiveOrigin) !== $positiveReport['event_validations']['D2101']['xml_sha256']) {
        throw new RuntimeException('Positive source hash differs.');
    }
    $sourceDom = new DOMDocument;
    $sourceDom->loadXML($positiveOrigin);
    $positiveDom = new DOMDocument;
    $positiveDom->loadXML($return);
    $positiveXPath = new DOMXPath($positiveDom);
    $positiveXPath->registerNamespace('d', 'http://www.dere.gov.br/schemas/evtRetornoTitPub/v0_0_2');
    dere9121Element54($positiveXPath, '/d:DeRE/d:evtRetornoTitPub')->setAttribute('id', dere9121DocumentEvent54($sourceDom)->getAttribute('id'));
    dere9121Element54($positiveXPath, '//d:infoRecEv/d:hash')->nodeValue = base64_encode(hash('sha256', $positiveOrigin, true));
    dere9121Element54($positiveXPath, '//d:vApurTot')->nodeValue = $positiveReport['projection']['debt_yield_projection']['total_vApur'];
    $positiveReturn = (string) $positiveDom->saveXML();
    $positiveScope = $scope;
    $positiveScope['origin_xml_sha256'] = hash('sha256', $positiveOrigin);

    return compact('scope', 'return', 'origin', 'positiveReturn', 'positiveOrigin', 'positiveScope', 'reader', 'sources');
}
it('reconciles only explicit synthetic D9121 against exact native D2101 bytes and context', function (string $case): void {
    $fixture = dere9121Fixture54();
    $scope = $fixture['scope'];
    $return = $fixture['return'];
    $origin = $fixture['origin'];
    $positiveReturn = $fixture['positiveReturn'];
    $positiveOrigin = $fixture['positiveOrigin'];
    $positiveScope = $fixture['positiveScope'];
    $reader = $fixture['reader'];
    $sources = $fixture['sources'];
    $inputScope = $scope;
    $inputReturn = $return;
    $inputOrigin = $origin;
    if (in_array($case, ['production-origin', 'excluded-origin'], true)) {
        $originalDom = new DOMDocument;
        $originalDom->loadXML($inputOrigin);
        $originalXPath = new DOMXPath($originalDom);
        $originalXPath->registerNamespace('d', 'http://www.dere.gov.br/schemas/evtDebOpOfPublic/v0_0_3');
        dere9121Element54($originalXPath, $case === 'production-origin' ? '//d:tpAmb' : '//d:tpOper')->nodeValue = $case === 'production-origin' ? '1' : '3';
        $inputOrigin = (string) $originalDom->saveXML();
        $inputScope['origin_xml_sha256'] = hash('sha256', $inputOrigin);
    } elseif ($case === 'positive') {
        [$inputReturn, $inputOrigin, $inputScope] = [$positiveReturn, $positiveOrigin, $positiveScope];
    } elseif ($case === 'real') {
        $inputScope['synthetic'] = false;
    } elseif ($case === 'origin-hash') {
        $inputScope['origin_xml_sha256'] = str_repeat('0', 64);
    } elseif ($case === 'supplier') {
        $inputScope['supplier_id'] = '22222222000191';
    } elseif ($case === 'period') {
        $inputScope['period'] = '2026-08';
    } elseif ($case === 'sequence') {
        $inputScope['expected_sequence'] = '01';
    } elseif ($case === 'pgcc') {
        $inputScope['pgcc_receipt'] = '1011-202609-'.str_repeat('Q', 19);
    } else {
        $dom = new DOMDocument;
        $dom->loadXML($inputReturn);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('d', 'http://www.dere.gov.br/schemas/evtRetornoTitPub/v0_0_2');
        if ($case === 'amount') {
            dere9121Element54($xpath, '//d:vApurTot')->nodeValue = '1.00';
        } elseif ($case === 'return-hash') {
            dere9121Element54($xpath, '//d:infoRecEv/d:hash')->nodeValue = str_repeat('A', 43).'=';
        } elseif ($case === 'event-id') {
            dere9121Element54($xpath, '/d:DeRE/d:evtRetornoTitPub')->setAttribute('id', 'DeRE21011'.str_repeat('1', 14).str_repeat('0', 19));
        } elseif ($case === 'error') {
            dere9121Element54($xpath, '//d:cdRetorno')->nodeValue = '0';
            dere9121Element54($xpath, '//d:descRetorno')->nodeValue = 'Erro';
        } elseif ($case === 'wrong-type') {
            dere9121Element54($xpath, '//d:tpEv')->nodeValue = 'D-1199';
        } elseif ($case === 'missing-total') {
            $node = dere9121Element54($xpath, '//d:infoTotTitOfPub');
            ($node->parentNode ?? throw new RuntimeException('Fixture node has no parent.'))->removeChild($node);
        }
        $inputReturn = (string) $dom->saveXML();
    }
    $result = $reader->read($inputReturn, $inputOrigin, $inputScope, $sources);

    $expected = in_array($case, ['zero', 'positive'], true) ? 'LOCAL_D9121_AGGREGATE_RECONCILED' : 'ACTION_REQUIRED';
    expect($result['status'])->toBe($expected)->and($result['production_ready'])->toBeFalse()
        ->and($result['received_return_verified'])->toBeFalse()->and($result['eligible_for_posting'])->toBeFalse();
    if (in_array($case, ['zero', 'positive'], true)) {
        expect($result['total_vApur'])->toBe($case === 'zero' ? '0.00' : '98.17')
            ->and($result['composition_integrated'])->toBeFalse();
    } else {
        $reason = match ($case) {
            'real', 'origin-hash' => 'EXPLICIT_SYNTHETIC_BOUND_SCOPE_REQUIRED',
            'amount' => 'ORIGIN_AGGREGATE_AMOUNT_CONFLICT',
            'missing-total' => 'TOTALIZATION_REQUIRED',
            'production-origin', 'excluded-origin' => 'SANDBOX_NON_EXCLUSION_ORIGIN_REQUIRED',
            default => 'RETURN_OR_ORIGIN_CONTEXT_CONFLICT',
        };
        expect($result['reason'])->toBe('RTC_DERE_D9121_'.$reason);
    }
})->with(['zero', 'positive', 'real', 'origin-hash', 'supplier', 'period', 'sequence', 'pgcc', 'amount', 'return-hash',
    'event-id', 'error', 'wrong-type', 'missing-total', 'production-origin', 'excluded-origin']);
