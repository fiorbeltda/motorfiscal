"""Stage only A156 scope and build immutable review artifacts. Does not commit or publish."""
import json
import subprocess
import zipfile
from pathlib import Path

from evidence_io import preserve_bytes, preserve_json
from handoffs156 import ROOT, OUT, sha

BASE = '1fa9969b2334806553a08b1a4276e22d1b7d7c8e'
DOC = 'docs/fiscal/agent-a/20261105/'
MODULE = 'app/Modules/Fiscal/ConsumptionReform/'
PRODUCTION = [MODULE+n+'.php' for n in ['ConsumptionReformEngine', 'TemporalRateCatalog', 'CreditEvidence156', 'LegalBaseReduction156']]
TESTS = ['tests/Fiscal/Integration/'+n+'Test.php' for n in ['ArithmeticEvidence156', 'BaseReductionMissing156',
    'CreditEvidence156', 'FinancialEvidence156', 'LegacyCoexistence156', 'LegalBaseReduction156', 'SyntheticDocumentEvidence156']]
SCRIPTS = ['scripts/agent_a/'+n for n in ['arithmetic156_fixture.php', 'handoff156_fixture.php', 'capture156.php',
    'console156.php', 'handoffs156.py', 'decimal156.py', 'review156.py', 'package156.py']]


def git(*args):
    return subprocess.check_output(['git', *args], cwd=ROOT)


def build():
    assert git('rev-parse', 'HEAD').decode().strip() == BASE
    receipt = json.loads((ROOT/DOC/'handoffs-H6-H10-receipt.json').read_text())
    assert receipt['current_source_validation']['failures'] == receipt['current_source_validation']['errors'] == 0
    for p, digest in receipt['production_sources'].items():
        assert sha(ROOT/p) == digest, p
    status = [x.decode() for x in git('status', '--porcelain=v1', '-z').split(b'\0') if x]
    paths = [x[3:] for x in status]
    permitted = set(PRODUCTION+TESTS+SCRIPTS)
    assert all(p in permitted or p.startswith(DOC) for p in paths), paths
    assert all(not x.startswith(('D ', ' D', 'R ')) for x in status)
    assert git('status', '--porcelain', '--', 'resources', 'vendor', '.env') == b''
    subprocess.run(['git', 'add', '--', *paths], cwd=ROOT, check=True)
    subprocess.run(['git', '-c', 'core.whitespace=blank-at-eol,blank-at-eof,space-before-tab,cr-at-eol',
        'diff', '--cached', '--check'], cwd=ROOT, check=True)
    staged = [x.decode() for x in git('diff', '--cached', '--name-only', '-z').split(b'\0') if x]
    assert set(staged) == set(paths)
    manifest = {}
    for p in staged:
        raw = git('show', ':'+p)
        assert raw == (ROOT/p).read_bytes(), p
        preserve_bytes(OUT/'source-package-final'/p, raw)
        manifest[p] = {'sha256':sha(ROOT/p), 'bytes':len(raw), 'git_blob':git('rev-parse', ':'+p).decode().strip()}
    preserve_json(OUT/'source-package-final/manifest.json', {'base':BASE, 'files':manifest})
    preserve_bytes(OUT/'A156-H6-H10.patch', git('diff', '--cached', '--binary', '--full-index'))
    mappings = {
        'H6': [PRODUCTION[0], PRODUCTION[2], TESTS[2], TESTS[6], DOC+'contracts-H6-H10.md'],
        'H7': [PRODUCTION[0], PRODUCTION[2], TESTS[2], TESTS[6], DOC+'contracts-H6-H10.md'],
        'H8': [PRODUCTION[0], PRODUCTION[1], TESTS[0], TESTS[3], SCRIPTS[0]],
        'H9': [PRODUCTION[0], PRODUCTION[1], TESTS[4], SCRIPTS[0], DOC+'dependencies-and-family-coverage.md'],
        'H10':[PRODUCTION[0], PRODUCTION[3], TESTS[1], TESTS[5], DOC+'requirements44-handoff-H10.json'],
    }
    scoped = {}
    for h, subset in mappings.items():
        name = h+'-review-only-overlapping.patch'
        preserve_bytes(OUT/name, git('diff', '--cached', '--binary', '--full-index', '--', *subset))
        scoped[h] = {'files':subset, 'review_diff':name, 'sha256':sha(OUT/name),
            'not_standalone_apply':True, 'shared_engine_hunks_overlap_other_handoffs':True}
    preserve_json(OUT/'commit-preflight.json', {'base':BASE, 'staged':manifest, 'staged_matches_worktree':True,
        'resources_unchanged':True, 'no_vendor_or_env':True})
    preserve_json(OUT/'delivery-manifest.json', {'base':BASE, 'files':manifest, 'handoffs':scoped,
        'complete_patch':'A156-H6-H10.patch', 'complete_patch_sha256':sha(OUT/'A156-H6-H10.patch'),
        'apply_once_only':'Use git pull for the commit; per-handoff diffs are overlapping review views, never independent patches.'})
    destination = OUT/'A156-motorfiscal-H6-H10.zip'
    assert not destination.exists()
    candidates = sorted(p for p in OUT.rglob('*') if p.is_file() and p != destination)
    with zipfile.ZipFile(destination, 'x', compression=zipfile.ZIP_DEFLATED) as archive:
        for p in candidates:
            archive.write(p, p.relative_to(OUT).as_posix())
    with zipfile.ZipFile(destination) as archive:
        assert archive.testzip() is None
        for p, item in manifest.items():
            import hashlib
            assert hashlib.sha256(archive.read('source-package-final/'+p)).hexdigest() == item['sha256']
    preserve_json(OUT/'zip-receipt.json', {'path':str(destination), 'sha256':sha(destination),
        'bytes':destination.stat().st_size, 'files':len(candidates), 'crc_verified':True,
        'source_files_verified':len(manifest), 'no_commit_or_push_in_this_script':True})
    print(json.dumps({'staged_files':len(staged), 'zip_sha256':sha(destination), 'zip':str(destination)}))


if __name__ == '__main__':
    build()
