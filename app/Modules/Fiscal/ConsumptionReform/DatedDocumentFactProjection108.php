<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Facts\FactSourceType;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use InvalidArgumentException;

/** Stable local evidence projection; upstream-derived ISS/federal presentation facts are not operation inputs. */
final readonly class DatedDocumentFactProjection108
{
    /** @return array<string, mixed> */
    public static function from(FactBag $source): array
    {
        $full = $source->canonicalValues();
        $provided = $full;
        $excluded = [];
        foreach (['service.lc116_code', 'service.ctribnac_code', 'resolved.iss.location_strategy',
            'payment.gross_amount', 'invoice.gross_amount'] as $key) {
            if ($source->get($key)?->sourceType === FactSourceType::DERIVED) {
                unset($provided[$key]);
                $excluded[] = $key;
            }
        }
        $proofs = $provided['provider.iss_regime_proof_records'] ?? null;
        if (! is_array($proofs) || ! array_is_list($proofs) || count($proofs) !== 1 || ! is_array($proofs[0])) {
            throw new InvalidArgumentException('Exactly one regime proof transport record is required.');
        }
        $qualification = $provided;
        // A carrier cannot participate in the very hash carried by its proof. All other supplied facts remain bound.
        unset($qualification['rtc.export_dated_dps_identity_records']);
        $qualification['provider.iss_regime_proof'] = $proofs[0];

        return ['facts' => $qualification, 'source_current_facts_hash' => CanonicalJson::hash($full),
            'provided_context_facts_hash' => CanonicalJson::hash($provided), 'excluded_derived_fact_keys' => $excluded,
            'identity_carrier_excluded_from_qualification_facts' => true,
            'regime_proof_normalized_from_singleton_transport' => true];
    }
}
