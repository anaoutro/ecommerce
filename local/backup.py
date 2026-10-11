#!/usr/bin/env python3
"""Backup local do banco e uploads, incluindo vendas, caixa, equipe e histórico."""
from pathlib import Path
import subprocess,datetime,json,sys
root=Path(__file__).resolve().parent
stamp=datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ')
folder=root/'backups'/stamp;folder.mkdir(parents=True,mode=0o700)
def export(service,command,target):
    with target.open('wb') as stream:
        subprocess.run(['docker','compose','exec','-T',service,'sh','-c',command],cwd=root,stdout=stream,check=True)
try:
    export('db','MYSQL_PWD="$(cat /run/secrets/db_root_password)" exec mariadb-dump --user=root --single-transaction ja_loja',folder/'database.sql')
    export('wordpress','tar -C /var/www/html/wp-content -czf - uploads',folder/'uploads.tar.gz')
    (folder/'LEIA-ME.txt').write_text('Backup local de banco e uploads. Contém dados privados. Preserve também o pacote do sistema e .secrets. Não publique.\nData UTC: '+stamp+'\n')
    for f in folder.iterdir():f.chmod(0o600)
except subprocess.CalledProcessError:
    raise SystemExit('Backup incompleto. Os arquivos parciais estão em '+str(folder))
print('Backup salvo em '+str(folder))
