<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Classification;

final readonly class CanonicalServiceClassifier
{
    public function __construct(private CanonicalServiceCatalog $catalog) {}

    public function classify(?string $canonicalId, ?string $ctribnacCode = null): ServiceClassificationDecision
    {
        if ($canonicalId !== null && $canonicalId !== '') {
            $service = $this->catalog->find($canonicalId);

            return new ServiceClassificationDecision(
                $service,
                $service === null ? 'SERVICE_UNSUPPORTED' : 'SERVICE_CLASSIFIED_EXPLICITLY',
            );
        }

        if ($ctribnacCode !== null && $ctribnacCode !== '') {
            $service = $this->catalog->findByCtribnac($ctribnacCode);

            return new ServiceClassificationDecision(
                $service,
                $service === null ? 'SERVICE_CTRIBNAC_UNSUPPORTED' : 'SERVICE_CLASSIFIED_BY_CTRIBNAC',
            );
        }

        return new ServiceClassificationDecision(null, 'SERVICE_IDENTIFIER_MISSING');
    }
}
