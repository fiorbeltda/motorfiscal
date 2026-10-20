"""Fork Phase 4B with acquired personal-use supplies under art. 5, I, a."""

import hashlib
import json
from pathlib import Path


root = Path(__file__).resolve().parents[1] / "resources/fiscal"
previous = json.loads((root / "bundles/consumption-phase4b-2026.09.28-13.json").read_text(encoding="utf-8"))


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
for fact in facts:
    if fact["key"] in ("rtc.recipient_relationship", "rtc.recipient_anchor_role"):
        fact["allowed_values"].append("SELF")
facts.extend([
    {"key": "rtc.personal_use_input_credit_eligible", "type": "BOOLEAN", "material": True},
    {"key": "rtc.personal_use_input_credit_evidence_id", "type": "STRING", "material": True, "min_length": 1},
])
facts_path = root / "facts/consumption-phase4b-2026.09.28-14.json"
write_new(facts_path, facts)

old_questions_path = "questions/consumption-phase4b-personal-use-2026.09.28-11.json"
questions = json.loads((root / old_questions_path).read_text(encoding="utf-8"))
for question in questions:
    if question["fact_key"] in ("rtc.recipient_relationship", "rtc.recipient_anchor_role"):
        question["options"].append("SELF")
questions.extend([
    {"fact_key": "rtc.personal_use_input_credit_eligible",
     "prompt": "A aquisição do bem ou serviço permitiu crédito de IBS e CBS, conforme documento e regra aplicável?",
     "rank": 162, "answer_type": "BOOLEAN"},
    {"fact_key": "rtc.personal_use_input_credit_evidence_id",
     "prompt": "Informe o documento de aquisição e a evidência da elegibilidade do crédito de IBS e CBS.",
     "rank": 163, "answer_type": "STRING"},
])
questions_path = root / "questions/consumption-phase4b-personal-use-2026.09.28-14.json"
write_new(questions_path, questions)

transition = json.loads((root / previous["resource_paths"]["rtc_transition_catalog"]).read_text(encoding="utf-8"))
transition["version"] = "2026.09.28-14"
transition["acquired_personal_use"] = True
transition_path = root / "catalogs/rtc-transition-2026.09.28-14.json"
write_new(transition_path, transition)

controls = json.loads((root / previous["production_controls"]["path"]).read_text(encoding="utf-8"))
controls["version"] = "2026.09.28-13"
for row in controls["controls"]:
    if row["id"] == "4B.2.INCIDENCE_AND_DESTINATION":
        row["gap"] = (
            "Uso pessoal adquirido sob art. 5º, I, a, qualificado por origem, vínculo e elegibilidade de crédito; "
            "uso pessoal de produção própria sob b exige origem comprovada. Documento específico, demais operações "
            "não onerosas, locais especiais e domicílio inferido ainda pendentes."
        )
controls_path = root / "production/phase4b-controls-2026.09.28-13.json"
write_new(controls_path, controls)

bundle = previous.copy()
bundle["bundle_id"] = "CONSUMPTION-PHASE4B-2026.09.28-14"
bundle["version"] = "2026.09.28-14"
bundle["predecessor_bundle_id"] = previous["bundle_id"]
bundle["knowledge_available_at"] = "2026-09-28T17:30:00-03:00"
bundle["resource_paths"] = previous["resource_paths"].copy()
bundle["resource_paths"]["fact_definitions"] = "facts/" + facts_path.name
bundle["resource_paths"]["rtc_transition_catalog"] = "catalogs/" + transition_path.name
bundle["catalog_versions"] = previous["catalog_versions"].copy()
bundle["catalog_versions"]["rtc_transition"] = transition["version"]
bundle["question_overlays"] = ["questions/" + questions_path.name if item == old_questions_path else item
                               for item in previous["question_overlays"]]
combined_questions = json.loads((root / previous["resource_paths"]["questions"]).read_text(encoding="utf-8"))
for overlay in bundle["question_overlays"]:
    combined_questions += json.loads((root / overlay).read_text(encoding="utf-8"))
bundle["content_hashes"] = previous["content_hashes"].copy()
bundle["content_hashes"]["fact_definitions"] = canonical_hash(facts)
bundle["content_hashes"]["questions"] = canonical_hash(combined_questions)
bundle["content_hashes"]["rtc_transition_catalog"] = canonical_hash(transition)
bundle["production_controls"] = {"path": "production/" + controls_path.name, "sha256": canonical_hash(controls)}
write_new(root / "bundles/consumption-phase4b-2026.09.28-14.json", bundle)
print(bundle["bundle_id"])
