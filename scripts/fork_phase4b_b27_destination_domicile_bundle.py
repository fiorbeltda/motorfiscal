"""B27: binds the engine destination to a replayable A151/A152/A153/B26 domicile report (DestinationDomicileReportB27).

Forks bundle B25 without touching it. Adds the exact DestinationDomicileReportB27::POLICY block and the ARRAY fact
rtc.destination_domicile_report with its question. With the gate, domicile bases require the report.
The B26 natural person of Decreto 12.955 art. 105 is covered by the A151 establishment path.
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
VERSION = "2026.11.05-b27-draft1"
PREVIOUS = "bundles/consumption-phase4b-2026.11.05-b25-draft1.json"
CAMARA_HASH = "dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde"
PHP = os.environ.get("PHP85", "E:/tools/php-8.5.12/php.exe")
NAMESPACE = "\\App\\Modules\\Fiscal\\ConsumptionReform\\"

POLICY_CLASSES = {"destination_domicile_report_b27": "DestinationDomicileReportB27"}

NEW_FACTS = [
    ("rtc.destination_domicile_report", "ARRAY",
     "Quando o destino é o domicílio do adquirente ou do destinatário, informe o relatório de qualificação de "
     "domicílio (um): estabelecimento cadastrado e fornecido (PJ ou PF contribuinte do art. 105), compra "
     "centralizada ou domicílio material de PF, conforme LC 214 art. 11 §3."),
]


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

    facts = read(previous["resource_paths"]["fact_definitions"])
    questions = read(previous["resource_paths"]["questions"])
    rank = max(row["rank"] for row in questions) + 1
    for offset, (key, kind, prompt) in enumerate(NEW_FACTS):
        facts.append({"key": key, "type": kind, "material": True})
        questions.append({"fact_key": key, "answer_type": kind, "rank": rank + offset, "prompt": prompt})
    assert len({row["key"] for row in facts}) == len(facts)
    assert len({row["fact_key"] for row in questions}) == len(questions)

    controls = read(previous["production_controls"]["path"])
    controls["version"] = VERSION
    extra = {
        "4B.2.INCIDENCE_AND_DESTINATION": " B26/B27: PF contribuinte/responsável do art. 105 (Decreto 12.955) usa o "
                                          "estabelecimento fornecido como domicílio (art. 107, I), com CNPJ exigido a partir "
                                          "de 2027 (§4-A); o destino por domicílio exige relatório A151/A152/A153 com replay "
                                          "idêntico e mesma parte, fornecedor, data e localidade. Cadastro não é autenticado.",
    }
    for control in controls["controls"]:
        if control["id"] in extra:
            control["gap"] += extra[control["id"]]

    bundle = copy.deepcopy(previous)
    bundle.update(bundle_id=f"CONSUMPTION-PHASE4B-{VERSION}", version=VERSION,
                  predecessor_bundle_id=previous["bundle_id"], knowledge_available_at=available,
                  notice="B27: ativa o vínculo destino-relatório de domicílio (B26/B27) numa versão nova; "
                         "bundles anteriores permanecem sem o gate.")
    bundle["catalog_versions"]["rtc_codes"] = VERSION
    for key, path, value in [
        ("rtc_code_catalog", f"catalogs/rtc-codes-{VERSION}.json", codes),
        ("fact_definitions", f"facts/consumption-phase4b-{VERSION}.json", facts),
        ("questions", f"questions/consumption-integrated-{VERSION}.json", questions),
    ]:
        write(path, value)
        bundle["resource_paths"][key] = path
        bundle["content_hashes"][key] = digest(value)
    write(f"production/phase4b-controls-{VERSION}.json", controls)
    bundle["production_controls"] = {"path": f"production/phase4b-controls-{VERSION}.json", "sha256": digest(controls)}
    write(target, bundle)
    camara = ROOT / "sources/official/lc214-camara-observed-20261001.html"
    assert hashlib.sha256(camara.read_bytes()).hexdigest() == CAMARA_HASH
    print(json.dumps({"bundle_id": bundle["bundle_id"], "knowledge_available_at": available,
                      "rtc_codes_hash": bundle["content_hashes"]["rtc_code_catalog"],
                      "facts_added": len(NEW_FACTS), "policies": list(POLICY_CLASSES)}, ensure_ascii=False))


if __name__ == "__main__":
    sys.exit(main())
