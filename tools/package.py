"""Build only the hash-verified runtime payload, excluding development files."""
import hashlib,json,pathlib,zipfile
root=pathlib.Path(__file__).resolve().parent.parent
m=json.loads((root/'release-manifest.json').read_text(encoding='utf-8'))
out=root/'artifacts';out.mkdir(exist_ok=True)
with zipfile.ZipFile(out/(m['slug']+'.zip'),'w',zipfile.ZIP_STORED) as z:
 for name,expected in sorted(m['runtimeFiles'].items()):
  rel=pathlib.PurePosixPath(name)
  if rel.is_absolute() or '..' in rel.parts: raise ValueError('Unsafe path')
  p=root.joinpath(*rel.parts)
  if p.is_symlink(): raise ValueError('No symlinks')
  data=p.read_bytes()
  if hashlib.sha256(data).hexdigest()!=expected: raise ValueError('Runtime changed: '+name)
  info=zipfile.ZipInfo(m['slug']+'/'+name,date_time=(2026,10,22,0,0,0))
  info.create_system=3;info.compress_type=zipfile.ZIP_STORED;info.external_attr=0o100644<<16
  z.writestr(info,data)
archive=out/(m['slug']+'.zip')
actual=hashlib.sha256(archive.read_bytes()).hexdigest()
if m.get('artifactSha256') and actual!=m['artifactSha256']: raise ValueError('ZIP hash mismatch')
print(archive.name,actual)
