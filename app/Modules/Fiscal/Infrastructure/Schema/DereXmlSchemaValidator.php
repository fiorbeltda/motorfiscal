<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\Schema;

use DOMDocument;
use RuntimeException;
use ZipArchive;

/** Validate against unchanged official schemas; an unsigned local candidate is not an accepted declaration. */
final readonly class DereXmlSchemaValidator
{
    /** @return array<string, mixed> */
    public function validate(string $xml, string $archivePath, string $archiveHash, string $rootSchema, string $signatureSchemaPath, string $signatureSchemaHash): array
    {
        $identity = ['xml_sha256' => hash('sha256', $xml), 'schema_sha256' => is_file($archivePath) ? hash_file('sha256', $archivePath) : null,
            'signature_schema_sha256' => is_file($signatureSchemaPath) ? hash_file('sha256', $signatureSchemaPath) : null,
            'production_ready' => false, 'signature_verified' => false];
        if (! preg_match('/^[a-f0-9]{64}$/', $archiveHash) || ! preg_match('/^[a-f0-9]{64}$/', $signatureSchemaHash)
            || $identity['schema_sha256'] !== $archiveHash || $identity['signature_schema_sha256'] !== $signatureSchemaHash) {
            return [...$identity, 'status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DERE_XSD_SOURCE_HASH_MISMATCH'];
        }
        if (! preg_match('/^[A-Za-z0-9_-]+\.xsd$/', $rootSchema) || str_contains($xml, '<!DOCTYPE') || str_contains($xml, '<!ENTITY')) {
            return [...$identity, 'status' => 'INVALID', 'reason' => 'RTC_DERE_XML_OR_SCHEMA_INPUT_INVALID'];
        }
        $directory = tempnam(sys_get_temp_dir(), 'dere-xsd-');
        if ($directory === false || ! unlink($directory) || ! mkdir($directory, 0700)) {
            throw new RuntimeException('Unable to prepare DeRE schemas.');
        }
        $files = [];
        try {
            $archive = new ZipArchive;
            if ($archive->open($archivePath) !== true) {
                return [...$identity, 'status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DERE_XSD_ARCHIVE_INVALID'];
            }
            try {
                for ($index = 0; $index < $archive->numFiles; $index++) {
                    $name = $archive->getNameIndex($index);
                    // Official 1.3.0 includes a readme. Never extract or interpret text entries.
                    if (is_string($name) && str_ends_with(strtolower($name), '.txt')
                        && ! str_contains($name, '/') && ! str_contains($name, '\\') && ! str_contains($name, '..')) {
                        continue;
                    }
                    if (! is_string($name) || ! preg_match('/^[A-Za-z0-9_-]+\.xsd$/', $name) || in_array($name, $files, true)
                        || $name === 'xmldsig-core-schema.xsd') {
                        return [...$identity, 'status' => 'INVALID', 'reason' => 'RTC_DERE_XSD_ARCHIVE_ENTRY_INVALID'];
                    }
                    $content = $archive->getFromIndex($index);
                    if (! is_string($content) || file_put_contents($directory.'/'.$name, $content) === false) {
                        throw new RuntimeException('Unable to prepare DeRE schema entry.');
                    }
                    $files[] = $name;
                }
            } finally {
                $archive->close();
            }
            if (! in_array($rootSchema, $files, true)) {
                return [...$identity, 'status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DERE_XSD_ROOT_MISSING'];
            }
            $dependency = 'xmldsig-core-schema.xsd';
            if (! copy($signatureSchemaPath, $directory.'/'.$dependency)) {
                throw new RuntimeException('Unable to prepare official XMLDSig dependency.');
            }
            $files[] = $dependency;
            $previous = libxml_use_internal_errors(true);
            libxml_clear_errors();
            try {
                $document = new DOMDocument;
                $document->resolveExternals = false;
                $document->substituteEntities = false;
                if (! $document->loadXML($xml, LIBXML_NONET)) {
                    return [...$identity, 'status' => 'INVALID', 'reason' => 'RTC_DERE_XML_MALFORMED', 'errors' => $this->errors()];
                }
                set_error_handler(static fn (int $severity, string $message): bool => str_starts_with($message, 'DOMDocument::schemaValidate()'));
                try {
                    $valid = $document->schemaValidate($directory.'/'.$rootSchema, LIBXML_NONET);
                } finally {
                    restore_error_handler();
                }
                if ($valid) {
                    return [...$identity, 'status' => 'VALID', 'reason' => 'RTC_DERE_XML_XSD_VALID_NO_SIGNATURE_VERIFICATION', 'errors' => []];
                }
                $errors = $this->errors();
                $unsignedOnly = count($errors) === 1 && str_contains($errors[0]['message'], 'Missing child element(s)')
                    && str_contains($errors[0]['message'], '{http://www.w3.org/2000/09/xmldsig#}Signature');
                $compilationFailed = array_any($errors, static fn (array $error): bool => str_contains($error['message'], 'failed to compile')
                    || str_contains($error['message'], 'not a valid regular expression') || str_contains($error['message'], 'failed to load'));

                return [...$identity, 'status' => $unsignedOnly || $compilationFailed ? 'ACTION_REQUIRED' : 'INVALID',
                    'reason' => $unsignedOnly ? 'RTC_DERE_UNSIGNED_LOCAL_CANDIDATE' : ($compilationFailed ? 'RTC_DERE_XSD_COMPILATION_FAILED' : 'RTC_DERE_XML_XSD_INVALID'),
                    'unsigned_candidate_structure_checked' => $unsignedOnly, 'errors' => $errors];
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
        } finally {
            foreach ($files as $name) {
                @unlink($directory.'/'.$name);
            }
            @rmdir($directory);
        }
    }

    /** @return list<array{line: int, message: string}> */
    private function errors(): array
    {
        return array_map(static fn (\LibXMLError $error): array => ['line' => $error->line, 'message' => trim($error->message)], array_slice(libxml_get_errors(), 0, 40));
    }
}
