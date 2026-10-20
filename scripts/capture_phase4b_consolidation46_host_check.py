from pathlib import Path
import subprocess,json,hashlib,sys
root=Path('.');e=root/'storage/app/phase4b-realization-consolidated-successor46-draft-20261009-28';name=sys.argv[1];cmd=sys.argv[2:];p=e/(name+'.log');assert not p.exists();r=subprocess.run(cmd,capture_output=True,encoding='utf-8');p.write_text(r.stdout+r.stderr,encoding='utf-8');(e/(name+'.json')).write_text(json.dumps({'command':cmd,'exit_code':r.returncode,'log_sha256':hashlib.sha256(p.read_bytes()).hexdigest()},indent=2)+'\n',encoding='utf-8');print(r.stdout+r.stderr);sys.exit(r.returncode)
