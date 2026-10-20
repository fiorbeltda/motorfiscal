"""Materialize art. 127 eligibility and remote legal/accounting classification in a new cut."""

import copy
import hashlib
import json
from datetime import datetime, timezone
from pathlib import Path

root = Path(__file__).resolve().parents[1] / "resources/fiscal"
version = "2026.10.01-3"


def read(path):
    return json.loads((root / path).read_text(encoding="utf-8"))


def digest(value):
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":")).encode()).hexdigest()


def write(path, value):
    path = root / path
    payload = json.dumps(value, ensure_ascii=False, indent=2) + "\n"
    if path.exists() and path.read_text(encoding="utf-8") != payload:
        raise RuntimeError(f"Refusing to overwrite {path}")
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(payload, encoding="utf-8")


previous = read("bundles/consumption-phase4b-2026.10.01-2.json")
target = f"bundles/consumption-phase4b-{version}.json"
available = read(target)["knowledge_available_at"] if (root / target).exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
source_file = "lc214-camara-observed-20261001.html"
source_hash = hashlib.sha256((root / "sources/official" / source_file).read_bytes()).hexdigest()
assert source_hash == "dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde"
source = {
    "id": "LC214-CONSOLIDATED-CAMARA-OBSERVED-20261001",
    "url": "https://www2.camara.leg.br/legin/fed/leicom/2025/leicomplementar-214-16-janeiro-2025-796905-normaatualizada-pl.html",
    "file": source_file, "sha256": source_hash, "version": "consolidated-observed-2026-10-01",
    "published_on": "2025-01-16", "retrieved_on": "2026-10-01",
    "source_kind": "OFFICIAL_CONSOLIDATED_ACT", "superseded_by": None,
    "proven_effective_scope": "LC 214 art. 127 including physical education corporate exception; arts. 344 and 348 reduced transition rates. Observation availability is recorded in the bundle.",
}
professions = ["ADMINISTRATOR", "LAWYER", "ARCHITECT_URBANIST", "SOCIAL_WORKER", "LIBRARIAN", "BIOLOGIST", "ACCOUNTANT", "ECONOMIST", "HOME_ECONOMIST", "PHYSICAL_EDUCATION", "ENGINEER_AGRONOMIST", "STATISTICIAN", "VETERINARIAN_ZOOTECHNICIAN", "MUSEOLOGIST", "CHEMIST", "PUBLIC_RELATIONS", "INDUSTRIAL_TECHNICIAN", "AGRICULTURAL_TECHNICIAN"]
codes = read(previous["resource_paths"]["rtc_code_catalog"])
codes["version"] = version
codes["professional_reduction"] = {
    "strategy": "LC214_ART127", "rule_id": "RTC.TREATMENT.LC214.ART127-1", "source_id": source["id"],
    "source_hash": source_hash, "valid_from": "2026-01-01", "published_at": available,
    "professions": professions,
}
assert codes["cclasstrib"]["200052"]["cst"] == "200"
for profession, canonical, ctrib in [("LAWYER", "CTRIBNAC_171401", "171401"), ("ACCOUNTANT", "CTRIBNAC_171901", "171901")]:
    for delivery in ["REMOTE", "DIGITAL"]:
        for treatment, tax_class in [("STANDARD", "000001"), ("REDUCED_30", "200052")]:
            rule_id = f"RTC.CLASSIFICATION.{profession}.{delivery}.{treatment}-1"
            codes["engine_decision_rules"][rule_id] = {
                "kind": "ENGINE_DECISION_RULE", "version": "1.0.0", "review_status": "SANDBOX_REVIEW_REQUIRED",
                "canonical_service_id": canonical, "operation_kind": "SERVICE", "market": "DOMESTIC",
                "delivery_mode": delivery, "destination_basis": "ACQUIRER_DOMICILE", "tax_treatment": treatment,
                "professional_category": profession, "cindop": "100301", "cclasstrib": tax_class,
                "ctribnac_code": ctrib, "valid_from": "2026-01-01", "published_at": available,
                "source_article": "LC214 art. 11, X, a; art. 127", "source_hash": source_hash,
            }
write(f"catalogs/rtc-codes-{version}.json", codes)

facts = read(previous["resource_paths"]["fact_definitions"])
new_facts = [{"key": "rtc.professional_category", "type": "ENUM", "material": True, "allowed_values": professions}]
boolean_keys = ["professional_council_supervised", "professional_service_linked_habilitation", "professional_partners_qualified_and_supervised", "professional_has_legal_entity_partner", "professional_holds_legal_entity_participation", "professional_has_unrelated_activities", "professional_services_direct_by_partners"]
new_facts += [{"key": f"rtc.{key}", "type": "BOOLEAN", "material": True} for key in boolean_keys]
new_facts += [{"key": f"rtc.{key}", "type": "STRING", "material": True, "min_length": 1} for key in ["professional_council_evidence_id", "professional_structure_evidence_id"]]
facts += new_facts
write(f"facts/consumption-phase4b-{version}.json", facts)
questions = read(previous["resource_paths"]["questions"])
prompts = ["Qual a profissão fiscalizada pelo conselho?", "O prestador está sujeito à fiscalização do conselho profissional?", "O serviço da pessoa física está vinculado à sua habilitação?", "Todos os sócios têm habilitação relacionada ao objeto e fiscalização profissional?", "Há pessoa jurídica entre os sócios?", "A sociedade participa de outra pessoa jurídica?", "A sociedade exerce atividade alheia à habilitação dos sócios?", "Os serviços da atividade-fim são prestados diretamente pelos sócios?", "Qual a prova da fiscalização pelo conselho?", "Qual a prova da habilitação e estrutura societária aplicável?"]
for index, (fact, prompt) in enumerate(zip(new_facts, prompts)):
    question = {"fact_key": fact["key"], "prompt": prompt, "rank": 210 + index, "answer_type": fact["type"]}
    if "allowed_values" in fact:
        question["options"] = fact["allowed_values"]
    questions.append(question)
write(f"questions/consumption-integrated-{version}.json", questions)
controls = read(previous["production_controls"]["path"])
controls["version"] = version
for control in controls["controls"]:
    if control["id"] in ["4B.4.BASE_AND_SPECIAL_REGIMES", "4B.6.CLASSIFICATION"]:
        control["source_ids"].append(source["id"])
    if control["id"] == "4B.4.BASE_AND_SPECIAL_REGIMES":
        control["gap"] = "Redução profissional de 30% com requisitos PF/PJ, exceção de educação física e cálculo efetivo materializados. Redução de 60%, demais benefícios e regimes específicos ainda pendentes."
    if control["id"] == "4B.6.CLASSIFICATION":
        control["gap"] = "Regras de advocacia e contabilidade domésticas remotas/digitais adicionadas, padrão e redução profissional. Demais serviços, destinos e revisão jurídica continuam pendentes."
write(f"production/phase4b-controls-{version}.json", controls)
bundle = copy.deepcopy(previous)
bundle.update(bundle_id=f"CONSUMPTION-PHASE4B-{version}", version=version, predecessor_bundle_id=previous["bundle_id"], knowledge_available_at=available)
bundle["catalog_versions"]["rtc_codes"] = version
for key, path, value in [("rtc_code_catalog", f"catalogs/rtc-codes-{version}.json", codes), ("fact_definitions", f"facts/consumption-phase4b-{version}.json", facts), ("questions", f"questions/consumption-integrated-{version}.json", questions)]:
    bundle["resource_paths"][key] = path
    bundle["content_hashes"][key] = digest(value)
bundle["production_controls"] = {"path": f"production/phase4b-controls-{version}.json", "sha256": digest(controls)}
bundle["official_sources"].append(source)
write(target, bundle)
write(f"sources/official/phase4b-manifest-{version}.json", {"version": version, "retrieved_on": "2026-10-01", "status": "SANDBOX_EVIDENCE_REVIEW_REQUIRED", "artifacts": bundle["official_sources"]})

base = read("scenarios/consumption-phase4b-2026.10.01-2/dps-standard-mandatory-2026.json")
common = {
    "service.canonical_id": "CTRIBNAC_171401", "service.ctribnac_code": "171401", "rtc.operation_kind": "SERVICE",
    "rtc.delivery_mode": "REMOTE", "rtc.tax_treatment": "REDUCED_30", "rtc.professional_category": "LAWYER",
    "rtc.treatment_rule_id": "RTC.TREATMENT.LC214.ART127-1", "rtc.treatment_evidence_id": "FIXTURE.ART127.ELIGIBILITY",
    "rtc.professional_council_supervised": True, "rtc.professional_council_evidence_id": "FIXTURE.COUNCIL.REGISTER",
    "rtc.professional_structure_evidence_id": "FIXTURE.ART127.STRUCTURE",
    "rtc.professional_partners_qualified_and_supervised": True, "rtc.professional_has_legal_entity_partner": False,
    "rtc.professional_holds_legal_entity_participation": False, "rtc.professional_has_unrelated_activities": False,
    "rtc.professional_services_direct_by_partners": True,
}
cases = [("lawyer-reduced-2026", {}), ("lawyer-standard-2026", {"rtc.tax_treatment": "STANDARD"}),
         ("lawyer-legal-partner-conflict-2026", {"rtc.professional_has_legal_entity_partner": True}),
         ("lawyer-council-missing-2026", {"rtc.professional_council_evidence_id": None}),
         ("accountant-reduced-2026", {"service.canonical_id": "CTRIBNAC_171901", "service.ctribnac_code": "171901", "rtc.professional_category": "ACCOUNTANT"}),
         ("lawyer-individual-reduced-2026", {"provider.person_type": "INDIVIDUAL", "rtc.professional_service_linked_habilitation": True}),
         ("lawyer-reduced-rate-gap-2027", {"rtc.operation_date": "2027-04-10"})]
for name, overrides in cases:
    scenario = copy.deepcopy(base)
    date = overrides.get("rtc.operation_date", "2026-10-01")
    scenario.update(scenario_id=name, rule_bundle_id=bundle["bundle_id"], catalog_versions=bundle["catalog_versions"], knowledge_at=available, competence_date=date)
    scenario["facts"].update(common)
    scenario["facts"].update(overrides)
    for key in ["rtc.cindop", "rtc.cclasstrib", "rtc.classification_rule_id"]:
        scenario["facts"].pop(key, None)
    write(f"scenarios/consumption-phase4b-{version}/{name}.json", scenario)
print(f"Created {bundle['bundle_id']} with {len(cases)} scenarios")
