# Prova de Fogo — 14/09/2026

Versão de homologação: **0.5.0-rc.1**.

## Central de teste

A central fica em `/fire-test.php` e exige login no sistema.

Ela permite:

- cadastrar todos os eventos futuros, sem limite de data;
- ativar um evento futuro e tornar esse evento a referência do Web e do Windows sincronizado;
- encerrar eventos;
- registrar problemas manualmente com severidade, categoria, descrição, passos, resultado esperado e resultado obtido;
- registrar automaticamente erros JavaScript ocorridos dentro da própria Central de Prova de Fogo;
- acompanhar status OPEN, IN_PROGRESS, RETEST, RESOLVED e WONT_FIX;
- exportar a lista de problemas em CSV;
- imprimir a relação de problemas para conferência e assinatura.

## Windows sincronizado

A versão Windows 0.5.0-rc.1 passa a usar a aplicação Web como interface principal. Assim, eventos, cartelas, vendas, sorteios, caixa, usuários, relatórios e a Central da Prova de Fogo usam a mesma base de dados e não criam divergência entre duas bases independentes.

Se a internet cair, o cliente Windows interrompe o modo sincronizado e mostra uma tela de reconexão. Ele não cria operações paralelas offline nessa versão de homologação, justamente para impedir divergência de caixa, sorteio ou cartelas durante a prova.

## Roteiro mínimo da prova

1. Login do Master e de um Operador.
2. Abrir a Central de Prova de Fogo.
3. Cadastrar um evento futuro e ativá-lo.
4. Criar venda digital e validar pagamento.
5. Criar/registrar operação com cartela física.
6. Abrir rodada e cadastrar múltiplos prêmios.
7. Sortear manualmente, por clique e automaticamente.
8. Validar contagem 3/2/1 nas cartelas digitais.
9. Simular vencedor digital e confirmar CHECKING antes da homologação.
10. Registrar vencedor físico e testar empate misto.
11. Homologar vencedor.
12. Conferir telão sem dados pessoais.
13. Conferir caixa, relatório gerencial, vendedor e auditoria.
14. Imprimir e exportar relatórios.
15. Abrir o cliente Windows e confirmar que vê os mesmos dados criados na Web.
16. Registrar todo problema encontrado em **🧪 Prova de Fogo**.

## Critério de bloqueio

Qualquer problema CRITICAL ou HIGH relacionado a sorteio, ganhador, duplicidade, pagamento, perda de dados ou permissão deve impedir a homologação final até correção e reteste.
