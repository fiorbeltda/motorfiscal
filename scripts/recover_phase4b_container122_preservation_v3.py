"""Recover interrupted export without misrepresenting it as a full rootfs.

Preserve every inventoried writable evidence entry through the partial tar plus
a supplemental tar. The unchanged base image is pinned, not re-exported.
"""
from datetime import datetime, timezone
from pathlib import Path
import hashlib
import json
import os
import subprocess
import tarfile

REPO = Path(__file__).resolve().parents[1]
DOCKER = r'D:\Docker\DockerDesktop\resources\bin\docker.exe'
OLD = Path(r'D:\ProjetoSaasExports\container-preservation122-20261021-v1')
ROOT = Path(r'D:\ProjetoSaasExports\container-preservation122-recovery-20261021-v3')
PARTIAL = OLD / '.tmp-app-container-before-evidence-mounts.tar367390339'
PREVIOUS = Path(r'D:\ProjetoSaasExports\container-preservation122-recovery-20261021-v2')
BOUNDARY = 'tmp/phase4b-price112-cli-v1/history/464-snapshot.json'


def sha(path):
    with path.open('rb') as stream:
        return hashlib.file_digest(stream, 'sha256').hexdigest()


def save(name, value):
    with (ROOT / name).open('x', encoding='utf-8') as stream:
        json.dump(value, stream, ensure_ascii=False, indent=2)


def docker(*args):
    return subprocess.run([DOCKER, *args], cwd=REPO, capture_output=True, check=True)


class HashingReader:
    def __init__(self, stream):
        self.stream = stream
        self.digest = hashlib.sha256()

    def read(self, size=-1):
        data = self.stream.read(size)
        self.digest.update(data)
        return data


def verify_archive(path, expected):
    matched = set()
    rejected = []
    bytes_verified = 0
    next_progress = 2_000_000_000
    with path.open('rb', buffering=1024 * 1024) as stream:
        reader = HashingReader(stream)
        with tarfile.open(fileobj=reader, mode='r|', bufsize=1024 * 1024) as archive:
            for member in archive:
                name = member.name.removeprefix('./').rstrip('/')
                if name not in expected:
                    continue
                row = expected[name]
                if row['type'] == 'directory':
                    assert member.isdir(), name
                elif row['type'] == 'link':
                    assert member.issym() and member.linkname == row['target'], name
                elif member.islnk():
                    target = member.linkname.removeprefix('./').rstrip('/')
                    assert target in expected and expected[target] == row, name
                else:
                    assert member.isfile() and member.size == row['bytes'], name
                    extracted = archive.extractfile(member)
                    assert extracted is not None, name
                    with extracted:
                        observed_hash = hashlib.file_digest(extracted, 'sha256').hexdigest()
                    if observed_hash != row['sha256']:
                        assert path == PARTIAL and name == BOUNDARY, 'Unexpected corruption: ' + name
                        rejected.append({'path': name, 'expected_sha256': row['sha256'], 'observed_sha256': observed_hash,
                                         'reason': 'INTERRUPTED_EXPORT_BOUNDARY_BYTES_DO_NOT_MATCH'})
                        print('REJECTED:partial export boundary entry; separate verified replacement required', flush=True)
                        continue
                    bytes_verified += row['bytes']
                    if bytes_verified >= next_progress:
                        print(f'VERIFIED:{path.name}:{bytes_verified} bytes', flush=True)
                        next_progress += 2_000_000_000
                assert name not in matched, 'Repeated archive entry: ' + name
                matched.add(name)
        # Include trailing padding or any unread bytes in the physical tar hash.
        while reader.read(1024 * 1024):
            pass
    return matched, reader.digest.hexdigest(), bytes_verified, rejected


def main():
    assert not ROOT.exists(), 'Exclusive recovery attempt already exists'
    inspection = json.loads((OLD / 'interrupted-export-structural-inspection.json').read_text(encoding='utf-8'))
    expected = json.loads((OLD / 'temporary-files-inventory.json').read_text(encoding='utf-8'))
    original = json.loads((OLD / 'container-inspect.json').read_text(encoding='utf-8'))[0]
    container = docker('compose', 'ps', '-q', 'app').stdout.decode().strip()
    assert container == original['Id'], 'Original container must still exist'
    current = json.loads(docker('inspect', container).stdout)[0]
    assert current['Image'] == original['Image']
    assert PARTIAL.stat().st_size == inspection['bytes']
    ROOT.mkdir()
    save('start.json', {'started_at': datetime.now(timezone.utc).isoformat(),
                       'host_python_pid': os.getpid(), 'collector_sha256': sha(Path(__file__)),
                       'container': container, 'image': current['Image'],
                       'original_inventory_sha256': sha(OLD / 'temporary-files-inventory.json')})
    # All mutable content changes must be covered by the inventoried roots.
    # /var and /var/www are parent-directory metadata, not omitted file content.
    original_diff = (OLD / 'container-diff.txt').read_text(encoding='utf-8')
    for line in original_diff.splitlines():
        status, path = line.split(' ', 1)
        assert status in ['A', 'C'], 'Deletion needs a separately preserved journal: ' + line
        assert path in ['/var', '/var/www'] or path == '/tmp' or path.startswith('/tmp/') or path == '/var/www/.composer' or path.startswith('/var/www/.composer/'), line
        if path not in ['/var', '/var/www', '/tmp', '/var/www/.composer']:
            assert path.lstrip('/') in expected, 'Uninventoried mutable entry: ' + path
    save('mutable-change-closure.json', {'all_changed_content_within_inventory': True,
                                       'parent_directory_metadata': ['/var', '/var/www'],
                                       'original_diff_sha256': sha(OLD / 'container-diff.txt'),
                                       'base_image_retained': current['Image']})
    failure = json.loads((PREVIOUS / 'failure.json').read_text(encoding='utf-8'))
    assert BOUNDARY in failure['error'] and inspection['last_member'] == BOUNDARY
    missing = [BOUNDARY]
    assert all(name in expected and '\n' not in name and '\r' not in name and not name.startswith('-') for name in missing)
    list_file = ROOT / 'supplemental-entry-list.txt'
    with list_file.open('x', encoding='utf-8', newline='\n') as stream:
        stream.write('\n'.join(missing) + '\n')
    remote_list = '/tmp/container-preservation122-recovery-entry-list-v3.txt'
    docker('cp', str(list_file), container + ':' + remote_list)
    supplement = ROOT / 'supplemental-writable-evidence.tar'
    command = [DOCKER, 'exec', container, 'tar', '--no-recursion', '-cf', '-', '-C', '/', '-T', remote_list]
    print('CAPTURE:supplemental writable entries:' + str(len(missing)), flush=True)
    with supplement.open('xb') as stream:
        result = subprocess.run(command, cwd=REPO, stdout=stream, stderr=subprocess.PIPE)
    with (ROOT / 'supplemental-capture.stderr').open('xb') as stream:
        stream.write(result.stderr)
    save('supplemental-capture-execution.json', {'command': command, 'exit_code': result.returncode,
                                               'finished_at': datetime.now(timezone.utc).isoformat()})
    assert result.returncode == 0, result.stderr[-1000:]
    matched = set()
    archives = []
    rejected_entries = []
    entry_sources = {}
    for path in [PARTIAL, PREVIOUS / 'supplemental-writable-evidence.tar', supplement]:
        print('VERIFY:' + path.name, flush=True)
        found, digest, byte_count, rejected = verify_archive(path, expected)
        rejected_entries.extend([{'archive': str(path), **row} for row in rejected])
        for name in found:
            entry_sources[name] = {'archive': str(path), **expected[name]}
        matched.update(found)
        archives.append({'path': str(path), 'sha256': digest, 'bytes': path.stat().st_size,
                         'temporary_entries_verified': len(found), 'temporary_file_bytes_verified': byte_count})
    assert matched == set(expected), sorted(set(expected) - matched)[:20]
    assert len(rejected_entries) == 1 and rejected_entries[0]['path'] == BOUNDARY
    save('verified-entry-source-map.json', entry_sources)
    # No tests/collectors were run on the original container during recovery.
    # Refuse unexpected writable paths; the one new list is preserved on D:.
    current_diff = docker('diff', container).stdout.decode()
    (ROOT / 'container-diff-after-recovery.txt').write_text(current_diff, encoding='utf-8')
    allowed = {line.split(' ', 1)[1] for line in original_diff.splitlines()}
    assert all(line.split(' ', 1)[1] in allowed | {remote_list, '/tmp/container-preservation122-recovery-entry-list-v2.txt'} for line in current_diff.splitlines())
    save('container-preservation-validation.json', {
        'finished_at': datetime.now(timezone.utc).isoformat(),
        'status': 'CONTAINER_WRITABLE_EVIDENCE_PRESERVED_FROM_RECOVERED_ARCHIVES',
        'container': container, 'image_sha256': current['Image'],
        'base_image_still_required_and_retained': True,
        'full_rootfs_export_complete': False, 'interrupted_export_preserved': True,
        'all_inventoried_writable_entries_preserved': True,
        'temporary_entries_verified': len(matched), 'archives': archives,
        'rejected_partial_entries': rejected_entries,
        'verified_entry_source_map': str(ROOT / 'verified-entry-source-map.json'),
        'verified_entry_source_map_sha256': sha(ROOT / 'verified-entry-source-map.json'),
        'temporary_inventory': str(OLD / 'temporary-files-inventory.json'),
        'temporary_inventory_sha256': sha(OLD / 'temporary-files-inventory.json'),
        'mutable_change_closure': str(ROOT / 'mutable-change-closure.json'),
        'operational_lists_preserved': [str(list_file), str(PREVIOUS / 'supplemental-entry-list.txt')],
        'bind_mounts_and_volumes_exported': False,
        'bind_mounts_and_volumes_preserved_separately': True,
        'container_recreated': False,
    })
    print('PASS:all11141 writable evidence entries preserved; original image pinned; no full-rootfs claim', flush=True)


if __name__ == '__main__':
    try:
        main()
    except Exception as exc:
        if ROOT.exists() and not (ROOT / 'failure.json').exists():
            save('failure.json', {'failed_at': datetime.now(timezone.utc).isoformat(), 'error': repr(exc)})
        raise
