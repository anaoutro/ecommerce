# Instalar a loja JA no WordPress com WooCommerce

O design é o mesmo da nova vitrine. WordPress e WooCommerce administram produtos, preços, estoque, carrinho e checkout; o cliente navega no tema personalizado da JA.

## Arquivos entregues

- `release/ja-eletronicos-theme.zip`: tema personalizado, com seis capas automáticas, categorias, pesquisa, favoritos e lista de produtos.
- `release/ja-loja-core.zip`: plugin que entrega os produtos reais à vitrine e prepara o carrinho WooCommerce usando os preços e o estoque do servidor.
- `release/ja-catalogo-importacao.zip`: catálogo de referência, CSV e fotos locais de 1.099 produtos. Não contém estoque real, dados fiscais ou histórico de vendas.

## Instalação

1. Preparar uma hospedagem com WordPress em português do Brasil, HTTPS, PHP 8.1 ou superior, banco MySQL/MariaDB, backups e acesso administrativo próprio.
2. Instalar WooCommerce. Configurar Brasil, BRL, endereço real da empresa, política de entrega, dados legais, página de conta, carrinho e checkout. Não inferir impostos nem cadastrar informações fiscais sem confirmação.
3. Em **Plugins → Adicionar plugin → Enviar plugin**, instalar `ja-loja-core.zip` e ativar.
4. Em **Aparência → Temas → Adicionar tema → Enviar tema**, instalar `ja-eletronicos-theme.zip` e ativar.
5. Criar uma página chamada Início e defini-la como página inicial em **Configurações → Leitura**. A página inicial usa automaticamente a vitrine personalizada. As páginas de carrinho, conta, produto e checkout usam o visual do tema.
6. Manter a instalação de testes privada enquanto produtos, estoque, fiscal, pagamentos e entrega são configurados. O modo “Em breve” do WooCommerce deve ser mantido até a validação; testes de checkout por visitantes precisam de uma instalação privada de homologação ou acesso autorizado à prévia desse modo.

## Importar o catálogo

O catálogo é uma fotografia da versão anterior, não uma integração com o sistema antigo. A quantidade de cada produto precisa ser conferida.

### Pelo painel do WooCommerce

Extrair `produtos-woocommerce.csv` do pacote de importação e usar **Produtos → Importar**. Conferir o mapeamento das colunas. Os produtos são rascunhos, com estoque zero. Os URLs das imagens apontam para o catálogo de referência no Netlify; para uma importação totalmente local, usar o comando abaixo.

### Por WP-CLI

Extrair o pacote de importação no servidor em uma pasta temporária e executar, a partir da instalação do WordPress:

```sh
wp ja import-catalog --source=/caminho/import-source/catalog.json --images=/caminho/import-source/images --dry-run
wp ja import-catalog --source=/caminho/import-source/catalog.json --images=/caminho/import-source/images
```

O importador cria produtos simples como rascunhos, com gestão de estoque habilitada e saldo zero; importa fotos locais para a biblioteca e preserva os SKUs. Reexecutá-lo não duplica nem sobrescreve os SKUs existentes. Para testar uma amostra, adicionar `--limit=8`.

Antes de publicar, confirmar preços, contar estoque, organizar variações e conferir descrições. Dados de referência não permitem inferir combinações de cor/modelo nem códigos fiscais. Uma importação de rascunhos não autoriza vendas automaticamente.

## Checkout

Na vitrine conectada, **Continuar com esta lista** substitui o carrinho WooCommerce pelos produtos e quantidades exibidos e abre o checkout. O plugin valida o produto, seu tipo, a disponibilidade e as quantidades no servidor. Valores enviados pelo navegador não definem preços. Criar a lista e abri-la no checkout não emite nota nem confirma um pagamento.

Produtos variáveis abrem a página do produto para escolher suas opções. O checkout mantém o fluxo padrão do WooCommerce, personalizado pelo tema. Frete, taxas e gateways dependem da configuração real da loja. Para pagamentos, instalar um gateway adequado e testar em modo de homologação antes de ativar transações reais.

O nonce do checkout é vinculado a uma identidade assinada por visitante. A página inicial conectada não deve ser servida por um cache público que compartilhe HTML e nonces entre visitantes; excluir a página desse cache ou adotar uma integração dinâmica de sessões. Carrinho, checkout e conta também devem ficar fora de cache público.

## Gestão JA — versão 0.2.0 em homologação

O plugin inclui um painel em `/?ja_management=1`, também acessível pelo menu Gestão JA. O login é o do WordPress. Administrador e gerente da loja têm acesso à gestão; o papel Caixa JA tem acesso restrito ao próprio caixa e às próprias vendas.

Foram acrescentados caixa, movimentações, vendas presenciais, contagem de estoque, compras, fornecedores, devolução integral, equipe e relatórios. Esses módulos têm testes de backend parciais; o painel novo e os relatórios corrigidos ainda precisam de homologação. Consulte `CONFERENCIA-REQUISITOS.md` antes de instalar para uso comercial.

## Limites desta entrega

Não inclui emissão de NF-e/NFC-e, configuração tributária, conciliação automática, TEF, devolução parcial nem funcionamento offline. Pix e cartão presenciais são registrados somente após confirmação manual do recebimento no aplicativo ou na maquininha. Os módulos de gestão novos não estão homologados para substituir o sistema atual. Nenhuma senha de funcionário é publicada ou compartilhada; a criação de acesso depende de e-mail funcional para a definição pessoal da senha.

Não existe hospedagem WordPress de produção conectada nesta sessão. A URL de prévia continua mostrando o catálogo de referência, sem pedidos, cobrança ou estoque real. Ativar WooCommerce na hospedagem deve ser uma etapa posterior; nunca inserir chaves administrativas em JavaScript público.
