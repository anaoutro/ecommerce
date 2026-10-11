#!/usr/bin/env python3
"""Prepara o ambiente de um clone, sem gerar ou distribuir credenciais."""
from pathlib import Path
import argparse
import hashlib
import json
import os
import shutil
import subprocess
import sys
import tempfile
import urllib.request

ROOT = Path(__file__).resolve().parents[1]

def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--offline', action='store_true', help='Exige dependências já presentes e não faz downloads.')
    args = parser.parse_args()
    tools = ROOT / 'local/tools'
    tools.mkdir(parents=True, exist_ok=True)
    lock = json.loads((ROOT / 'scripts/dependencies.json').read_text())
    for name, dependency in lock.items():
        target = tools / name
        expected = dependency['sha256']
        if target.is_file() and hashlib.sha256(target.read_bytes()).hexdigest() == expected:
            print(f'Dependência verificada: {name}')
            continue
        if args.offline:
            raise SystemExit(f'Dependência ausente ou divergente: {name}. Execute sem --offline para baixar a versão registrada.')
        print(f'Baixando da origem oficial: {name}')
        temp_path = None
        try:
            request = urllib.request.Request(dependency['url'], headers={'User-Agent': 'JA-Eletronicos-local-setup/1.0'})
            with urllib.request.urlopen(request, timeout=120) as response, tempfile.NamedTemporaryFile(dir=tools, delete=False) as output:
                temp_path = Path(output.name)
                shutil.copyfileobj(response, output)
            if hashlib.sha256(temp_path.read_bytes()).hexdigest() != expected:
                raise SystemExit(f'O hash de {name} mudou. O download não foi instalado. Revise a versão e a origem antes de atualizar scripts/dependencies.json.')
            os.replace(temp_path, target)
        finally:
            if temp_path is not None and temp_path.exists():
                temp_path.unlink()
    shutil.copytree(ROOT / 'resources/translations', tools / 'translations', dirs_exist_ok=True)
    subprocess.run([sys.executable, str(ROOT / 'scripts/build-local.py'), '--woocommerce', str(tools / 'woocommerce.zip'), '--wp-cli', str(tools / 'wp-cli.phar')], check=True)
    print('Ambiente preparado. Siga local/LEIA-ME.md para iniciar os serviços.')

if __name__ == '__main__':
    main()
