<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

final readonly class DpsIssuerFieldValidator
{
    /**
     * @param  array<string, mixed>  $layout
     * @param  array<string, mixed>  $codes
     * @param  array<string, mixed>  $issuerFields
     * @return array{status: string, reason: string, field?: string, xsd_source_hash?: string}
     */
    public function validate(array $issuerFields, array $layout, array $codes): array
    {
        $schema = $codes['dps_xsd'] ?? null;
        $fields = $codes['dps_xsd_fields'] ?? null;
        if (! is_array($schema) || ! is_array($fields)
            || ! is_string($schema['source_sha256'] ?? null)
            || ($layout['xsd_source_hash'] ?? null) !== $schema['source_sha256']) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DPS_XSD_VERSION_NOT_PROVEN'];
        }
        foreach ($issuerFields as $field => $value) {
            $path = str_starts_with($field, 'DPS.') ? $field : 'DPS.infDPS.'.$field;
            $spec = $fields[$path] ?? null;
            if (! is_array($spec)) {
                return ['status' => 'INVALID', 'reason' => 'RTC_DPS_ISSUER_FIELD_NOT_IN_XSD',
                    'field' => $field, 'xsd_source_hash' => $schema['source_sha256']];
            }
            if (! is_string($value) && ! is_int($value)) {
                return ['status' => 'INVALID', 'reason' => 'RTC_DPS_ISSUER_FIELD_VALUE_INVALID',
                    'field' => $field, 'xsd_source_hash' => $schema['source_sha256']];
            }
            $text = (string) $value;
            if (isset($spec['enum']) && (! is_array($spec['enum']) || ! in_array($text, $spec['enum'], true))) {
                return ['status' => 'INVALID', 'reason' => 'RTC_DPS_ISSUER_FIELD_ENUM_INVALID',
                    'field' => $field, 'xsd_source_hash' => $schema['source_sha256']];
            }
            if (isset($spec['pattern']) && is_string($spec['pattern'])
                && preg_match('~\A(?:'.str_replace('~', '\\~', $spec['pattern']).')\z~D', $text) !== 1) {
                return ['status' => 'INVALID', 'reason' => 'RTC_DPS_ISSUER_FIELD_PATTERN_INVALID',
                    'field' => $field, 'xsd_source_hash' => $schema['source_sha256']];
            }
            $length = mb_strlen($text, 'UTF-8');
            foreach (['length' => '!=', 'minLength' => '<', 'maxLength' => '>'] as $facet => $comparison) {
                if (! isset($spec[$facet])) {
                    continue;
                }
                $limit = (int) $spec[$facet];
                $failed = match ($comparison) {
                    '!=' => $length !== $limit,
                    '<' => $length < $limit,
                    '>' => $length > $limit,
                };
                if ($failed) {
                    return ['status' => 'INVALID', 'reason' => 'RTC_DPS_ISSUER_FIELD_LENGTH_INVALID',
                        'field' => $field, 'xsd_source_hash' => $schema['source_sha256']];
                }
            }
        }

        return ['status' => 'VALID', 'reason' => 'RTC_DPS_ISSUER_FIELDS_MATCH_XSD',
            'xsd_source_hash' => $schema['source_sha256']];
    }
}
