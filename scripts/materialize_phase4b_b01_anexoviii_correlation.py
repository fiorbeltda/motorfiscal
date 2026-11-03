"""B01: materializa o Anexo VIII v1.02.00 (26/10/2026) como referência versionada.

Somente leitura das planilhas oficiais preservadas. Mesma regra literal da
materialização 1.01.00 (`materialize_rtc_catalogs.py`): linhas da aba
"tabela geral" com NBS, INDOP e cClassTrib preenchidos. Nenhuma linha
incompleta é preenchida por herança. A correlação é referência oficial
provisória e nunca recebe autoridade decisória; o catálogo não é ligado a
nenhum bundle.

Uso: python -I -X utf8 scripts/materialize_phase4b_b01_anexoviii_correlation.py
"""

import collections
import hashlib
import json
from pathlib import Path

from openpyxl import load_workbook

ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "resources/fiscal/sources/official"
OUTPUT = ROOT / "resources/fiscal/catalogs/rtc-correlation-anexoviii-v1.02.00-b01.json"

CURRENT = "anexo_viii-tabela-correlacao-v01-02-00-20261026.xlsx"
CURRENT_SHA = "12cc80bc92687856b47e961b98cdbaa3f8cae568c099b3ef71994e73482ac7d0"
PREVIOUS = "anexoviii-correlacaoitemnbsindopcclasstrib_ibscbs_v1-01-00.xlsx"
PREVIOUS_SHA = "a21be0e86b7ae2c0c1cfec4ef0d398b96520345790b50f79e4c5b7d1dfe32fb3"
PAGE = "https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/rtc"


def sha(name):
    return hashlib.sha256((SOURCE / name).read_bytes()).hexdigest()


def text(value):
    return "" if value is None else str(value)


def read(name):
    rows = list(load_workbook(SOURCE / name, read_only=True, data_only=True)["tabela geral"].values)
    header = [text(c) for c in rows[0][:10]]
    complete, incomplete = [], 0
    for number, row in enumerate(rows[1:], 2):
        row = list(row) + [None] * (10 - len(row))
        if row[2] and row[6] and row[8]:
            complete.append({
                "row": number,
                "lc116_code": text(row[0]),
                "nbs": text(row[2]),
                "cindop": text(row[6]).zfill(6),
                "ibs_incidence_place": text(row[7]),
                "cclasstrib": text(row[8]),
                "kind": "OFFICIAL_CORRELATION",
                "decision_authority": False,
            })
        elif any(c is not None and text(c).strip() != "" for c in row[:10]):
            incomplete += 1
    return header, complete, incomplete, len(rows) - 1


def key(entry):
    return (entry["lc116_code"], entry["nbs"], entry["cindop"], entry["cclasstrib"])


for name, expected in ((CURRENT, CURRENT_SHA), (PREVIOUS, PREVIOUS_SHA)):
    if sha(name) != expected:
        raise SystemExit(f"Bytes oficiais divergentes para {name}")

header, correlations, incomplete, data_rows = read(CURRENT)
prev_header, prev_correlations, prev_incomplete, prev_rows = read(PREVIOUS)
current_keys = collections.Counter(key(e) for e in correlations)
previous_keys = collections.Counter(key(e) for e in prev_correlations)
added = sorted((current_keys - previous_keys).elements())
removed = sorted((previous_keys - current_keys).elements())

catalog = {
    "version": "anexoviii-correlation-1.02.00-b01",
    "status": "SANDBOX_REFERENCE_NOT_ACTIVATED",
    "kind": "OFFICIAL_CORRELATION_REFERENCE",
    "decision_authority": False,
    "bundle_bound": False,
    "source": {
        "file": CURRENT,
        "sha256": CURRENT_SHA,
        "bytes": (SOURCE / CURRENT).stat().st_size,
        "url": PAGE + "/" + CURRENT,
        "publisher": "Portal Nacional da NFS-e (gov.br/nfse) - documentacao tecnica RTC",
        "page": PAGE,
        "page_listing_title": "Anexo VIII versao 1.02.00 (26/10/2026) - Correlacao entre Item de Serv., NBS, cClassTrib e cIndOp (AnexoVII)",
        "version": "1.02.00",
        "version_basis": "PAGE_TITLE_AND_FILENAME_NO_INTERNAL_VERSION_CELL",
        "published_on": "2026-10-26",
        "page_updated_at": "2026-10-26T15:52:00-03:00",
        "retrieved_at": "2026-11-03T12:04:30+00:00",
        "available_at": "2026-11-03T12:04:30+00:00",
        "page_notice": "Anexo VIII listado como trabalho inicial, sem regras de negocio baseadas nele nos ambientes indicados.",
        "sheets": ["tabela geral", "REGRA OP NAO ONE_EXT", "Planilha2"],
    },
    "previous": {
        "file": PREVIOUS,
        "sha256": PREVIOUS_SHA,
        "version": "1.01.00",
        "retrieved_on": "2026-09-25",
        "revalidated_on": "2026-11-03",
        "revalidation": "BYTES_EQUAL_CURRENT_OFFICIAL_DOWNLOAD",
        "page_label_ambiguity": "Na pagina de 26/10/2026 o titulo '1.01.00' aponta para ..._v1-00-00.xlsx e o titulo '1.00.00' aponta para ..._v1-01-00.xlsx (este arquivo). Nenhuma das planilhas declara versao internamente. Rotulo historico 1.01.00 preservado; ambiguidade registrada, nao resolvida.",
    },
    "extraction_rule": "Aba 'tabela geral'; linhas com NBS (col C), INDOP (col G) e cClassTrib (col I) preenchidos; cIndOp com zeros a esquerda ate 6 digitos; sem heranca de celulas vazias.",
    "header": header,
    "structural_changes_from_previous": {
        "header_previous": prev_header,
        "changed_columns": [
            {"column": i + 1, "previous": prev_header[i], "current": header[i]}
            for i in range(10) if prev_header[i] != header[i]
        ],
        "sheets_previous": ["REGRA inc. X", "tabela geral", "Planilha2"],
    },
    "counts": {
        "data_rows": data_rows,
        "complete_correlations": len(correlations),
        "incomplete_nonempty_rows_not_filled": incomplete,
        "previous_data_rows": prev_rows,
        "previous_complete_correlations": len(prev_correlations),
        "previous_incomplete_nonempty_rows_not_filled": prev_incomplete,
    },
    "diff_vs_previous": {
        "key": ["lc116_code", "nbs", "cindop", "cclasstrib"],
        "added": [list(k) for k in added],
        "removed": [list(k) for k in removed],
        "unchanged": sum((current_keys & previous_keys).values()),
    },
    "correlations": correlations,
}
OUTPUT.write_text(json.dumps(catalog, ensure_ascii=False, indent=2) + "\n", encoding="utf-8", newline="\n")
print(json.dumps({"output": str(OUTPUT.relative_to(ROOT)), **catalog["counts"],
                  "added": len(added), "removed": len(removed),
                  "unchanged": catalog["diff_vs_previous"]["unchanged"],
                  "changed_columns": catalog["structural_changes_from_previous"]["changed_columns"]}, ensure_ascii=False))
