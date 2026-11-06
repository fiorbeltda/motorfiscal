"""B34: integrates the A158 split-payment ledger (LC214 arts. 31-35) in a new bundle.

Forks bundle B33 without touching it or any earlier bundle.
- Adds the exact SplitPaymentIntegrationB34::POLICY block (gate `split_payment_a158`).
- Adds the ARRAY fact `rtc.split_payment_ledger` (contract RTC-SPLIT-LEDGER-A158-V1) right after
  `rtc.acquirer_collection_ledger`, and its question with the same rank.
- The transition catalog is untouched: `split_deployments` stays empty, so the installed bundle still stops at
  RTC_SPLIT_OFFICIAL_DEPLOYMENT_NOT_PROVEN. No deployment, percentage or authority receipt is invented.
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
VERSION = "2026.11.06-b34-draft1"
PREVIOUS = "bundles/consumption-phase4b-2026.11.06-b33-draft1.json"
CAMARA_HASH = "dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde"
PHP = os.environ.get("PHP85", "E:/tools/php-8.5.12/php.exe")
NAMESPACE = "\\App\\Modules\\Fiscal\\ConsumptionReform\\"

POLICY_CLASSES = {"split_payment_a158": "SplitPaymentIntegrationB34"}
ANCHOR_FACT = "rtc.acquirer_collection_ledger"
NEW_FACT = ("rtc.split_payment_ledger", "ARRAY",
            "No split payment (LC 214, arts. 31-35, procedimento padrão), informe o livro de conciliação da operação "
            "(um, contrato RTC-SPLIT-LEDGER-A158-V1): partes, documento, cronograma de transações/parcelas, débitos de "
            "IBS/CBS iguais aos calculados, informes do PSP, repasses STR/TES, aplicações com recibo da autoridade, "
            "excedentes e transferências ao fornecedor.")


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
    for key, policy in php_policies().items():
        assert key not in codes, key
        codes[key] = policy

    key, kind, prompt = NEW_FACT
    facts = read(previous["resource_paths"]["fact_definitions"])
    questions = read(previous["resource_paths"]["questions"])
    assert key not in {row["key"] for row in facts}
    anchor = [i for i, row in enumerate(facts) if row["key"] == ANCHOR_FACT]
    anchor_q = [row for row in questions if row["fact_key"] == ANCHOR_FACT]
    assert len(anchor) == 1 and len(anchor_q) == 1
    facts.insert(anchor[0] + 1, {"key": key, "type": kind, "material": True})
    q_index = questions.index(anchor_q[0])
    questions.insert(q_index + 1, {"fact_key": key, "answer_type": kind, "rank": anchor_q[0]["rank"], "prompt": prompt})
    assert len({row["key"] for row in facts}) == len(facts)
    assert len({row["fact_key"] for row in questions}) == len(questions)

    controls = read(previous["production_controls"]["path"])
    controls["version"] = VERSION
    extra = {
        "4B.3.SUBJECTS_AND_COLLECTION": " B34: o livro de conciliação do split payment do A158 (procedimento padrão) "
                                        "passa a ser exigido e vinculado à decisão do motor (partes, documento, "
                                        "transação, data da operação, débitos calculados e instante de conhecimento) "
                                        "atrás do gate split_payment_a158. A implantação oficial do split segue não "
                                        "comprovada (split_deployments vazio); o procedimento simplificado segue sem "
                                        "percentual oficial. Nada é autenticado, pago ou lançado.",
    }
    for control in controls["controls"]:
        if control["id"] in extra:
            control["gap"] += extra[control["id"]]

    bundle = copy.deepcopy(previous)
    bundle.update(bundle_id=f"CONSUMPTION-PHASE4B-{VERSION}", version=VERSION,
                  predecessor_bundle_id=previous["bundle_id"], knowledge_available_at=available,
                  notice="B34: integra o livro de conciliação do split payment do A158 ao motor atrás de gate próprio "
                         "numa versão nova; bundles anteriores permanecem inalterados.")
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
                      "policies": list(POLICY_CLASSES)}, ensure_ascii=False))


if __name__ == "__main__":
    sys.exit(main())
