"""Fork Phase 4B with proof of self-provided personal-use services."""

import hashlib
import json
from pathlib import Path


root = Path(__file__).resolve().parents[1] / "resources/fiscal"
previous = json.loads((root / "bundles/consumption-phase4b-2026.09.28-12.json").read_text(encoding="utf-8"))


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
    {"key": "rtc.personal_use_supply_origin", "type": "ENUM", "material": True,
     "allowed_values": ["SELF_PRODUCED_OR_PROVIDED", "ACQUIRED_FROM_THIRD_PARTY"]},
    {"key": "rtc.personal_use_origin_evidence_id", "type": "STRING", "material": True, "min_length": 1},
])
facts_path = root / "facts/consumption-phase4b-2026.09.28-13.json"
write_new(facts_path, facts)

questions = [
    {"fact_key": "rtc.personal_use_supply_origin", "prompt": "O bem ou serviço para uso pessoal foi produzido ou prestado pelo próprio contribuinte, ou adquirido de terceiro?", "rank": 160,
     "answer_type": "ENUM", "options": ["SELF_PRODUCED_OR_PROVIDED", "ACQUIRED_FROM_THIRD_PARTY"]},
    {"fact_key": "rtc.personal_use_origin_evidence_id", "prompt": "Informe o documento que comprova a origem do bem ou serviço fornecido para uso pessoal.", "rank": 161,
     "answer_type": "STRING"},
]
questions_path = root / "questions/consumption-phase4b-personal-origin-2026.09.28-13.json"
write_new(questions_path, questions)

transition = json.loads((root / previous["resource_paths"]["rtc_transition_catalog"]).read_text(encoding="utf-8"))
transition["version"] = "2026.09.28-13"
transition["personal_use_supply_origin"] = True
transition_path = root / "catalogs/rtc-transition-2026.09.28-13.json"
write_new(transition_path, transition)

controls = json.loads((root / previous["production_controls"]["path"]).read_text(encoding="utf-8"))
controls["version"] = "2026.09.28-12"
for row in controls["controls"]:
    if row["id"] == "4B.2.INCIDENCE_AND_DESTINATION":
        row["gap"] = (
            "Fornecimento para uso pessoal sob art. 5º, I, b, exige prova da prestação ou produção pelo próprio contribuinte; "
            "a hipótese de bem ou serviço adquirido com crédito, documento específico, demais operações não onerosas, "
            "locais especiais e domicílio inferido ainda pendem."
        )
controls_path = root / "production/phase4b-controls-2026.09.28-12.json"
write_new(controls_path, controls)

bundle = previous.copy()
bundle["bundle_id"] = "CONSUMPTION-PHASE4B-2026.09.28-13"
bundle["version"] = "2026.09.28-13"
bundle["predecessor_bundle_id"] = previous["bundle_id"]
bundle["knowledge_available_at"] = "2026-09-28T17:00:00-03:00"
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
write_new(root / "bundles/consumption-phase4b-2026.09.28-13.json", bundle)
print(bundle["bundle_id"])
