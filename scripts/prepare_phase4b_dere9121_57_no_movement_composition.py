"""Prepare separate native no-movement composition/settlement successors without altering55 runtime."""
from pathlib import Path
import json
ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-dere9121-57-no-movement-preparation-20261013'
APP = ROOT / 'app/Modules/Fiscal/ConsumptionReform'
composition = (APP / 'DereFinancialPublicOfferCurrentBaseCompositionQualifier.php').read_text(encoding='utf-8')
composition = composition.replace('final readonly class DereFinancialPublicOfferCurrentBaseCompositionQualifier',
    'final readonly class DereFinancialPublicOfferNoMovementCompositionPrototype57')
composition = composition.replace('new DerePublicOfferDebtReturnXmlReader', 'new DerePublicOfferDebtNoMovementReturnXmlReaderPrototype57')
anchor = "        $debtProjection = $projection['debt_yield_projection'] ?? null;"
assert composition.count(anchor) == 1
replacement = """        $debtProjection = $projection['debt_no_movement_projection'] ?? null;
        if (! is_array($debtProjection) || ($debtProjection['contract'] ?? null) !== \\App\\Modules\\Fiscal\\NFSe\\DereDebtNoMovementXmlProjector::CONTRACT) {
            return $this->stop('NATIVE_NO_MOVEMENT_PROJECTION_REQUIRED');
        }
        // The native projector has independently reconciled empty unmoved public accounts.
        // The explicit D9121 still must contain a zero total and bind these exact origin bytes.
        $debtProjection['total_vApur'] = '0.00';"""
composition = composition.replace(anchor, replacement)
composition = composition.replace('DERE-FINANCIAL-PUBLIC-OFFER-COMPOSITION-LOCAL-1.3.0-54', 'DERE-PUBLIC-NO-MOVEMENT-COMPOSITION-PROTOTYPE-57')
with (E / 'planned-no-movement-composition.php').open('x', encoding='utf-8') as stream:
    stream.write(composition)
planner = (APP / 'DereFinancialPublicOfferNegativeBaseSettlementPlanner.php').read_text(encoding='utf-8')
planner = planner.replace('final readonly class DereFinancialPublicOfferNegativeBaseSettlementPlanner',
    'final readonly class DereFinancialPublicOfferNoMovementSettlementPrototype57')
planner = planner.replace('new DereFinancialPublicOfferCurrentBaseCompositionQualifier',
    'new DereFinancialPublicOfferNoMovementCompositionPrototype57')
with (E / 'planned-no-movement-settlement.php').open('x', encoding='utf-8') as stream:
    stream.write(planner)
snapshot = json.loads((ROOT / 'storage/app/phase4b-d2101-no-movement-20261006-21/financial-snapshot.json').read_text(encoding='utf-8'))
bundle = json.loads((ROOT / 'resources/fiscal/bundles/consumption-phase4b-2026.10.13-28-draft54.json').read_text(encoding='utf-8'))
financial = snapshot['input']
financial.update(scenario_id='SYNTHETIC-D9121-57-NO-MOVEMENT', knowledge_at=bundle['knowledge_available_at'], rule_bundle_id=bundle['bundle_id'])
financial['catalog_versions'] = bundle['catalog_versions']
with (E / 'financial-input.json').open('x', encoding='utf-8') as stream:
    json.dump(financial, stream, ensure_ascii=False, indent=2)
print('PASS: separate no-movement composition, settlement prototype and own financial input prepared;55 source unchanged')
