"""Install previously preserved sources/classes without overwriting any differing bytes."""
import hashlib
import json
from pathlib import Path

root = Path(__file__).resolve().parents[1]
staging = Path('D:/ProjetoSaasExports/phase4b-iss134-20261022-v1')
own = Path('D:/ProjetoSaasExports/phase4b-own135-20261023-v1')
pairs = [(staging / 'source-staging135-v1' / name, root / 'app/Modules/Fiscal/ConsumptionReform' / name)
         for name in ['DocumentComex135.php', 'PartialExportForeignClassification135.php']]
pairs += [(own / 'primary-v1' / name, root / 'resources/fiscal/sources/official' / ('own135-' + name))
          for name in ['law10833.html', 'law9718.html', 'law9715.html', 'mp2158.html', 'lc116.html']]
pairs += [(own / 'court-v1/stf-tema118.html', root / 'resources/fiscal/sources/official/own135-stf-tema118.html'),
          (Path('D:/ProjetoSaasExports/phase4b-price135-20261023-v1/primary-v2/lc214.html'), root / 'resources/fiscal/sources/official/own135-lc214.html'),
          (staging / 'layout-followup-v1/anexovi-v1-04-01.xlsx', root / 'resources/fiscal/sources/official/export135-anexovi10401.xlsx')]
report = []
for source, destination in pairs:
    body = source.read_bytes()
    if destination.exists():
        assert destination.read_bytes() == body, destination
    else:
        with destination.open('xb') as handle:
            handle.write(body)
    report.append({'source': str(source), 'destination': str(destination), 'sha256': hashlib.sha256(body).hexdigest()})
out = Path('D:/ProjetoSaasExports/phase4b-pair135-20261023-v1/source-install-v1')
out.mkdir(parents=True, exist_ok=False)
(out / 'receipt.json').write_text(json.dumps({'files': report}, indent=2) + '\n', encoding='utf-8')
print(json.dumps({'files_installed_and_verified': len(report)}))
