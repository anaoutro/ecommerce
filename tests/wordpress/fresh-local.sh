#!/bin/bash
set -e
D=(env -u DOCKER_HOST -u DOCKER_CONTEXT -u DOCKER_TLS -u DOCKER_TLS_VERIFY -u DOCKER_CERT_PATH docker --host=unix:///var/run/docker.sock)
# Apenas a instalação local criada para os testes desta sessão, sem dados do lojista.
"${D[@]}" compose --project-directory /workspace/sites/ja-eletronicos/local down -v
"${D[@]}" compose --project-directory /workspace/sites/ja-eletronicos/local up -d db mailpit wordpress
"${D[@]}" compose --project-directory /workspace/sites/ja-eletronicos/local --profile setup run --rm -e JA_IMPORT_LIMIT=8 install
