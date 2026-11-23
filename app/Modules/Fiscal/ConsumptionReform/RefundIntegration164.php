<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class RefundIntegration164
{
    public const KEY = 'refund_deadlines_a164';

    public const FACT = 'rtc.refund_deadline_record';

    public const POLICY = ['version' => 'A164', 'contract' => RefundDeadline164::CONTRACT,
        'calendar_catalog' => SplitDeadlineIntegration163::CALENDARS, 'sources' => RefundDeadline164::SOURCES,
        'rules' => ['STANDARD_FALLBACK', 'ACQUIRER_EXCESS'], 'clock' => 'SPECIFIC_REGULATION_ART29_AND_ART36',
        'correct_link' => 'AUTHORITY_CONFIRMATION_DISTINCT_FROM_SUPPLIED_ARRANGEMENT_LINK',
        'coverage' => ['SELECTED_EXCESSES', 'FULL_EXCESSES'], 'posting' => false];

    /** @param array<string, mixed> $codes */
    public static function valid(array $codes): bool
    {
        return is_array($codes[self::KEY] ?? null)
            && CanonicalJson::hash($codes[self::KEY]) === CanonicalJson::hash(self::POLICY)
            && SplitDeadlineIntegration163::valid($codes);
    }

    /** @param array<string, mixed> $codes */
    public static function synthetic(array $codes, FactBag $facts): bool
    {
        $records = $facts->value(self::FACT);
        if (! self::valid($codes) || ! is_array($records)) {
            return false;
        }
        foreach ($records as $input) {
            if (is_array($input) && ($input['source_kind'] ?? null) === 'SYNTHETIC_TEST') {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $codes
     * @param  array<string, mixed>|null  $standard
     * @param  array<string, mixed>|null  $acquirer
     * @return array<string, mixed>
     */
    public function integrate(array $codes, FactBag $facts, DateTimeImmutable $known, ?array $standard, ?array $acquirer): array
    {
        try {
            $g = new SplitLegalEvidence162;
            $g->must(self::valid($codes), 'RTC_REFUND_POLICY_REQUIRED');
            $records = $facts->value(self::FACT);
            $g->must(is_array($records) && array_is_list($records) && count($records) === 1 && is_array($records[0]), 'RTC_REFUND_RECORD_REQUIRED');
            $input = $records[0];
            $method = $facts->value('rtc.collection_method');
            $context = ($input['procedure'] ?? null) === 'ACQUIRER_EXCESS' ? $acquirer : $standard;
            $g->must(is_array($context) && (($input['procedure'] ?? null) === 'ACQUIRER_EXCESS' ? $method === 'ACQUIRER' : $method === 'SPLIT_PAYMENT'), 'RTC_REFUND_COLLECTION_METHOD_CONFLICT');
            $g->must(is_array($input['context_report'] ?? null)
                && CanonicalJson::hash($input['context_report']) === CanonicalJson::hash($context), 'RTC_REFUND_CURRENT_COLLECTION_CONTEXT_CONFLICT');
            $g->must($g->instant($input['known_at'] ?? null) == $known, 'RTC_REFUND_KNOWLEDGE_CONFLICT');
            $report = (new RefundDeadline164($codes[SplitDeadlineIntegration163::CALENDARS]))->assess($input);
            $g->must($report['status'] === 'LOCAL_REFUND_DEADLINES_ASSESSED', (string) ($report['reason'] ?? 'RTC_REFUND_ASSESSMENT_REFUSED'));

            return ['status' => 'RESOLVED', 'report' => $report];
        } catch (InvalidArgumentException $exception) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => $exception->getMessage(), 'missing_facts' => [self::FACT]];
        }
    }
}
