"""Bind independent ISS immunity proof, conditional rate invariant and DPS1.01 codes."""
import copy
import hashlib
import json
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1] / "resources/fiscal"
VERSION = "2026.10.02-5"


def read(path):
    return json.loads((ROOT / path).read_text(encoding="utf-8"))


def digest(value):
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":")).encode()).hexdigest()


def write(path, value):
    target = ROOT / path
    if target.exists():
        if read(path) != value:
            raise RuntimeError(f"Refusing to overwrite {target}")
        return
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(json.dumps(value, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


previous = read("bundles/consumption-phase4b-2026.10.02-4.json")
target = f"bundles/consumption-phase4b-{VERSION}.json"
available = read(target)["knowledge_available_at"] if (ROOT / target).exists() else datetime.now(timezone.utc).replace(microsecond=0).isoformat()
cf_hash = hashlib.sha256((ROOT / "sources/official/cf1988-camara-observed-20261002.html").read_bytes()).hexdigest()
assert cf_hash == "654bbf1797b5624339021f53d5a8f612c917135f0c23a232c9bc39ce86828744"
source = {"id": "CF1988-CONSOLIDATED-CAMARA-OBSERVED-20261002",
    "url": "https://www2.camara.leg.br/legin/fed/consti/1988/constituicao-1988-5-outubro-1988-322142-normaatualizada-pl.html",
    "file": "cf1988-camara-observed-20261002.html", "sha256": cf_hash,
    "version": "consolidated-observed-2026-10-02", "published_on": "1988-10-05", "retrieved_on": "2026-10-02",
    "source_kind": "OFFICIAL_CONSOLIDATED_ACT", "superseded_by": None,
    "proven_effective_scope": "CF150 VI b/c and paragraph4; institutional ISS immunity and essential purposes, observed candidate availability."}
codes = read(previous["resource_paths"]["rtc_code_catalog"])
codes["version"] = VERSION
codes["local_xml_projection"]["contract_version"] = "DPS-LOCAL-2026.10.02-1"
codes["iss_institutional_immunity"] = {
    "strategy": "CF150_VI_B_C_PAR4_CTN9_14", "status": "ACTIVE", "valid_from": "2026-01-01", "published_at": available,
    "cf_source_id": source["id"], "cf_source_hash": cf_hash,
    "ctn_source_hash": codes["institutional_immunity"]["ctn_source_hash"],
    "supplier_rule": copy.deepcopy(codes["institutional_immunity"]), "review_status": "SANDBOX_REVIEW_REQUIRED",
    "document_layout": "DPS_1.01", "tribISSQN": "2", "religious_tpImunidade": "2", "nonprofit_tpImunidade": "3",
}
facts = read(previous["resource_paths"]["fact_definitions"])
facts.extend([{"key": "iss.evaluate_institutional_immunity", "type": "BOOLEAN", "material": True},
              {"key": "iss.institutional_immunity_proof", "type": "ARRAY", "material": True}])
questions = read(previous["resource_paths"]["questions"])
rank = max(row["rank"] for row in questions) + 1
questions.extend([
    {"fact_key": "iss.evaluate_institutional_immunity", "answer_type": "BOOLEAN", "rank": rank,
     "prompt": "Deseja qualificar a imunidade institucional de ISS, com prova específica das finalidades essenciais?"},
    {"fact_key": "iss.institutional_immunity_proof", "answer_type": "ARRAY", "rank": rank + 1,
     "prompt": "Informe uma prova de ISS vinculada ao fornecedor, documento e período, finalidades essenciais e referências da qualificação; a classificação RTC não comprova sozinha a imunidade de ISS."}])
invariants = read(previous["resource_paths"]["invariants"])
for row in invariants:
    if row["id"] == "ISS_RATE_REQUIRED":
        row.update(exempt_when_path="iss.incidence.value", exempt_when_value="IMMUNE")
controls = read(previous["production_controls"]["path"])
controls["version"] = VERSION
for control in controls["controls"]:
    if control["id"] in ["4B.4.BASE_AND_SPECIAL_REGIMES", "INTEGRATED.ISS_MUNICIPAL"]:
        control["source_ids"] = sorted(set(control["source_ids"] + [source["id"], "CTN-CONSOLIDATED-CAMARA-OBSERVED-20261002"]))
        control["gap"] += " Imunidade institucional de ISS com prova das finalidades essenciais, nominal zero e campos DPS1.01; demais perfis e atos municipais tributáveis continuam pendentes."
bundle = copy.deepcopy(previous)
bundle.update(bundle_id=f"CONSUMPTION-PHASE4B-{VERSION}", version=VERSION,
              predecessor_bundle_id=previous["bundle_id"], knowledge_available_at=available)
bundle["catalog_versions"]["rtc_codes"] = VERSION
bundle["official_sources"].append(source)
for key, path, value in [
    ("rtc_code_catalog", f"catalogs/rtc-codes-{VERSION}.json", codes),
    ("fact_definitions", f"facts/consumption-phase4b-{VERSION}.json", facts),
    ("questions", f"questions/consumption-integrated-{VERSION}.json", questions),
    ("invariants", f"invariants/consumption-phase4b-{VERSION}.json", invariants),
]:
    write(path, value)
    bundle["resource_paths"][key] = path
    bundle["content_hashes"][key] = digest(value)
write(f"production/phase4b-controls-{VERSION}.json", controls)
bundle["production_controls"] = {"path": f"production/phase4b-controls-{VERSION}.json", "sha256": digest(controls)}
write(target, bundle)
write(f"sources/official/phase4b-manifest-{VERSION}.json", {"version": VERSION, "retrieved_on": "2026-10-02",
    "status": "SANDBOX_EVIDENCE_REVIEW_REQUIRED", "artifacts": bundle["official_sources"]})
cases = {}
for name in ["nonprofit-education", "religious-entity"]:
    case = read(f"scenarios/consumption-phase4b-2026.10.02-4/institutional-immunity-{name}-2026.json")
    case.update(rule_bundle_id=bundle["bundle_id"], catalog_versions=bundle["catalog_versions"], knowledge_at=available)
    case["facts"]["iss.evaluate_institutional_immunity"] = True
    case["facts"]["iss.institutional_immunity_proof"] = [{
        "supplier_id": case["facts"]["rtc.supplier_id"], "document_id": case["facts"]["rtc.document_id"],
        "valid_from": "2026-01-01", "valid_to": "2026-12-31", "observed_on": "2026-09-30",
        "service_related_to_essential_purposes": True,
        "essential_scope_evidence_id": "FIXTURE.ISS.ESSENTIAL.SERVICE", "iss_status_evidence_id": "FIXTURE.ISS.STATUS"}]
    cases[name] = case
family = copy.deepcopy(cases["nonprofit-education"])
family["facts"].update({"customer.person_type": "PF", "rtc.buyer_relation": "B2C", "rtc.acquirer_id": "12345678902",
    "federal.evaluate_withholdings": True, "payer.person_type": "INDIVIDUAL", "payment.occurred": True,
    "payment.date": "2026-10-01", "payment.gross_amount": "1000.00", "invoice.issue_date": "2026-10-01",
    "invoice.gross_amount": "1000.00", "federal.service_category": "OTHER", "inss.service_group": "OUTSIDE_EXHAUSTIVE_LIST",
    "inss.service_category": "OTHER", "inss.execution_mode": "DIRECT_SERVICE"})
cases["family-payer-with-4a"] = family
for name in ["missing-iss-proof", "wrong-iss-owner", "wrong-iss-document", "expired-iss-proof", "unrelated-iss-service",
             "missing-essential-evidence", "unknown-essential-scope", "conflicting-rtc-treatment"]:
    case = copy.deepcopy(cases["nonprofit-education"])
    proof = case["facts"]["iss.institutional_immunity_proof"][0]
    if name == "missing-iss-proof":
        del case["facts"]["iss.institutional_immunity_proof"]
    elif name in ["wrong-iss-owner", "wrong-iss-document"]:
        proof["supplier_id" if name == "wrong-iss-owner" else "document_id"] = "wrong-party"
    elif name == "expired-iss-proof":
        proof["valid_to"] = "2026-09-30"
    elif name == "unrelated-iss-service":
        proof["service_related_to_essential_purposes"] = False
    elif name == "missing-essential-evidence":
        del proof["essential_scope_evidence_id"]
    elif name == "unknown-essential-scope":
        del proof["service_related_to_essential_purposes"]
    else:
        case["facts"]["rtc.tax_treatment"] = "STANDARD"
    cases[name] = case
for name, case in cases.items():
    case["scenario_id"] = f"integrated-institutional-immunity-{name}-2026"
    write(f"scenarios/consumption-integrated-{VERSION}/{case['scenario_id']}.json", case)
print(f"Created {bundle['bundle_id']}: {len(cases)} integrated ISS/RTC cases, 20 official sources")
