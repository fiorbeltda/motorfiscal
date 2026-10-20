<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use InvalidArgumentException;

/** Explicit proof carriers only; financial records and unknown supplied facts stay bound. */
final readonly class DocumentPriceFactProjection112
{
    public const PRICE_RECORDS = 'rtc.export_contract_price_records';

    public const COMPOSITION_RECORDS = 'rtc.export_price_composition_records';

    public const PRICE_PROOFS = 'rtc.export_contract_price_proof_records';

    public const COMPOSITION_PROOFS = 'rtc.export_price_composition_proof_records';

    /** @return array<string, mixed> */
    public static function from(FactBag $source): array
    {
        if ($source->has('provider.iss_regime_proof')) {
            throw new InvalidArgumentException('Use only the typed singleton regime proof transport.');
        }
        $base = DatedDocumentFactProjection108::from($source);
        $facts = $base['facts'];
        unset($facts[self::PRICE_PROOFS], $facts[self::COMPOSITION_PROOFS]);

        return [...$base, 'facts' => $facts,
            'qualification_context_facts_hash' => CanonicalJson::hash($facts),
            'excluded_price_proof_carrier_keys' => [self::PRICE_PROOFS, self::COMPOSITION_PROOFS],
            'monetary_record_fact_keys_bound' => [self::PRICE_RECORDS, self::COMPOSITION_RECORDS],
            'monetary_records_hash' => CanonicalJson::hash([
                self::PRICE_RECORDS => $facts[self::PRICE_RECORDS] ?? null,
                self::COMPOSITION_RECORDS => $facts[self::COMPOSITION_RECORDS] ?? null])];
    }
}
