"""Materialize immutable RTC reference catalogs from preserved official workbooks.

Run with the bundled workspace Python environment (openpyxl). This script never
uses the provisional correlation workbook to decide a tax treatment.
"""

import hashlib
import json
from datetime import datetime
from pathlib import Path

from openpyxl import load_workbook

ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "resources/fiscal/sources/official"
OUTPUT = ROOT / "resources/fiscal/catalogs"


def workbook(name, sheet):
    return load_workbook(SOURCE / name, read_only=True, data_only=True)[sheet]


def sha(name):
    return hashlib.sha256((SOURCE / name).read_bytes()).hexdigest()


def write(name, value):
    (OUTPUT / name).write_text(json.dumps(value, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


def date(value):
    return value.strftime("%Y-%m-%d") if isinstance(value, datetime) else None


ind_file = "anexovii-indop_ibscbs_v1-02-00.xlsx"
classes_file = "nfe-cclasstrib-it2025.002-v1.60-20260623.xlsx"
nbs_file = "anexo-b-servicos-nacionais-v1.01-20260122.xlsx"
corr_file = "anexoviii-correlacaoitemnbsindopcclasstrib_ibscbs_v1-01-00.xlsx"
active_layout_file = "anexovi-leiautesrn_rtc_ibscbs-v1-01-03-nt004.xlsx"
future_layout_file = "anexovi-leiautesrn_rtc_ibscbs-v1-04-00-2013-nt009.xlsx"

cindop = {}
for row in list(workbook(ind_file, "cIndOp Public").values)[1:]:
    code = str(row[0] or "")
    if len(code) == 6 and code.isdigit():
        cindop[code] = {
            "operation": str(row[1] or ""),
            "characteristic": str(row[2] or ""),
            "destination_basis": str(row[3] or ""),
            "legal_reference": str(row[4] or ""),
            "nfse_supported": row[7] == "S",
        }

classes = {}
for row in list(workbook(classes_file, "cClass 2026-06-01 Pub").values)[1:]:
    code = str(row[2] or "")
    if len(code) == 6 and code.isdigit():
        classes[code] = {
            "cst": str(row[0] or ""),
            "name": str(row[3] or ""),
            "legal_reference": str(row[6] or ""),
            "valid_from": date(row[21]),
            "valid_to": date(row[22]),
            "source_updated_on": date(row[23]),
            "nfse_supported": row[33] == 1,
        }

nbs = {}
for row in list(workbook(nbs_file, "LISTA.NBS_v2.0").values)[1:]:
    code = str(row[0] or "")
    if code and row[1]:
        nbs[code] = str(row[1])

correlations = []
for number, row in enumerate(list(workbook(corr_file, "tabela geral").values)[1:], 2):
    if row[2] and row[6] and row[8]:
        correlations.append({
            "row": number,
            "lc116_code": str(row[0] or ""),
            "nbs": str(row[2]),
            "cindop": str(row[6]).zfill(6),
            "cclasstrib": str(row[8]),
            "kind": "OFFICIAL_CORRELATION",
            "decision_authority": False,
        })

write("rtc-codes-2026.09.25-1.json", {
    "version": "2026.09.25-1",
    "status": "SANDBOX_CANDIDATE",
    "sources": {
        "cindop": {"file": ind_file, "sha256": sha(ind_file), "version": "1.02.00", "retrieved_on": "2026-09-25"},
        "cclasstrib": {"file": classes_file, "sha256": sha(classes_file), "version": "IT 2025.002 v1.60", "published_on": "2026-06-23", "retrieved_on": "2026-09-25"},
        "nbs": {"file": nbs_file, "sha256": sha(nbs_file), "version": "v1.01-20260122", "retrieved_on": "2026-09-25"},
        "correlation": {"file": corr_file, "sha256": sha(corr_file), "version": "1.01.00", "retrieved_on": "2026-09-25", "status": "PROVISIONAL_NO_DECISION_AUTHORITY"},
        "active_layout": {"file": active_layout_file, "sha256": sha(active_layout_file), "version": "NT004 1.01.03", "retrieved_on": "2026-09-25"},
        "future_layout": {"file": future_layout_file, "sha256": sha(future_layout_file), "version": "NT009 1.04.00", "retrieved_on": "2026-09-25", "status": "NOT_DEPLOYED"},
    },
    "document_layouts": [{
        "version": "NT004-RTC-1.01.03",
        "valid_from": "2026-01-05",
        "valid_to": None,
        "sandbox_coverage_through": "2026-12-31",
        "published_at": "2025-12-10T00:00:00-03:00",
        "status": "ACTIVE",
        "source_hash": sha(active_layout_file),
        "note": "Portal RTC indicates NT004 plus tpRetPisCofins from NT007 as current production baseline; sandbox coverage stops at 2026-12-31 without claiming legal sunset.",
    }, {
        "version": "NT009-RTC-1.04.00",
        "valid_from": None,
        "valid_to": None,
        "published_at": "2026-06-04T00:00:00-03:00",
        "status": "FUTURE_UNDEPLOYED",
        "source_hash": sha(future_layout_file),
    }],
    "cindop": dict(sorted(cindop.items())),
    "cclasstrib": dict(sorted(classes.items())),
    "nbs": dict(sorted(nbs.items())),
    "correlations": correlations,
    "engine_decision_rules": {
        "RTC.CLASSIFICATION.SAAS.RESIDUAL.SANDBOX-1": {
            "kind": "ENGINE_DECISION_RULE",
            "version": "1.0.0",
            "canonical_service_id": "SOFTWARE_DEVELOPMENT",
            "operation_kind": "DIGITAL",
            "delivery_mode": "DIGITAL",
            "destination_basis": "ACQUIRER_DOMICILE",
            "cindop": "100301",
            "cclasstrib": "000001",
            "market": "DOMESTIC",
            "valid_from": "2026-01-01",
            "valid_to": None,
            "published_at": "2026-09-25T09:35:00-03:00",
            "legal_basis": "LC 214/2025 art. 11, X, a, 1; regra geral de tributação integral sujeita à revisão jurídica",
            "evidence": ["AnexoVII-IndOp_IBSCBS_V1.02.00", "IT 2025.002 v1.60"],
            "review_status": "SANDBOX_REVIEW_REQUIRED",
        },
        "RTC.CLASSIFICATION.IMPORTED_SAAS.RESIDUAL.SANDBOX-1": {
            "kind": "ENGINE_DECISION_RULE",
            "version": "1.0.0",
            "canonical_service_id": "SOFTWARE_DEVELOPMENT",
            "operation_kind": "DIGITAL",
            "delivery_mode": "DIGITAL",
            "destination_basis": "ACQUIRER_DOMICILE",
            "cindop": "100301",
            "cclasstrib": "000001",
            "market": "IMPORT",
            "valid_from": "2026-01-01",
            "valid_to": "2026-12-31",
            "published_at": "2026-09-25T09:35:00-03:00",
            "legal_basis": "LC 214/2025 arts. 11, X, a, 1, e 64, §5º, III-IV; classificação interpretativa restrita a SaaS digital padrão",
            "evidence": ["AnexoVII-IndOp_IBSCBS_V1.02.00", "IT 2025.002 v1.60", "LC214-CONSOLIDATED-CAMARA-2026"],
            "review_status": "SANDBOX_REVIEW_REQUIRED",
        },
        "RTC.CLASSIFICATION.SAAS.RECIPIENT_DESTINATION.SANDBOX-1": {
            "kind": "ENGINE_DECISION_RULE",
            "version": "1.0.0",
            "canonical_service_id": "SOFTWARE_DEVELOPMENT",
            "operation_kind": "DIGITAL",
            "delivery_mode": "DIGITAL",
            "destination_basis": "RECIPIENT_DOMICILE",
            "cindop": "100302",
            "cclasstrib": "000001",
            "market": "DOMESTIC",
            "valid_from": "2026-01-01",
            "valid_to": "2026-12-31",
            "published_at": "2026-09-25T09:35:00-03:00",
            "legal_basis": "LC 214/2025 art. 11, X, a, 2; adquirente estrangeiro e destinatário brasileiro",
            "evidence": ["AnexoVII-IndOp_IBSCBS_V1.02.00", "IT 2025.002 v1.60", "LC214-CONSOLIDATED-CAMARA-2026"],
            "review_status": "SANDBOX_REVIEW_REQUIRED",
        },
        "RTC.CLASSIFICATION.IMPORTED_SAAS.RECIPIENT_DESTINATION.SANDBOX-1": {
            "kind": "ENGINE_DECISION_RULE",
            "version": "1.0.0",
            "canonical_service_id": "SOFTWARE_DEVELOPMENT",
            "operation_kind": "DIGITAL",
            "delivery_mode": "DIGITAL",
            "destination_basis": "RECIPIENT_DOMICILE",
            "cindop": "100302",
            "cclasstrib": "000001",
            "market": "IMPORT",
            "valid_from": "2026-01-01",
            "valid_to": "2026-12-31",
            "published_at": "2026-09-25T09:35:00-03:00",
            "legal_basis": "LC 214/2025 arts. 11, X, a, 2, e 64, §5º, IV e VI; classificação interpretativa restrita a SaaS digital padrão",
            "evidence": ["AnexoVII-IndOp_IBSCBS_V1.02.00", "IT 2025.002 v1.60", "LC214-CONSOLIDATED-CAMARA-2026"],
            "review_status": "SANDBOX_REVIEW_REQUIRED",
        },
    },
})

print(f"cIndOp={len(cindop)} cClassTrib={len(classes)} NBS={len(nbs)} correlations={len(correlations)}")
