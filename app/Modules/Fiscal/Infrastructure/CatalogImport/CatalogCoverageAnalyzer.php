<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\CatalogImport;

final readonly class CatalogCoverageAnalyzer
{
    /**
     * @param  list<Lc116ServiceEntry>  $lc116
     * @param  list<NationalServiceCodeEntry>  $ctribNac
     * @param  list<IssLocationRuleRow>  $territorial
     * @return array<string, mixed>
     */
    public function analyze(array $lc116, array $ctribNac, array $territorial): array
    {
        $activeLc = [];
        $vetoedLc = [];
        foreach ($lc116 as $entry) {
            if ($entry->status === 'ACTIVE') {
                $activeLc[$entry->code] = true;
            } else {
                $vetoedLc[$entry->code] = true;
            }
        }
        $referencedLc = [];
        $invalidLc = [];
        $ctribCodes = [];
        foreach ($ctribNac as $entry) {
            $ctribCodes['code:'.$entry->code] = $entry->code;
            if ($entry->lc116Code === null) {
                continue;
            }
            $referencedLc[$entry->lc116Code] = true;
            if (! isset($activeLc[$entry->lc116Code])) {
                $invalidLc[] = $entry->lc116Code;
            }
        }
        $territorialCodes = [];
        foreach ($territorial as $entry) {
            $territorialCodes['code:'.$entry->ctribnacCode] = $entry->ctribnacCode;
        }

        $missingLc = array_values(array_diff(array_keys($activeLc), array_keys($referencedLc)));
        $missingTerritorial = array_values(array_diff(array_values($ctribCodes), array_values($territorialCodes)));
        $unknownTerritorial = array_values(array_diff(array_values($territorialCodes), array_values($ctribCodes)));
        $invalidLc = array_values(array_unique($invalidLc));
        sort($missingLc, SORT_STRING);
        sort($missingTerritorial, SORT_STRING);
        sort($unknownTerritorial, SORT_STRING);
        sort($invalidLc, SORT_STRING);

        return [
            'valid' => $missingLc === [] && $invalidLc === [] && $unknownTerritorial === [],
            'territorial_complete' => $missingTerritorial === [],
            'active_lc116_codes' => count($activeLc),
            'vetoed_lc116_codes' => count($vetoedLc),
            'ctribnac_codes' => count($ctribCodes),
            'territorial_codes' => count($territorialCodes),
            'missing_lc116_references' => $missingLc,
            'invalid_or_vetoed_lc116_references' => $invalidLc,
            'missing_territorial_codes' => $missingTerritorial,
            'unknown_territorial_codes' => $unknownTerritorial,
        ];
    }
}
