<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\NFSe;

/** Checks the observed table's paths, active rows, basic facets and conditional group/choice structure; not an XSD or full business-rule validator. */
final readonly class AnexoViStructure143
{
    /** @param array<string,string> $fields
     * @param  array<string,mixed>  $profile
     * @return array<string,mixed>
     */
    public function validate(array $fields, array $profile): array
    {
        $rows = $profile['rows'] ?? [];
        $present = ['DPS' => true];
        $references = [];
        foreach ($fields as $path => $value) {
            $spec = $rows[$path] ?? null;
            if (! is_array($spec) || ! in_array($spec['kind'], ['E', 'CE', 'A', 'ID'], true)) {
                return $this->stop('FIELD_NOT_ACTIVE_IN_SOURCE', $path);
            }
            if (! is_string($value) || trim($value) === ''
                || preg_match('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', $value) !== 0
                || ! $this->facet($value, $spec, $path)) {
                return $this->stop('FIELD_FACET_CONFLICT', $path);
            }
            $parts = explode('.', $path);
            while ($parts !== []) {
                $present[implode('.', $parts)] = true;
                array_pop($parts);
            }
            $references[$path] = $spec['source_row'];
        }
        $choices = [];
        foreach ($rows as $path => $spec) {
            $parent = substr($path, 0, (int) strrpos($path, '.'));
            if (! isset($present[$parent]) || ! str_starts_with($spec['occurrence'], '1-')) {
                continue;
            }
            if (in_array($spec['kind'], ['CE', 'CG'], true)) {
                $choices[$parent][] = $path;
            } elseif (! isset($present[$path])) {
                return $this->stop('REQUIRED_FIELD_OR_GROUP_MISSING', $path);
            }
        }
        foreach ($choices as $parent => $paths) {
            if (count(array_filter($paths, static fn (string $path): bool => isset($present[$path]))) !== 1) {
                return $this->stop('EXACT_SOURCE_CHOICE_REQUIRED', $parent);
            }
        }

        return ['status' => 'OBSERVED_TABLE_STRUCTURE_CONFORMS', 'source_row_references' => $references,
            'source_sha256' => $profile['source_sha256'], 'full_business_rule_validation' => false,
            'xsd_validation' => false, 'remote_acceptance_proven' => false];
    }

    /** @param array<string,mixed> $spec */
    private function facet(string $value, array $spec, string $path): bool
    {
        $type = $spec['type'];
        $size = $spec['size'];
        if ($type === 'D') {
            // Exact date/timestamp and competence relations are checked by the document context qualifier.
            return true;
        }
        if (! preg_match('/\A(\d+)(?:-(\d+))?(?:V(\d+))?\z/', $size, $parts)) {
            return false;
        }
        $minimum = (int) $parts[1];
        $maximum = isset($parts[2]) && $parts[2] !== '' ? (int) $parts[2] : $minimum;
        if ($type === 'C') {
            $length = mb_strlen($value);

            return $length <= (isset($parts[3]) ? $maximum + 1 + (int) $parts[3] : $maximum)
                && ($spec['kind'] !== 'ID' || $length === $maximum);
        }
        if ($type !== 'N') {
            return false;
        }
        if (isset($parts[3])) {
            if (! preg_match('/\A(-?)([0-9]+)\.([0-9]+)\z/', $value, $number)) {
                return false;
            }

            return ($number[1] === '' || $path === 'DPS.infDPS.valores.vAjusteBC.vAjusteBCIBSCBSComExt')
                && strlen($number[2]) >= $minimum && strlen($number[2]) <= $maximum
                && strlen($number[3]) === (int) $parts[3];
        }

        return ctype_digit($value) && strlen($value) >= $minimum && strlen($value) <= $maximum;
    }

    /** @return array<string,mixed> */
    private function stop(string $reason, string $path): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'ANEXOVI143_'.$reason, 'path' => $path,
            'xsd_validation' => false, 'full_business_rule_validation' => false];
    }
}
