<?php

declare(strict_types=1);

use App\Modules\Fiscal\NFSe\DpsForeignPartyProjector;
use App\Modules\Fiscal\NFSe\DpsImportCandidatePolicy130;

it('requires the explicit source policy before exposing the foreign supplier role', function (string $case): void {
    $codes = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/rtc-codes-2026.10.22-129-draft1.json'), true, 512, JSON_THROW_ON_ERROR);
    $codes['import_candidate_policy130'] = DpsImportCandidatePolicy130::policy();
    $known = new DateTimeImmutable('2026-10-22T17:34:00+00:00');
    switch ($case) {
        case 'missing': unset($codes['import_candidate_policy130']);
            break;
        case 'altered': $codes['import_candidate_policy130']['document_obligation_complete'] = true;
            break;
        case 'schema': $codes['dps_xsd']['source_sha256'] = str_repeat('0', 64);
            break;
        case 'early': $known = new DateTimeImmutable('2026-10-22T17:33:37.832937+00:00');
            break;
    }
    expect((new DpsImportCandidatePolicy130)->qualify($codes, $known)['status'])->toBe('ACTION_REQUIRED')
        ->and((new DpsForeignPartyProjector)->project([], 'prest', $codes, $known)['status'])->toBe('ACTION_REQUIRED');
})->with(['missing', 'altered', 'schema', 'early']);

it('does not permit supplier regime fields on the historical customer fragments', function (): void {
    $codes = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/rtc-codes-2026.10.22-128-draft1.json'), true, 512, JSON_THROW_ON_ERROR);
    $identity = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/scenarios/dps-foreign-acquirer-2026.10.15-76-draft1/nif-identity.json'), true, 512, JSON_THROW_ON_ERROR);
    $identity['foreign_acquirer']['regime'] = ['opSimpNac' => '1', 'regEspTrib' => '0', 'evidence_id' => 'E'];
    expect((new DpsForeignPartyProjector)->project($identity['foreign_acquirer'], 'toma', $codes,
        new DateTimeImmutable('2026-10-22T17:34:00+00:00'))['reason'])->toBe('RTC_DPS_FOREIGN_PARTY_ADDITIONAL_PARTY_DATA_NOT_MAPPED');
});
