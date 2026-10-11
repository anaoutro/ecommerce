#!/usr/bin/env python3
"""Restaura um backup somente no projeto Docker local, mediante confirmação explícita."""
from pathlib import Path,PurePosixPath
import subprocess,sys,tarfile
root=Path(__file__).resolve().parent
if len(sys.argv)!=2:raise SystemExit('Uso: python restaurar.py backups/PASTA-DO-BACKUP')
folder=Path(sys.argv[1]).resolve();sql=folder/'database.sql';uploads=folder/'uploads.tar.gz'
if not sql.is_file() or not uploads.is_file():raise SystemExit('O backup precisa conter database.sql e uploads.tar.gz.')
with tarfile.open(uploads) as archive:
    for member in archive.getmembers():
        path=PurePosixPath(member.name)
        if path.is_absolute() or '..' in path.parts or not path.parts or path.parts[0]!='uploads' or member.issym() or member.islnk():raise SystemExit('Arquivo de uploads contém um caminho não permitido.')
if input('Isso substitui os dados do sistema LOCAL. Faça um backup antes. Digite RESTAURAR para continuar: ')!='RESTAURAR':raise SystemExit('Restauração cancelada.')
def run(args,**kwargs):return subprocess.run(['docker','compose',*args],cwd=root,check=True,**kwargs)
run(['stop','wordpress'])
try:
    with sql.open('rb') as stream:run(['exec','-T','db','sh','-c','MYSQL_PWD="$(cat /run/secrets/db_root_password)" exec mariadb --user=root ja_loja'],stdin=stream)
    # Um contêiner temporário trabalha no volume parado, mantendo os arquivos atuais de uploads como reserva.
    command='cd /var/www/html/wp-content; if [ -d uploads ]; then mv uploads "uploads.before-restore.$(date -u +%Y%m%dT%H%M%SZ)"; fi; tar --no-same-owner -xzf -; chown -R www-data:www-data uploads'
    with uploads.open('rb') as stream:run(['run','--rm','--no-deps','-T','--entrypoint','sh','wordpress','-c',command],stdin=stream)
finally:run(['start','wordpress'])
print('Backup restaurado no sistema local. Abra a loja e confira os dados.')
