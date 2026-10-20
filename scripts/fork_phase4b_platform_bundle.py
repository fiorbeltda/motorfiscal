"""Fork the Phase 4B candidate with article 22 platform responsibility rules."""

import hashlib
import json
from pathlib import Path

root = Path(__file__).resolve().parents[1] / "resources/fiscal"
source = json.loads((root / "bundles/consumption-phase4b-2026.09.28-3.json").read_text(encoding="utf-8"))


def canonical_hash(value):
    return hashlib.sha256(
        json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":")).encode("utf-8")
    ).hexdigest()


def write_new(path, value):
    payload = json.dumps(value, ensure_ascii=False, indent=2) + "\n"
    if path.exists() and path.read_text(encoding="utf-8") != payload:
        raise RuntimeError(f"Refusing to overwrite changed artifact: {path}")
    path.write_text(payload, encoding="utf-8")


new_facts = [
    {"key": "rtc.platform_remote_intermediation", "type": "BOOLEAN", "material": True},
    {"key": "rtc.platform_controlled_elements", "type": "ARRAY", "material": True},
    {"key": "rtc.platform_activity_exclusion", "type": "ENUM", "material": True,
     "allowed_values": ["NONE", "INTERNET_ACCESS_ONLY", "AUTHORIZED_PAYMENT_ONLY", "ADVERTISING_ONLY", "SEARCH_COMPARISON_ONLY"]},
    {"key": "rtc.platform_qualification_evidence_id", "type": "STRING", "material": True, "min_length": 1},
    {"key": "rtc.platform_reported_operation", "type": "BOOLEAN", "material": True},
    {"key": "rtc.platform_report_evidence_id", "type": "STRING", "material": True, "min_length": 1},
    {"key": "rtc.supplier_document_full_value", "type": "BOOLEAN", "material": True},
    {"key": "rtc.supplier_document_evidence_id", "type": "STRING", "material": True, "min_length": 1},
    {"key": "rtc.platform_document_registered", "type": "BOOLEAN", "material": True},
    {"key": "rtc.platform_document_evidence_id", "type": "STRING", "material": True, "min_length": 1},
    {"key": "rtc.supplier_is_taxpayer", "type": "BOOLEAN", "material": True},
    {"key": "rtc.supplier_taxpayer_evidence_id", "type": "STRING", "material": True, "min_length": 1},
    {"key": "rtc.supplier_registered", "type": "BOOLEAN", "material": True},
    {"key": "rtc.supplier_taxpayer_authority_notice_id", "type": "STRING", "material": True, "min_length": 1},
]
facts = json.loads((root / source["resource_paths"]["fact_definitions"]).read_text(encoding="utf-8"))
facts.extend(new_facts)
facts_path = root / "facts/consumption-phase4b-2026.09.28-4.json"
write_new(facts_path, facts)

questions = []
for rank, fact in enumerate(new_facts, 350):
    row = {"fact_key": fact["key"], "prompt": "Informe ou comprove " + fact["key"] + " para a responsabilidade da plataforma IBS/CBS.",
           "rank": rank, "answer_type": fact["type"]}
    if fact["type"] == "ENUM":
        row["options"] = fact["allowed_values"]
    questions.append(row)
questions_path = root / "questions/consumption-phase4b-platform-2026.09.28-4.json"
write_new(questions_path, questions)

transition = json.loads((root / source["resource_paths"]["rtc_transition_catalog"]).read_text(encoding="utf-8"))
transition["version"] = "2026.09.28-4"
transition["platform_responsibility_qualification"] = True
transition_path = root / "catalogs/rtc-transition-2026.09.28-4.json"
write_new(transition_path, transition)

controls = json.loads((root / source["production_controls"]["path"]).read_text(encoding="utf-8"))
controls["version"] = "2026.09.28-3"
for row in controls["controls"]:
    if row["id"] == "4B.2.INCIDENCE_AND_DESTINATION":
        row["gap"] = "Operações não onerosas além do empregado, locais especiais e domicílio inferido ainda sem decisão completa; definição legal de plataforma qualificada no novo corte."
    if row["id"] == "4B.3.SUBJECTS_AND_COLLECTION":
        row["gap"] = "Responsabilidade de plataforma pelos gatilhos do art. 22 e elegibilidade de adquirente/split qualificadas; recolhimento, cadastro, aquisição pública e exceções operacionais ainda pendentes."
controls_path = root / "production/phase4b-controls-2026.09.28-3.json"
write_new(controls_path, controls)

bundle = source.copy()
bundle["bundle_id"] = "CONSUMPTION-PHASE4B-2026.09.28-4"
bundle["version"] = "2026.09.28-4"
bundle["predecessor_bundle_id"] = source["bundle_id"]
bundle["knowledge_available_at"] = "2026-09-28T11:36:00-03:00"
bundle["resource_paths"] = source["resource_paths"].copy()
bundle["resource_paths"]["fact_definitions"] = "facts/" + facts_path.name
bundle["resource_paths"]["rtc_transition_catalog"] = "catalogs/" + transition_path.name
bundle["catalog_versions"] = source["catalog_versions"].copy()
bundle["catalog_versions"]["rtc_transition"] = transition["version"]
bundle["question_overlays"] = source["question_overlays"] + ["questions/" + questions_path.name]
bundle["content_hashes"] = source["content_hashes"].copy()
bundle["content_hashes"]["fact_definitions"] = canonical_hash(facts)
bundle["content_hashes"]["rtc_transition_catalog"] = canonical_hash(transition)
all_questions = []
for path in [bundle["resource_paths"]["questions"], *bundle["question_overlays"]]:
    all_questions.extend(json.loads((root / path).read_text(encoding="utf-8")))
bundle["content_hashes"]["questions"] = canonical_hash(all_questions)
bundle["production_controls"] = {"path": "production/" + controls_path.name, "sha256": canonical_hash(controls)}
bundle_path = root / "bundles/consumption-phase4b-2026.09.28-4.json"
write_new(bundle_path, bundle)
print(bundle["bundle_id"], bundle["production_controls"]["sha256"])
