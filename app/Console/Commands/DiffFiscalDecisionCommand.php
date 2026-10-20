<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Fiscal\Infrastructure\Scenario\DecisionDiffService;
use RuntimeException;
use Throwable;

final class DiffFiscalDecisionCommand extends FiscalJsonCommand
{
    protected $signature = 'fiscal:decision:diff {left : Left result or snapshot JSON} {right : Right result or snapshot JSON}';

    protected $description = 'Produce a machine-readable structural diff between fiscal decisions';

    public function handle(): int
    {
        try {
            $left = $this->decode((string) $this->argument('left'));
            $right = $this->decode((string) $this->argument('right'));
            $changes = (new DecisionDiffService)->diff($left, $right);
            $this->outputJson(['identical' => $changes === [], 'change_count' => count($changes), 'changes' => $changes]);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->outputJson(['status' => 'ERROR', 'error' => $exception->getMessage()]);

            return self::FAILURE;
        }
    }

    /** @return array<string, mixed> */
    private function decode(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Decision file not found: {$path}");
        }
        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($data)) {
            throw new RuntimeException('Decision document must be a JSON object.');
        }
        if (isset($data['snapshot_version'], $data['result'])) {
            if (! is_array($data['result'])) {
                throw new RuntimeException('Snapshot result must be a JSON object.');
            }

            return $data['result'];
        }

        return $data;
    }
}
