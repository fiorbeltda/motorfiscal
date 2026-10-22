<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\Schema;

use DOMDocument;
use RuntimeException;
use ZipArchive;

final readonly class DpsXmlSchemaValidator
{
    private const VALUES_ROOT = '<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema" targetNamespace="http://www.sped.fazenda.gov.br/nfse" xmlns="http://www.sped.fazenda.gov.br/nfse" elementFormDefault="qualified"><xs:include schemaLocation="tiposComplexos_v1.01.xsd"/><xs:element name="valores" type="TCInfoValores"/></xs:schema>';

    /**
     * @return array{status: string, reason: string, xml_sha256: string, schema_sha256: string, errors: list<array{line: int, message: string}>}
     */
    public function validate(string $xml, string $zipPath, string $expectedSha256, string $rootSchema): array
    {
        return $this->validateInternal($xml, $zipPath, $expectedSha256, $rootSchema);
    }

    /** Validate a local fragment against an unchanged official complex type, never a complete DPS.
     * @return array<string, mixed>
     */
    public function validateValuesFragment(string $xml, string $zipPath, string $expectedSha256, string $rootSchema): array
    {
        return [...$this->validateInternal($xml, $zipPath, $expectedSha256, $rootSchema, true),
            'validation_scope' => 'LOCAL_VALUES_ROOT_OFFICIAL_TCInfoValores_TYPE', 'full_document_validated' => false,
            'local_root_declaration_sha256' => hash('sha256', self::VALUES_ROOT)];
    }

    /** @return array{status: string, reason: string, xml_sha256: string, schema_sha256: string, errors: list<array{line: int, message: string}>} */
    private function validateInternal(string $xml, string $zipPath, string $expectedSha256, string $rootSchema, bool $valuesFragment = false): array
    {
        $xmlHash = hash('sha256', $xml);
        $schemaHash = is_file($zipPath) ? (string) hash_file('sha256', $zipPath) : '';
        if (! preg_match('/^[a-f0-9]{64}$/', $expectedSha256) || $schemaHash !== $expectedSha256) {
            return $this->result('ACTION_REQUIRED', 'RTC_DPS_XSD_SOURCE_HASH_MISMATCH', $xmlHash, $schemaHash);
        }
        if (! preg_match('~^(?:[A-Za-z0-9_.-]+/)*[A-Za-z0-9_.-]+\.xsd$~', $rootSchema)
            || in_array('..', explode('/', $rootSchema), true)
            || in_array('.', explode('/', $rootSchema), true)) {
            return $this->result('INVALID', 'RTC_DPS_XSD_ROOT_INVALID', $xmlHash, $schemaHash);
        }
        $prefix = str_contains($rootSchema, '/') ? substr($rootSchema, 0, (int) strrpos($rootSchema, '/') + 1) : '';
        $rootName = basename($rootSchema);
        $directory = tempnam(sys_get_temp_dir(), 'dps-xsd-');
        if ($directory === false || ! unlink($directory) || ! mkdir($directory, 0700)) {
            throw new RuntimeException('Unable to create temporary DPS schema directory.');
        }
        $files = [];
        try {
            $archive = new ZipArchive;
            if ($archive->open($zipPath) !== true) {
                return $this->result('ACTION_REQUIRED', 'RTC_DPS_XSD_ARCHIVE_INVALID', $xmlHash, $schemaHash);
            }
            try {
                for ($index = 0; $index < $archive->numFiles; $index++) {
                    $name = $archive->getNameIndex($index);
                    if (! is_string($name)) {
                        return $this->result('INVALID', 'RTC_DPS_XSD_ARCHIVE_ENTRY_INVALID', $xmlHash, $schemaHash);
                    }
                    if (! str_starts_with($name, $prefix) || $name === $prefix) {
                        continue;
                    }
                    $entry = substr($name, strlen($prefix));
                    if (! preg_match('/^[A-Za-z0-9_.-]+\.xsd$/', $entry)
                        || in_array($entry, $files, true)) {
                        return $this->result('INVALID', 'RTC_DPS_XSD_ARCHIVE_ENTRY_INVALID', $xmlHash, $schemaHash);
                    }
                    $content = $archive->getFromIndex($index);
                    if (! is_string($content) || file_put_contents($directory.DIRECTORY_SEPARATOR.$entry, $content) === false) {
                        throw new RuntimeException('Unable to extract DPS schema entry.');
                    }
                    $files[] = $entry;
                }
            } finally {
                $archive->close();
            }
            if (! in_array($rootName, $files, true)) {
                return $this->result('ACTION_REQUIRED', 'RTC_DPS_XSD_ROOT_MISSING', $xmlHash, $schemaHash);
            }
            if ($valuesFragment) {
                if (! in_array('tiposComplexos_v1.01.xsd', $files, true) || in_array('local-values134.xsd', $files, true)) {
                    return $this->result('ACTION_REQUIRED', 'RTC_DPS134_VALUES_TYPE_NOT_BOUND', $xmlHash, $schemaHash);
                }
                $rootName = 'local-values134.xsd';
                if (file_put_contents($directory.DIRECTORY_SEPARATOR.$rootName, self::VALUES_ROOT) === false) {
                    throw new RuntimeException('Unable to create local values root declaration.');
                }
                $files[] = $rootName;
            }

            $previous = libxml_use_internal_errors(true);
            libxml_clear_errors();
            try {
                $document = new DOMDocument;
                $document->resolveExternals = false;
                $document->substituteEntities = false;
                $loaded = $document->loadXML($xml, LIBXML_NONET);
                if (! $loaded) {
                    return $this->result('INVALID', 'RTC_DPS_XML_MALFORMED', $xmlHash, $schemaHash, $this->errors());
                }
                set_error_handler(static fn (int $severity, string $message): bool => str_starts_with($message, 'DOMDocument::schemaValidate()'));
                try {
                    $valid = $document->schemaValidate($directory.DIRECTORY_SEPARATOR.$rootName, LIBXML_NONET);
                } finally {
                    restore_error_handler();
                }
                if (! $valid) {
                    $errors = $this->errors();
                    $schemaFailed = array_any($errors, static fn (array $error): bool => str_contains($error['message'], 'not a valid regular expression')
                        || str_contains($error['message'], 'failed to compile'));

                    return $this->result($schemaFailed ? 'ACTION_REQUIRED' : 'INVALID',
                        $schemaFailed ? 'RTC_DPS_XSD_COMPILATION_FAILED' : 'RTC_DPS_XML_XSD_INVALID',
                        $xmlHash, $schemaHash, $errors);
                }

                return $this->result('VALID', 'RTC_DPS_XML_XSD_VALID', $xmlHash, $schemaHash);
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
        } finally {
            foreach ($files as $name) {
                @unlink($directory.DIRECTORY_SEPARATOR.$name);
            }
            @rmdir($directory);
        }
    }

    /** @return list<array{line: int, message: string}> */
    private function errors(): array
    {
        $errors = [];
        foreach (array_slice(libxml_get_errors(), 0, 20) as $error) {
            $errors[] = ['line' => $error->line, 'message' => trim($error->message)];
        }

        return $errors;
    }

    /**
     * @param  list<array{line: int, message: string}>  $errors
     * @return array{status: string, reason: string, xml_sha256: string, schema_sha256: string, errors: list<array{line: int, message: string}>}
     */
    private function result(string $status, string $reason, string $xmlHash, string $schemaHash, array $errors = []): array
    {
        return ['status' => $status, 'reason' => $reason,
            'xml_sha256' => $xmlHash, 'schema_sha256' => $schemaHash, 'errors' => $errors];
    }
}
