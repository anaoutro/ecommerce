$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot
python preparar.py
if ($LASTEXITCODE -ne 0) { throw 'Instale Python 3 e execute novamente.' }
docker compose up -d db mailpit wordpress
if ($LASTEXITCODE -ne 0) { throw 'Abra o Docker Desktop e tente novamente.' }
docker compose --profile setup run --rm install
if ($LASTEXITCODE -ne 0) { throw 'A instalação encontrou um erro; veja as mensagens acima.' }
Start-Process 'http://localhost:8090'
Write-Host 'Consulte ACESSO-LOCAL.txt para entrar no painel.'
