"""Extract statutory annexes II/III and version education/health/presence decisions."""

import copy
import hashlib
import json
from datetime import datetime, timezone
from html.parser import HTMLParser
from pathlib import Path

root = Path(__file__).resolve().parents[1] / "resources/fiscal"
version = "2026.10.01-5"


def read(path):
    return json.loads((root / path).read_text(encoding="utf-8"))


def digest(value):
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":")).encode()).hexdigest()


def write(path, value):
    target = root / path
    if target.exists():
        if json.loads(target.read_text(encoding="utf-8")) != value:
            raise RuntimeError(f"Refusing to overwrite {target}")
        return
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(json.dumps(value, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


class StatutoryTables(HTMLParser):
    def __init__(self):
        super().__init__()
        self.tables, self.rows, self.row, self.cell = [], None, None, None

    def handle_starttag(self, tag, attrs):
        if tag == "table":
            self.rows = []
        elif tag == "tr" and self.rows is not None:
            self.row = []
        elif tag in ("td", "th") and self.row is not None:
            self.cell = []

    def handle_data(self, data):
        if self.cell is not None:
            self.cell.append(data)

    def handle_endtag(self, tag):
        if tag in ("td", "th") and self.cell is not None:
            self.row.append(" ".join("".join(self.cell).split()))
            self.cell = None
        elif tag == "tr" and self.row is not None:
            self.rows.append(self.row)
            self.row = None
        elif tag == "table" and self.rows is not None:
            self.tables.append(self.rows)
            self.rows = None


previous = read("bundles/consumption-phase4b-2026.10.01-4.json")
target = f"bundles/consumption-phase4b-{version}.json"
available = read(target)["knowledge_available_at"] if (root / target).exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
source = root / "sources/official/lc214-camara-observed-20261001.html"
source_hash = hashlib.sha256(source.read_bytes()).hexdigest()
assert source_hash == "dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde"
tables = StatutoryTables()
tables.feed(source.read_text(encoding="utf-8"))
education, health = tables.tables[1], tables.tables[2]
assert education[0][-1] == health[0][-1] == "NBS" and len(education) == 10 and len(health) == 31
assert education[2][2] == "1.2201.20.00" and health[7][2] == "1.2301.21.00"
annexes = {}
for category, table, article, tax_class in [("EDUCATION", education, "129", "200028"), ("HEALTH", health, "130", "200029")]:
    items = {row[0]: {"description": row[1], "nbs": row[2]} for row in table[1:]}
    assert sorted(map(int, items)) == list(range(1, len(items) + 1))
    if category == "EDUCATION":
        assert items["9"]["nbs"] == ""
        items["9"]["special_education"] = True
    annexes[category] = {"rule_id": f"RTC.TREATMENT.LC214.ART{article}-1", "source_article": f"LC214 art. {article}; Anexo {'II' if category == 'EDUCATION' else 'III'}; art. 348, III, a", "cclasstrib": tax_class, "items": items}
codes = read(previous["resource_paths"]["rtc_code_catalog"])
codes["version"] = version
codes["annex_service_reduction"] = {"strategy": "LC214_ANNEX_II_III", "valid_from": "2026-01-01", "published_at": available,
    "source_hash": source_hash, "source_id": "LC214-CONSOLIDATED-CAMARA-OBSERVED-20261001", "annexes": annexes}
codes["presence_destination_qualification"] = {"published_at": available, "source_hash": source_hash, "source_article": "LC214 art. 11, III"}
# Interpretive national-service/NBS mappings, independently reviewed from statutory eligibility.
mappings = [
    ("EDUCATION", "080101", ["1.2201"], None), ("EDUCATION", "080102", ["1.2204"], None),
    ("EDUCATION", "080201", ["1.2202", "1.2203", "1.2205.13.00"], None),
    ("HEALTH", "040101", ["1.2301.21.00", "1.2301.22.00"], None),
    ("HEALTH", "040301", ["1.2301.1"], None), ("HEALTH", "040302", ["1.2301.93.00"], None),
    ("HEALTH", "040401", ["1.2301.99.00"], ["24"]), ("HEALTH", "040601", ["1.2301.91.00"], None),
    ("HEALTH", "040701", ["1.2301.99.00"], ["26"]), ("HEALTH", "040802", ["1.2301.92.00"], None),
    ("HEALTH", "040803", ["1.2301.99.00"], ["21"]), ("HEALTH", "041001", ["1.2301.99.00"], ["22"]),
    ("HEALTH", "041201", ["1.2301.23.00"], None), ("HEALTH", "041601", ["1.2301.98.00"], None),
    ("HEALTH", "041701", ["1.2302"], None), ("HEALTH", "041704", ["1.2302"], None),
    ("HEALTH", "041901", ["1.2301.95.00"], None), ("HEALTH", "042101", ["1.2301.96.00"], None),
    ("HEALTH", "250101", ["1.2603.00.00"], None), ("HEALTH", "250202", ["1.2603.00.00"], None),
]
services = read(previous["resource_paths"]["service_catalog"])["services"]
assert all(any(s["ctribnac_code"] == code for s in services) for _, code, _, _ in mappings)
for category, code, prefixes, allowed_items in mappings:
    for treatment, tax_class in [("STANDARD", "000001"), ("REDUCED_60", annexes[category]["cclasstrib"])]:
        for basis, modes in [("ACQUIRER_DOMICILE", ["REMOTE", "DIGITAL"]), ("PERSON_PRESENCE", ["PRESENTIAL"])]:
            rule = {"kind": "ENGINE_DECISION_RULE", "version": "1.0.0", "review_status": "SANDBOX_REVIEW_REQUIRED",
                "canonical_service_id": f"CTRIBNAC_{code}", "ctribnac_code": code, "operation_kind": "SERVICE", "market": "DOMESTIC",
                "delivery_modes": modes, "destination_basis": basis, "tax_treatment": treatment, "annex_service_category": category,
                "nbs_prefixes": prefixes, "cclasstrib": tax_class, "valid_from": "2026-01-01", "published_at": available,
                "source_article": "LC214 art. 11; arts. 129-130", "source_hash": source_hash}
            if allowed_items:
                rule["annex_items"] = allowed_items
            if basis == "PERSON_PRESENCE":
                rule["cindop_by_presence"] = {"SUPPLIER": "030101", "OTHER": "030102"}
            else:
                rule["cindop"] = "100301"
            codes["engine_decision_rules"][f"RTC.CLASSIFICATION.ANNEX.{code}.{basis}.{treatment}-1"] = rule
write(f"catalogs/rtc-codes-{version}.json", codes)
facts = read(previous["resource_paths"]["fact_definitions"])
new_facts = [
    {"key": "rtc.annex_service_category", "type": "ENUM", "material": True, "allowed_values": ["EDUCATION", "HEALTH"]},
    {"key": "rtc.annex_service_item", "type": "CODE", "material": True, "pattern": r"/^[0-9]{1,2}$/"},
    {"key": "rtc.service_nbs_code", "type": "CODE", "material": True, "pattern": r"/^1\.[0-9]{4}\.[0-9]{2}\.[0-9]{2}$/"},
    {"key": "rtc.health_medical_audit_glosa_amount", "type": "DECIMAL", "material": True},
    {"key": "rtc.special_education_target_group", "type": "ENUM", "material": True, "allowed_values": ["DISABILITY", "GLOBAL_DEVELOPMENT", "HIGH_ABILITIES"]},
]
for key in ["annex_service_description_confirmed", "annex_service_only_consideration", "health_glosa_paid", "service_on_person_or_enjoyed_in_person", "presence_at_supplier_establishment"]:
    new_facts.append({"key": f"rtc.{key}", "type": "BOOLEAN", "material": True})
for key in ["service_nbs_evidence_id", "annex_service_scope_evidence_id", "special_education_evidence_id", "health_medical_audit_glosa_evidence_id", "health_glosa_nonpayment_evidence_id", "service_presence_evidence_id"]:
    new_facts.append({"key": f"rtc.{key}", "type": "STRING", "material": True, "min_length": 1})
new_facts += [{"key": "rtc.performance_municipality_ibge", "type": "CODE", "material": True, "pattern": r"/^[0-9]{7}$/"}]
for key in ["performance_state", "supplier_establishment_state"]:
    new_facts.append({"key": f"rtc.{key}", "type": "CODE", "material": True, "pattern": r"/^[A-Z]{2}$/"})
facts += new_facts
write(f"facts/consumption-phase4b-{version}.json", facts)
questions = read(previous["resource_paths"]["questions"])
labels = ["Categoria do serviço no anexo legal", "Item do anexo legal", "NBS completo do serviço", "Valor glosado pela auditoria médica", "Público elegível da educação especial", "Descrição corresponde ao item do anexo?", "O valor corresponde somente à contraprestação do serviço identificado?", "A glosa médica foi paga?", "Serviço prestado fisicamente sobre pessoa ou integralmente fruído presencialmente?", "Prestação no estabelecimento do fornecedor?", "Prova da classificação NBS", "Prova do escopo da contraprestação", "Prova do público da educação especial", "Prova da auditoria médica", "Prova de não pagamento da glosa", "Prova da prestação presencial", "Município da prestação presencial", "UF da prestação presencial", "UF do estabelecimento fornecedor"]
assert len(new_facts) == len(labels)
for i, (fact, label) in enumerate(zip(new_facts, labels)):
    question = {"fact_key": fact["key"], "prompt": label, "rank": 230 + i, "answer_type": fact["type"]}
    if "allowed_values" in fact:
        question["options"] = fact["allowed_values"]
    questions.append(question)
write(f"questions/consumption-integrated-{version}.json", questions)
municipal = read(previous["resource_paths"]["municipal_parameters"])
municipal.update(version=version, catalog_id="MUNICIPAL_ANNEX_SERVICES_SANDBOX_FIXTURE")
for _, code, _, _ in mappings:
    municipal["parameters"].append({"municipality_ibge": "3550308", "ctribnac_code": code, "effective_from": "2026-01-01", "effective_to": None, "rate": "5.00", "responsibility": "PROVIDER"})
write(f"catalogs/municipal-annex-services-sandbox-{version}.json", municipal)
controls = read(previous["production_controls"]["path"])
controls["version"] = version
for row in controls["controls"]:
    if row["id"] == "4B.4.BASE_AND_SPECIAL_REGIMES":
        row["gap"] = "Redução de 30% profissional e de 60% em educação/saúde com anexos II/III e glosa médica não paga materializadas; demais benefícios/regimes e operações combinadas ainda pendentes."
    if row["id"] == "4B.2.INCIDENCE_AND_DESTINATION":
        row["gap"] += " Destino presencial sobre pessoa com prova de local e estabelecimento implementado; demais locais especiais pendentes."
    if row["id"] == "4B.7.DOCUMENT":
        row["gap"] += " Glosa médica altera base RTC com trace, mas exige grupo documental antes de XML; conflito oficial de série permanece."
write(f"production/phase4b-controls-{version}.json", controls)
bundle = copy.deepcopy(previous)
bundle.update(bundle_id=f"CONSUMPTION-PHASE4B-{version}", version=version, predecessor_bundle_id=previous["bundle_id"], knowledge_available_at=available)
bundle["catalog_versions"].update(rtc_codes=version, municipal_parameters=version)
for key, path, value in [("rtc_code_catalog", f"catalogs/rtc-codes-{version}.json", codes), ("fact_definitions", f"facts/consumption-phase4b-{version}.json", facts), ("questions", f"questions/consumption-integrated-{version}.json", questions), ("municipal_parameters", f"catalogs/municipal-annex-services-sandbox-{version}.json", municipal)]:
    bundle["resource_paths"][key], bundle["content_hashes"][key] = path, digest(value)
bundle["production_controls"] = {"path": f"production/phase4b-controls-{version}.json", "sha256": digest(controls)}
write(target, bundle)
write(f"sources/official/phase4b-manifest-{version}.json", {"version": version, "retrieved_on": "2026-10-01", "status": "SANDBOX_EVIDENCE_REVIEW_REQUIRED", "artifacts": bundle["official_sources"]})

base = read("scenarios/consumption-phase4b-2026.10.01-2/dps-standard-mandatory-2026.json")
common = {"rtc.operation_kind": "SERVICE", "rtc.tax_treatment": "REDUCED_60", "rtc.delivery_mode": "PRESENTIAL", "rtc.destination_basis": "PERSON_PRESENCE", "rtc.destination_municipality_ibge": "3550308",
    "rtc.recipient_id": "12345678901", "rtc.service_on_person_or_enjoyed_in_person": True, "rtc.service_presence_evidence_id": "FIXTURE.PERSONAL.PERFORMANCE", "rtc.performance_municipality_ibge": "3550308", "rtc.performance_state": "SP", "rtc.supplier_establishment_state": "SP", "rtc.presence_at_supplier_establishment": True,
    "rtc.annex_service_description_confirmed": True, "rtc.annex_service_only_consideration": True, "rtc.annex_service_scope_evidence_id": "FIXTURE.ANNEX.CONSIDERATION", "rtc.service_nbs_evidence_id": "FIXTURE.NBS.CLASSIFICATION", "rtc.treatment_evidence_id": "FIXTURE.ANNEX.ELIGIBILITY"}
edu = {"service.canonical_id": "CTRIBNAC_080101", "service.ctribnac_code": "080101", "rtc.annex_service_category": "EDUCATION", "rtc.annex_service_item": "2", "rtc.service_nbs_code": "1.2201.20.00", "rtc.treatment_rule_id": "RTC.TREATMENT.LC214.ART129-1"}
med = {"service.canonical_id": "CTRIBNAC_040101", "service.ctribnac_code": "040101", "rtc.annex_service_category": "HEALTH", "rtc.annex_service_item": "7", "rtc.service_nbs_code": "1.2301.21.00", "rtc.treatment_rule_id": "RTC.TREATMENT.LC214.ART130-1", "rtc.health_medical_audit_glosa_amount": "0.00"}
cases = [("education-presential-2026", edu, {}), ("education-remote-2026", edu, {"rtc.delivery_mode": "REMOTE", "rtc.destination_basis": "ACQUIRER_DOMICILE", "rtc.destination_municipality_ibge": "3509502"}),
    ("health-presential-2026", med, {}), ("health-unpaid-glosa-2026", med, {"rtc.health_medical_audit_glosa_amount": "100.00", "rtc.health_medical_audit_glosa_evidence_id": "FIXTURE.MEDICAL.AUDIT", "rtc.health_glosa_paid": False, "rtc.health_glosa_nonpayment_evidence_id": "FIXTURE.MEDICAL.NONPAYMENT"}),
    ("health-paid-glosa-2026", med, {"rtc.health_medical_audit_glosa_amount": "100.00", "rtc.health_medical_audit_glosa_evidence_id": "FIXTURE.MEDICAL.AUDIT", "rtc.health_glosa_paid": True}),
    ("education-nbs-conflict-2026", edu, {"rtc.service_nbs_code": "1.2301.21.00"}), ("education-mixed-consideration-2026", edu, {"rtc.annex_service_only_consideration": False}),
    ("health-presence-conflict-2026", med, {"rtc.performance_municipality_ibge": "3509502"}),
    ("education-special-2026", edu, {"rtc.annex_service_item": "9", "rtc.special_education_target_group": "DISABILITY", "rtc.special_education_evidence_id": "FIXTURE.SPECIAL.EDUCATION"})]
for year in range(2027, 2034):
    cases.append((f"education-rate-gap-{year}", edu, {"rtc.operation_date": f"{year}-04-10"}))
for name, profile, overrides in cases:
    scenario = copy.deepcopy(base)
    scenario.update(scenario_id=name, competence_date=overrides.get("rtc.operation_date", "2026-10-01"), knowledge_at=available, rule_bundle_id=bundle["bundle_id"], catalog_versions=bundle["catalog_versions"])
    scenario["facts"].update(common)
    scenario["facts"].update(profile)
    scenario["facts"].update(overrides)
    if scenario["facts"]["rtc.delivery_mode"] != "PRESENTIAL":
        for key in ["rtc.service_on_person_or_enjoyed_in_person", "rtc.service_presence_evidence_id", "rtc.performance_municipality_ibge", "rtc.performance_state", "rtc.presence_at_supplier_establishment"]:
            scenario["facts"].pop(key, None)
    for key in ["rtc.cindop", "rtc.cclasstrib", "rtc.classification_rule_id"]:
        scenario["facts"].pop(key, None)
    write(f"scenarios/consumption-phase4b-{version}/{name}.json", scenario)
    if name in ["education-presential-2026", "health-presential-2026", "health-unpaid-glosa-2026"]:
        integrated = copy.deepcopy(scenario)
        integrated["scenario_id"] = f"integrated-{name}"
        write(f"scenarios/consumption-integrated-{version}/{integrated['scenario_id']}.json", integrated)
identity = read("scenarios/dps-local-2026.10.01-2/standard-identity.json")
identity.update(service_municipality="3550308", recipient_name="Destinatário sintético da prestação presencial")
write(f"scenarios/dps-local-{version}/presential-identity.json", identity)
print(f"Created {bundle['bundle_id']}: 9 education and 30 health annex items, 20 service mappings, {len(cases)} RTC cases")
