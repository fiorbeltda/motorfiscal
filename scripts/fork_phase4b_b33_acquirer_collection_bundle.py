"""B33: integrates A157 (acquirer collection) and withdraws the duplicated B25 settlement gate in a new bundle.

Forks bundle B32 without touching it or any earlier bundle.
- Removes the `settlement_cycle_b25` policy and the ARRAY fact `rtc.settlement_records` (and its question):
  the B25 component duplicated A157 (4B.3.8) and A158 (4B.3.7) and was withdrawn on 06/11/2026.
  Bundles b25..b32 keep the inert key; the engine no longer reads it.
- Adds the exact AcquirerCollection157::POLICY block and the ARRAY fact `rtc.acquirer_collection_ledger`
  (contract RTC-ACQUIRER-COLLECTION-A157-V1), as requested in docs/fiscal/agent-a/20261105/handoff-A157-to-B.md.
  The new question takes the rank of the removed one.
Split payment (4B.3.7) stays with A158, which is not yet integrated in the engine.
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
VERSION = "2026.11.06-b33-draft1"
PREVIOUS = "bundles/consumption-phase4b-2026.11.05-b32-draft1.json"
CAMARA_HASH = "dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde"
PHP = os.environ.get("PHP85", "E:/tools/php-8.5.12/php.exe")
NAMESPACE = "\\App\\Modules\\Fiscal\\ConsumptionReform\\"

POLICY_CLASSES = {"acquirer_collection_a157": "AcquirerCollection157"}
WITHDRAWN_POLICIES = ["settlement_cycle_b25"]
REPLACED_FACT = "rtc.settlement_records"
NEW_FACT = ("rtc.acquirer_collection_ledger", "ARRAY",
            "No recolhimento pelo adquirente, informe o livro de reconciliação da operação (um, contrato "
            "RTC-ACQUIRER-COLLECTION-A157-V1): partes, transação de pagamento, instrumento sem split, débitos de "
            "IBS/CBS, parcelas já extintas, histórico completo, recolhimentos do adquirente com comprovantes e "
            "repasses do excedente ao fornecedor.")


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


def php_policies():
    code = ("require 'vendor/autoload.php'; echo json_encode(["
            + ",".join(f"'{k}' => {NAMESPACE}{c}::POLICY" for k, c in POLICY_CLASSES.items())
            + "], JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);")
    out = subprocess.run([PHP, "-r", code], cwd=REPO, capture_output=True, check=True)
    return json.loads(out.stdout.decode("utf-8"))


def main():
    previous = read(PREVIOUS)
    target = f"bundles/consumption-phase4b-{VERSION}.json"
    available = read(target)["knowledge_available_at"] if (ROOT / target).exists() \
        else datetime.now(timezone.utc).replace(microsecond=0).isoformat()

    codes = read(previous["resource_paths"]["rtc_code_catalog"])
    codes["version"] = VERSION
    for key in WITHDRAWN_POLICIES:
        assert key in codes, key
        del codes[key]
    for key, policy in php_policies().items():
        assert key not in codes, key
        codes[key] = policy

    key, kind, prompt = NEW_FACT
    facts = read(previous["resource_paths"]["fact_definitions"])
    questions = read(previous["resource_paths"]["questions"])
    assert key not in {row["key"] for row in facts}
    fact_rows = [i for i, row in enumerate(facts) if row["key"] == REPLACED_FACT]
    question_rows = [i for i, row in enumerate(questions) if row["fact_key"] == REPLACED_FACT]
    assert len(fact_rows) == 1 and len(question_rows) == 1
    facts[fact_rows[0]] = {"key": key, "type": kind, "material": True}
    rank = questions[question_rows[0]]["rank"]
    questions[question_rows[0]] = {"fact_key": key, "answer_type": kind, "rank": rank, "prompt": prompt}
    assert len({row["key"] for row in facts}) == len(facts)
    assert len({row["fact_key"] for row in questions}) == len(questions)

    controls = read(previous["production_controls"]["path"])
    controls["version"] = VERSION
    extra = {
        "4B.3.SUBJECTS_AND_COLLECTION": " B33: o ciclo de liquidação B25 foi retirado por duplicar A157/A158; o "
                                        "recolhimento pelo adquirente (art. 36) passa a ser reconciliado pelo A157 "
                                        "(livro com comprovantes, excedente e repasses); o split payment (arts. 31-35) "
                                        "segue com o A158, ainda fora do motor. Nada é autenticado, pago ou lançado.",
    }
    for control in controls["controls"]:
        if control["id"] in extra:
            control["gap"] += extra[control["id"]]

    bundle = copy.deepcopy(previous)
    bundle.update(bundle_id=f"CONSUMPTION-PHASE4B-{VERSION}", version=VERSION,
                  predecessor_bundle_id=previous["bundle_id"], knowledge_available_at=available,
                  notice="B33: ativa o recolhimento pelo adquirente do A157 e retira o gate duplicado B25 "
                         "(settlement_cycle_b25) numa versão nova; bundles anteriores permanecem inalterados.")
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
    camara = ROOT / "sources/official/lc214-camara-observed-20261001.html"
    assert hashlib.sha256(camara.read_bytes()).hexdigest() == CAMARA_HASH
    print(json.dumps({"bundle_id": bundle["bundle_id"], "knowledge_available_at": available,
                      "rtc_codes_hash": bundle["content_hashes"]["rtc_code_catalog"],
                      "withdrawn": WITHDRAWN_POLICIES, "policies": list(POLICY_CLASSES)}, ensure_ascii=False))


if __name__ == "__main__":
    sys.exit(main())
