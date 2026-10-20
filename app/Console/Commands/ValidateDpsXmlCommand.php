<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Schema\DpsXmlSchemaValidator;
use RuntimeException;
use Throwable;

final class ValidateDpsXmlCommand extends FiscalJsonCommand
{
    protected $signature = 'fiscal:dps:validate-xml
        {path : Complete local DPS XML path}
        {--bundle-manifest=bundles/consumption-phase4b-2026.10.02-14.json}
        {--schema-archive= : Local official XSD archive; must match the bundle hash}';

    protected $description = 'Validate a complete local DPS XML against the hash-bound official XSD archive';

    public function handle(): int
    {
        try {
            $xmlPath = (string) $this->argument('path');
            if (! is_file($xmlPath)) {
                throw new RuntimeException('DPS XML file not found.');
            }
            $xml = file_get_contents($xmlPath);
            if (! is_string($xml)) {
                throw new RuntimeException('Unable to read DPS XML file.');
            }
            $knowledge = (new FiscalEngineFactory)->captureKnowledge(
                resource_path('fiscal'), (string) $this->option('bundle-manifest'));
            $schema = $knowledge['documents']['rtc_code_catalog']['dps_xsd'] ?? null;
            if (! is_array($schema) || ! is_string($schema['source_sha256'] ?? null)
                || ! is_string($schema['root_schema'] ?? null)) {
                throw new RuntimeException('Bundle does not bind a DPS XSD archive.');
            }
            $archive = $this->option('schema-archive');
            $sourceFile = $schema['source_file']
                ?? $knowledge['documents']['rtc_code_catalog']['sources']['dps_xsd']['file'] ?? null;
            if (! is_string($sourceFile) || ! preg_match('/^[A-Za-z0-9_.-]+\.zip$/', $sourceFile)) {
                throw new RuntimeException('Bundle DPS XSD source filename is invalid.');
            }
            $archivePath = is_string($archive) && $archive !== '' ? $archive
                : resource_path('fiscal/sources/official/'.$sourceFile);
            $report = (new DpsXmlSchemaValidator)->validate(
                $xml, $archivePath, $schema['source_sha256'], $schema['root_schema']);
            $this->outputJson(['scope' => 'XML_SCHEMA_ONLY_NO_SIGNATURE_OR_TRANSMISSION',
                'bundle_id' => $knowledge['rule_bundle_id'], 'schema_version' => $schema['version'] ?? null,
                ...$report]);

            return $report['status'] === 'VALID' ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            $this->outputJson(['status' => 'ERROR', 'error' => $exception->getMessage()]);

            return self::FAILURE;
        }
    }
}
