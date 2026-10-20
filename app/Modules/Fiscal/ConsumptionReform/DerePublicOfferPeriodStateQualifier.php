<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Schema\DereXmlSchemaValidator;
use DateTimeImmutable;
use DOMDocument;
use DOMXPath;

/** Contextual synthetic period timeline; never authenticates the government's period state. */
final readonly class DerePublicOfferPeriodStateQualifier
{
    /** @param list<array<string,mixed>> $periodEvents
     * @param  array<string|int,mixed>  $publicEvents
     * @param  array<string,mixed>  $history
     * @return array<string,mixed>
     */
    public function qualify(array $periodEvents, array $publicEvents, string $supplier, string $period,
        DateTimeImmutable $knownAt, string $sources, array $history): array
    {
        if (($history['synthetic'] ?? null) !== true || ($history['source_type'] ?? null) !== 'SYNTHETIC_COMPLETE_PERIOD_HISTORY'
            || ($history['supplier_id'] ?? null) !== $supplier || ($history['period'] ?? null) !== $period
            || ($history['initial_state'] ?? null) !== 'OPEN' || ! array_is_list($periodEvents) || count($periodEvents) > 200
            || ! is_string($history['from'] ?? null) || ! is_string($history['through'] ?? null)) {
            return $this->stop('COMPLETE_SYNTHETIC_CONTEXT_REQUIRED');
        }
        $from = $this->clock($history['from']);
        $through = $this->clock($history['through']);
        $known = [(int) $knownAt->format('U'), (int) ($knownAt->format('u').'0')];
        if ($from === null || $through === null || $from > $through || $through !== $known
            || ($history['history_hash'] ?? null) !== CanonicalJson::hash(['supplier_id' => $supplier, 'period' => $period,
                'initial_state' => 'OPEN', 'from' => $history['from'], 'through' => $history['through'], 'events' => $periodEvents])) {
            return $this->stop('HISTORY_HASH_OR_KNOWLEDGE_CONFLICT');
        }
        $public = (new DerePublicOfferEventVersionReconciler)->select($publicEvents, $supplier, $period, $knownAt, $sources,
            ['synthetic' => true, 'period_open' => true]);
        if (($public['status'] ?? null) !== 'LOCAL_PUBLIC_EVENT_VERSION_CHAIN_RECONCILED') {
            return $public;
        }
        $state = 'OPEN';
        $activeReceipt = null;
        $closeSequence = $reopenSequence = 0;
        $last = $from;
        $receipts = $ids = $timeline = [];
        foreach ($periodEvents as $event) {
            $close = ($event['kind'] ?? null) === 'CLOSE';
            if (! $close && ($event['kind'] ?? null) !== 'REOPEN') {
                return $this->stop('PERIOD_EVENT_KIND_INVALID');
            }
            if (! is_string($event['origin_xml'] ?? null) || ! is_string($event['return_xml'] ?? null)) {
                return $this->stop('ORIGIN_RETURN_BYTES_REQUIRED');
            }
            $originName = $close ? 'evtFechMensal' : 'evtReabertMensal';
            $returnName = $close ? 'evtRetornoMensal' : 'evtRetornoReabert';
            $version = $close ? 'v0_0_2' : 'v0_0_1';
            foreach (['origin_xml' => $originName, 'return_xml' => $returnName] as $field => $name) {
                $validation = (new DereXmlSchemaValidator)->validate($event[$field],
                    $sources.'/dere-observed-20261006-cgibs-xsd-v1-3-0.zip', DereNegativeBaseReturnXmlReader::SCHEMA_HASH,
                    $name.'-'.$version.'.xsd', $sources.'/w3c-xmldsig-core-schema-observed-20261002.xsd',
                    'd102ad3df7664c307e0c2c776ba4a90513b1969974d8a940bae1a77f9f21e15d');
                if ($validation['reason'] !== 'RTC_DERE_UNSIGNED_LOCAL_CANDIDATE') {
                    return $this->stop('PERIOD_EVENT_SCHEMA_UNPROVEN');
                }
            }
            $origin = $this->xpath($event['origin_xml'], $originName.'/'.$version);
            $response = $this->xpath($event['return_xml'], $returnName.'/'.$version);
            $o = '/d:DeRE/d:'.$originName;
            $r = '/d:DeRE/d:'.$returnName;
            $id = $this->value($origin, $o.'/@id');
            $receipt = $this->value($response, $r.'/d:infoRecEv/d:nrRecibo');
            $eventCode = $close ? '1199' : '1198';
            $expectedSequence = $close ? $closeSequence : $reopenSequence;
            if (! str_starts_with($id, 'DeRE'.$eventCode.'1'.$supplier) || isset($ids[$id])
                || $this->value($response, $r.'/@id') !== $id
                || $this->value($origin, $o.'/d:ideEvento/d:tpOper') !== '1'
                || $this->value($origin, $o.'/d:ideEvento/d:tpAmb') !== '2'
                || $this->value($origin, $o.'/d:ideContrib/d:nrInsc') !== substr($supplier, 0, 8)
                || $this->value($response, $r.'/d:ideContrib/d:nrInsc') !== substr($supplier, 0, 8)
                || $this->value($origin, $o.'/d:idePeriodo/d:perApur') !== $period
                || $this->value($response, $r.'/d:infoEvento/d:idePeriodo/d:perApur') !== $period
                || $this->value($response, $r.'/d:ideStatus/d:cdRetorno') !== '1'
                || $this->value($response, $r.'/d:ideStatus/d:descRetorno') !== 'Sucesso'
                || $this->value($response, $r.'/d:infoRecEv/d:tpEv') !== 'D-'.$eventCode
                || $expectedSequence > 99 || $this->value($response, $r.'/d:infoRecEv/d:seqEvento') !== sprintf('%02d', $expectedSequence)
                || $this->value($response, $r.'/d:infoRecEv/d:hash') !== base64_encode(hash('sha256', $event['origin_xml'], true))
                || ! preg_match('/^'.$eventCode.'-'.str_replace('-', '', $period).'-[0-9]{19}$/', $receipt) || isset($receipts[$receipt])) {
                return $this->stop('PERIOD_EVENT_ID_SCOPE_SEQUENCE_OR_HASH_CONFLICT');
            }
            if ((int) $response->evaluate('count('.$r.'/d:ideStatus/d:ocorrencias[d:tipo="1"])') !== 0) {
                return $this->stop('PERIOD_RETURN_ERROR_OCCURRENCE');
            }
            $received = $this->clock($this->value($response, $r.'/d:infoRecEv/d:dhRecepcao'));
            $processed = $this->clock($this->value($response, $r.'/d:infoRecEv/d:dhProcess'));
            if ($received === null || $processed === null || $received < $last || $processed < $received || $processed > $through) {
                return $this->stop('PERIOD_EVENT_CHRONOLOGY_OR_COVERAGE_CONFLICT');
            }
            if ($close) {
                if ($state !== 'OPEN') {
                    return $this->stop('CLOSE_WITHOUT_OPEN_INTERVAL');
                }
                $reconciled = (new DereNegativeBaseReturnXmlReader)->read($event['return_xml'],
                    ['supplier_id' => $supplier, 'period' => $period, 'closing_event_hash' => base64_encode(hash('sha256', $event['origin_xml'], true))], $sources, true);
                if (($reconciled['status'] ?? null) !== 'LOCAL_RETURN_RECONCILED') {
                    return $reconciled;
                }
                $state = 'CLOSED';
                $activeReceipt = $receipt;
                $closeSequence++;
            } else {
                if ($state !== 'CLOSED' || $this->value($origin, $o.'/d:infoReabertura/d:nrReciboReab') !== $activeReceipt) {
                    return $this->stop('REOPEN_WITHOUT_LAST_ACTIVE_CLOSING');
                }
                $state = 'OPEN';
                $reopenSequence++;
            }
            $ids[$id] = $receipts[$receipt] = true;
            $last = $processed;
            $timeline[] = ['kind' => $event['kind'], 'received' => $received, 'processed' => $processed, 'state' => $state,
                'receipt' => $receipt, 'origin_xml_sha256' => hash('sha256', $event['origin_xml']),
                'return_xml_sha256' => hash('sha256', $event['return_xml'])];
        }
        foreach ($publicEvents as $event) {
            $response = $this->xpath($event['return_xml'], 'evtRetornoTitPub/v0_0_2');
            $r = '/d:DeRE/d:evtRetornoTitPub/d:infoRecEv';
            $received = $this->clock($this->value($response, $r.'/d:dhRecepcao'));
            $processed = $this->clock($this->value($response, $r.'/d:dhProcess'));
            if ($received === null || $processed === null || $received < $from || $processed > $through) {
                return $this->stop('PUBLIC_EVENT_OUTSIDE_COMPLETE_HISTORY');
            }
            $at = 'OPEN';
            foreach ($timeline as $transition) {
                if ($transition['processed'] <= $received) {
                    $at = $transition['state'];
                } elseif ($transition['received'] <= $processed) {
                    return $this->stop('PUBLIC_EVENT_OVERLAPS_PERIOD_TRANSITION');
                }
            }
            if ($at !== 'OPEN') {
                return $this->stop('PUBLIC_EVENT_RECEIVED_WHILE_PERIOD_CLOSED');
            }
        }
        if ($state !== 'OPEN') {
            return $this->stop('PERIOD_CURRENTLY_CLOSED');
        }

        return ['status' => 'LOCAL_PUBLIC_PERIOD_HISTORY_QUALIFIED', 'supplier_id' => $supplier, 'period' => $period,
            'history_hash' => $history['history_hash'], 'current_state' => $state, 'last_closing_receipt' => $activeReceipt,
            'closing_count' => $closeSequence, 'reopening_count' => $reopenSequence, 'timeline' => $timeline,
            'public_chain_hash' => $public['chain_hash'], 'period_state_locally_reconciled' => true,
            'period_state_externally_verified' => false, 'closing_origin_economics_reconciled' => false,
            'government_return_authenticated' => false, 'eligible_for_posting' => false, 'production_ready' => false];
    }

    /** @return array{int,int}|null */
    private function clock(string $value): ?array
    {
        if (! preg_match('/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})\.(\d{7})([+-]\d{2}:\d{2})$/', $value, $parts)) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $parts[1].$parts[3]);
        if ($date === false || $date->format('Y-m-d\TH:i:sP') !== $parts[1].$parts[3]) {
            return null;
        }

        return [(int) $date->format('U'), (int) $parts[2]];
    }

    private function xpath(string $xml, string $namespace): DOMXPath
    {
        $document = new DOMDocument;
        $document->resolveExternals = false;
        $document->substituteEntities = false;
        $document->loadXML($xml, LIBXML_NONET);
        $path = new DOMXPath($document);
        $path->registerNamespace('d', 'http://www.dere.gov.br/schemas/'.$namespace);

        return $path;
    }

    private function value(DOMXPath $path, string $query): string
    {
        $value = $path->evaluate('string('.$query.')');

        return is_string($value) ? $value : '';
    }

    /** @return array<string,mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DERE_PUBLIC_PERIOD_'.$reason, 'production_ready' => false];
    }
}
