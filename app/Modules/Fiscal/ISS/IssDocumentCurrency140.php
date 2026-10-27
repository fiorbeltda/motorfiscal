<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ISS;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeImmutable;
use Throwable;

/** Qualified local import-consultancy currency context; never rewrites RTC or payment facts. */
final readonly class IssDocumentCurrency140
{
    public const CONTRACT = 'ISS-DOCUMENT-CURRENCY-140-V1';

    public const AVAILABLE_AT = '2026-10-27T12:05:00+00:00';

    public const PROOFS = 'iss.document_currency_proof_records';

    public const FOREIGN_REGIME = 'NOT_APPLICABLE_FOREIGN';

    /** @param array<string, mixed> $catalog */
    public function __construct(private array $catalog) {}

    /** @return array<string, mixed> */
    public static function policy(): array
    {
        return ['contract' => self::CONTRACT, 'available_at' => self::AVAILABLE_AT,
            'municipality' => '3550308', 'lc116_code' => '17.01',
            'operation_scope' => 'IMPORTED_CONSULTANCY_COMPLETED_FINAL_REPORT_PREQUALIFIED_LOCAL_CASE',
            'reference_day' => 'EXPLICIT_ISS_SUPPLY_DAY', 'side' => 'SALE', 'bulletin' => 'Fechamento',
            'supported_currency' => ['BRL', 'USD'], 'previous_day_fallback' => false,
            'sources' => ['iss140-sp-sc008-2015.pdf' => '05f655b02feaa3a2647e00a164feb3e9d44d65712ec1cf1700381cc35bd9edbe',
                'iss140-ctn-consolidated.html' => '532d816da9dce29fdc7854933c7736681cee647a6e950a499fd8f205d02bff2d',
                'iss140-sp-law13701-consolidated.html' => '9966f679d5859fb868d5639bb17e85188198444063b3fb613495e6e5111d4c89'],
            'technical_rounding' => 'HALF_UP_2_FROM_EXACT_PRODUCT_NOT_MUNICIPAL_ROUNDING_APPROVAL',
            'legal_applicability_approved' => false, 'complete_document_obligation' => false];
    }

    /** @return array<string, mixed> */
    public function qualify(FactBag $facts, string $day, DateTimeImmutable $known, string $municipality, string $lc116): array
    {
        if ($facts->value('operation.service_origin') !== 'FOREIGN') {
            return $facts->has('iss.original_price_currency')
                ? $this->stop('OPERATION_SCOPE_NOT_MAPPED') : ['status' => 'NOT_APPLICABLE'];
        }
        $policy = $this->catalog['policy'] ?? null;
        if (! is_array($policy) || CanonicalJson::hash($policy) !== CanonicalJson::hash(self::policy())
            || $known < new DateTimeImmutable(self::AVAILABLE_AT)) {
            return $this->stop('EXACT_CURRENT_POLICY_REQUIRED');
        }
        $required = ['operation.amount', 'provider.tax_regime', 'iss.original_price_amount', 'iss.original_price_currency', 'iss.document_supply_on',
            'iss.document_reference', 'iss.document_supplier_id', 'iss.document_customer_id',
            'iss.supply_event_kind', 'iss.supply_event_reference', self::PROOFS];
        $missing = $facts->missing($required);
        if ($missing !== []) {
            return $this->stop('DOCUMENT_CURRENCY_FACTS_MISSING', $missing);
        }
        if ($municipality !== '3550308' || $lc116 !== '17.01' || $facts->value('provider.person_type') !== 'FOREIGN_LEGAL_ENTITY'
            || $facts->value('provider.tax_regime') !== self::FOREIGN_REGIME
            || $facts->value('iss.document_supply_on') !== $day || $facts->value('iss.supply_event_kind') !== 'FINAL_REPORT_DELIVERY') {
            return $this->stop('MATERIAL_SCOPE_OR_ISS_DATE_CONFLICT');
        }
        foreach (['iss.document_reference', 'iss.document_supplier_id', 'iss.document_customer_id', 'iss.supply_event_reference'] as $key) {
            if (! $this->reference($facts->value($key))) {
                return $this->stop('EXPLICIT_DOCUMENT_AND_PARTY_IDENTITIES_REQUIRED', [$key]);
            }
        }
        foreach (['rtc.document_id' => 'iss.document_reference', 'rtc.supplier_id' => 'iss.document_supplier_id', 'rtc.acquirer_id' => 'iss.document_customer_id'] as $other => $key) {
            if ($facts->has($other) && $facts->value($other) !== $facts->value($key)) {
                return $this->stop('CROSS_TAX_DOCUMENT_OR_PARTY_CONFLICT');
            }
        }
        $amount = $facts->value('iss.original_price_amount');
        $currency = $facts->value('iss.original_price_currency');
        if (! is_string($amount) || preg_match('/\A(?:0|[1-9][0-9]{0,14})(?:\.[0-9]{1,8})?\z/', $amount) !== 1
            || ! in_array($currency, self::policy()['supported_currency'], true)) {
            return $this->stop('EXACT_AMOUNT_AND_SUPPORTED_CURRENCY_REQUIRED');
        }
        $proofs = $facts->value(self::PROOFS);
        if (! is_array($proofs) || ! array_is_list($proofs) || count($proofs) !== 1 || ! is_array($proofs[0])) {
            return $this->stop('ONE_CAPTURED_DOCUMENT_PROOF_REQUIRED');
        }
        $proof = $proofs[0];
        $keys = ['contract', 'evidence_id', 'observed_at', 'valid_from', 'valid_to', 'revoked_at', 'superseded_at', 'value', 'origin', 'source_hash'];
        if (! $this->keys($proof, $keys) || $proof['contract'] !== self::CONTRACT || ! $this->reference($proof['evidence_id'])
            || ! is_array($proof['value'])) {
            return $this->stop('EXACT_PROOF_CONTRACT_REQUIRED');
        }
        try {
            $observed = $this->instant($proof['observed_at']);
            if ($observed === null || $observed > $known || $observed < new DateTimeImmutable(self::AVAILABLE_AT)
                || ! $this->day($proof['valid_from']) || ! $this->day($proof['valid_to'])
                || $proof['valid_from'] > $day || $proof['valid_to'] < $day) {
                return $this->stop('PROOF_KNOWLEDGE_OR_COVERAGE_CONFLICT');
            }
            foreach (['revoked_at', 'superseded_at'] as $key) {
                if ($proof[$key] !== null && ($this->instant($proof[$key]) === null || $this->instant($proof[$key]) <= $known)) {
                    return $this->stop('PROOF_REVOKED_OR_SUPERSEDED');
                }
            }
        } catch (Throwable) {
            return $this->stop('EXACT_PROOF_DATES_REQUIRED');
        }
        $quote = null;
        $sourceId = null;
        $product = BigDecimal::of($amount);
        if ($currency !== 'BRL') {
            $source = $this->catalog['quote_source'] ?? null;
            if (! is_array($source) || ! $this->reference($source['source_id'] ?? null)) {
                return $this->stop('QUOTE_SOURCE_REQUIRED');
            }
            $quote = (new PtaxClosingQuote140)->select($source, $currency, $day, $known);
            if ($quote['status'] !== 'EXACT_DAY_BCB_CLOSING_SALE_QUOTE_SELECTED') {
                return [...$this->stop('EXACT_ISS_DAY_QUOTE_REQUIRED'), 'upstream' => $quote];
            }
            if (new DateTimeImmutable($source['observed_at']) > $observed) {
                return $this->stop('PROOF_PREDATES_QUOTE_CAPTURE');
            }
            $sourceId = $source['source_id'] ?? null;
            $product = $product->multipliedBy($quote['rate']);
        }
        $converted = (string) $product->toScale(2, RoundingMode::HalfUp);
        $expected = ['policy_hash' => CanonicalJson::hash(self::policy()), 'document_reference' => $facts->value('iss.document_reference'),
            'supplier_id' => $facts->value('iss.document_supplier_id'), 'customer_id' => $facts->value('iss.document_customer_id'),
            'municipality' => $municipality, 'lc116_code' => $lc116, 'iss_supply_on' => $day,
            'supply_event_kind' => $facts->value('iss.supply_event_kind'), 'supply_event_reference' => $facts->value('iss.supply_event_reference'),
            'original_amount' => $amount, 'original_currency' => $currency, 'converted_price_brl' => $converted,
            'original_operation_amount_brl' => $facts->value('operation.amount'),
            'quote_source_id' => $sourceId, 'quote_source_sha256' => $quote['raw_source_sha256'] ?? null,
            'quote_date' => $quote['quote_date'] ?? null, 'rate' => $quote['rate'] ?? null,
            'qualified_scope' => self::policy()['operation_scope']];
        if (CanonicalJson::encode($proof['value']) !== CanonicalJson::encode($expected)) {
            return $this->stop('FULL_DOCUMENT_PRICE_PARTIES_OR_QUOTE_CONTEXT_CONFLICT');
        }
        $origin = $proof['origin'];
        $body = array_diff_key($proof, ['origin' => true, 'source_hash' => true]);
        if (! is_array($origin) || ! $this->keys($origin, ['reference', 'media_type', 'content', 'content_sha256'])
            || ! $this->reference($origin['reference']) || $origin['media_type'] !== 'application/json'
            || $origin['content'] !== CanonicalJson::encode($body) || hash('sha256', $origin['content']) !== $origin['content_sha256']
            || $proof['source_hash'] !== CanonicalJson::hash(array_diff_key($proof, ['source_hash' => true]))) {
            return $this->stop('CAPTURED_SOURCE_CONTENT_OR_HASH_CONFLICT');
        }

        return ['status' => 'LOCAL_ISS_DOCUMENT_PRICE_CONVERTED', 'original_amount' => $amount, 'original_currency' => $currency,
            'converted_price_brl' => $converted, 'product_exact' => (string) $product, 'iss_supply_on' => $day, 'quote' => $quote,
            'proof_hash' => $proof['source_hash'], 'policy_hash' => CanonicalJson::hash(self::policy()),
            'rtc_operation_amount_preserved' => $facts->value('operation.amount'), 'rtc_quote_reused' => false,
            'payment_date_used_as_tax_point' => false, 'legal_applicability_approved' => false, 'document_obligation_complete' => false,
            'production_ready' => false];
    }

    private function reference(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    private function day(mixed $value): bool
    {
        $date = is_string($value) ? DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function instant(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value)) {
            return null;
        }
        $format = str_contains($value, '.') ? 'Y-m-d\TH:i:s.uP' : 'Y-m-d\TH:i:sP';
        $date = DateTimeImmutable::createFromFormat('!'.$format, $value);

        return $date !== false && $date->format($format) === $value ? $date : null;
    }

    /** @param array<array-key, mixed> $value
     * @param  list<string>  $keys
     */
    private function keys(array $value, array $keys): bool
    {
        return count($value) === count($keys) && array_diff(array_keys($value), $keys) === [];
    }

    /** @param list<string> $missing
     * @return array<string, mixed>
     */
    private function stop(string $reason, array $missing = []): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'ISS_CURRENCY140_'.$reason, 'missing_facts' => $missing, 'production_ready' => false];
    }
}
