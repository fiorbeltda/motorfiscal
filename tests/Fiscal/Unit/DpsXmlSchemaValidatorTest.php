<?php

declare(strict_types=1);

use App\Modules\Fiscal\Infrastructure\Schema\DpsXmlSchemaValidator;

it('validates complete XML against the hash-bound production XSD package', function (): void {
    $path = dirname(__DIR__, 3).'/tests/Fixtures/dps-production-schema-valid.xml';
    $archive = dirname(__DIR__, 3).'/resources/fiscal/sources/official/nfse-esquemas_xsd-v1-01-20260209.zip';
    $xml = (string) file_get_contents($path);
    $validator = new DpsXmlSchemaValidator;
    $hash = 'e7935cbd9470527c6cc32984c1b2263e614183bf0139ce2733eaaed2de9a8072';

    $valid = $validator->validate($xml, $archive, $hash, 'Schemas/1.01/DPS_v1.01.xsd');
    expect($valid['status'])->toBe('VALID')
        ->and($valid['schema_sha256'])->toBe($hash)
        ->and($valid['errors'])->toBe([]);

    $ordinarySerie = str_replace('<serie>^1$</serie>', '<serie>1</serie>', $xml);
    $invalid = $validator->validate($ordinarySerie, $archive, $hash, 'Schemas/1.01/DPS_v1.01.xsd');
    expect($invalid['status'])->toBe('INVALID')
        ->and($invalid['reason'])->toBe('RTC_DPS_XML_XSD_INVALID')
        ->and($invalid['errors'][0]['message'])->toContain('serie');

    $wrongHash = $validator->validate($xml, $archive, str_repeat('0', 64), 'Schemas/1.01/DPS_v1.01.xsd');
    expect($wrongHash['reason'])->toBe('RTC_DPS_XSD_SOURCE_HASH_MISMATCH');
});

it('identifies the preserved pilot XSD compile error separately from XML errors', function (): void {
    $path = dirname(__DIR__, 3).'/tests/Fixtures/dps-production-schema-valid.xml';
    $archive = dirname(__DIR__, 3).'/resources/fiscal/sources/official/nfse-esquemas_xsd-rtc-v1-00-20251210.zip';
    $report = (new DpsXmlSchemaValidator)->validate((string) file_get_contents($path), $archive,
        '04662996e290add36f118c4457d8efed17848eb65d64e8b7ab56c6983c2429ad', 'DPS_v1.01.xsd');

    expect($report['status'])->toBe('ACTION_REQUIRED')
        ->and($report['reason'])->toBe('RTC_DPS_XSD_COMPILATION_FAILED');
});
