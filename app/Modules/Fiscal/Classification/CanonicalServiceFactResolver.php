<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Classification;

use App\Modules\Fiscal\Core\Decision\FiscalDecisionContext;
use App\Modules\Fiscal\Core\Evidence\EvidenceReference;
use App\Modules\Fiscal\Core\Facts\FactResolutionResult;
use App\Modules\Fiscal\Core\Facts\FactResolver;
use App\Modules\Fiscal\Core\Facts\FactSourceType;
use App\Modules\Fiscal\Core\Facts\FactValue;

final readonly class CanonicalServiceFactResolver implements FactResolver
{
    private const SUPPORTED_FACTS = [
        'service.lc116_code',
        'service.ctribnac_code',
        'resolved.iss.location_strategy',
    ];

    public function __construct(private CanonicalServiceCatalog $catalog) {}

    public function supports(string $factKey): bool
    {
        return in_array($factKey, self::SUPPORTED_FACTS, true);
    }

    public function dependencies(string $factKey): array
    {
        return $this->supports($factKey) ? ['service.canonical_id'] : [];
    }

    public function resolve(string $factKey, FiscalDecisionContext $context): FactResolutionResult
    {
        if (! $this->supports($factKey)) {
            return new FactResolutionResult;
        }
        $canonicalId = $context->facts->value('service.canonical_id');
        if (! is_string($canonicalId) || $canonicalId === '') {
            return new FactResolutionResult;
        }
        $service = $this->catalog->find($canonicalId);
        if ($service === null) {
            return new FactResolutionResult;
        }

        $value = match ($factKey) {
            'service.lc116_code' => $service->lc116Code,
            'service.ctribnac_code' => $service->ctribnacCode,
            'resolved.iss.location_strategy' => $service->locationStrategy->value,
            default => throw new \LogicException("Unsupported canonical service fact: {$factKey}"),
        };

        return new FactResolutionResult([
            new FactValue(
                key: $factKey,
                value: $value,
                sourceType: FactSourceType::DERIVED,
                effectiveFrom: $context->competenceDate,
                effectiveTo: $context->competenceDate,
                recordedAt: $context->knowledgeAt,
                evidence: new EvidenceReference($service->sourceId, $service->id),
            ),
        ]);
    }
}
