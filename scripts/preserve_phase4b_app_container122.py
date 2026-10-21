"""Preserve the old writable container before adding cross-drive evidence mounts.

This is an exclusive attempt, gated on completion of all historical replays.
Bind mounts/volumes are not exported; they remain preserved separately.
"""
from datetime import datetime, timezone
from pathlib import Path
import hashlib
import json
import subprocess
import tarfile

REPO = Path(__file__).resolve().parents[1]
DOCKER = r'D:\Docker\DockerDesktop\resources\bin\docker.exe'
ROOT = Path(r'D:\ProjetoSaasExports\container-preservation122-20261021-v1')
HISTORY = Path(r'D:\ProjetoSaasExports\phase4b-credit122-preparation-20261021\historical-replay-v1\credit122-historical-validation.json')


def sha(path):
    with path.open('rb') as stream:
        return hashlib.file_digest(stream, 'sha256').hexdigest()


def save(name, value):
    with (ROOT / name).open('x', encoding='utf-8') as stream:
        json.dump(value, stream, ensure_ascii=False, indent=2)


def docker(*args):
    return subprocess.run([DOCKER, *args], cwd=REPO, capture_output=True, check=True)


def main():
    prior = json.loads(HISTORY.read_text(encoding='utf-8'))
    assert prior['historical_exact_replays'] == 1441
    assert prior['input_inventory_unchanged'] is True
    assert prior['status'] == 'CREDIT122_ALL_ACCEPTED119_AND_PRIOR_REPLAYS_EXACT'
    assert not ROOT.exists(), 'Exclusive preservation attempt already exists'
    ROOT.mkdir()
    container = docker('compose', 'ps', '-q', 'app').stdout.decode().strip()
    assert container
    save('start.json', {'started_at': datetime.now(timezone.utc).isoformat(),
                       'container': container, 'historical_report_sha256': sha(HISTORY)})
    (ROOT / 'container-inspect.json').write_bytes(docker('inspect', container).stdout)
    (ROOT / 'container-diff.txt').write_bytes(docker('diff', container).stdout)
    # Preserve the entire rootfs, not just known temporary directories. Inventory
    # temporary evidence/cache files separately to verify their exact tar bytes.
    php = r'''$out=[];foreach(['/tmp','/var/www/.composer'] as $root){
    if(!is_dir($root)){continue;}
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST);
    foreach($it as $f){$p=$f->getPathname();if($f->isLink()){$row=['type'=>'link','target'=>readlink($p)];}
    elseif($f->isDir()){$row=['type'=>'directory'];}
    elseif($f->isFile()){$row=['type'=>'file','bytes'=>$f->getSize(),'sha256'=>hash_file('sha256',$p)];}
    else{throw new RuntimeException('Unsupported entry:'.$p);}$out[ltrim($p,'/')]=$row;}}
    ksort($out);echo json_encode($out,JSON_THROW_ON_ERROR);'''
    print('INVENTORY:old container temporary evidence', flush=True)
    inventory = json.loads(docker('exec', container, 'php', '-r', php).stdout)
    save('temporary-files-inventory.json', inventory)
    export = ROOT / 'app-container-before-evidence-mounts.tar'
    print('EXPORT:preserving complete container rootfs on D:', flush=True)
    docker('export', '--output', str(export), container)
    print('VERIFY:all inventoried temporary files against exported tar', flush=True)
    matched = set()
    with tarfile.open(export, 'r|') as archive:
        for member in archive:
            name = member.name.removeprefix('./').rstrip('/')
            if name not in inventory:
                continue
            expected = inventory[name]
            if expected['type'] == 'directory':
                assert member.isdir(), name
            elif expected['type'] == 'link':
                assert member.issym() and member.linkname == expected['target'], name
            elif member.islnk():
                target = member.linkname.removeprefix('./').rstrip('/')
                assert target in inventory, name
                assert inventory[target] == expected, name
            else:
                assert member.isfile() and member.size == expected['bytes'], name
                stream = archive.extractfile(member)
                assert stream is not None, name
                with stream:
                    assert hashlib.file_digest(stream, 'sha256').hexdigest() == expected['sha256'], name
            matched.add(name)
    assert matched == set(inventory), sorted(set(inventory) - matched)[:20]
    save('container-preservation-validation.json', {
        'finished_at': datetime.now(timezone.utc).isoformat(),
        'status': 'CONTAINER_ROOTFS_PRESERVED_WITH_TEMPORARY_EVIDENCE_HASHES',
        'container': container, 'export': str(export), 'export_bytes': export.stat().st_size,
        'export_sha256': sha(export), 'temporary_entries_verified': len(matched),
        'temporary_inventory_sha256': sha(ROOT / 'temporary-files-inventory.json'),
        'bind_mounts_and_volumes_exported': False,
        'bind_mounts_and_volumes_preserved_separately': True,
        'container_recreated': False,
    })
    print('PASS:old container preserved on D:; recreation not performed', flush=True)


if __name__ == '__main__':
    main()
