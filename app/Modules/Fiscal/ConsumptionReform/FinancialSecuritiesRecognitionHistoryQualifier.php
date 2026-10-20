<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeImmutable;
use InvalidArgumentException;
use Throwable;

/** Replays captured fiscal decisions and a closing position before assigning historical recognition balances. */
final readonly class FinancialSecuritiesRecognitionHistoryQualifier
{
    public const CONTRACT = 'TVM-RECOGNITION-HISTORY-REPLAY-2026.10.06-1';

    /** @param array<string,mixed> $scope
     * @param  array<string,mixed>  $history
     * @param  array<string,mixed>  $rule
     * @return array<string,mixed>
     */
    public function resolve(array $scope, array $history, DateTimeImmutable $knownAt, array $rule): array
    {
        try {
            if (($rule['contract'] ?? null) !== self::CONTRACT || ($rule['status'] ?? null) !== 'ACTIVE'
                || ! is_string($rule['published_at'] ?? null) || new DateTimeImmutable($rule['published_at']) > $knownAt
                || ! $this->hash($rule['source_hash'] ?? null) || ! is_array($rule['permitted_bundle_manifest_hashes'] ?? null)
                || ! is_array($rule['daily_memory_policies'] ?? null)) {
                return $this->stop('RULE_UNAVAILABLE');
            }
            if (array_key_exists('source_policy_selection', $rule)) {
                $selection = $rule['source_policy_selection'];
                if (! is_array($selection) || ($selection['strategy'] ?? null) !== 'BY_EXACT_REPLAYED_MANIFEST_SET'
                    || ($selection['status'] ?? null) !== 'ACTIVE' || ! is_string($selection['published_at'] ?? null)
                    || new DateTimeImmutable($selection['published_at']) > $knownAt
                    || ! $this->hash($selection['source_hash'] ?? null) || ! is_array($selection['profiles'] ?? null)
                    || $selection['source_hash'] !== CanonicalJson::hash(array_diff_key($selection, ['source_hash' => true]))) {
                    return $this->stop('SOURCE_POLICY_SELECTION_UNAVAILABLE');
                }
                $sources = $history['fiscal_sources'] ?? null;
                if (! is_array($sources) || ! array_is_list($sources) || $sources === [] || count($sources) > 120) {
                    return $this->stop('SOURCE_COVERAGE_INVALID');
                }
                $manifests = [];
                foreach ($sources as $source) {
                    $manifest = $source['snapshot']['knowledge']['content_hashes']['bundle_manifest'] ?? null;
                    if (! $this->hash($manifest) || ! in_array($manifest, $rule['permitted_bundle_manifest_hashes'], true)) {
                        return $this->stop('FISCAL_KNOWLEDGE_NOT_PERMITTED');
                    }
                    $manifests[] = $manifest;
                }
                $manifests = array_values(array_unique($manifests));
                sort($manifests, SORT_STRING);
                $profile = $selection['profiles'][CanonicalJson::hash($manifests)] ?? null;
                if (! is_array($profile) || ($profile['manifest_hashes'] ?? null) !== $manifests
                    || ! is_array($profile['policy'] ?? null) || isset($profile['policy']['source_policy_selection'])
                    || ($profile['policy_hash'] ?? null) !== CanonicalJson::hash($profile['policy'])) {
                    return $this->stop('SOURCE_POLICY_PROFILE_UNPROVEN');
                }
                foreach ($manifests as $manifest) {
                    if (! in_array($manifest, $profile['policy']['permitted_bundle_manifest_hashes'] ?? [], true)) {
                        return $this->stop('SOURCE_POLICY_PROFILE_PERMISSION_CONFLICT');
                    }
                }

                // The selected frozen policy performs all scope, closing-memory and fiscal snapshot replays.
                return $this->resolve($scope, $history, $knownAt, $profile['policy']);
            }
            $title = $scope['title'] ?? null;
            $opening = $scope['opening'] ?? null;
            if (! is_string($scope['supplier_id'] ?? null) || ! is_string($scope['period'] ?? null)
                || preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])$/', $scope['period']) !== 1 || ! is_array($title)
                || ! (new FinancialPublicOfferDebtQualifier)->validTitleIdentity($title) || ! is_array($opening)) {
                return $this->stop('SCOPE_INVALID');
            }
            $period = $scope['period'];
            $priorPeriod = (new DateTimeImmutable($period.'-01'))->modify('-1 month')->format('Y-m');
            $priorStart = $priorPeriod.'-01';
            $priorEnd = (new DateTimeImmutable($priorStart))->format('Y-m-t');
            if ($priorEnd > $knownAt->format('Y-m-d')) {
                return $this->stop('PREVIOUS_PERIOD_NOT_COMPLETE');
            }
            $closing = $history['closing_memory'] ?? null;
            $policy = $rule['daily_memory_policies'][$priorPeriod] ?? null;
            if (! is_array($closing) || ($closing['contract'] ?? null) !== 'TVM-RECOGNITION-CLOSING-MEMORY-1'
                || ! is_array($closing['position'] ?? null) || ! is_array($closing['result'] ?? null) || ! is_array($policy)
                || ! is_string($closing['known_at'] ?? null) || new DateTimeImmutable($closing['known_at']) > $knownAt
                || ($closing['policy_hash'] ?? null) !== CanonicalJson::hash($policy)
                || ($closing['position']['supplier_id'] ?? null) !== $scope['supplier_id']
                || ($closing['position']['period'] ?? null) !== $priorPeriod
                || ($closing['position']['idTitulo'] ?? null) !== $title['idTitulo'] || ($closing['position']['cCta'] ?? null) !== $title['cCta']) {
                return $this->stop('CLOSING_MEMORY_SCOPE_CONFLICT');
            }
            $memory = (new FinancialDebtDailyMemoryCalculator)->calculate($closing['position'], $policy['series'], new DateTimeImmutable($closing['known_at']), $policy);
            if (($memory['status'] ?? null) !== 'RESOLVED' || CanonicalJson::encode($memory) !== CanonicalJson::encode($closing['result'])) {
                return $this->stop('CLOSING_MEMORY_REPLAY_MISMATCH');
            }
            foreach (['quantity' => 'closing_quantity', 'unit_cost' => 'closing_unit_cost', 'acquisition_cost' => 'closing_acquisition_cost'] as $inputKey => $resultKey) {
                if (! $this->decimal($opening[$inputKey] ?? null)->isEqualTo($this->decimal($memory[$resultKey]))) {
                    return $this->stop('CURRENT_OPENING_POSITION_CONFLICT');
                }
            }
            if (! $this->decimal($opening['book_balance'] ?? null)->isEqualTo($this->decimal($memory['monthly']['vSaldoContFin']))) {
                return $this->stop('CURRENT_OPENING_POSITION_CONFLICT');
            }
            $sources = $history['fiscal_sources'] ?? null;
            if (! is_array($sources) || ! array_is_list($sources) || $sources === [] || count($sources) > 120) {
                return $this->stop('SOURCE_COVERAGE_INVALID');
            }
            $keys = ['assessed_produced_return', 'excluded_art195_return', 'removed_pis_cofins', 'deferred_fair_value'];
            $unoffsetPublicLoss = ($title['instrument_type'] ?? null) === 'CORPORATE_DEBT_PUBLIC_OFFER'
                && ($rule['public_negative_yield_policy'] ?? null) === 'REPLAYED_UNOFFSET_TITLE_LOSS_NO_CURRENT_DEDUCTION';
            if ($unoffsetPublicLoss) {
                $keys[] = 'unoffset_public_title_loss';
            }
            /** @var array<string,BigDecimal> $pool */
            $pool = array_fill_keys($keys, BigDecimal::of('0.00000000'));
            $seen = $replayed = $references = $publicPositions = [];
            $publicChain = ($rule['public_position_chain_policy'] ?? null) === 'REPLAYED_CONSECUTIVE_PUBLIC_YIELD_POSITION_CHAIN';
            $publicRealizedClosing = ($rule['public_realized_closing_policy'] ?? null) === 'REPLAYED_PUBLIC_REALIZATION_CLOSING_AND_RETAINED_CURRENT_CURVE';
            $publicExplicitClosing = ($rule['public_explicit_net_closing_policy'] ?? null) === 'REPLAYED_PUBLIC_FIRST_ACQUISITION_NET_CLOSING_WITH_CARRYING_ROUNDING';
            $publicCreatedClosing = ($rule['public_created_rounding_closing_policy'] ?? null) === FinancialPublicCreatedRoundingClosingQualifier::METHOD;
            $carrying = null;
            if ($publicCreatedClosing && (! $unoffsetPublicLoss || $publicChain || $publicRealizedClosing || $publicExplicitClosing || count($sources) !== 1)) {
                return $this->stop('PUBLIC_CREATED_ROUNDING_CLOSING_POLICY_CONFLICT');
            }
            if ($publicExplicitClosing && (! $unoffsetPublicLoss || $publicChain || $publicRealizedClosing || count($sources) !== 1)) {
                return $this->stop('PUBLIC_EXPLICIT_NET_CLOSING_POLICY_CONFLICT');
            }
            if ($publicRealizedClosing && (! $unoffsetPublicLoss || $publicChain || count($sources) !== 1)) {
                return $this->stop('PUBLIC_REALIZED_CLOSING_POLICY_CONFLICT');
            }
            if ($publicChain && ! $unoffsetPublicLoss) {
                return $this->stop('PUBLIC_POSITION_CHAIN_POLICY_CONFLICT');
            }
            $factory = new FiscalEngineFactory;
            $service = new RtcScenarioRunService($factory);
            foreach ($sources as $source) {
                if (! is_array($source) || ! is_array($source['snapshot'] ?? null) || ! is_string($source['financial_entry_id'] ?? null)) {
                    return $this->stop('FISCAL_SOURCE_INVALID');
                }
                $snapshot = $source['snapshot'];
                $knowledge = $snapshot['knowledge'] ?? null;
                $input = $snapshot['input'] ?? null;
                if (! is_array($knowledge) || ! is_array($input) || ! is_array($snapshot['result'] ?? null)) {
                    return $this->stop('FISCAL_SNAPSHOT_INVALID');
                }
                $manifestHash = $knowledge['content_hashes']['bundle_manifest'] ?? null;
                if (! $this->hash($manifestHash) || ! in_array($manifestHash, $rule['permitted_bundle_manifest_hashes'], true)) {
                    return $this->stop('FISCAL_KNOWLEDGE_NOT_PERMITTED');
                }
                $sourcePeriod = substr((string) ($input['competence_date'] ?? ''), 0, 7);
                $sourceEnd = (new DateTimeImmutable($sourcePeriod.'-01'))->format('Y-m-t');
                if (($input['competence_date'] ?? null) !== $sourceEnd || $sourcePeriod > $priorPeriod
                    || ! is_string($input['knowledge_at'] ?? null) || new DateTimeImmutable($input['knowledge_at']) > $knownAt) {
                    return $this->stop('FISCAL_PERIOD_OR_KNOWLEDGE_CONFLICT');
                }
                $snapshotHash = CanonicalJson::hash($snapshot);
                if (! isset($replayed[$snapshotHash])) {
                    // Validate every captured document against the manifest before using the narrower RTC replay.
                    $factory->makeFromKnowledge($knowledge);
                    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($input);
                    $result = $service->evaluate($knowledge, $scenario)->toArray();
                    $documentOnlyPending = ($result['status'] ?? null) === 'ACTION_REQUIRED'
                        && ($result['reason_codes'] ?? null) === ['RTC_FINANCIAL_DOCUMENT_PENDING'];
                    if ((($result['status'] ?? null) !== 'RESOLVED' && ! $documentOnlyPending)
                        || CanonicalJson::encode($result) !== CanonicalJson::encode($snapshot['result'])) {
                        return $this->stop('FISCAL_SNAPSHOT_REPLAY_MISMATCH');
                    }
                    $financial = array_values(array_filter($result['trace'], static fn (array $row): bool => ($row['stage'] ?? null) === 'FINANCIAL_OPERATION_AND_BASE'));
                    if (count($financial) !== 1 || ($financial[0]['status'] ?? null) !== 'RESOLVED'
                        || ($financial[0]['base']['status'] ?? null) !== 'RESOLVED'
                        || ($financial[0]['base']['supplier_id'] ?? null) !== $scope['supplier_id']) {
                        return $this->stop('FISCAL_FINANCIAL_SCOPE_CONFLICT');
                    }
                    $gross = array_values(array_filter($result['trace'], static fn (array $row): bool => ($row['stage'] ?? null) === 'FINANCIAL_GROSS_DEBITS'));
                    if (count($gross) !== 1 || ($gross[0]['status'] ?? null) !== 'RESOLVED'
                        || ($gross[0]['base'] ?? null) !== $financial[0]['base']['net_base']) {
                        return $this->stop('PRIOR_GROSS_TAX_BASE_UNRESOLVED');
                    }
                    $replayed[$snapshotHash] = $financial[0]['base'];
                }
                $entryMatches = array_values(array_filter($replayed[$snapshotHash]['entries'], static fn (array $entry): bool => ($entry['id'] ?? null) === $source['financial_entry_id']));
                if (count($entryMatches) !== 1) {
                    return $this->stop('SOURCE_ENTRY_ABSENT_OR_DUPLICATE');
                }
                $entry = $entryMatches[0];
                $sourceKey = CanonicalJson::hash(['supplier_id' => $scope['supplier_id'], 'period' => $sourcePeriod,
                    'document_id' => $entry['document_id'], 'financial_entry_id' => $source['financial_entry_id']]);
                if (isset($seen[$sourceKey])) {
                    return $this->stop('DUPLICATE_FISCAL_SOURCE');
                }
                $seen[$sourceKey] = true;
                /** @var array<string,BigDecimal> $available */
                $available = array_fill_keys($keys, BigDecimal::of('0.00000000'));
                if (($entry['kind'] ?? null) === 'PUBLIC_OFFER_DEBT_YIELD') {
                    $binding = $entry['d2101_binding'] ?? null;
                    if (! is_array($binding) || ($binding['status'] ?? null) !== 'RESOLVED'
                        || ! in_array($binding['gross_nature'] ?? null, $unoffsetPublicLoss ? ['C', 'D'] : ['C'], true)
                        || ($binding['xml_title']['idTitulo'] ?? null) !== $title['idTitulo']
                        || ($binding['xml_title']['cCta'] ?? null) !== $title['cCta']
                        || ($binding['xml_title']['CNPJDevedor'] ?? null) !== $title['CNPJDevedor']
                        || ($title['instrument_type'] ?? null) !== 'CORPORATE_DEBT_PUBLIC_OFFER') {
                        return $this->stop('PUBLIC_TITLE_OR_NEGATIVE_RECOGNITION_UNPROVEN');
                    }
                    $available['assessed_produced_return'] = $this->decimal($binding['taxable_amount']);
                    $available['excluded_art195_return'] = $this->decimal($binding['art195_excess']);
                    $available['removed_pis_cofins'] = $this->decimal($binding['pis_deduction_applied']);
                    $gross = $this->decimal($binding['gross_amount']);
                    if (($binding['gross_nature'] ?? null) === 'D') {
                        if (! $this->sum($available)->isZero() || $gross->isZero()
                            || ! $this->decimal($binding['excluded_positive_amount'])->isZero()
                            || ! $this->decimal($binding['unoffset_title_loss'])->isEqualTo($gross)
                            || ! $this->signed($binding['xml_title']['vVarMensal'])->isEqualTo($gross->negated())) {
                            return $this->stop('PUBLIC_UNOFFSET_LOSS_COMPONENT_CONFLICT');
                        }
                        $available['unoffset_public_title_loss'] = $gross->negated();
                    }
                    if (! $this->sum($available)->isEqualTo(($binding['gross_nature'] ?? null) === 'D' ? $gross->negated() : $gross)) {
                        return $this->stop('PUBLIC_GROSS_COMPONENT_CONFLICT');
                    }
                    if ($publicRealizedClosing) {
                        $available = $this->publicRealizedClosing($entry, $available, $closing, $memory, $sourcePeriod, $priorPeriod);
                        if ($available === null) {
                            return $this->stop('PUBLIC_REALIZED_CLOSING_COMPONENTS_UNPROVEN');
                        }
                    }
                    if ($publicCreatedClosing) {
                        $created = (new FinancialPublicCreatedRoundingClosingQualifier)->resolve($scope, $source, $entry, $available,
                            $closing, $memory, $sourcePeriod, $priorPeriod, $knownAt);
                        if ($created === null) {
                            return $this->stop('PUBLIC_CREATED_ROUNDING_CLOSING_UNPROVEN');
                        }
                        $available = $created['available'];
                        $carrying = $created['carrying'];
                    } elseif (isset($source['created_rounding_closing_evidence']) || isset($source['current_fiscal_allocation'])) {
                        return $this->stop('PUBLIC_CREATED_ROUNDING_CLOSING_POLICY_UNPROVEN');
                    }
                    if ($publicExplicitClosing) {
                        $explicit = $this->publicExplicitClosing($scope, $source, $entry, $available, $closing, $memory, $sourcePeriod, $priorPeriod, $knownAt);
                        if ($explicit === null) {
                            return $this->stop('PUBLIC_EXPLICIT_NET_CLOSING_UNPROVEN');
                        }
                        $available = $explicit['available'];
                        $carrying = $explicit['carrying'];
                    } elseif (isset($source['net_closing_evidence'])) {
                        return $this->stop('PUBLIC_EXPLICIT_NET_CLOSING_POLICY_UNPROVEN');
                    }
                } else {
                    if ($publicRealizedClosing || $publicExplicitClosing || $publicCreatedClosing) {
                        return $this->stop('PUBLIC_REALIZED_CLOSING_SOURCE_UNPROVEN');
                    }
                    $identity = ['idTitulo' => $title['idTitulo'], 'cCta' => $title['cCta'], 'CNPJDevedor' => $title['CNPJDevedor'], 'currency' => 'BRL'];
                    $public = ($title['instrument_type'] ?? null) === 'CORPORATE_DEBT_PUBLIC_OFFER';
                    if (CanonicalJson::encode($entry['security_identity'] ?? null) !== CanonicalJson::encode($identity)) {
                        return $this->stop('PRIVATE_ENTRY_TITLE_UNPROVEN');
                    }
                    if ($public && ! $this->publicDeferral($source, $entry, $replayed[$snapshotHash]['entries'], $sources,
                        $closing, $memory, $policy, $scope, $sourcePeriod, $priorPeriod, $knownAt, $rule)) {
                        return $this->stop('PUBLIC_DEFERRAL_BRIDGE_UNPROVEN');
                    }
                    if (($entry['kind'] ?? null) === 'FINANCIAL_REVENUE' && ($entry['bucket'] ?? null) === 'REVENUE') {
                        $available['assessed_produced_return'] = $this->decimal($entry['computed_amount']);
                    } elseif (($entry['kind'] ?? null) === 'FAIR_VALUE_UNREALIZED' && ($entry['bucket'] ?? null) === 'DEFERRED') {
                        if (isset($entry['fair_value_nature']) || isset($entry['deferred_signed_amount'])) {
                            if ((! $public && ($rule['signed_private_deferral_policy'] ?? null) !== 'REPLAYED_INDEPENDENT_SIGNED_SUBACCOUNT_RECOGNITION')
                                || ! in_array($entry['fair_value_nature'] ?? null, ['C', 'D'], true) || ! is_string($entry['deferred_signed_amount'] ?? null)) {
                                return $this->stop('SIGNED_PRIVATE_DEFERRAL_POLICY_UNPROVEN');
                            }
                            $signed = $this->signed($entry['deferred_signed_amount']);
                            if (! $signed->abs()->isEqualTo($this->decimal($entry['computed_amount']))
                                || (! $signed->isZero() && $signed->isNegative() !== ($entry['fair_value_nature'] === 'D'))) {
                                return $this->stop('SIGNED_PRIVATE_DEFERRAL_COMPONENT_CONFLICT');
                            }
                            $available['deferred_fair_value'] = $signed;
                        } else {
                            $available['deferred_fair_value'] = $this->decimal($entry['computed_amount']);
                        }
                    } else {
                        return $this->stop('SOURCE_RECOGNITION_KIND_UNPROVEN');
                    }
                }
                $share = $this->decimal($source['retained_share'] ?? null);
                if (($publicRealizedClosing || $publicExplicitClosing || $publicCreatedClosing) && ! $share->isEqualTo('1')) {
                    return $this->stop('PUBLIC_REALIZED_CLOSING_RETAINED_TWICE');
                }
                if ($publicChain) {
                    $position = $entry['public_offer_debt']['position'] ?? null;
                    $calculation = $entry['d2101_binding']['calculation'] ?? null;
                    if (($entry['kind'] ?? null) !== 'PUBLIC_OFFER_DEBT_YIELD' || ! $share->isEqualTo('1')
                        || ! is_array($position) || ! is_array($calculation)
                        || ($position['supplier_id'] ?? null) !== $scope['supplier_id']
                        || ($position['period'] ?? null) !== $sourcePeriod || isset($publicPositions[$sourcePeriod])) {
                        return $this->stop('PUBLIC_POSITION_CHAIN_SOURCE_CONFLICT');
                    }
                    $publicPositions[$sourcePeriod] = ['position' => $position, 'result' => $calculation];
                }
                if ($share->isNegative() || $share->isGreaterThan('1')) {
                    return $this->stop('RETAINED_SHARE_INVALID');
                }
                $retained = array_map(static fn (BigDecimal $v): BigDecimal => $v->multipliedBy($share)->toScale(8, RoundingMode::HalfEven), $available);
                foreach ($keys as $key) {
                    $pool[$key] = $pool[$key]->plus($retained[$key]);
                }
                $value = ['supplier_id' => $scope['supplier_id'], 'current_period' => $period, 'title' => $title,
                    'snapshot_hash' => $snapshotHash, 'financial_entry_id' => $source['financial_entry_id'], 'retained_share' => $source['retained_share']];
                if (! (new FinancialPublicOfferDebtQualifier)->proof($source['assignment_evidence'] ?? null, $value, $scope['supplier_id'], $sourcePeriod.'-01', $sourceEnd, $knownAt)) {
                    return $this->stop('INDEPENDENT_RETAINED_ASSIGNMENT_UNPROVEN');
                }
                $references[] = ['source_key' => $sourceKey, 'snapshot_hash' => $snapshotHash, 'decision_hash' => $snapshot['result']['decision_hash'],
                    'snapshot_status' => $snapshot['result']['status'],
                    'period' => $sourcePeriod, 'entry_id' => $source['financial_entry_id'], 'retained_share' => $source['retained_share'],
                    'available' => $this->amounts($available), 'retained' => $this->amounts($retained), 'assignment_evidence_hash' => $source['assignment_evidence']['source_hash'],
                    ...($publicExplicitClosing ? ['net_closing_evidence_hash' => $source['net_closing_evidence']['source_hash']] : [])];
                if ($publicCreatedClosing) {
                    $references[array_key_last($references)]['created_rounding_closing_evidence_hash'] = $source['created_rounding_closing_evidence']['source_hash'];
                }
            }
            if ($publicChain && ! $this->publicPositionChain($publicPositions, $closing, $priorPeriod)) {
                return $this->stop('PUBLIC_POSITION_CHAIN_CONTINUITY_UNPROVEN');
            }
            if ($publicCreatedClosing && $carrying === null) {
                return $this->stop('PUBLIC_CREATED_ROUNDING_CLOSING_UNPROVEN');
            }
            $uncovered = $publicCreatedClosing ? $this->signed($carrying['uncovered_created_rounding']) : BigDecimal::of('0');
            if (! $this->sum($pool)->plus($uncovered)->isEqualTo($this->decimal($opening['book_balance'])->minus($this->decimal($opening['acquisition_cost'])))) {
                return $this->stop('CLOSING_CARRYING_COMPONENT_CONFLICT');
            }
            usort($references, static fn (array $a, array $b): int => $a['source_key'] <=> $b['source_key']);
            $value = ['scope' => $scope, 'sources' => $references, 'closing_memory_hash' => CanonicalJson::hash($closing), 'opening_fiscal_components' => $this->amounts($pool)];
            if ($publicExplicitClosing || $publicCreatedClosing) {
                $value['opening_carrying_components'] = $carrying;
            }
            if (! (new FinancialPublicOfferDebtQualifier)->proof($history['complete_evidence'] ?? null, $value, $scope['supplier_id'], $priorStart, $priorEnd, $knownAt)) {
                return $this->stop('COMPLETE_HISTORY_UNPROVEN');
            }

            return ['status' => 'RESOLVED', 'reason' => 'RTC_TVM_HISTORY_REPLAY_QUALIFIED', 'contract' => self::CONTRACT,
                'supplier_id' => $scope['supplier_id'], 'period' => $period, 'prior_period' => $priorPeriod,
                'title' => $title, 'opening_fiscal_components' => $this->amounts($pool), 'sources' => $references,
                'source_set_hash' => CanonicalJson::hash($references), 'closing_memory_hash' => CanonicalJson::hash($closing),
                'complete_evidence_hash' => $history['complete_evidence']['source_hash'], 'source_hash' => $rule['source_hash'],
                'fiscal_snapshots_replayed' => count($replayed), 'closing_memory_replayed' => true,
                ...($publicChain ? ['public_position_chain_replayed' => true, 'public_position_chain_hash' => CanonicalJson::hash($publicPositions)] : []),
                ...($publicRealizedClosing ? ['public_realized_closing_replayed' => true] : []),
                ...($publicExplicitClosing ? ['public_explicit_net_closing_replayed' => true, 'opening_carrying_components' => $carrying,
                    'fiscal_pool_basis' => 'NET_CARRYING_RETURN_WITH_SEPARATE_ECONOMIC_ROUNDING'] : []),
                ...($publicCreatedClosing ? ['public_created_rounding_closing_replayed' => true, 'opening_carrying_components' => $carrying,
                    'fiscal_pool_basis' => 'PROVED_CURRENT_ALLOCATION_AND_PRIOR_COVERAGE_WITH_UNCOVERED_CREATED_ROUNDING'] : []),
                ...($unoffsetPublicLoss ? ['historical_public_loss_current_deduction_created' => false, 'historical_public_loss_cross_title_offset_created' => false] : []),
                'scope' => 'HISTORICAL_RECOGNITION_REPLAY_ONLY_NO_CURRENT_TAX_TOTALIZATION', 'production_ready' => false, 'posting_performed' => false];
        } catch (InvalidArgumentException|\DateMalformedStringException) {
            return $this->stop('AMOUNT_OR_DATE_INVALID');
        } catch (Throwable) {
            return $this->stop('CAPTURED_KNOWLEDGE_OR_REPLAY_INVALID');
        }
    }

    /** Only a replayed first-acquisition source, zero prior/FV and an explicit conserved net allocation.
     * @param  array<string,mixed>  $scope
     * @param  array<string,mixed>  $source
     * @param  array<string,mixed>  $entry
     * @param  array<string,BigDecimal>  $current
     * @param  array<string,mixed>  $closing
     * @param  array<string,mixed>  $memory
     * @return array{available:array<string,BigDecimal>,carrying:array<string,string>}|null
     */
    private function publicExplicitClosing(array $scope, array $source, array $entry, array $current, array $closing, array $memory,
        string $sourcePeriod, string $priorPeriod, DateTimeImmutable $known): ?array
    {
        $binding = $entry['d2101_binding'];
        $analysis = $binding['sale_recognition_binding']['analysis'] ?? null;
        if ($sourcePeriod !== $priorPeriod || ($entry['public_offer_debt']['position']['first_acquisition'] ?? null) !== true
            || array_column($entry['public_offer_debt']['position']['operations'] ?? [], 'kind') !== ['BUY', 'SELL']
            || $this->decimal($memory['closing_quantity'])->isZero()
            || ! is_array($analysis) || ($analysis['status'] ?? null) !== 'RECONCILED' || ($analysis['public_offer_art195'] ?? null) !== true
            || ($analysis['replayed_fiscal_history'] ?? null) !== null || count($analysis['realizations'] ?? []) !== 1
            || CanonicalJson::encode($entry['public_offer_debt']['title'] ?? null) !== CanonicalJson::encode($scope['title'])
            || CanonicalJson::encode($entry['public_offer_debt']['position']) !== CanonicalJson::encode($closing['position'])
            || CanonicalJson::encode($binding['calculation'] ?? null) !== CanonicalJson::encode($memory)
            || ! $current['excluded_art195_return']->isZero() || ! $current['removed_pis_cofins']->isZero() || ! $current['deferred_fair_value']->isZero()) {
            return null;
        }
        $inventory = $analysis['recognition_inventory'];
        foreach ($inventory['opening'] as $amount) {
            if (! $this->signed($amount)->isZero()) {
                return null;
            }
        }
        if (($inventory['other_releases'] ?? []) !== [] || ! $this->signed($inventory['closing']['prior_produced_return'])->isZero()
            || ! $this->signed($inventory['closing']['deferred_fair_value_realized'])->isZero()) {
            return null;
        }
        $sale = $analysis['realizations'][0];
        foreach (['accounting_gain', 'new_public_produced_return', 'prior_return_not_reincluded'] as $field) {
            if (! $this->signed($sale[$field])->isZero()) {
                return null;
            }
        }
        foreach (['deferred_fair_value_realized', 'new_fair_value_change_at_sale'] as $field) {
            if (! $this->signed($sale['components'][$field])->isZero()) {
                return null;
            }
        }
        $realized = $this->signed($sale['book_value_released'])->minus($this->signed($sale['acquisition_cost_released']));
        $carrying = ['prior_produced_return' => (string) $this->signed($inventory['closing']['current_produced_return_already_in_curve'])->toScale(8),
            'rounding_bridge' => (string) $this->signed($inventory['closing']['rounding_bridge'])->toScale(8)];
        $retainedNet = $this->signed($carrying['prior_produced_return'])->plus($this->signed($carrying['rounding_bridge']));
        $total = $this->sum($current);
        if (! $realized->plus($retainedNet)->isEqualTo($total)
            || (! $realized->isZero() && ($realized->isNegative() !== $total->isNegative() || $realized->abs()->isGreaterThan($total->abs())))
            || (! $retainedNet->isZero() && ($retainedNet->isNegative() !== $total->isNegative() || $retainedNet->abs()->isGreaterThan($total->abs())))) {
            return null;
        }
        $available = $current;
        $key = $total->isNegative() ? 'unoffset_public_title_loss' : 'assessed_produced_return';
        $available[$key] = $available[$key]->minus($realized);
        $value = ['supplier_id' => $scope['supplier_id'], 'source_period' => $sourcePeriod, 'current_period' => $scope['period'],
            'title' => $scope['title'], 'snapshot_hash' => CanonicalJson::hash($source['snapshot']), 'decision_hash' => $source['snapshot']['result']['decision_hash'],
            'financial_entry_id' => $source['financial_entry_id'], 'method' => 'REPLAYED_PUBLIC_FIRST_ACQUISITION_NET_CLOSING_WITH_CARRYING_ROUNDING',
            'daily_memory_hash' => CanonicalJson::hash($memory), 'analysis_hash' => CanonicalJson::hash($analysis),
            'monthly_fiscal_components' => $this->amounts($current), 'realized_net_carrying_return' => (string) $realized->toScale(8),
            'closing_carrying_components' => $carrying, 'retained_fiscal_components' => $this->amounts($available),
            'fiscal_origin' => 'D2101', 'normative_homologation_claimed' => false];
        if (! (new FinancialPublicOfferDebtQualifier)->proof($source['net_closing_evidence'] ?? null, $value, $scope['supplier_id'],
            $sourcePeriod.'-01', (new DateTimeImmutable($sourcePeriod.'-01'))->format('Y-m-t'), $known)) {
            return null;
        }

        return ['available' => $available, 'carrying' => $carrying];
    }

    /** Use the prior actual realization inventory, then retain only its own unreleased daily yield.
     * @param  array<string,mixed>  $entry
     * @param  array<string,BigDecimal>  $current
     * @param  array<string,mixed>  $closing
     * @param  array<string,mixed>  $memory
     * @return array<string,BigDecimal>|null
     */
    private function publicRealizedClosing(array $entry, array $current, array $closing, array $memory, string $sourcePeriod, string $priorPeriod): ?array
    {
        $binding = $entry['d2101_binding'];
        $analysis = $binding['sale_recognition_binding']['analysis'] ?? null;
        if ($sourcePeriod !== $priorPeriod || ! is_array($analysis) || ($analysis['status'] ?? null) !== 'RECONCILED'
            || ($analysis['public_offer_art195'] ?? null) !== true
            || ($analysis['replayed_fiscal_history']['status'] ?? null) !== 'RESOLVED'
            || CanonicalJson::encode($entry['public_offer_debt']['position'] ?? null) !== CanonicalJson::encode($closing['position'])
            || CanonicalJson::encode($binding['calculation'] ?? null) !== CanonicalJson::encode($memory)) {
            return null;
        }
        $inventory = $analysis['recognition_inventory'];
        foreach (['opening', 'closing'] as $edge) {
            if (! $this->signed($inventory[$edge]['deferred_fair_value_realized'] ?? null)->isZero()
                || ! $this->signed($inventory[$edge]['rounding_bridge'] ?? null)->isZero()) {
                return null;
            }
        }
        $remaining = $this->signed($inventory['closing']['current_produced_return_already_in_curve'] ?? null);
        $variation = $this->signed($memory['monthly']['vVarMensal']);
        if ((! $remaining->isZero() && ($remaining->isNegative() !== $variation->isNegative() || $remaining->abs()->isGreaterThan($variation->abs())))) {
            return null;
        }
        foreach ($memory['daily_memory'] as $day) {
            $appropriation = $this->signed($day['appropriation']);
            if (! $appropriation->isZero() && ($variation->isZero() || $appropriation->isNegative() !== $variation->isNegative())) {
                return null;
            }
        }
        $retained = [];
        foreach ($current as $key => $amount) {
            $retained[$key] = $variation->isZero() ? BigDecimal::of('0.00000000')
                : $amount->multipliedBy($remaining)->dividedBy($variation, 8, RoundingMode::HalfEven);
        }
        if (! $this->sum($retained)->isEqualTo($remaining)) {
            return null;
        }
        $prior = $inventory['closing_fiscal_components'] ?? null;
        if (! is_array($prior) || count($prior) !== 4) {
            return null;
        }
        foreach (['assessed_produced_return', 'excluded_art195_return', 'removed_pis_cofins', 'unoffset_public_title_loss'] as $key) {
            $retained[$key] = $retained[$key]->plus($this->signed($prior[$key] ?? null));
        }

        return $retained;
    }

    /** Fully retained yield positions only; intermediate releases require a separate allocation policy.
     * @param  array<string,array{position:array<string,mixed>,result:array<string,mixed>}>  $positions
     * @param  array<string,mixed>  $closing
     */
    private function publicPositionChain(array &$positions, array $closing, string $priorPeriod): bool
    {
        ksort($positions);
        $previous = null;
        $previousPeriod = null;
        foreach ($positions as $period => $record) {
            $position = $record['position'];
            if ($previous === null) {
                if (($position['first_acquisition'] ?? null) !== true) {
                    return false;
                }
                foreach ($position['operations'] ?? [] as $operation) {
                    if (($operation['kind'] ?? null) !== 'BUY') {
                        return false;
                    }
                }
            } else {
                if ((new DateTimeImmutable($previousPeriod.'-01'))->modify('+1 month')->format('Y-m') !== $period
                    || ($position['first_acquisition'] ?? null) !== false || ($position['operations'] ?? null) !== []) {
                    return false;
                }
                foreach (['quantity' => 'closing_quantity', 'unit_cost' => 'closing_unit_cost', 'acquisition_cost' => 'closing_acquisition_cost'] as $inputKey => $resultKey) {
                    if (! $this->decimal($position['opening'][$inputKey] ?? null)->isEqualTo($this->decimal($previous[$resultKey]))) {
                        return false;
                    }
                }
                if (! $this->decimal($position['opening']['book_balance'] ?? null)->isEqualTo($this->decimal($previous['monthly']['vSaldoContFin']))) {
                    return false;
                }
            }
            $previous = $record['result'];
            $previousPeriod = $period;
        }

        return $previousPeriod === $priorPeriod
            && CanonicalJson::encode($positions[$priorPeriod]['position']) === CanonicalJson::encode($closing['position'])
            && CanonicalJson::encode($previous) === CanonicalJson::encode($closing['result']);
    }

    /** The public yield memory excludes fair value; replay the full closing memory and prove its daily difference.
     * @param  array<string,mixed>  $source
     * @param  array<string,mixed>  $entry
     * @param  array<int,array<string,mixed>>  $entries
     * @param  array<int,array<string,mixed>>  $sources
     * @param  array<string,mixed>  $closing
     * @param  array<string,mixed>  $memory
     * @param  array<string,mixed>  $policy
     * @param  array<string,mixed>  $scope
     * @param  array<string,mixed>  $rule
     */
    private function publicDeferral(array $source, array $entry, array $entries, array $sources, array $closing, array $memory,
        array $policy, array $scope, string $sourcePeriod, string $priorPeriod, DateTimeImmutable $knownAt, array $rule): bool
    {
        if (($rule['public_deferral_policy'] ?? null) !== 'REPLAYED_FIRST_ACQUISITION_YIELD_AND_SIGNED_FAIR_VALUE_DAILY_BRIDGE'
            || ($entry['kind'] ?? null) !== 'FAIR_VALUE_UNREALIZED' || ($entry['bucket'] ?? null) !== 'DEFERRED'
            || ! in_array($entry['fair_value_nature'] ?? null, ['C', 'D'], true)
            || ! is_string($entry['deferred_signed_amount'] ?? null) || $sourcePeriod !== $priorPeriod
            || ($source['retained_share'] ?? null) !== '1' || ($closing['position']['first_acquisition'] ?? null) !== true) {
            return false;
        }
        $bridge = $source['public_deferral_bridge'] ?? null;
        if (! is_array($bridge) || ! is_string($bridge['yield_entry_id'] ?? null)) {
            return false;
        }
        $yieldEntries = array_values(array_filter($entries, static fn (array $v): bool => ($v['id'] ?? null) === $bridge['yield_entry_id']));
        $yieldSources = array_values(array_filter($sources, static fn (array $v): bool => ($v['financial_entry_id'] ?? null) === $bridge['yield_entry_id']
            && ($v['retained_share'] ?? null) === '1' && CanonicalJson::encode($v['snapshot'] ?? null) === CanonicalJson::encode($source['snapshot'])));
        if (count($yieldEntries) !== 1 || count($yieldSources) !== 1 || ($yieldEntries[0]['kind'] ?? null) !== 'PUBLIC_OFFER_DEBT_YIELD') {
            return false;
        }
        $yield = $yieldEntries[0];
        $binding = $yield['d2101_binding'] ?? null;
        $position = $yield['public_offer_debt']['position'] ?? null;
        if (! is_array($binding) || ($binding['status'] ?? null) !== 'RESOLVED' || ($binding['gross_nature'] ?? null) !== 'C'
            || ! is_array($position) || ($position['first_acquisition'] ?? null) !== true) {
            return false;
        }
        // No intervening disposal, coupon or amortization is inferred by this first-acquisition policy.
        foreach ($closing['position']['operations'] as $operation) {
            if (($operation['kind'] ?? null) !== 'BUY') {
                return false;
            }
        }
        $ignore = ['contract_curve' => true, 'declared_monthly' => true, 'memory_evidence' => true];
        if (CanonicalJson::encode(array_diff_key($position, $ignore)) !== CanonicalJson::encode(array_diff_key($closing['position'], $ignore))) {
            return false;
        }
        $yieldMemory = (new FinancialDebtDailyMemoryCalculator)->calculate($position, $policy['series'], new DateTimeImmutable($closing['known_at']), $policy);
        if (($yieldMemory['status'] ?? null) !== 'RESOLVED' || count($yieldMemory['daily_memory']) !== count($memory['daily_memory'])
            || ! $this->decimal($yieldMemory['monthly']['vVarMensal'])->isEqualTo($this->decimal($binding['gross_amount']))) {
            return false;
        }
        $signed = $this->signed($entry['deferred_signed_amount']);
        $date = $entry['recognized_on'] ?? null;
        if (! is_string($date) || $date < $sourcePeriod.'-01' || $date > (new DateTimeImmutable($sourcePeriod.'-01'))->format('Y-m-t')) {
            return false;
        }
        foreach ($memory['daily_memory'] as $index => $day) {
            $yieldDay = $yieldMemory['daily_memory'][$index];
            $expected = $day['date'] === $date ? $signed : BigDecimal::of('0');
            if ($day['date'] !== $yieldDay['date']
                || ! $this->signed($day['appropriation'])->minus($this->signed($yieldDay['appropriation']))->isEqualTo($expected)
                || ! $this->decimal($day['book_balance'])->minus($this->decimal($yieldDay['book_balance']))->isEqualTo($day['date'] >= $date ? $signed : BigDecimal::of('0'))) {
                return false;
            }
        }
        $value = ['supplier_id' => $scope['supplier_id'], 'period' => $sourcePeriod, 'title' => $scope['title'],
            'financial_entry_id' => $entry['id'], 'document_id' => $entry['document_id'], 'yield_entry_id' => $yield['id'],
            'yield_binding_hash' => CanonicalJson::hash($binding), 'yield_memory_hash' => CanonicalJson::hash($yieldMemory),
            'closing_memory_hash' => CanonicalJson::hash($closing), 'recognized_on' => $date,
            'deferred_signed_amount' => $entry['deferred_signed_amount'], 'subaccount_evidence_id' => $entry['subaccount_evidence_id'],
            'produced_yield_excludes_fair_value' => true];

        return (new FinancialPublicOfferDebtQualifier)->proof($bridge['independent_evidence'] ?? null, $value,
            $scope['supplier_id'], $sourcePeriod.'-01', (new DateTimeImmutable($sourcePeriod.'-01'))->format('Y-m-t'), $knownAt);
    }

    private function decimal(mixed $value): BigDecimal
    {
        if (! is_string($value) || preg_match('/^(0|[1-9][0-9]{0,14})(\.[0-9]{1,8})?$/', $value) !== 1) {
            throw new InvalidArgumentException('Canonical nonnegative decimal required.');
        }

        return BigDecimal::of($value);
    }

    private function signed(mixed $value): BigDecimal
    {
        if (! is_string($value) || preg_match('/^-?(0|[1-9][0-9]{0,14})(\.[0-9]{1,8})?$/', $value) !== 1) {
            throw new InvalidArgumentException('Canonical signed decimal required.');
        }

        return BigDecimal::of($value);
    }

    /** @param array<string,BigDecimal> $values */
    private function sum(array $values): BigDecimal
    {
        return array_reduce($values, static fn (BigDecimal $a, BigDecimal $b): BigDecimal => $a->plus($b), BigDecimal::of('0'));
    }

    /** @param array<string,BigDecimal> $values
     * @return array<string,string>
     */
    private function amounts(array $values): array
    {
        return array_map(static fn (BigDecimal $v): string => (string) $v->toScale(8, RoundingMode::HalfEven), $values);
    }

    private function hash(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-f0-9]{64}$/', $value) === 1;
    }

    /** @return array<string,mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_TVM_HISTORY_'.$reason, 'production_ready' => false];
    }
}
