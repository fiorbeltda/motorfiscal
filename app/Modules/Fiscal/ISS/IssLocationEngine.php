<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ISS;

use App\Modules\Fiscal\Core\Facts\FactBag;

final class IssLocationEngine
{
    public function resolve(
        IssLocationStrategy $strategy,
        FactBag $facts,
        ?string $importOverride = null,
        ?string $ctribnacCode = null,
    ): IssLocationDecision {
        if ($facts->value('operation.service_origin') === 'FOREIGN' && $importOverride === 'EDEmit') {
            return $this->resolveImportLocation($facts);
        }

        $factKey = match ($strategy) {
            IssLocationStrategy::PROVIDER_ESTABLISHMENT => 'provider.municipality_ibge',
            IssLocationStrategy::EVENT_LOCATION => 'operation.event_municipality_ibge',
            IssLocationStrategy::CONSTRUCTION_WORK_LOCATION => 'operation.construction_municipality_ibge',
            IssLocationStrategy::SERVICE_EXECUTION_LOCATION => 'operation.execution_municipality_ibge',
            IssLocationStrategy::CUSTOMER_ESTABLISHMENT, IssLocationStrategy::IMPORT_CUSTOMER_LOCATION => 'customer.municipality_ibge',
            IssLocationStrategy::INTERMEDIARY_ESTABLISHMENT => 'operation.intermediary_municipality_ibge',
        };

        $missing = [];
        if (! $facts->has($factKey)) {
            $missing[] = $factKey;
        }
        if ($strategy === IssLocationStrategy::CONSTRUCTION_WORK_LOCATION && ! $facts->has('operation.construction_work_id')) {
            $missing[] = 'operation.construction_work_id';
        }

        if ($missing !== []) {
            return new IssLocationDecision($strategy, null, 'ISS_LOCATION_FACTS_MISSING', $missing);
        }

        $locationCode = (string) $facts->value($factKey);
        if ($strategy === IssLocationStrategy::SERVICE_EXECUTION_LOCATION
            && $locationCode === '0000000'
            && is_string($ctribnacCode)
            && str_starts_with($ctribnacCode, '2001')) {
            return new IssLocationDecision($strategy, null, 'ISS_MARITIME_LOCATION_FORBIDDEN_FOR_LC116_20_01');
        }
        if ($strategy === IssLocationStrategy::SERVICE_EXECUTION_LOCATION && $locationCode === '0000000') {
            if (! $facts->has('provider.municipality_ibge')) {
                return new IssLocationDecision($strategy, null, 'ISS_MARITIME_PROVIDER_LOCATION_MISSING', ['provider.municipality_ibge']);
            }

            return new IssLocationDecision(
                IssLocationStrategy::PROVIDER_ESTABLISHMENT,
                new LocationIdentifier((string) $facts->value('provider.municipality_ibge')),
                'ISS_MARITIME_LOCATION_RESOLVED_TO_PROVIDER',
            );
        }

        return new IssLocationDecision(
            $strategy,
            new LocationIdentifier($locationCode),
            'ISS_LOCATION_RESOLVED',
        );
    }

    private function resolveImportLocation(FactBag $facts): IssLocationDecision
    {
        if (! $facts->has('operation.issuer_role')) {
            return new IssLocationDecision(IssLocationStrategy::IMPORT_CUSTOMER_LOCATION, null, 'ISS_IMPORT_ISSUER_ROLE_MISSING', ['operation.issuer_role']);
        }
        $role = $facts->value('operation.issuer_role');
        $factKey = match ($role) {
            'CUSTOMER' => 'customer.municipality_ibge',
            'INTERMEDIARY' => 'operation.intermediary_municipality_ibge',
            default => null,
        };
        if ($factKey === null) {
            return new IssLocationDecision(IssLocationStrategy::IMPORT_CUSTOMER_LOCATION, null, 'ISS_IMPORT_ISSUER_ROLE_INVALID');
        }
        if (! $facts->has($factKey)) {
            return new IssLocationDecision(IssLocationStrategy::IMPORT_CUSTOMER_LOCATION, null, 'ISS_IMPORT_ISSUER_LOCATION_MISSING', [$factKey]);
        }

        return new IssLocationDecision(
            IssLocationStrategy::IMPORT_CUSTOMER_LOCATION,
            new LocationIdentifier((string) $facts->value($factKey)),
            'ISS_IMPORT_LOCATION_RESOLVED_TO_ISSUER',
        );
    }
}
