"""Build immutable corpus cases for collection eligibility and deployment proof."""

import json
import subprocess
from pathlib import Path

root = Path(__file__).resolve().parents[1] / "resources/fiscal"
base = json.loads((root / "scenarios/consumption-phase4b/regular-saas-2026.json").read_text(encoding="utf-8"))
bundle = json.loads((root / "bundles/consumption-phase4b-2026.09.28-3.json").read_text(encoding="utf-8"))
cases = {
    "acquirer-eligible-unpaid-2026": {
        "rtc.collection_method": "ACQUIRER",
        "rtc.collection_legal_basis": "LC214 art. 36",
        "rtc.acquirer_regime": "REGULAR",
        "rtc.payment_instrument_split_capable": False,
        "rtc.payment_instrument_evidence_id": "payment-contract-1",
        "rtc.payment_transaction_id": "payment-1",
        "rtc.accessory_obligations_fulfilled": False,
    },
    "acquirer-split-capable-conflict-2026": {
        "rtc.collection_method": "ACQUIRER",
        "rtc.collection_legal_basis": "LC214 art. 36",
        "rtc.acquirer_regime": "REGULAR",
        "rtc.payment_instrument_split_capable": True,
        "rtc.payment_instrument_evidence_id": "payment-contract-2",
        "rtc.payment_transaction_id": "payment-2",
    },
    "split-official-deployment-unproven-2026": {
        "rtc.collection_method": "SPLIT_PAYMENT",
        "rtc.collection_legal_basis": "LC214 arts. 31-35",
        "rtc.payment_instrument_split_capable": True,
        "rtc.payment_instrument_evidence_id": "card-arrangement-1",
        "rtc.payment_transaction_id": "payment-3",
        "rtc.split_deployment_evidence_id": "unverified-claim",
    },
}
expected_reasons = {
    "acquirer-eligible-unpaid-2026": "RTC_STANDARD_TAXATION",
    "acquirer-split-capable-conflict-2026": "RTC_ACQUIRER_COLLECTION_CONDITIONS_CONFLICT",
    "split-official-deployment-unproven-2026": "RTC_SPLIT_OFFICIAL_DEPLOYMENT_NOT_PROVEN",
}


def write_new(path, value):
    path.parent.mkdir(exist_ok=True)
    payload = json.dumps(value, ensure_ascii=False, indent=2) + "\n"
    if path.exists() and path.read_text(encoding="utf-8") != payload:
        raise RuntimeError(f"Refusing to overwrite changed artifact: {path}")
    path.write_text(payload, encoding="utf-8")


for name, overrides in cases.items():
    scenario = json.loads(json.dumps(base))
    scenario["scenario_id"] = name
    scenario["knowledge_at"] = "2026-09-28T11:33:00-03:00"
    scenario["rule_bundle_id"] = bundle["bundle_id"]
    scenario["catalog_versions"] = bundle["catalog_versions"]
    scenario["facts"].update(overrides)
    relative = f"scenarios/consumption-phase4b-2026.09.28-3/{name}.json"
    write_new(root / relative, scenario)

    command = ["docker", "compose", "exec", "-T", "app", "php", "artisan", "fiscal:rtc:run",
               "resources/fiscal/" + relative,
               "--bundle-manifest=bundles/consumption-phase4b-2026.09.28-3.json"]
    completed = subprocess.run(command, cwd=root.parents[1], capture_output=True, text=True)
    result = json.loads(completed.stdout)
    reason = expected_reasons[name]
    if reason not in result["reason_codes"]:
        raise RuntimeError(f"Unexpected result for {name}: {result['status']} {result['reason_codes']}")
    expected = {key: result[key] for key in ("status", "reason_codes", "missing_facts", "decision_hash")}
    write_new(root / f"expected/consumption-phase4b-2026.09.28-3/{name}.json", expected)
    print(name, result["status"], result["reason_codes"])
