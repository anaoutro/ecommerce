# Desenvolvimento

## Ambiente

Use Python 3 e Docker Compose. Execute `python scripts/prepare-local.py` na raiz e siga o README. O instalador mantém banco e uploads em volumes Docker. Não use `docker compose down -v` em um ambiente com dados que deseja preservar.

O tema e o plugin em `local/packages/` são cópias geradas dos fontes em `wordpress/`. Edite os fontes, gere os pacotes novamente e recrie o contêiner WordPress para atualizar os mounts:

```sh
python scripts/build-local.py --woocommerce local/tools/woocommerce.zip --wp-cli local/tools/wp-cli.phar
cd local
docker compose up -d --force-recreate wordpress
```

`build-wordpress.py` regenera partes do tema a partir de `dist/`; mudanças no design gerado devem ser feitas nessa origem para não serem sobrescritas.

## Testes

Os scripts em `tests/wordpress/` exercitam integração com WordPress/WooCommerce reais. Eles pressupõem um banco isolado e fixtures específicas; não são testes unitários independentes nem devem rodar contra produção. Consulte os pré-requisitos dentro de cada script e [o registro de validação](../wordpress/VALIDACAO.md).

`fresh-local.sh` apaga os volumes do projeto local durante a validação. Use apenas numa instalação de teste descartável. `local-smoke.py` cria acessos e altera estoque. Execute-o somente nessa base isolada.

Para revisão de sintaxe, use `php -l` nos arquivos PHP e `node --check` nos arquivos JavaScript. Verifique a navegação e as operações afetadas em um ambiente isolado quando modificar regras de negócio.

## Publicar o repositório

Não versione `local/.secrets/`, `ACESSO-LOCAL.txt`, `.env`, backups, banco, uploads da empresa ou logs. Eles estão excluídos pelo `.gitignore`. Os prints deste repositório usam somente a instalação local de referência.

No GitHub, crie um repositório vazio chamado `ja-eletronicos`, importe este código e use esta descrição:

> Loja personalizada e gestão de caixa, estoque e compras com WordPress e WooCommerce.

Tópicos sugeridos: `wordpress`, `woocommerce`, `php`, `ecommerce`, `inventory-management`, `point-of-sale`, `docker-compose`.

O README usa imagens relativas e funciona sem links para este workspace. Para a imagem social, selecione `docs/screenshots/cover.png` em Settings → General → Social preview.
