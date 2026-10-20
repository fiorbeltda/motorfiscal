"""Fork Phase 4B with import credit proof and document linkage."""

import hashlib
import json
from pathlib import Path


root = Path(__file__).resolve().parents[1] / "resources/fiscal"
previous = json.loads((root / "bundles/consumption-phase4b-2026.09.28-14.json").read_text(encoding="utf-8"))


def canonical_hash(value):
    return hashlib.sha256(
        json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":")).encode("utf-8")
    ).hexdigest()


def write_new(path, value):
    payload = json.dumps(value, ensure_ascii=False, indent=2) + "\n"
    if path.exists() and path.read_text(encoding="utf-8") != payload:
        raise RuntimeError(f"Refusing to overwrite changed artifact: {path}")
    path.write_text(payload, encoding="utf-8")


facts = json.loads((root / previous["resource_paths"]["fact_definitions"]).read_text(encoding="utf-8"))
facts.extend([
    {"key": "rtc.import_credit_fiscal_document_id", "type": "STRING", "material": True, "min_length": 1},
    {"key": "rtc.import_credit_extinction_evidence_id", "type": "STRING", "material": True, "min_length": 1},
])
facts_path = root / "facts/consumption-phase4b-2026.09.28-15.json"
write_new(facts_path, facts)

questions = [
    {"fact_key": "rtc.import_credit_fiscal_document_id",
     "prompt": "Qual documento fiscal eletrônico idôneo comprova IBS e CBS na importação?",
     "rank": 164, "answer_type": "STRING"},
    {"fact_key": "rtc.import_credit_extinction_evidence_id",
     "prompt": "Qual prova identifica a extinção dos débitos de IBS e CBS dessa importação?",
     "rank": 165, "answer_type": "STRING"},
]
questions_path = root / "questions/consumption-phase4b-import-credit-2026.09.28-15.json"
write_new(questions_path, questions)

transition = json.loads((root / previous["resource_paths"]["rtc_transition_catalog"]).read_text(encoding="utf-8"))
transition["version"] = "2026.09.28-15"
transition["import_credit_qualification"] = True
transition_path = root / "catalogs/rtc-transition-2026.09.28-15.json"
write_new(transition_path, transition)

controls = json.loads((root / previous["production_controls"]["path"]).read_text(encoding="utf-8"))
controls["version"] = "2026.09.28-14"
for row in controls["controls"]:
    if row["id"] == "4B.5.CREDITS":
        row["gap"] = (
            "Crédito de importação digital qualificado para adquirente regular com documento fiscal, extinção e vínculo; "
            "projeção definitiva da importação, preservação ou anulação em benefícios e apropriação por tratamento ainda incompletas."
        )
controls_path = root / "production/phase4b-controls-2026.09.28-14.json"
write_new(controls_path, controls)

bundle = previous.copy()
bundle["bundle_id"] = "CONSUMPTION-PHASE4B-2026.09.28-15"
bundle["version"] = "2026.09.28-15"
bundle["predecessor_bundle_id"] = previous["bundle_id"]
bundle["knowledge_available_at"] = "2026-09-28T18:00:00-03:00"
bundle["resource_paths"] = previous["resource_paths"].copy()
bundle["resource_paths"]["fact_definitions"] = "facts/" + facts_path.name
bundle["resource_paths"]["rtc_transition_catalog"] = "catalogs/" + transition_path.name
bundle["catalog_versions"] = previous["catalog_versions"].copy()
bundle["catalog_versions"]["rtc_transition"] = transition["version"]
bundle["question_overlays"] = previous["question_overlays"] + ["questions/" + questions_path.name]
combined_questions = json.loads((root / previous["resource_paths"]["questions"]).read_text(encoding="utf-8"))
for overlay in bundle["question_overlays"]:
    combined_questions += json.loads((root / overlay).read_text(encoding="utf-8"))
bundle["content_hashes"] = previous["content_hashes"].copy()
bundle["content_hashes"]["fact_definitions"] = canonical_hash(facts)
bundle["content_hashes"]["questions"] = canonical_hash(combined_questions)
bundle["content_hashes"]["rtc_transition_catalog"] = canonical_hash(transition)
bundle["production_controls"] = {"path": "production/" + controls_path.name, "sha256": canonical_hash(controls)}
write_new(root / "bundles/consumption-phase4b-2026.09.28-15.json", bundle)
print(bundle["bundle_id"])
