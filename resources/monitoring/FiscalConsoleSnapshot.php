<?php

declare(strict_types=1);

namespace FiscalConsole;

/** Read-only observer. Does not instantiate or invoke any fiscal engine. */
final class FiscalConsoleSnapshot
{
    public function __construct(private readonly string $root) {}

    public function collect(): array
    {
        $warnings = [];
        $roadmap = $this->json($this->root.'/resources/monitoring/roadmap.json');
        $accepted = $this->latestAcceptance();
        $report = $accepted['report'] ?? [];
        $bundle = null;
        $bundlePath = null;
        $controls = [];
        $assessment = [];
        $review = $this->json($this->root.'/resources/monitoring/objective-review.json');
        if ($accepted === null) {
            $warnings[] = 'Nenhuma aceitação com suíte completa aprovada foi encontrada. Dados indisponíveis não significam falha do motor.';
        } else {
            $hash = $report['bundle_sha256'] ?? null;
            $bundlePaths = [];
            foreach (($report['final_quality'] ?? []) as $gate) {
                foreach ((is_array($gate) ? ($gate['command'] ?? []) : []) as $argument) {
                    if (is_string($argument) && str_starts_with($argument, '--bundle-manifest=bundles/')) {
                        $relative = 'resources/fiscal/'.substr($argument, strlen('--bundle-manifest='));
                        if ($this->safeRelative($relative)) {
                            $bundlePaths[] = $this->root.'/'.$relative;
                        }
                    }
                }
            }
            foreach (array_unique($bundlePaths) as $path) {
                if (is_string($hash) && hash_equals($hash, (string) @hash_file('sha256', $path))) {
                    $bundle = $this->json($path);
                    $bundlePath = $this->relative($path);
                    break;
                }
            }
            if ($bundle === null) {
                $warnings[] = 'O bundle da última aceitação não pôde ser vinculado por hash. Objetivos e produção ficam sem aferição.';
            }
            // Read the captured assessment bound by its recorded hash, not a mutable/latest matrix.
            foreach (($report['final_quality'] ?? []) as $label => $gate) {
                $command = is_array($gate) ? ($gate['command'] ?? []) : [];
                if (! is_array($command) || ! in_array('fiscal:rtc:production-assess', $command, true)) {
                    continue;
                }
                if (! is_string($label) || preg_match('/^[a-zA-Z0-9_-]+$/D', $label) !== 1) {
                    continue;
                }
                $log = dirname($accepted['path']).'/'.$label.'.log';
                $expected = $gate['log_sha256'] ?? null;
                if (! is_string($expected) || ! hash_equals($expected, (string) @hash_file('sha256', $log))) {
                    $warnings[] = 'Avaliação de produção ausente ou diferente do hash aceito.';

                    continue;
                }
                $candidate = $this->json($log);
                if ($bundle !== null && ($candidate['bundle_id'] ?? null) === ($bundle['bundle_id'] ?? null)) {
                    $assessment = $candidate;
                    $controls = is_array($candidate['controls'] ?? null) ? $candidate['controls'] : [];
                    break;
                }
            }
        }

        $integrity = ['checked' => 0, 'mismatches' => [], 'state' => 'UNKNOWN'];
        foreach (($report['runtime_hashes'] ?? []) as $path => $expected) {
            if (! is_string($path) || ! $this->safeRelative($path) || ! is_string($expected)) {
                continue;
            }
            $integrity['checked']++;
            $actual = @hash_file('sha256', $this->root.'/'.$path);
            if (! is_string($actual) || ! hash_equals($expected, $actual)) {
                $integrity['mismatches'][] = $path;
            }
        }
        if ($integrity['checked'] > 0) {
            $integrity['state'] = $integrity['mismatches'] === [] ? 'MATCH' : 'CHANGED';
        }
        if ($integrity['state'] === 'CHANGED') {
            $warnings[] = 'Arquivos monitorados mudaram desde a aceitação. Os testes exibidos descrevem o corte anterior, não validam as alterações atuais.';
        }
        if ($bundle !== null && $controls === []) {
            $warnings[] = 'Matriz de objetivos vinculada ao bundle indisponível.';
        }

        $phases = [];
        foreach (($roadmap['phases'] ?? []) as $phase) {
            $phase['percent'] = null;
            $phase['state'] = $phase['state'] ?? 'UNMEASURED';
            $phase['details'] = [];
            $phase['completed'] = 0;
            $phase['total'] = 0;
            $phase['progress_note'] = $phase['summary'] ?? '';
            $phase['functional_percent'] = null;
            $source = is_string($phase['source'] ?? null) ? $phase['source'] : null;
            $document = $source === null ? '' : $this->read($this->root.'/'.$source);
            if (isset($phase['closure_marker']) && str_contains($document, $phase['closure_marker'])) {
                $phase['state'] = 'CLOSED';
                $phase['percent'] = 100;
                $phase['completed'] = $phase['total'] = 1;
                $phase['functional_percent'] = 100;
            }
            if ($phase['id'] === '4B') {
                $phase['state'] = 'IN_PROGRESS';
                $phase['total'] = count($phase['objectives']);
                foreach ($phase['objectives'] as $objective) {
                    $rows = array_values(array_filter($controls, static fn ($row): bool => is_array($row)
                        && str_starts_with((string) ($row['id'] ?? ''), $objective['id'].'.')));
                    $states = array_column($rows, 'state');
                    $closed = $rows !== [] && count(array_filter($states, static fn ($s): bool => $s === 'APPROVED')) === count($rows);
                    $state = $closed ? 'APPROVED' : (in_array('PARTIAL', $states, true) ? 'PARTIAL' : ($rows === [] ? 'UNKNOWN' : 'OPEN'));
                    $phase['completed'] += (int) $closed;
                    $coverage = [];
                    if (($review['baseline_bundle'] ?? null) === ($bundle['bundle_id'] ?? null) && $bundle !== null) {
                        foreach (($review['objectives'] ?? []) as $entry) {
                            if (is_array($entry) && ($entry['id'] ?? null) === $objective['id']) {
                                $coverage = $entry;
                                break;
                            }
                        }
                    }
                    $phase['details'][] = [...$objective, 'state' => $state, 'controls' => $rows,
                        'finished' => $coverage['finished'] ?? [], 'pending' => $coverage['pending'] ?? [],
                        'evidence' => $coverage['evidence'] ?? []];
                }
                $allKnown = ! in_array('UNKNOWN', array_column($phase['details'], 'state'), true);
                $phase['percent'] = $allKnown ? (int) round(100 * $phase['completed'] / max(1, $phase['total'])) : null;
                if ($phase['percent'] === 100) {
                    $phase['state'] = 'CLOSED';
                    $phase['functional_percent'] = 100;
                }
                $phase['progress_note'] = $allKnown
                    ? $phase['completed'].'/8 grupos formalmente encerrados; '.count(array_filter($phase['details'], static fn ($o): bool => $o['state'] === 'PARTIAL')).' parciais. Auditoria80 requisitos pendente.'
                    : 'Cobertura e encerramento sem evidência suficiente.';
            }
            $phases[] = $phase;
        }

        $documents = [];
        $documentPaths = glob($this->root.'/docs/fiscal/*.md') ?: [];
        usort($documentPaths, static fn ($a, $b): int => filemtime($b) <=> filemtime($a));
        foreach (array_slice($documentPaths, 0, 8) as $path) {
            $text = $this->read($path);
            $lines = preg_split('/\R/', $text) ?: [];
            $summary = [];
            foreach ($lines as $line) {
                if (trim($line) !== '' && ! str_starts_with($line, '#')) {
                    $summary[] = trim($line);
                }
                if (count($summary) === 4) {
                    break;
                }
            }
            $documents[] = ['name' => basename($path), 'title' => ltrim($lines[0] ?? basename($path), '# '),
                'summary' => implode(' ', $summary), 'modified_at' => gmdate(DATE_ATOM, (int) filemtime($path))];
        }
        usort($documents, static fn ($a, $b): int => strcmp($b['modified_at'], $a['modified_at']));

        return [
            'observed_at' => gmdate(DATE_ATOM), 'current_phase' => '4B',
            'scope_note' => $roadmap['scope_note'] ?? '', 'warnings' => $warnings,
            'phases' => $phases, 'bundle' => $bundle['bundle_id'] ?? null, 'bundle_source' => $bundlePath,
            'acceptance' => $accepted === null ? null : ['source' => $this->relative($accepted['path']),
                'recorded_at' => $report['created_at'], 'tests' => $report['full_suite']['tests'] ?? null,
                'assertions' => $report['full_suite']['assertions'] ?? null,
                'scenarios' => $report['scenarios'] ?? null,
                'replays' => isset($report['exact_replays']) ? $report['exact_replays'] + ($report['prior_exact_replays'] ?? 0) : null,
                'sources' => $report['official_sources_valid'] ?? null,
                'production_ready' => $bundle === null ? null : ($report['production_ready'] ?? null),
                'blocker_count' => $bundle === null ? null : ($report['production_blocker_count'] ?? null)],
            'integrity' => $integrity, 'documents' => array_slice($documents, 0, 8),
            'task_progress' => $this->taskProgress(),
            'roadmap_summary' => ['closed_phases' => count(array_filter($phases, static fn ($p): bool => $p['state'] === 'CLOSED')),
                'criterion' => $review['criterion'] ?? null,
                'reviewed_at' => $review['reviewed_at'] ?? null],
            'dependencies' => $assessment['external_dependencies'] ?? [],
        ];
    }

    private function latestAcceptance(): ?array
    {
        $candidates = [];
        foreach (glob($this->root.'/storage/app/*/*development-validation.json') ?: [] as $path) {
            $report = $this->json($path);
            if (($report['full_suite_complete'] ?? false) === true
                && ($report['full_suite']['exit_code'] ?? null) === 0
                && is_string($report['created_at'] ?? null)
                && strtotime($report['created_at']) !== false) {
                $candidates[] = ['path' => $path, 'report' => $report];
            }
        }
        usort($candidates, static fn ($a, $b): int => strtotime($b['report']['created_at']) <=> strtotime($a['report']['created_at']));

        return $candidates[0] ?? null;
    }

    private function taskProgress(): ?array
    {
        $progress = $this->json($this->root.'/resources/monitoring/task-progress.json');
        $steps = $progress['milestones'] ?? null;
        if (! is_array($steps) || ! array_is_list($steps) || $steps === []) {
            return null;
        }
        $completed = count(array_filter($steps, static fn ($step): bool => is_array($step) && ($step['complete'] ?? null) === true));
        $progress['completed'] = $completed;
        $progress['total'] = count($steps);
        $progress['percent'] = (int) round(100 * $completed / count($steps));

        return $progress;
    }

    private function read(string $path): string
    {
        // Bound individual reads; incomplete files during development remain unavailable.
        if (! is_file($path) || filesize($path) > 2_000_000) {
            return '';
        }

        return (string) @file_get_contents($path);
    }

    private function json(string $path): array
    {
        $value = json_decode($this->read($path), true);

        return is_array($value) ? $value : [];
    }

    private function safeRelative(string $path): bool
    {
        return preg_match('~^(app|tests|resources)/[a-zA-Z0-9_./-]+$~D', $path) === 1
            && ! in_array('..', explode('/', $path), true);
    }

    private function relative(string $path): string
    {
        return str_replace('\\', '/', substr($path, strlen($this->root) + 1));
    }
}
