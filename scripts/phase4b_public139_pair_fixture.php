<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\NFSe\DocumentFederalPayment139;

require_once __DIR__.'/phase4b_retention137_fixture.php';

/** Public legal parameters and payment captures here are synthetic Sandbox fixtures. */
function public139PairFixture(string $profile = 'federal-direct'): array
{
    [$payer, $entity, $convention, $net] = match ($profile) {
        'federal-direct' => ['FEDERAL_PUBLIC_ADMINISTRATION', 'DIRECT', false, '580.73'],
        'federal-mixed-company' => ['FEDERAL_PUBLIC_ADMINISTRATION', 'MIXED_CAPITAL_COMPANY', false, '580.73'],
        'municipal-no-convention' => ['MUNICIPAL_PUBLIC_ADMINISTRATION', 'DIRECT', false, '610.55'],
        'municipal-with-convention' => ['MUNICIPAL_PUBLIC_ADMINISTRATION', 'DIRECT', true, '580.73'],
        'state-public-company' => ['STATE_PUBLIC_ADMINISTRATION', 'PUBLIC_COMPANY', false, '611.51'],
        default => throw new InvalidArgumentException('Unknown synthetic public139 profile.'),
    };
    $known = (new DateTimeImmutable(DocumentFederalPayment139::AVAILABLE_AT))->modify('+5 seconds')->format('Y-m-d\TH:i:s.uP');
    $case = retention137Fixture($known, DocumentFederalPayment139::AVAILABLE_AT);
    foreach ($case['declarations']['payment_proofs'] as &$proof) {
        $proof['contract'] = DocumentFederalPayment139::CONTRACT;
        $proof['evidence_id'] = str_replace('PAYMENT137', 'PAYMENT139', $proof['evidence_id']);
        $proof['value']['source_policy_hash'] = CanonicalJson::hash(DocumentFederalPayment139::policy());
        if ($proof['value']['document']['role'] === 'RTC_DOMESTIC') {
            $proof['value']['payment']['payer_person_type'] = $payer;
            $proof['value']['payment']['net_brl'] = $net;
            $proof['value']['profile']['payer_tax_regime'] = 'PUBLIC_ENTITY';
            $proof['value']['federal_facts'] += ['payer.public_entity_type' => $entity,
                'payer.siafi_electronic_collection' => false, 'public.annex_category' => 'GENERAL_SERVICES',
                'public.glosa_without_new_invoice' => false, 'public.has_late_additions' => false,
                'public.beneficiary_treatment' => 'STANDARD'];
            if ($payer === 'MUNICIPAL_PUBLIC_ADMINISTRATION') {
                $proof['value']['federal_facts'] += ['public.article33_convention_exists' => $convention,
                    'public.local_ir_collection_rule_id' => 'SYNTHETIC-LOCAL-IR-RULE-139',
                    'public.local_ir_collection_code' => 'SYNTHETIC-LOCAL-IR-139',
                    'public.local_ir_due_rule' => 'SYNTHETIC-LOCAL-DUE-RULE-139',
                    'public.local_ir_valid_from' => '2026-01-01', 'public.local_ir_valid_to' => '2026-12-31'];
                if ($convention) {
                    $proof['value']['federal_facts'] += ['public.article33_convention_source_id' => 'SYNTHETIC-CONVENTION-139',
                        'public.article33_convention_valid_from' => '2026-01-01', 'public.article33_convention_valid_to' => '2026-12-31'];
                }
            }
            $proof['value']['payment']['bank'] = payment137Bank($proof['value']);
        }
        $proof = own135Capture($proof);
    }
    unset($proof);

    return $case;
}

/** Isolated hypothetical withholding exceptions; these do not exempt the supplier's own debits. */
function public139PairSiafiFixture(?bool $siafi): array
{
    $case = public139PairFixture();
    foreach ($case['declarations']['payment_proofs'] as &$proof) {
        if ($proof['value']['document']['role'] !== 'RTC_DOMESTIC') {
            continue;
        }
        $proof['value']['federal_facts'] = array_replace($proof['value']['federal_facts'], [
            'public.beneficiary_treatment' => 'EXEMPT', 'federal.irrf_status' => 'EXEMPT',
            'federal.irrf_exception_legal_basis' => 'SYNTHETIC-HYPOTHETICAL-IR-EXCEPTION',
            'public.exception_disclosed_on_invoice' => true,
            'federal.cofins_status' => 'EXEMPT', 'federal.cofins_exception_legal_basis' => 'SYNTHETIC-HYPOTHETICAL-COFINS-EXCEPTION',
            'federal.csll_status' => 'EXEMPT', 'federal.csll_exception_legal_basis' => 'SYNTHETIC-HYPOTHETICAL-CSLL-EXCEPTION',
        ]);
        if ($siafi === null) {
            unset($proof['value']['federal_facts']['payer.siafi_electronic_collection']);
        } else {
            $proof['value']['federal_facts']['payer.siafi_electronic_collection'] = $siafi;
        }
        $proof['value']['payment']['net_brl'] = $siafi === true ? '637.16' : '641.33';
        $proof['value']['payment']['bank'] = payment137Bank($proof['value']);
        $proof = own135Capture($proof);
    }
    unset($proof);

    return $case;
}
