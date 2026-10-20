"""Version local XML projection, official schedule evidence and the observed CGSN supersession."""

import copy
import hashlib
import json
from datetime import datetime, timezone
from pathlib import Path

root = Path(__file__).resolve().parents[1] / "resources/fiscal"
version = "2026.10.01-2"
available_at = datetime.now(timezone.utc).replace(microsecond=0).isoformat()
previous = json.loads((root / "bundles/consumption-phase4b-2026.09.28-18.json").read_text(encoding="utf-8"))


def canonical_hash(value):
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":")).encode()).hexdigest()


def write_new(path, value):
    payload = json.dumps(value, ensure_ascii=False, indent=2) + "\n"
    if path.exists():
        if path.read_text(encoding="utf-8") != payload:
            raise RuntimeError(f"Refusing to rewrite versioned artifact {path}")
        return
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(payload, encoding="utf-8")


target_bundle = root / f"bundles/consumption-phase4b-{version}.json"
if target_bundle.exists():
    available_at = json.loads(target_bundle.read_text(encoding="utf-8"))["knowledge_available_at"]

new_sources = []
for source_id, filename, url, expected, scope, published in [
    ("NFSE-CONTRIBUTOR-API-MANUAL-OBSERVED-20261001", "nfse-manual-contribuintes-api-v1.2-observed-20261001.pdf",
     "https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/documentacao-atual/manual-contribuintes-emissor-publico-api-sistema-nacional-nfs-e-v1-2-out2025.pdf",
     "ac2f36e34ff565cc36d67c5d67415c33cc09f27b2dea006e8da9bcd2ddce5581",
     "API manual lists DPS ID components and independent municipal/business validation. No proof of first deployment date.", None),
    ("RFB-RTC-DOCUMENT-SCHEDULE-OBSERVED-20261001", "rfb-rtc-obrigacoes-observed-20261001.html",
     "https://www.gov.br/receitafederal/pt-br/acesso-a-informacao/acoes-e-programas/programas-e-atividades/reforma-tributaria-do-consumo/orientacoes-da-reforma-tributaria",
     "1ec6502ba7247c26d7034544b777f8154b9f11638dc79a65ebd3006e3d9026f1",
     "Official procedural guidance: RTC NFS-e general service schedule 2026-10-01 and specific subitems 2026-12-01; full joint act review pending.", "2026-09-18"),
    ("MF-CGSN194-NOTICE-OBSERVED-20261001", "mf-simples-prazos-2027-observed-20261001.html",
     "https://www.gov.br/fazenda/pt-br/assuntos/noticias/2026/setembro/simples-nacional-2027-tem-novos-prazos-para-microempresas-e-empresas-de-pequeno-porte/",
     "bd0b7a81f841fcdcdda3ed9dcd4401144a7750e6b8dc11da0d3141434e4d7c1c",
     "Supersession alert for 2027 first-semester option deadlines; CGSN 194 full act inaccessible. Announced dates are not active normative windows.", "2026-09-29"),
]:
    source_hash = hashlib.sha256((root / "sources/official" / filename).read_bytes()).hexdigest()
    if source_hash != expected:
        raise RuntimeError(f"Source hash mismatch: {source_id}")
    new_sources.append({"id": source_id, "file": filename, "url": url, "sha256": source_hash,
                        "version": "observed-2026-10-01", "published_on": published,
                        "retrieved_on": "2026-10-01", "proven_effective_scope": scope,
                        "source_kind": "OFFICIAL_TECHNICAL_MANUAL" if filename.endswith(".pdf") else "OFFICIAL_GUIDANCE",
                        "superseded_by": None})

codes = json.loads((root / previous["resource_paths"]["rtc_code_catalog"]).read_text(encoding="utf-8"))
codes["version"] = version
codes["local_xml_projection"] = {
    "contract_version": "DPS-LOCAL-2026.10.01-1", "scope": "LOCAL_NO_SIGNATURE_OR_TRANSMISSION",
    "schema_version": "1.01", "source_sha256": codes["dps_xsd"]["source_sha256"],
    "requires_deterministic_snapshot_replay": True, "source_business_validation": "NOT_PERFORMED",
    "known_source_conflict": "RTC_DPS_OFFICIAL_SERIE_PATTERN_CONFLICT",
}
for rule in codes["engine_decision_rules"].values():
    rule["ctribnac_code"] = "010101"
source = new_sources[1]
common = {"document": "NFSE", "status": "OFFICIAL_GUIDANCE", "valid_from": "2026-01-05", "valid_to": "2026-12-31",
          "regimes": ["REGULAR", "FOREIGN"], "published_at": "2026-09-21T09:09:00-03:00",
          "source_id": source["id"], "source_hash": source["sha256"]}
specific = ["0103", "0105", "0109", "1601"]
codes["document_obligations"] = [
    {**common, "id": "RTC.NFSE.GENERAL.SCHEDULE-2026-1", "mandatory_from": "2026-10-01", "excluded_subitems": specific},
    {**common, "id": "RTC.NFSE.SPECIFIC.SCHEDULE-2026-1", "mandatory_from": "2026-12-01", "subitems": specific},
]
codes_path = root / f"catalogs/rtc-codes-{version}.json"
write_new(codes_path, codes)

transition = json.loads((root / previous["resource_paths"]["rtc_transition_catalog"]).read_text(encoding="utf-8"))
transition["version"] = version
transition["simples_option_supersession_notices"] = [{
    "valid_from": "2027-01-01", "valid_to": "2027-06-30", "observed_at": available_at,
    "source_id": new_sources[2]["id"], "source_hash": new_sources[2]["sha256"],
    "full_act_verified": False,
    "act_url": "https://www.in.gov.br/web/dou/-/resolucao-cgsn-n-194-de-25-de-setembro-de-2026-735097948",
    "announced_filing_from": "2026-09-01", "announced_filing_to": "2026-10-30",
    "announced_cancellation_from": "2026-11-03", "announced_cancellation_to": "2026-12-20",
    "note": "Withdraw stale positive first-semester qualification after observation. Do not activate replacement without full normative act.",
}]
transition_path = root / f"catalogs/rtc-transition-{version}.json"
write_new(transition_path, transition)

controls = json.loads((root / previous["production_controls"]["path"]).read_text(encoding="utf-8"))
controls["version"] = version
for control in controls["controls"]:
    if control["id"] == "4B.7.DOCUMENT":
        control["source_ids"] += [new_sources[0]["id"], new_sources[1]["id"], "NFSE-PRODUCTION-XSD-20260209"]
        control["gap"] = "DPS XML local de serviço doméstico tributável com tomador/destinatário e retenções 4A, replay e hashes implementados. Faceta serie do XSD oficial conflita com série operacional; perfis especiais, prova municipal e implantação NT009 ainda pendentes. Cronograma RTC explícito e independente de implantação, com revisão do ato completo pendente."
    if control["id"] == "4B.3.SIMPLES_MEI":
        control["source_ids"].append(new_sources[2]["id"])
        control["gap"] = "Aviso oficial de supersessão dos prazos do primeiro semestre de 2027 detectado: janela histórica deixa de qualificar após observação; íntegra da CGSN 194 pendente. Apuração interna, início de atividade, MEI e períodos seguintes ainda pendentes."
controls_path = root / f"production/phase4b-controls-{version}.json"
write_new(controls_path, controls)

bundle = copy.deepcopy(previous)
bundle.update(bundle_id=f"CONSUMPTION-PHASE4B-{version}", version=version,
              predecessor_bundle_id="CONSUMPTION-PHASE4B-2026.10.01-1", knowledge_available_at=available_at)
bundle["catalog_versions"].update(rtc_codes=version, rtc_transition=version)
bundle["resource_paths"].update(rtc_code_catalog=f"catalogs/{codes_path.name}", rtc_transition_catalog=f"catalogs/{transition_path.name}")
bundle["content_hashes"].update(rtc_code_catalog=canonical_hash(codes), rtc_transition_catalog=canonical_hash(transition))
# Earlier incremental overlays append duplicate fact keys and cannot load the integrated
# engine. Materialize the explicit latest question for each fact in this new version only.
questions = {}
for path in [previous["resource_paths"]["questions"], *previous.get("question_overlays", [])]:
    for question in json.loads((root / path).read_text(encoding="utf-8")):
        questions[question["fact_key"]] = question
question_list = sorted(questions.values(), key=lambda row: (row["rank"], row["fact_key"]))
question_path = root / f"questions/consumption-integrated-{version}.json"
write_new(question_path, question_list)
bundle["resource_paths"]["questions"] = f"questions/{question_path.name}"
bundle["question_overlays"] = []
bundle["content_hashes"]["questions"] = canonical_hash(question_list)
bundle["official_sources"] += new_sources
bundle["production_controls"] = {"path": f"production/{controls_path.name}", "sha256": canonical_hash(controls)}
write_new(target_bundle, bundle)
write_new(root / f"sources/official/phase4b-manifest-{version}.json",
          {"version": version, "retrieved_on": "2026-10-01", "status": "SANDBOX_EVIDENCE_REVIEW_REQUIRED", "artifacts": bundle["official_sources"]})

base_scenario = json.loads((root / "scenarios/consumption-phase4b-2026.09.28-18/regular-saas-production-xsd-2026.json").read_text(encoding="utf-8"))
for name, date in [("dps-standard-pre-mandatory-2026", "2026-09-30"), ("dps-standard-mandatory-2026", "2026-10-01")]:
    scenario = copy.deepcopy(base_scenario)
    scenario.update(scenario_id=name, competence_date=date, knowledge_at=available_at,
                    rule_bundle_id=bundle["bundle_id"], catalog_versions=bundle["catalog_versions"])
    scenario["facts"]["rtc.operation_date"] = date
    write_new(root / f"scenarios/consumption-phase4b-{version}/{name}.json", scenario)
identity = {"environment": "2", "issued_at": "2026-10-01T13:30:00-03:00", "application_version": "FISCAL-SANDBOX-1",
            "series": "1", "number": "1", "competence_date": "2026-10-01", "issuer_municipality": "3550308",
            "supplier_cnpj": "11111111000191", "simples_option": "1", "special_regime": "0", "total_tax_indicator": "0",
            "service_municipality": "3509502", "acquirer_name": "Tomador sintético de homologação local",
            "identity_evidence_id": "FIXTURE.DPS.IDENTITY", "service_location_evidence_id": "FIXTURE.SERVICE.LOCATION",
            "regime_evidence_id": "FIXTURE.SUPPLIER.REGIME", "total_tax_evidence_id": "FIXTURE.TOTAL.TAX"}
write_new(root / f"scenarios/dps-local-{version}/standard-identity.json", identity)
current_scenario = json.loads((root / f"scenarios/consumption-phase4b-{version}/dps-standard-mandatory-2026.json").read_text(encoding="utf-8"))
federal = copy.deepcopy(current_scenario)
federal["scenario_id"] = "dps-integrated-iss-4a-rtc-2026"
federal["facts"].update({
    "federal.evaluate_withholdings": True, "payment.occurred": True, "payment.date": "2026-10-01",
    "payment.gross_amount": "1000.00", "payer.person_type": "PRIVATE_LEGAL_ENTITY", "payer.tax_regime": "LUCRO_REAL",
    "payer.head_office_tax_id": "22222222000191", "federal.service_category": "PROFESSIONAL_SERVICE",
    "federal.pis_status": "APPLICABLE", "federal.cofins_status": "APPLICABLE", "federal.csll_status": "APPLICABLE",
    "federal.income_nature_code": "17001", "inss.service_group": "OUTSIDE_EXHAUSTIVE_LIST",
    "inss.service_category": "SOFTWARE_DEVELOPMENT", "inss.execution_mode": "CONTRACT_FOR_RESULT",
    "invoice.issue_date": "2026-10-01", "invoice.gross_amount": "1000.00",
})
write_new(root / f"scenarios/consumption-integrated-{version}/{federal['scenario_id']}.json", federal)
federal_identity = {**identity, "pis_cofins_cst": "01", "pis_cofins_evidence_id": "FIXTURE.OWN.PIS.COFINS.CLASSIFICATION"}
write_new(root / f"scenarios/dps-local-{version}/federal-identity.json", federal_identity)
simples = json.loads((root / "scenarios/consumption-phase4b/simples-regular-h1-2027-confirmed.json").read_text(encoding="utf-8"))
simples.update(scenario_id="simples-first-semester-supersession-2027", rule_bundle_id=bundle["bundle_id"], catalog_versions=bundle["catalog_versions"])
write_new(root / f"scenarios/consumption-phase4b-{version}/{simples['scenario_id']}.json", simples)
print(json.dumps({"bundle_id": bundle["bundle_id"], "knowledge_available_at": available_at, "official_sources": len(bundle["official_sources"])}, ensure_ascii=False))
