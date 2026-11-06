"""B37 (4B.4.10): regime específico de bens imóveis (LC 214/2025, arts. 251-265): locação/cessão/arrendamento and
administração/intermediação, the real-estate operations documented by NFS-e.

Forks bundle B36 without touching it or any earlier bundle.
- Adds RealEstateRegimeB37::POLICY under `real_estate_regime_b37`, with `published_at` = knowledge availability.
- Adds engine decision rules (SANDBOX_REVIEW_REQUIRED), destination at the property (LC214 art. 11, II):
  lease (cTribNac 990101 "Serviços sem a incidência de ISSQN e ICMS", NBS 1.1002, cIndOp 020101, cClassTrib 200027, 70%);
  administration (cTribNac 171201, NBS 1.1001.1) and intermediation (cTribNac 100501, NBS 1.1001.2), cIndOp 020301,
  cClassTrib 200046 (50%).
- Extends `rtc.specific_regime` with REAL_ESTATE and adds the regime facts.
Every legal element is quoted from lc214-planalto-observed-20261103.htm (SHA256 0a0d190b...); nothing is estimated.
Re-running is idempotent; it refuses to overwrite a file with different content.
"""

import copy
import hashlib
import json
import os
import subprocess
import sys
from datetime import datetime, timezone
from pathlib import Path

REPO = Path(__file__).resolve().parents[1]
ROOT = REPO / "resources/fiscal"
VERSION = "2026.11.06-b37-draft1"
PREVIOUS = "bundles/consumption-phase4b-2026.11.06-b36-draft1.json"
PLANALTO = "sources/official/lc214-planalto-observed-20261103.htm"
PLANALTO_HASH = "0a0d190bc39689f50b199646b51dd84d01f6d7392c179287b46909c3d4f4673c"
PHP = os.environ.get("PHP85", "E:/tools/php-8.5.12/php.exe")

FACTS = [
    ("rtc.real_estate_activity", "ENUM", ["LEASE", "ADMINISTRATION", "INTERMEDIATION"],
     "Operação com bem imóvel (LC 214, art. 252, III e IV)"),
    ("rtc.real_estate_lease_form", "ENUM", ["LEASE", "ONEROUS_ASSIGNMENT", "ARRENDAMENTO", "SERVITUDE", "USE_OR_SPACE_ASSIGNMENT",
                                             "USE_PERMIT", "RIGHT_OF_WAY"],
     "Forma da locação (art. 252, III e §1º: servidão, cessão de uso ou de espaço, permissão de uso, direito de passagem)"),
    ("rtc.real_estate_contributor_basis", "ENUM", ["REGULAR_LEGAL_ENTITY", "INDIVIDUAL_ART251_THRESHOLD", "INDIVIDUAL_NOT_CONTRIBUTOR"],
     "Condição de contribuinte do regime regular (art. 251, caput e §§1º-2º)"),
    ("rtc.real_estate_contributor_evidence_id", "STRING", None, "Prova da condição de contribuinte (art. 251)"),
    ("rtc.property_registry_id", "STRING", None, "Inscrição do imóvel no CIB ou matrícula (art. 265)"),
    ("rtc.property_state", "CODE", None, "UF do imóvel"),
    ("rtc.property_municipality_ibge", "CODE", None, "Município (IBGE) do imóvel"),
    ("rtc.property_use", "ENUM", ["RESIDENTIAL", "NON_RESIDENTIAL"], "Uso do imóvel"),
    ("rtc.lease_term_class", "ENUM", ["UP_TO_90_DAYS_UNINTERRUPTED", "OVER_90_DAYS"],
     "Prazo da locação (art. 253: residencial até 90 dias ininterruptos segue a hotelaria)"),
    ("rtc.real_estate_payment_date", "DATE", None, "Data do pagamento (art. 254, III e IV: fato gerador)"),
    ("rtc.real_estate_settlement", "ARRAY", None,
     "Apuração da locação (um registro, contrato RTC-REAL-ESTATE-LEASE-B37-V1): valor cobrado, tributos/emolumentos do imóvel e "
     "despesas de condomínio (art. 255, §2º) e, se residencial, redutor social com valor atualizado e fonte (art. 260)."),
]
RANK = 247
PATTERNS = {"rtc.property_state": "/^[A-Z]{2}$/", "rtc.property_municipality_ibge": "/^[0-9]{7}$/"}


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
    target.write_bytes((json.dumps(value, ensure_ascii=False, indent=2) + "\n").encode("utf-8"))


def php_policy():
    code = ("require 'vendor/autoload.php'; echo json_encode(\\App\\Modules\\Fiscal\\ConsumptionReform\\RealEstateRegimeB37::POLICY,"
            " JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);")
    out = subprocess.run([PHP, "-r", code], cwd=REPO, capture_output=True, check=True)
    return json.loads(out.stdout.decode("utf-8"))


def rule(canonical, ctribnac, kind, nbs, cindop, cclass, label, published):
    r = {"kind": "ENGINE_DECISION_RULE", "version": "1.0.0", "review_status": "SANDBOX_REVIEW_REQUIRED",
         "canonical_service_id": canonical, "operation_kind": kind, "market": "DOMESTIC",
         "delivery_modes": ["PRESENTIAL", "REMOTE", "DIGITAL"], "destination_basis": "PROPERTY",
         "tax_treatment": "SPECIFIC_REGIME", "specific_regime": "REAL_ESTATE", "nbs_prefixes": [nbs],
         "cindop": cindop, "cclasstrib": cclass, "valid_from": "2026-01-01", "published_at": published,
         "source_article": f"LC214 art. 11, II; arts. 251-261 ({label})", "source_hash": PLANALTO_HASH}
    if ctribnac:
        r["ctribnac_code"] = ctribnac
    return r


RULES = [
    ("RTC.CLASSIFICATION.REAL_ESTATE.LEASE.PROPERTY.B37-1", "CTRIBNAC_990101", "990101", "REAL_ESTATE", "1.1002", "020101", "200027", "locação, art. 261 par. único"),
    ("RTC.CLASSIFICATION.REAL_ESTATE.171201.ADMINISTRATION.B37-1", "CTRIBNAC_171201", "171201", "SERVICE", "1.1001.1", "020301", "200046", "administração, art. 261"),
    ("RTC.CLASSIFICATION.REAL_ESTATE.100501.INTERMEDIATION.B37-1", "CTRIBNAC_100501", "100501", "SERVICE", "1.1001.2", "020301", "200046", "intermediação, art. 261"),
]


def main():
    assert hashlib.sha256((ROOT / PLANALTO).read_bytes()).hexdigest() == PLANALTO_HASH
    previous = read(PREVIOUS)
    target = f"bundles/consumption-phase4b-{VERSION}.json"
    available = read(target)["knowledge_available_at"] if (ROOT / target).exists() \
        else datetime.now(timezone.utc).replace(microsecond=0).isoformat()

    codes = read(previous["resource_paths"]["rtc_code_catalog"])
    codes["version"] = VERSION
    assert "real_estate_regime_b37" not in codes
    for cclass, ref, pct in (("200027", "Art. 261, parágrafo único", 70), ("200046", "Art. 261", 50)):
        row = codes["cclasstrib"][cclass]
        assert row["cst"] == "200" and row["nfse_supported"] is True and row["legal_reference"] == ref
        assert row["source_reduction_ibs_percent"] == pct and row["source_reduction_cbs_percent"] == pct
    for c in ("020101", "020301"):
        assert codes["cindop"][c]["nfse_supported"] is True
    codes["real_estate_regime_b37"] = {**php_policy(), "published_at": available}
    for rid, canonical, ctribnac, kind, nbs, cindop, cclass, label in RULES:
        assert rid not in codes["engine_decision_rules"]
        codes["engine_decision_rules"][rid] = rule(canonical, ctribnac, kind, nbs, cindop, cclass, label, available)

    facts = read(previous["resource_paths"]["fact_definitions"])
    questions = read(previous["resource_paths"]["questions"])
    regime = [r for r in facts if r["key"] == "rtc.specific_regime"]
    assert len(regime) == 1 and regime[0]["allowed_values"] == ["HOSPITALITY", "TRAVEL_AGENCY"]
    regime[0]["allowed_values"] = ["HOSPITALITY", "TRAVEL_AGENCY", "REAL_ESTATE"]
    regime_q = [q for q in questions if q["fact_key"] == "rtc.specific_regime"]
    assert len(regime_q) == 1
    regime_q[0]["options"] = ["HOSPITALITY", "TRAVEL_AGENCY", "REAL_ESTATE"]
    for key, kind, values, prompt in FACTS:
        assert key not in {r["key"] for r in facts}, key
        fact = {"key": key, "type": kind, "material": True}
        question = {"fact_key": key, "prompt": prompt, "rank": RANK, "answer_type": kind}
        if values is not None:
            fact["allowed_values"] = values
            question["options"] = values
        if kind == "STRING":
            fact["min_length"] = 1
        if key in PATTERNS:
            fact["pattern"] = PATTERNS[key]
        facts.append(fact)
        questions.append(question)

    controls = read(previous["production_controls"]["path"])
    controls["version"] = VERSION
    for control in controls["controls"]:
        if control["id"] == "4B.4.BASE_AND_SPECIAL_REGIMES":
            control["gap"] += (" B37: regime específico de bens imóveis (LC 214, arts. 251-265) para NFS-e: locação/cessão/"
                               "arrendamento com redução de 70% (200027), base sem tributos do imóvel e condomínio, redutor "
                               "social residencial com valor atualizado e fonte; administração/intermediação com 50% (200046). "
                               "Alienação (redutor de ajuste), incorporação e construção civil ficam fora desta etapa.")

    bundle = copy.deepcopy(previous)
    bundle.update(bundle_id=f"CONSUMPTION-PHASE4B-{VERSION}", version=VERSION,
                  predecessor_bundle_id=previous["bundle_id"], knowledge_available_at=available,
                  notice="B37: regime específico de bens imóveis (LC 214, arts. 251-265) numa versão nova; "
                         "bundles anteriores permanecem inalterados.")
    bundle["catalog_versions"]["rtc_codes"] = VERSION
    for res_key, path, value in [
        ("rtc_code_catalog", f"catalogs/rtc-codes-{VERSION}.json", codes),
        ("fact_definitions", f"facts/consumption-phase4b-{VERSION}.json", facts),
        ("questions", f"questions/consumption-integrated-{VERSION}.json", questions),
    ]:
        write(path, value)
        bundle["resource_paths"][res_key] = path
        bundle["content_hashes"][res_key] = digest(value)
    write(f"production/phase4b-controls-{VERSION}.json", controls)
    bundle["production_controls"] = {"path": f"production/phase4b-controls-{VERSION}.json", "sha256": digest(controls)}
    write(target, bundle)
    print(json.dumps({"bundle_id": bundle["bundle_id"], "knowledge_available_at": available,
                      "rtc_codes_hash": bundle["content_hashes"]["rtc_code_catalog"]}, ensure_ascii=False))


if __name__ == "__main__":
    sys.exit(main())
