# JA Eletrônicos — WordPress e WooCommerce no seu computador

Este pacote executa **WordPress e WooCommerce reais**, com tema próprio da JA e plugin de gestão. Não é uma página estática. Os dados ficam no banco local e persistem quando você fecha o navegador ou para os serviços.

## Abrir pela primeira vez

1. Instale e abra o **Docker Desktop**, com suporte a contêineres Linux, e instale **Python 3**. No Linux, Docker Engine com Compose e Python 3 também atendem. A máquina precisa de aproximadamente 4 GB de memória disponíveis e espaço para as imagens Docker e os produtos.
2. Extraia `ja-eletronicos-local.zip` em uma pasta do computador.
3. No terminal, entre na pasta extraída e execute:

**Windows — PowerShell:**

```powershell
powershell -ExecutionPolicy Bypass -File .\abrir.ps1
```

**macOS ou Linux:**

```sh
bash abrir.sh
```

A primeira execução baixa os servidores e importa 1.099 produtos e fotos. Pode levar vários minutos. Internet é necessária para os downloads. As traduções oficiais em português estão incluídas no pacote.

4. Abra **http://localhost:8090** para a vitrine e **http://localhost:8090/?ja_management=1** para a gestão. O login e a senha gerada ficam no arquivo **ACESSO-LOCAL.txt**, no seu computador. Não compartilhe esse arquivo.
5. Abra **http://localhost:8025** para ver os e-mails locais de definição de senha, recuperação de acesso e pedidos. As mensagens ficam nessa caixa de teste e não são entregues a pessoas reais.

## O que está integrado

- Vitrine própria com campanhas automáticas, categorias, pesquisa, carrinho e checkout WooCommerce.
- Caixa por funcionário, abertura, sangria, reforço, dinheiro contado e diferença de fechamento.
- Venda presencial por SKU/código de barras, preço validado no servidor, pagamento manual e comprovante não fiscal.
- Estoque compartilhado entre o caixa e os pedidos online, com proteção de unidades reservadas.
- Cadastro de produtos, custo, estoque mínimo, contagem física, entradas e perdas.
- Fornecedores, compras e recebimento com estoque e custo médio atualizados.
- Devolução integral de venda presencial, reposição de estoque e saída de dinheiro quando aplicável.
- Pedidos online, relatórios de vendas/custos/margem, histórico, criação e desativação de funcionários.
- Exportação do catálogo, estoque, custo e código de barras em CSV.

O administrador pode usar o cadastro completo do WooCommerce para fotos, descrições, categorias e variações. O acesso do funcionário fica restrito ao caixa e às próprias vendas.

## Preparar a apresentação

Os 1.099 produtos e fotos são referências do catálogo anterior. **Nenhuma quantidade real foi importada: o estoque começa em zero.** Confira o preço e registre a contagem de um produto em Produtos e estoque → Ajustar. Ele fica disponível na vitrine e no caixa com a mesma quantidade.

Abra um caixa, faça uma venda presencial e confira o saldo desse produto na loja. Para testar a compra online, use outro navegador ou uma janela privada, adicione um produto com estoque, escolha a retirada e o pagamento presencial. O pedido criado no checkout aparece na gestão e no WooCommerce. **Não ocorre cobrança automática.** Pagamento na retirada e retirada sem tarifa são configurações apenas da apresentação local; o endereço real e as regras da empresa ainda precisam ser cadastrados.

Em Equipe e acessos, crie o funcionário com um e-mail próprio de apresentação. O link de definição da senha aparecerá na caixa local de e-mails. Abra esse link em janela privada e confira o acesso restrito.

O relatório estima margem dos itens, excluindo frete e tributos do pedido. Sem custo cadastrado, ele indica que a margem está incompleta. Despesas gerais e taxas da maquininha não compõem esse cálculo.

## Parar e abrir novamente

```sh
docker compose stop
```

```sh
docker compose up -d db mailpit wordpress
```

Esses comandos preservam o banco. **Não use `docker compose down -v`: isso apaga os dados locais.** Rodar o instalador novamente preserva o que já foi importado.

## Backup

Com o sistema aberto, execute:

```sh
python backup.py
```

O backup contém banco e uploads, incluindo pedidos, caixa, usuários e histórico. Os arquivos ficam em `backups/`. Preserve também a pasta `.secrets` e o pacote do sistema em armazenamento privado. Para restaurar um backup local, execute:

```sh
python restaurar.py backups/NOME-DA-PASTA
```

A restauração pede confirmação, substitui o banco local e mantém a pasta anterior de uploads como reserva. Valide dados e acessos após restaurar. Esses scripts são locais; backups externos e a política da hospedagem continuam necessários na publicação.

## Validação

A instalação em banco vazio foi exercitada com oito produtos de amostra. O pacote inclui todo o catálogo para a instalação normal. Caixa, estoque, compras, devolução integral, relatórios, exportação e permissões têm testes de backend; a venda e a navegação também passaram no navegador. A conferência final dos e-mails locais e dos scripts de backup/restauração permanece pendente. Não houve cobrança de dinheiro nem acesso ao banco real da empresa.

## Limites desta versão local

- Não emite NF-e/NFC-e. O comprovante está identificado como não fiscal.
- Não cobra Pix ou cartão online e não se conecta à maquininha. Esses meios dependem da conta do lojista e de configuração posterior; no caixa, o operador confirma o recebimento.
- A devolução presencial é integral; devoluções parciais e reembolsos online podem ser administrados no WooCommerce nativo, conforme o gateway.
- Não contém estoque, custos, clientes e histórico reais do sistema antigo. Quantidades e custos precisam ser conferidos.
- O acesso fica restrito ao computador (`localhost`). Não é hospedagem permanente, não deve ser exposto diretamente à internet e não substitui a homologação com o lojista.

Código próprio: GPL-2.0-or-later. WooCommerce: GPL-3.0; WordPress e seus componentes conservam as licenças originais. As distribuições incluídas têm origem e hashes registrados em DEPENDENCIAS.json.
