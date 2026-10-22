<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Fiscal\ISS\IssDocumentBase133;
use RuntimeException;
use Throwable;

final class CalculateIssDocumentBase133Command extends FiscalJsonCommand
{
    protected $signature = 'fiscal:iss:document-base {input : Fresh composition111 and per-document tax context JSON}';

    protected $description = 'Calculate separate local ISS document bases from fresh material and price evidence';

    public function handle(): int
    {
        try {
            $input = json_decode((string) file_get_contents((string) $this->argument('input')), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($input) || ! is_array($input['input111'] ?? null) || ! is_array($input['proofs'] ?? null)
                || ! is_string($input['knowledge_at'] ?? null) || ! is_array($input['policy'] ?? null)) {
                throw new RuntimeException('Explicit composition,tax context,knowledge and policy required.');
            }
            foreach (IssDocumentBase133::policy()['sources'] as $file => $digest) {
                if (hash_file('sha256', resource_path('fiscal/sources/official/iss133-'.$file)) !== $digest) {
                    throw new RuntimeException('Municipal source bytes missing or changed.');
                }
            }
            $result = (new IssDocumentBase133)->calculate($input['input111'], $input['proofs'], $input['knowledge_at'], $input['policy']);
            $this->outputJson($result);

            return $result['status'] === 'LOCAL_ISS_DOCUMENT_BASES_CALCULATED' ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            $this->outputJson(['status' => 'ERROR', 'error' => $exception->getMessage(), 'production_ready' => false]);

            return self::FAILURE;
        }
    }
}
