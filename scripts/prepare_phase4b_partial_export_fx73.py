"""Prepare explicit partial-export conversion research without changing the running generation."""
from pathlib import Path
from datetime import datetime, timezone
import hashlib, json, shutil

ROOT = Path(__file__).resolve().parents[1]
E = ROOT / 'storage/app/phase4b-partial-export-fx73-preparation-20261014'
E.mkdir(exist_ok=False)
P = ROOT / 'storage/app/phase4b-fx67-preparation-20261013'
def sha(path): return hashlib.sha256(path.read_bytes()).hexdigest()
research = json.loads((P / 'fx-research-contract.json').read_text(encoding='utf-8'))
sources = []
for item in research['excerpts'][:3]:
    original = P / item['source']
    assert sha(original) == item['source_sha256']
    sources.append({'path': original.relative_to(ROOT).as_posix(), 'sha256': sha(original),
                    'observation_scope': 'PRESERVED_PRIMARY_CAPTURE67_CURRENT_CBS_TEXT_RECHECKED_IN_BROWSER_20261014'})
converter = ROOT / 'app/Modules/Fiscal/ConsumptionReform/ImportFxQuoteConverter.php'
shutil.copyfile(converter, E / 'before-export-prototype-converter.php')
source = converter.read_text(encoding='utf-8').replace('ImportFxQuoteConverter', 'PartialExportFxQuotePrototype73')
source = source.replace('ORDINARY_SERVICE_IMPORT', 'ORDINARY_PARTIAL_SERVICE_EXPORT')
source = source.replace('LOCAL_SOURCE_BOUND_IMPORT_FX_CONVERSION', 'LOCAL_SOURCE_BOUND_PARTIAL_EXPORT_FX_CONVERSION')
source = source.replace('RTC_IMPORT_FX_', 'RTC_PARTIAL_EXPORT_FX_')
with (E / 'planned-partial-export-quote.php').open('x', encoding='utf-8') as stream: stream.write(source)
record = {'status': 'PARTIAL_EXPORT_FX_PROTOTYPE_PREPARED_NOT_INTEGRATED',
          'prepared_at': datetime.now(timezone.utc).isoformat(), 'sources': sources,
          'conversion_basis': 'CBS_REG_ART13_PAR4_GENERAL_OPERATION_BASE_NOT_MATERIAL_GOODS_IMPORT_ART81',
          'foreign_share_basis': 'CBS_REG_ART93_LC214_ART80_PAR5_ONLY_FOREIGN_EXECUTION_OR_CONSUMPTION_EXPORT',
          'reviewed_primary_url': 'https://www2.camara.leg.br/legin/fed/decret/2026/decreto-12955-29-abril-2026-799019-normaatualizada-pe.html',
          'current_converter_sha256': sha(converter), 'prototype_sha256': sha(E / 'planned-partial-export-quote.php'),
          'remaining_requirements': ['IBS rule qualification', 'USD material origin selection', 'distinct Brazilian supplier and foreign acquirer binding',
              'domestic recipient and both document identities', 'original amount to BRL reconciliation', 'allocation after exact conversion',
              'separate export facts/questions and own bundle', 'engine integration and native corpus', 'document obligation closure', 'full regression'],
          'app_installed': False, 'phase4b_complete': False, 'production_ready': False}
with (E / 'partial-export-fx-preparation.json').open('x', encoding='utf-8') as stream: json.dump(record, stream, indent=2)
print('PASS:explicit export context prototype prepared; current engine and running suite unchanged')
