"""Create immutable platform responsibility scenarios for article 22."""

import json
import subprocess
from pathlib import Path

root = Path(__file__).resolve().parents[1] / "resources/fiscal"
bundle = json.loads((root / "bundles/consumption-phase4b-2026.09.28-4.json").read_text(encoding="utf-8"))
qualified = {
    "rtc.platform_id": "platform-1",
    "rtc.platform_remote_intermediation": True,
    "rtc.platform_controlled_elements": ["PAYMENT"],
    "rtc.platform_activity_exclusion": "NONE",
    "rtc.platform_qualification_evidence_id": "platform-contract-1",
    "rtc.platform_reported_operation": True,
    "rtc.platform_report_evidence_id": "authority-report-1",
    "rtc.supplier_document_full_value": True,
    "rtc.supplier_document_evidence_id": "dps-issued-1",
    "rtc.platform_document_registered": True,
    "rtc.platform_document_evidence_id": "platform-register-1",
}
cases = {
    "platform-compliant-domestic-2026": ("regular-saas-2026", qualified, "RTC_2026_COLLECTION_DISPENSED"),
    "platform-missing-information-domestic-2026": (
        "regular-saas-2026",
        {**qualified, "rtc.platform_reported_operation": False, "rtc.platform_report_evidence_id": "authority-omission-1"},
        "RTC_2026_COLLECTION_DISPENSED",
    ),
    "platform-unregistered-supplier-notice-missing-2026": (
        "regular-saas-2026",
        {**qualified, "rtc.supplier_document_full_value": False, "rtc.supplier_is_taxpayer": True,
         "rtc.supplier_taxpayer_evidence_id": "registry-1", "rtc.supplier_registered": False},
        "RTC_PLATFORM_UNREGISTERED_SUPPLIER_NOTICE_MISSING",
    ),
    "platform-payment-only-excluded-2026": (
        "regular-saas-2026",
        {"rtc.platform_id": "payment-provider-1", "rtc.platform_remote_intermediation": True,
         "rtc.platform_controlled_elements": ["PAYMENT"],
         "rtc.platform_activity_exclusion": "AUTHORIZED_PAYMENT_ONLY",
         "rtc.platform_qualification_evidence_id": "central-bank-license-1"},
        "RTC_2026_COLLECTION_DISPENSED",
    ),
    "platform-search-fee-basis-unproven-2026": (
        "regular-saas-2026",
        {"rtc.platform_id": "search-provider-1", "rtc.platform_remote_intermediation": True,
         "rtc.platform_controlled_elements": ["TERMS"],
         "rtc.platform_activity_exclusion": "SEARCH_COMPARISON_ONLY",
         "rtc.platform_qualification_evidence_id": "search-terms-1"},
        "RTC_PLATFORM_SEARCH_FEE_BASIS_UNPROVEN",
    ),
    "platform-foreign-import-document-gap-2026": (
        "import-digital-2026-document-gap", qualified, "RTC_IMPORT_DOCUMENT_OBLIGATION_NOT_PROVEN",
    ),
    "platform-qualification-missing-2026": (
        "regular-saas-2026", {"rtc.platform_id": "platform-1"}, "RTC_PLATFORM_QUALIFICATION_UNPROVEN",
    ),
}


def write_new(path, value):
    path.parent.mkdir(exist_ok=True)
    payload = json.dumps(value, ensure_ascii=False, indent=2) + "\n"
    if path.exists() and path.read_text(encoding="utf-8") != payload:
        raise RuntimeError(f"Refusing to overwrite changed artifact: {path}")
    path.write_text(payload, encoding="utf-8")


for name, (source, overrides, expected_reason) in cases.items():
    scenario = json.loads((root / f"scenarios/consumption-phase4b/{source}.json").read_text(encoding="utf-8"))
    scenario["scenario_id"] = name
    scenario["knowledge_at"] = "2026-09-28T11:38:00-03:00"
    scenario["rule_bundle_id"] = bundle["bundle_id"]
    scenario["catalog_versions"] = bundle["catalog_versions"]
    scenario["facts"].update(overrides)
    relative = f"scenarios/consumption-phase4b-2026.09.28-4/{name}.json"
    write_new(root / relative, scenario)
    command = ["docker", "compose", "exec", "-T", "app", "php", "artisan", "fiscal:rtc:run",
               "resources/fiscal/" + relative,
               "--bundle-manifest=bundles/consumption-phase4b-2026.09.28-4.json"]
    completed = subprocess.run(command, cwd=root.parents[1], capture_output=True, text=True)
    result = json.loads(completed.stdout)
    if expected_reason not in result["reason_codes"]:
        raise RuntimeError(f"Unexpected result for {name}: {result['status']} {result['reason_codes']}")
    expected = {key: result[key] for key in ("status", "reason_codes", "missing_facts", "decision_hash")}
    write_new(root / f"expected/consumption-phase4b-2026.09.28-4/{name}.json", expected)
    print(name, result["status"], result["reason_codes"])
