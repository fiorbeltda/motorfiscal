"""B35 (4B.4.10): regime específico de hotelaria, parques de diversão e parques temáticos (LC 214/2025, arts. 277-283).

Forks bundle B34 without touching it or any earlier bundle.
- Adds HospitalityRegimeB35::POLICY under `hospitality_regime_b35`, with `published_at` = knowledge availability of this bundle.
- Adds engine decision rules (SANDBOX_REVIEW_REQUIRED) for the NFS-e lodging services (cTribNac 090101-090104, NBS 1.0303)
  and amusement/theme parks (cTribNac 120501, NBS 1.2507), destination by presence (LC214 art. 11, III; cIndOp
  030101/030102), official cClassTrib 200048 (CST 200, indNFSe = 1, reduction 40% in the official table).
- Adds the facts/questions of the regime. Food and beverages (art. 280, par. único) are never folded into this base.
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
VERSION = "2026.11.06-b35-draft1"
PREVIOUS = "bundles/consumption-phase4b-2026.11.06-b34-draft1.json"
PLANALTO = "sources/official/lc214-planalto-observed-20261103.htm"
PLANALTO_HASH = "0a0d190bc39689f50b199646b51dd84d01f6d7392c179287b46909c3d4f4673c"
PHP = os.environ.get("PHP85", "E:/tools/php-8.5.12/php.exe")

FACTS = [
    ("rtc.specific_regime", "ENUM", ["HOSPITALITY"], "Regime específico da operação (LC 214, Título V)"),
    ("rtc.hospitality_activity", "ENUM", ["HOTEL", "AMUSEMENT_PARK", "THEME_PARK"],
     "Atividade do regime de hotelaria e parques (LC 214, arts. 277-279)"),
    ("rtc.hospitality_scope_evidence_id", "STRING", None, "Prova de que a atividade se enquadra nos arts. 278/279"),
    ("rtc.hospitality_food_beverage_in_price", "BOOLEAN", None,
     "O preço inclui alimentação ou bebidas? (art. 280, parágrafo único: seguem o regime de bares e restaurantes)"),
    ("rtc.hotel_unit_kind", "ENUM", ["EXCLUSIVE_GUEST_UNITS_ESTABLISHMENT", "FURNISHED_RESIDENTIAL_PROPERTY"],
     "Hotelaria: unidades de uso exclusivo dos hóspedes em estabelecimento destinado a isso, ou imóvel residencial mobiliado (art. 278)"),
    ("rtc.hotel_temporary_lodging", "BOOLEAN", None, "O fornecimento é de alojamento temporário? (art. 278)"),
    ("rtc.park_presential_attractions_essential", "BOOLEAN", None,
     "A atividade essencial é disponibilizar atrações de entretenimento fruídas presencialmente no local? (art. 279, I)"),
    ("rtc.theme_park_theme", "ENUM", ["HISTORICAL", "CULTURAL", "ETHNOGRAPHIC", "LUDIC", "ENVIRONMENTAL"],
     "Tema do parque temático (art. 279, II)"),
]
RANK = 245
LODGING = ["090101", "090102", "090103", "090104"]
PARKS = ["120501"]


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
    code = ("require 'vendor/autoload.php'; echo json_encode(\\App\\Modules\\Fiscal\\ConsumptionReform\\HospitalityRegimeB35::POLICY,"
            " JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);")
    out = subprocess.run([PHP, "-r", code], cwd=REPO, capture_output=True, check=True)
    return json.loads(out.stdout.decode("utf-8"))


def rule(ctribnac, nbs, label, published):
    return {"kind": "ENGINE_DECISION_RULE", "version": "1.0.0", "review_status": "SANDBOX_REVIEW_REQUIRED",
            "canonical_service_id": f"CTRIBNAC_{ctribnac}", "ctribnac_code": ctribnac, "operation_kind": "SERVICE",
            "market": "DOMESTIC", "delivery_modes": ["PRESENTIAL"], "destination_basis": "PERSON_PRESENCE",
            "tax_treatment": "SPECIFIC_REGIME", "specific_regime": "HOSPITALITY", "nbs_prefixes": [nbs],
            "cclasstrib": "200048", "cindop_by_presence": {"SUPPLIER": "030101", "OTHER": "030102"},
            "valid_from": "2026-01-01", "published_at": published,
            "source_article": f"LC214 art. 11, III; arts. 277-283 ({label})", "source_hash": PLANALTO_HASH}


def main():
    assert hashlib.sha256((ROOT / PLANALTO).read_bytes()).hexdigest() == PLANALTO_HASH
    previous = read(PREVIOUS)
    target = f"bundles/consumption-phase4b-{VERSION}.json"
    available = read(target)["knowledge_available_at"] if (ROOT / target).exists() \
        else datetime.now(timezone.utc).replace(microsecond=0).isoformat()

    codes = read(previous["resource_paths"]["rtc_code_catalog"])
    codes["version"] = VERSION
    assert "hospitality_regime_b35" not in codes
    row = codes["cclasstrib"]["200048"]
    assert row["cst"] == "200" and row["nfse_supported"] is True and row["legal_reference"] == "Art. 281"
    assert row["source_reduction_ibs_percent"] == 40 and row["source_reduction_cbs_percent"] == 40
    for c in ("030101", "030102"):
        assert codes["cindop"][c]["nfse_supported"] is True
    codes["hospitality_regime_b35"] = {**php_policy(), "published_at": available}
    for ctribnac in LODGING:
        rid = f"RTC.CLASSIFICATION.HOSPITALITY.{ctribnac}.PERSON_PRESENCE.B35-1"
        assert rid not in codes["engine_decision_rules"]
        codes["engine_decision_rules"][rid] = rule(ctribnac, "1.0303", "hotelaria, art. 278", available)
    for ctribnac in PARKS:
        rid = f"RTC.CLASSIFICATION.HOSPITALITY.{ctribnac}.PERSON_PRESENCE.B35-1"
        assert rid not in codes["engine_decision_rules"]
        codes["engine_decision_rules"][rid] = rule(ctribnac, "1.2507", "parques, art. 279", available)

    facts = read(previous["resource_paths"]["fact_definitions"])
    questions = read(previous["resource_paths"]["questions"])
    for key, kind, values, prompt in FACTS:
        assert key not in {r["key"] for r in facts}, key
        fact = {"key": key, "type": kind, "material": True}
        question = {"fact_key": key, "prompt": prompt, "rank": RANK, "answer_type": kind}
        if values is not None:
            fact["allowed_values"] = values
            question["options"] = values
        if kind == "STRING":
            fact["min_length"] = 1
        facts.append(fact)
        questions.append(question)

    controls = read(previous["production_controls"]["path"])
    controls["version"] = VERSION
    for control in controls["controls"]:
        if control["id"] == "4B.4.BASE_AND_SPECIAL_REGIMES":
            control["gap"] += (" B35: regime específico de hotelaria, parques de diversão e parques temáticos (LC 214, arts. "
                               "277-283) materializado: redução de 40%, cClassTrib 200048, crédito vedado ao adquirente "
                               "(art. 283), alimentação/bebidas separadas (art. 280, par. único). Regras de classificação em "
                               "revisão Sandbox; bares/restaurantes ficam fora do escopo NFS-e.")

    bundle = copy.deepcopy(previous)
    bundle.update(bundle_id=f"CONSUMPTION-PHASE4B-{VERSION}", version=VERSION,
                  predecessor_bundle_id=previous["bundle_id"], knowledge_available_at=available,
                  notice="B35: regime específico de hotelaria e parques (LC 214, arts. 277-283) numa versão nova; "
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
