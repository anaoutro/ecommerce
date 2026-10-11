# Validação desta entrega

## Vitrine e carrossel

Testados no Chromium em larguras de 390, 768 e 1440 pixels. Busca, categorias, filtros, ordenação, favoritos, lista com quantidades e exportação CSV funcionaram sem erros JavaScript. Não houve transbordamento horizontal no celular.

As seis capas foram verificadas quanto a troca automática a cada 6,5 segundos, pausa, navegação direta e pelo teclado, gesto de deslizar, links para a categoria, preferência de movimento reduzido e altura estável no celular. O carrossel interrompe a passagem quando perde visibilidade, quando a página fica oculta e durante interação por foco ou mouse.

As 1.099 imagens dos produtos foram baixadas e seus caminhos locais foram verificados. Capas e logotipo usam WebP, e as fontes são locais, com licenças OFL incluídas. A prévia não precisa acessar o Netlify para carregar fotos.

## WordPress e WooCommerce

Testados em uma instalação local isolada com PHP 8.3 e WooCommerce 11.2.1. Foram usados produtos fictícios; a base real da empresa não foi acessada.

- Tema e plugin passaram pela verificação de sintaxe PHP.
- Vitrine carregou produtos publicados do WooCommerce, sem erros JavaScript.
- Produto sem estoque ficou indisponível para adicionar à lista.
- A lista foi transferida para o carrinho e abriu o checkout nativo, com produto e total exibidos. Produtos variáveis abriram a página de seleção das opções.
- Preço enviado pelo navegador foi ignorado; prevaleceu o preço do produto no servidor.
- Repetir a transferência não duplicou quantidades no carrinho.
- Quantidade acima do estoque, quantidade negativa e nonce inválido foram rejeitados.
- Falhas de validação não apagaram o carrinho anterior.
- Um visitante não conseguiu usar o nonce de outro visitante.
- O importador criou três rascunhos com saldo zero, com fotos na biblioteca de mídia; repetir a execução não criou duplicatas.

## Verificação ainda necessária antes de vender

Não foram configurados gateway de pagamento, frete real, tributação ou emissão fiscal. Nenhuma transação financeira foi feita. É necessário testar pedidos completos, pagamentos aprovados/recusados, cancelamentos, devoluções, reserva/baixa de estoque, entrega e configuração fiscal no ambiente real antes de abrir a loja.

A prévia em Sites continua estática e privada. A instalação WordPress usada nos testes é local; o kit não representa uma instalação de produção já conectada.

## Gestão JA — testes parciais da versão 0.2.0

Uma bateria em WordPress real passou por 30 verificações de backend: cadastro com código de barras/custo/mínimo, contagem de estoque, restrições do caixa, abertura única, venda com preço do servidor, troco, baixa única, repetição sem duplicação, rejeição de pagamento insuficiente, sangria, fornecedor, compra pendente, recebimento, custo médio, devolução integral e prevenção de repetição, fechamento com diferença, restrição de caixa alheio, reserva online e proteção de unidades reservadas.

A bateria encontrou erro na consulta do relatório ao incluir objetos de reembolso junto aos pedidos. A consulta foi corrigida para usar apenas `shop_order`; a repetição da bateria e os testes de navegador do painel novo foram interrompidos e não produziram uma validação final. As demais alterações posteriores a essa execução, incluindo pedidos online, recuperação de confirmação e exportação, ainda precisam de validação.

O código não deve ser tratado como sistema pronto para produção. Consulte a matriz em `CONFERENCIA-REQUISITOS.md`.

## Atualização de 11/10/2026 — apresentação local

A repetição da bateria de gestão passou por todas as verificações, incluindo os relatórios corrigidos e a exportação. Os testes no navegador passaram nas contas de administrador e funcionário: navegação dos módulos, venda pelo caixa, busca por código de barras, bloqueio de ajuste por funcionário, rejeição de alteração sem nonce, redirecionamento ao painel JA e layout de 390 px sem transbordamento. O painel não apresentou erros JavaScript.

Foi corrigida a montagem da URL REST para instalações com links permanentes padrão. O redirecionamento do funcionário ocorre no início da requisição administrativa, antes da verificação de acesso do painel nativo.

O pacote local foi instalado em banco vazio com uma amostra de oito produtos. WordPress, WooCommerce, tema e plugin ativaram, as traduções oficiais incluídas foram carregadas, as fotos foram importadas e os oito produtos ficaram visíveis com saldo zero. O instalador passou a criar a conta sem exibir senha em prompt, argumento ou saída. A senha gerada fica somente no computador local, fora do pacote distribuído.

A conferência final do e-mail local de definição de senha não foi executada após a interrupção da ferramenta. Backup/restauração têm scripts incluídos e ainda precisam de teste completo. Gateway online, frete real, regras tributárias, emissão fiscal, leitor físico e implantação de produção permanecem sem homologação. Os testes não acessaram dados reais do estabelecimento nem movimentaram dinheiro.
