"""Fork Phase 4B with the LC 214 art. 6 donation qualification."""

import hashlib
import json
from pathlib import Path


root = Path(__file__).resolve().parents[1] / "resources/fiscal"
previous = json.loads((root / "bundles/consumption-phase4b-2026.09.28-15.json").read_text(encoding="utf-8"))


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
    {"key": "rtc.donation_no_counterbenefit", "type": "BOOLEAN", "material": True},
    {"key": "rtc.donation_counterbenefit_evidence_id", "type": "STRING", "material": True, "min_length": 1},
    {"key": "rtc.donation_input_credit_eligible", "type": "BOOLEAN", "material": True},
    {"key": "rtc.donation_input_credit_evidence_id", "type": "STRING", "material": True, "min_length": 1},
    {"key": "rtc.donation_credit_option", "type": "ENUM", "material": True,
     "allowed_values": ["ANNUL_CREDITS", "TAX_MARKET_VALUE"]},
    {"key": "rtc.donation_credit_annulment_evidence_id", "type": "STRING", "material": True, "min_length": 1},
])
facts_path = root / "facts/consumption-phase4b-2026.09.28-16.json"
write_new(facts_path, facts)

questions = [
    ("rtc.donation_no_counterbenefit", "A doação não proporciona contraprestação em benefício do doador?", "BOOLEAN"),
    ("rtc.donation_counterbenefit_evidence_id", "Qual documento comprova a ausência de contraprestação?", "STRING"),
    ("rtc.donation_input_credit_eligible", "O bem ou serviço doado permitiu apropriação de crédito IBS/CBS, inclusive na produção?", "BOOLEAN"),
    ("rtc.donation_input_credit_evidence_id", "Qual documento comprova a elegibilidade ou ausência de crédito na aquisição ou produção?", "STRING"),
    ("rtc.donation_credit_option", "Se houve crédito elegível, a opção é tributar pelo valor de mercado ou anular créditos?", "ENUM"),
    ("rtc.donation_credit_annulment_evidence_id", "Qual prova identifica a anulação dos créditos da doação?", "STRING"),
]
questions_doc = [{"fact_key": key, "prompt": prompt, "rank": 166 + index, "answer_type": kind}
                 for index, (key, prompt, kind) in enumerate(questions)]
questions_path = root / "questions/consumption-phase4b-donation-2026.09.28-16.json"
write_new(questions_path, questions_doc)

transition = json.loads((root / previous["resource_paths"]["rtc_transition_catalog"]).read_text(encoding="utf-8"))
transition["version"] = "2026.09.28-16"
transition["donation_qualification"] = True
transition_path = root / "catalogs/rtc-transition-2026.09.28-16.json"
write_new(transition_path, transition)

controls = json.loads((root / previous["production_controls"]["path"]).read_text(encoding="utf-8"))
controls["version"] = "2026.09.28-15"
for row in controls["controls"]:
    if row["id"] == "4B.2.INCIDENCE_AND_DESTINATION":
        row["gap"] = (
            "Doação sem contraprestação qualificada por elegibilidade de crédito e opção de anulação ou valor de mercado; "
            "cálculo, documento, demais operações não onerosas, locais especiais e domicílio inferido ainda pendentes."
        )
controls_path = root / "production/phase4b-controls-2026.09.28-15.json"
write_new(controls_path, controls)

bundle = previous.copy()
bundle["bundle_id"] = "CONSUMPTION-PHASE4B-2026.09.28-16"
bundle["version"] = "2026.09.28-16"
bundle["predecessor_bundle_id"] = previous["bundle_id"]
bundle["knowledge_available_at"] = "2026-09-28T18:30:00-03:00"
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
write_new(root / "bundles/consumption-phase4b-2026.09.28-16.json", bundle)
print(bundle["bundle_id"])
