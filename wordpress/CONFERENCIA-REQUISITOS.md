# Conferência dos requisitos — JA Eletrônicos

Estado em 11/10/2026: **não está hospedado em WordPress de produção e ainda não está homologado para substituir o sistema atual**. Nenhuma senha, banco de dados ou histórico do estabelecimento foi acessado. Nota fiscal está fora do escopo desta etapa por decisão do proprietário do projeto. O caixa pode exigir internet, conforme resposta do usuário.

| Requisito informado | Implementação existente | Evidência e pendência |
|---|---|---|
| Design próprio, responsivo, sem aparência de tema genérico | Tema WordPress exclusivo e vitrine com carrossel de seis campanhas | Vitrine testada em Chromium em 390, 768 e 1440 px. Não instalada em produção. |
| WordPress e WooCommerce reais | Tema e plugin instalados em WordPress local, PHP 8.3 e WooCommerce 11.2.1 | Instalação de teste real. A prévia hospedada é estática; não executa WordPress. |
| Produtos, preços e checkout | Catálogo do WooCommerce, carrinho e checkout nativo com identidade visual própria | Transferência de lista, preços no servidor e restrições de estoque testados. Pagamento real ainda não configurado. |
| Vendas presenciais e online no mesmo estoque | Venda presencial cria pedido WooCommerce e usa reserva/baixa nativas | Testes locais passaram para baixa única, impedimento de excesso e preservação de estoque reservado por pedido online. |
| Caixa do funcionário | Papel Caixa JA, login WordPress, API restrita e painel próprio | Testes de permissões passaram; funcionário não altera estoque/compras e não fecha caixa de outro usuário. Painel testado no navegador em contas de administrador e funcionário. |
| Administrador vê gestão completa | Capacidade de gestão para administrador e gerente WooCommerce | API disponibiliza compras, custos, relatórios e equipe somente ao gestor. Interface navegada no teste; operação diária com o lojista ainda precisa de homologação. |
| Abertura/fechamento e dinheiro | Fundo inicial, sangria, reforço, saldo esperado, valor contado e diferença | Testes de backend passaram. Pix/cartão são confirmações manuais após verificar aplicativo/maquininha; não há integração TEF ou conciliação automática. |
| Código de barras | Campo próprio, pesquisa por código/SKU e inclusão no caixa | Cadastro validado no backend. Inclusão pela busca do código testada; leitor físico ainda não testado. |
| Estoque mínimo | Limite por produto, lista de reposição e alertas nativos WooCommerce | Campo gravado e permissões testadas. E-mail da hospedagem e entrega de alertas pendentes. |
| Entradas, saídas e inventário | Contagem física, entrada, perda e motivos no histórico | Teste de contagem passou. Proteção de unidades reservadas passou. Revisão de concorrência de todos os tipos de ajuste ainda necessária. |
| Fornecedores e compras | Cadastro, compra pendente, recebimento e custo médio ponderado | Testes passaram: compra pendente não aumenta saldo; recebimento aumenta e não duplica; custo médio correto. |
| Cancelamentos e devoluções | Devolução integral de venda presencial repõe estoque e desconta dinheiro do caixa | Testes passaram, incluindo prevenção de dupla devolução. Devolução parcial não foi implementada. Pix/cartão precisam ser devolvidos pelo meio de pagamento. |
| Relatórios e margens | Consulta de pedidos, totais e custos históricos | Consulta corrigida e repetição da bateria passou. Relatório abriu no navegador. Falta validar períodos, dados e custos reais com o lojista. |
| Gestão de funcionários | Criação de acesso por e-mail, desativação e encerramento de sessões | Código implementado. E-mail de definição de senha e fluxo completo ainda não testados. |
| Exportar dados próprios | CSV de produtos, códigos, estoque, mínimos e custos | Exportação retornou CSV no navegador e no backend. Dados reais ainda precisam de conferência. |
| Histórico de operações | Usuário, data, referência e detalhes em tabela própria | Registros criados durante testes. Auditoria do painel não cobre todas as possíveis alterações externas pelo painel nativo, plugins ou acesso ao banco. |
| Pedidos online e entrega | Tela de pedidos e alterações restritas de estado; edição completa nativa | Código novo não homologado. Endereço, retirada/frete, gateway e cenários de pagamento ainda precisam ser configurados e testados. |
| Funcionamento sem internet | Fora do escopo confirmado | Caixa exige conexão; não há sincronização offline. |
| Nota fiscal | Adiada conforme solicitado | Nenhuma emissão. O comprovante presencial está identificado como documento não fiscal. |
| Hospedagem, domínio, backup e operação real | Nenhuma hospedagem WordPress de produção conectada | Precisa de servidor PHP/MySQL, HTTPS, domínio, backup/restauração e e-mail. A hospedagem de prévia estática não atende esse requisito. |
| Migração do negócio | 1.099 produtos e fotos de referência disponíveis para importação | Não há quantidades reais, custo histórico, clientes, vendas, fornecedores ou códigos de barras do sistema antigo. Importação cria rascunhos com saldo zero; exige conferência física. |

## Versão local para apresentação

`release/ja-eletronicos-local.zip` executa WordPress, WooCommerce e o sistema em localhost, com banco persistente. A instalação em banco vazio foi testada com oito produtos de amostra. O pacote inclui os 1.099 produtos e fotos para a importação completa, sempre com quantidade inicial zero. Pagamentos locais são manuais; nenhuma cobrança ocorre. E-mails locais, backup e restauração têm configuração/scripts incluídos, mas a conferência final desses fluxos está pendente.

## Decisão

O pacote é uma implementação em desenvolvimento com funcionalidades de backend já exercitadas. **Ainda não é uma entrega operacional completa**. Os arquivos de instalação contêm código novo que precisa de homologação antes de uso comercial.

Para colocar em operação, faltam: completar a validação técnica e dos fluxos com o lojista; instalar em hospedagem WordPress; cadastrar dados reais e estoque contado; configurar pagamento, entrega, e-mail e backups; testar compra online completa, concorrência com caixa presencial, devolução e recuperação após falhas. A emissão fiscal permanece separada conforme solicitado.
