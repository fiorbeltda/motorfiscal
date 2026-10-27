<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\NFSe;

use App\Modules\Fiscal\FederalWithholding\PublicAdministrationRuleSet;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use RuntimeException;

/** Selects current public-payment rules without rewriting the prior ISS/RTC snapshot. */
final readonly class DocumentFederalPayment139
{
    public const CONTRACT = 'LOCAL-DOCUMENT-FEDERAL-PAYMENT-139-V1';

    public const AVAILABLE_AT = '2026-10-26T19:22:00+00:00';

    /** @return array<string, mixed> */
    public static function policy(): array
    {
        return [...DocumentFederalPayment137::policy(), 'contract' => self::CONTRACT,
            'available_at' => self::AVAILABLE_AT, 'federal_knowledge' => [
                'manifest_path' => 'bundles/consumption-phase4b-2026.10.26-139-draft1.json',
                'manifest_sha256' => '25fb3000a2e4f7c6e3dafcc6322e2e91a000b4742bbe10a6a5436151d1ac4cff',
                'bundle_id' => 'CONSUMPTION-PHASE4B-2026.10.26-139-draft1',
                'public_payment_contract' => PublicAdministrationRuleSet::DOCUMENT_PAYMENT_CONTRACT,
                'scope' => 'FRESH_WITHHOLDINGS_ONLY_PRIOR_ISS_RTC_AND_OWN_DEBITS_REPLAYED_SEPARATELY',
            ]];
    }

    /** @return array<string, mixed> */
    public function captureKnowledge(string $resourceRoot): array
    {
        $selection = self::policy()['federal_knowledge'];
        $file = $resourceRoot.'/'.$selection['manifest_path'];
        if (! is_file($file) || hash_file('sha256', $file) !== $selection['manifest_sha256']) {
            throw new RuntimeException('Exact public139 manifest bytes required.');
        }
        $knowledge = (new FiscalEngineFactory)->captureKnowledge($resourceRoot, $selection['manifest_path']);
        if ($knowledge['rule_bundle_id'] !== $selection['bundle_id']
            || ($knowledge['documents']['bundle_manifest']['public_payment_contract'] ?? null) !== $selection['public_payment_contract']) {
            throw new RuntimeException('Exact public139 knowledge selection required.');
        }

        return $knowledge;
    }

    /**
     * @param  array<array-key, mixed>  $snapshot
     * @param  array<array-key, mixed>  $declarations
     * @param  array<array-key, mixed>  $proofs
     * @param  array<array-key, mixed>  $policy
     * @return array<string, mixed>
     */
    public function bind(array $snapshot, array $declarations, array $proofs, array $policy, string $root): array
    {
        $result = (new DocumentFederalPayment137)->bind($snapshot, $declarations, $proofs, $policy, $root, $this);
        if (isset($result['reason'])) {
            $result['reason'] = str_replace('DOCPAY137_', 'DOCPAY139_', $result['reason']);
        }
        if (($result['status'] ?? null) === 'STAGED_PER_DOCUMENT_FEDERAL_PAYMENTS_BOUND') {
            $result['federal_knowledge_selection'] = self::policy()['federal_knowledge'];
            $result['prior_snapshot_replaced'] = false;
            $result['public_payment_contract'] = PublicAdministrationRuleSet::DOCUMENT_PAYMENT_CONTRACT;
        }

        return $result;
    }
}
