#!/usr/bin/env python3
"""Prepara segredos apenas no computador local. Não cria contas externas."""
from pathlib import Path
import secrets,json,os
root=Path(__file__).resolve().parent
folder=root/'.secrets';folder.mkdir(mode=0o700,exist_ok=True)
folder.chmod(0o700)
for name in ['db_password','db_root_password']:
    path=folder/name
    if not path.exists():path.write_text(secrets.token_hex(24)+'\n');path.chmod(0o600)
    # Compose monta o arquivo diretamente; o PHP roda como www-data.
    # A pasta privada continua impedindo o acesso de outros usuários no host.
    path.chmod(0o644 if name=='db_password' else 0o600)
bootstrap=folder/'bootstrap.json'
if not bootstrap.exists():
    password=secrets.token_urlsafe(24)
    bootstrap.write_text(json.dumps({'password':password}));bootstrap.chmod(0o600)
    access=root/'ACESSO-LOCAL.txt'
    access.write_text('USO LOCAL — não publicar este arquivo\nLoja: http://localhost:8090\nGestão: http://localhost:8090/?ja_management=1\nLogin: ja-admin\nSenha: '+password+'\nE-mails locais: http://localhost:8025\n')
    access.chmod(0o600)
print('Configuração preparada. O acesso está salvo em ACESSO-LOCAL.txt; nenhuma senha foi enviada para terceiros.')
