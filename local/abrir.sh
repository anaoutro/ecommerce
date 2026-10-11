#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
python3 preparar.py
docker compose up -d db mailpit wordpress
docker compose --profile setup run --rm install
printf '\nAbra http://localhost:8090 e consulte ACESSO-LOCAL.txt.\n'
