#!/usr/bin/env python3
"""Cria um pacote local com todos os componentes; nenhum segredo ou venda é incluído."""
from pathlib import Path
import argparse,shutil,json,hashlib,zipfile
root=Path(__file__).resolve().parents[1]
a=argparse.ArgumentParser();a.add_argument('--woocommerce',type=Path,required=True);a.add_argument('--wp-cli',type=Path,required=True);args=a.parse_args()
local=root/'local'
for source,name in [(root/'wordpress/ja-eletronicos-theme','ja-eletronicos-theme'),(root/'wordpress/ja-loja-core','ja-loja-core')]:
    dest=local/'packages'/name
    if dest.exists():shutil.rmtree(dest)
    shutil.copytree(source,dest)
(local/'tools').mkdir(exist_ok=True)
for source,name in [(args.woocommerce,'woocommerce.zip'),(args.wp_cli,'wp-cli.phar')]:
    if not source.is_file():raise SystemExit('Dependência não encontrada: '+str(source))
    if source.resolve() != (local/'tools'/name).resolve():shutil.copyfile(source,local/'tools'/name)
(local/'imports').mkdir(exist_ok=True)
shutil.copyfile(root/'wordpress/import-source/catalog.json',local/'imports/catalog.json')
shutil.copytree(root/'dist/assets/products',local/'imports/images',dirs_exist_ok=True)
manifest={f.name:hashlib.sha256(f.read_bytes()).hexdigest() for f in (local/'tools').iterdir() if f.is_file()}
manifest['translations']={str(f.relative_to(local/'tools')):hashlib.sha256(f.read_bytes()).hexdigest() for f in (local/'tools/translations').rglob('*') if f.is_file()}
manifest['sources']={'woocommerce':'https://downloads.wordpress.org/plugin/woocommerce.zip','wp-cli':'https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar'}
(local/'DEPENDENCIAS.json').write_text(json.dumps(manifest,indent=2))
release=root/'release';release.mkdir(exist_ok=True)
with zipfile.ZipFile(release/'ja-eletronicos-local.zip','w',zipfile.ZIP_DEFLATED) as z:
    for f in local.rglob('*'):
        relative=f.relative_to(local)
        if not f.is_file() or any(part in ['.secrets','backups','__pycache__'] for part in relative.parts) or f.name=='ACESSO-LOCAL.txt':continue
        z.write(f,'ja-eletronicos-local/'+str(relative))
print('Pacote local:',release/'ja-eletronicos-local.zip')
