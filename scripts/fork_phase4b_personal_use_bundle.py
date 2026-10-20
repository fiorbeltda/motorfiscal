"""Fork Phase 4B with article 5 personal-use recipient qualification."""

import hashlib
import json
from pathlib import Path


root = Path(__file__).resolve().parents[1] / "resources/fiscal"
source = json.loads((root / "bundles/consumption-phase4b-2026.09.28-10.json").read_text(encoding="utf-8"))
direct_roles = ["PARTNER", "SHAREHOLDER", "ADMINISTRATOR", "BOARD_OR_COMMITTEE_MEMBER", "EMPLOYEE"]
family_roles = ["SPOUSE_OR_PARTNER", "RELATIVE_FIRST_DEGREE", "RELATIVE_SECOND_DEGREE", "RELATIVE_THIRD_DEGREE"]


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
for row in facts:
    if row["key"] == "rtc.recipient_relationship":
        row["allowed_values"] = direct_roles + family_roles + ["OTHER"]
facts += [
    {"key": "rtc.recipient_anchor_role", "type": "ENUM", "material": True, "allowed_values": direct_roles},
    {"key": "rtc.recipient_anchor_evidence_id", "type": "STRING", "material": True, "min_length": 1},
]
facts_path = root / "facts/consumption-phase4b-2026.09.28-11.json"
write_new(facts_path, facts)

questions = [
    {"fact_key": "rtc.recipient_relationship", "prompt": "Qual é a relação comprovada do destinatário pessoa física com o fornecedor?",
     "rank": 157, "answer_type": "ENUM", "options": direct_roles + family_roles + ["OTHER"]},
    {"fact_key": "rtc.recipient_anchor_role", "prompt": "Qual é o papel da pessoa física à qual o familiar está vinculado?",
     "rank": 158, "answer_type": "ENUM", "options": direct_roles},
    {"fact_key": "rtc.recipient_anchor_evidence_id", "prompt": "Informe o documento que comprova o papel da pessoa vinculada ao familiar.",
     "rank": 159, "answer_type": "STRING"},
]
questions_path = root / "questions/consumption-phase4b-personal-use-2026.09.28-11.json"
write_new(questions_path, questions)

transition = json.loads((root / source["resource_paths"]["rtc_transition_catalog"]).read_text(encoding="utf-8"))
transition["version"] = "2026.09.28-11"
transition["personal_use_recipients"] = True
transition_path = root / "catalogs/rtc-transition-2026.09.28-11.json"
write_new(transition_path, transition)

controls = json.loads((root / source["production_controls"]["path"]).read_text(encoding="utf-8"))
controls["version"] = "2026.09.28-10"
for row in controls["controls"]:
    if row["id"] == "4B.2.INCIDENCE_AND_DESTINATION":
        row["gap"] = (
            "Fornecimentos de SaaS para uso pessoal a empregados, sócios, administradores, conselheiros e familiares "
            "das pessoas abrangidas qualificados com valor de mercado; documento específico, demais hipóteses "
            "não onerosas, locais especiais e domicílio inferido ainda pendentes."
        )
controls_path = root / "production/phase4b-controls-2026.09.28-10.json"
write_new(controls_path, controls)

bundle = source.copy()
bundle["bundle_id"] = "CONSUMPTION-PHASE4B-2026.09.28-11"
bundle["version"] = "2026.09.28-11"
bundle["predecessor_bundle_id"] = source["bundle_id"]
bundle["knowledge_available_at"] = "2026-09-28T15:15:00-03:00"
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
write_new(root / "bundles/consumption-phase4b-2026.09.28-11.json", bundle)
print(bundle["bundle_id"])
