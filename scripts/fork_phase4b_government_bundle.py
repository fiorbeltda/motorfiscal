"""Fork Phase 4B with dated government-purchase qualification."""

import hashlib
import json
from pathlib import Path


root = Path(__file__).resolve().parents[1] / "resources/fiscal"
source = json.loads((root / "bundles/consumption-phase4b-2026.09.28-11.json").read_text(encoding="utf-8"))


def canonical_hash(value):
    return hashlib.sha256(
        json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":")).encode("utf-8")
    ).hexdigest()


def write_new(path, value):
    payload = json.dumps(value, ensure_ascii=False, indent=2) + "\n"
    if path.exists() and path.read_text(encoding="utf-8") != payload:
        raise RuntimeError(f"Refusing to overwrite changed artifact: {path}")
    path.write_text(payload, encoding="utf-8")


facts = json.loads((root / source["resource_paths"]["fact_definitions"]).read_text(encoding="utf-8"))
facts += [
    {"key": "rtc.government_entity_evidence_id", "type": "STRING", "material": True, "min_length": 1},
    {"key": "rtc.government_public_law_person", "type": "BOOLEAN", "material": True},
    {"key": "rtc.government_in_person", "type": "BOOLEAN", "material": True},
    {"key": "rtc.government_licitation_waived", "type": "BOOLEAN", "material": True},
    {"key": "rtc.government_national_uniform_rate", "type": "BOOLEAN", "material": True},
    {"key": "rtc.government_procurement_evidence_id", "type": "STRING", "material": True, "min_length": 1},
    {"key": "rtc.government_uniform_rate_rule_id", "type": "STRING", "material": True, "min_length": 1},
    {"key": "rtc.government_federation", "type": "ENUM", "material": True,
     "allowed_values": ["UNION", "STATE", "MUNICIPALITY", "DISTRICT"]},
    {"key": "rtc.government_payment_date", "type": "DATE", "material": True},
    {"key": "rtc.government_payment_evidence_id", "type": "STRING", "material": True, "min_length": 1},
]
facts_path = root / "facts/consumption-phase4b-2026.09.28-12.json"
write_new(facts_path, facts)

questions = [
    {"fact_key": "rtc.government_entity_evidence_id", "prompt": "Informe o ato que comprova a natureza do ente adquirente.", "rank": 180, "answer_type": "STRING"},
    {"fact_key": "rtc.government_public_law_person", "prompt": "O adquirente é pessoa jurídica de direito público interno?", "rank": 181, "answer_type": "BOOLEAN"},
    {"fact_key": "rtc.government_in_person", "prompt": "A aquisição foi realizada presencialmente?", "rank": 182, "answer_type": "BOOLEAN"},
    {"fact_key": "rtc.government_licitation_waived", "prompt": "A aquisição foi dispensada de licitação conforme ato documentado?", "rank": 183, "answer_type": "BOOLEAN"},
    {"fact_key": "rtc.government_national_uniform_rate", "prompt": "A operação está sujeita a alíquota nacionalmente uniforme enumerada no art. 472?", "rank": 184, "answer_type": "BOOLEAN"},
    {"fact_key": "rtc.government_procurement_evidence_id", "prompt": "Informe o ato que prova a compra presencial dispensada de licitação.", "rank": 184, "answer_type": "STRING"},
    {"fact_key": "rtc.government_uniform_rate_rule_id", "prompt": "Informe o dispositivo de alíquota nacionalmente uniforme aplicável.", "rank": 184, "answer_type": "STRING"},
    {"fact_key": "rtc.government_federation", "prompt": "Qual ente federativo contratou a aquisição?", "rank": 185, "answer_type": "ENUM", "options": ["UNION", "STATE", "MUNICIPALITY", "DISTRICT"]},
    {"fact_key": "rtc.government_payment_date", "prompt": "Em que data o ente público realizou o pagamento?", "rank": 186, "answer_type": "DATE"},
    {"fact_key": "rtc.government_payment_evidence_id", "prompt": "Informe o comprovante do pagamento público.", "rank": 187, "answer_type": "STRING"},
]
questions_path = root / "questions/consumption-phase4b-government-2026.09.28-12.json"
write_new(questions_path, questions)

transition = json.loads((root / source["resource_paths"]["rtc_transition_catalog"]).read_text(encoding="utf-8"))
transition["version"] = "2026.09.28-12"
transition["government_purchase_qualification"] = True
transition_path = root / "catalogs/rtc-transition-2026.09.28-12.json"
write_new(transition_path, transition)

controls = json.loads((root / source["production_controls"]["path"]).read_text(encoding="utf-8"))
controls["version"] = "2026.09.28-11"
for row in controls["controls"]:
    if row["id"] == "4B.3.SUBJECTS_AND_COLLECTION":
        row["gap"] = (
            "Compras públicas qualificadas pelos arts. 372, 472 e 473, com exceções e parcelas temporais; "
            "redutor não publicado, cálculo de destinação, consórcios, recolhimento e cadastro ainda pendentes."
        )
controls_path = root / "production/phase4b-controls-2026.09.28-11.json"
write_new(controls_path, controls)

bundle = source.copy()
bundle["bundle_id"] = "CONSUMPTION-PHASE4B-2026.09.28-12"
bundle["version"] = "2026.09.28-12"
bundle["predecessor_bundle_id"] = source["bundle_id"]
bundle["knowledge_available_at"] = "2026-09-28T16:30:00-03:00"
bundle["resource_paths"] = source["resource_paths"].copy()
bundle["resource_paths"]["fact_definitions"] = "facts/" + facts_path.name
bundle["resource_paths"]["rtc_transition_catalog"] = "catalogs/" + transition_path.name
bundle["catalog_versions"] = source["catalog_versions"].copy()
bundle["catalog_versions"]["rtc_transition"] = transition["version"]
bundle["question_overlays"] = source["question_overlays"] + ["questions/" + questions_path.name]
combined_questions = json.loads((root / source["resource_paths"]["questions"]).read_text(encoding="utf-8"))
for overlay in bundle["question_overlays"]:
    combined_questions += json.loads((root / overlay).read_text(encoding="utf-8"))
bundle["content_hashes"] = source["content_hashes"].copy()
bundle["content_hashes"]["fact_definitions"] = canonical_hash(facts)
bundle["content_hashes"]["questions"] = canonical_hash(combined_questions)
bundle["content_hashes"]["rtc_transition_catalog"] = canonical_hash(transition)
bundle["production_controls"] = {"path": "production/" + controls_path.name, "sha256": canonical_hash(controls)}
write_new(root / "bundles/consumption-phase4b-2026.09.28-12.json", bundle)
print(bundle["bundle_id"])
