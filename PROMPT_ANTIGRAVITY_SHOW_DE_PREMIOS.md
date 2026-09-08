# PROMPT MESTRE — ANTIGRAVITY

## Sistema Web “Show de Prêmios” para `mskpoeira.com.br/showdepremios`

Você é o agente responsável por **projetar, implementar, testar e preparar para implantação** um sistema web completo chamado **SHOW DE PRÊMIOS**, destinado a substituir uma planilha de controle diário de vendas, rodadas, vendedores(as), premiações, caixa, dashboard e relatórios.

O sistema deverá ser instalado de forma **isolada e segura** no endereço:

`https://mskpoeira.com.br/showdepremios`

Não altere nem quebre o restante do site `mskpoeira.com.br`.

---

## 1. REGRA PRINCIPAL DE EXECUÇÃO

Antes de programar:

1. Inspecione a estrutura atual do site/hospedagem e identifique:
   - linguagem/backend existente;
   - versão do PHP, se houver;
   - disponibilidade de MySQL/MariaDB;
   - estrutura de `public_html`;
   - `.htaccess`;
   - HTTPS;
   - Composer;
   - cron;
   - possíveis restrições de hospedagem compartilhada;
   - existência de WordPress ou outro CMS/framework.

2. Faça backup dos arquivos que eventualmente precisarem ser alterados.

3. O sistema deve ficar preferencialmente **inteiro dentro de `/showdepremios`**, sem depender do site principal.

4. Não altere arquivos do site principal, exceto se for estritamente necessário para rotear `/showdepremios`; nesse caso:
   - faça backup;
   - documente exatamente a alteração;
   - prefira sempre uma solução isolada.

5. Se a hospedagem não indicar claramente outro stack mais adequado, utilize como padrão:
   - **PHP 8.2+**
   - **MySQL/MariaDB**
   - PDO com prepared statements
   - HTML5
   - CSS responsivo
   - JavaScript moderno sem necessidade de Node em produção
   - Chart.js para gráficos
   - Dompdf para relatórios PDF, quando tecnicamente viável.

6. Se o site for WordPress, prefira ainda assim um **aplicativo independente em `/showdepremios`**, salvo se houver motivo técnico forte para integração como plugin.

7. Nunca hardcode senhas, credenciais de banco ou segredos no código versionado.

---

## 2. OBJETIVO DO SISTEMA

Criar um sistema simples de operar, visualmente limpo e confiável para controlar:

- datas de operação;
- vendedores(as);
- quantidade vendida;
- valor automático das vendas;
- rodadas;
- 1º e 2º prêmio;
- premiação sugerida para a próxima rodada;
- lucro;
- margem;
- caixa inicial;
- retiradas;
- caixa esperado;
- caixa contado;
- divergências;
- fechamento diário;
- dashboard;
- ranking de vendedores(as);
- relatório gerencial final;
- impressão;
- exportação em PDF;
- auditoria de alterações;
- backup dos dados.

O sistema deve substituir a lógica da planilha, mas **não deve parecer uma planilha complicada**. A interface deve parecer um pequeno sistema administrativo/PDV.

---

## 3. INÍCIO DO HISTÓRICO

O histórico do novo sistema começa em:

Data inicial: **06/09/2026**.

Não importar nenhum histórico anterior a essa data.

Não criar automaticamente registros anteriores.

O primeiro dia padrão será 06/09/2026 somente quando o usuário abrir ou criar esse dia.

Usar o fuso horário:

`America/Sao_Paulo`

---

## 4. PADRÃO BRASILEIRO OBRIGATÓRIO

Toda a aplicação deve usar padrão brasileiro:

- idioma: **Português do Brasil**
- datas visíveis: `dd/mm/aaaa`
- hora: `HH:mm`
- moeda: `R$ 1.234,56`
- número decimal: vírgula
- separador de milhar: ponto
- percentuais: `50,00%`
- timezone: `America/Sao_Paulo`
- moeda no banco: usar DECIMAL, nunca FLOAT
- datas no banco: formato SQL normal; converter somente na apresentação

Não exibir datas no padrão americano na interface.

---

## 5. IDENTIDADE VISUAL

Criar um visual **clean, leve, moderno e fácil de entender**.

### Paleta sugerida

- Fundo geral: branco/cinza muito claro
- Cabeçalhos: azul petróleo/azul acinzentado suave
- Destaques positivos: verde suave
- Alertas: vermelho suave
- Atenção: âmbar suave
- Campos editáveis: **amarelo claro**
- Campos automáticos/somente leitura: azul ou cinza muito claro

Não usar cores excessivamente fortes.

Não encher a tela de bordas.

Usar bastante espaço em branco.

Tipografia legível.

Ícones discretos.

### Campos editáveis

Todo campo que o usuário pode alterar deve:

- ter fundo **amarelo claro**;
- mostrar ícone discreto `✎` no label ou próximo dele;
- ter tooltip: `Campo editável`;
- possuir contraste adequado;
- ficar claramente diferente de campo automático.

Campos calculados devem ser visualmente somente leitura.

---

## 6. RESPONSIVIDADE

O sistema deve funcionar bem em:

- computador;
- notebook;
- tablet;
- celular.

Não reproduzir literalmente colunas fixas de Excel em telas pequenas.

Em desktop, usar tabelas quando fizer sentido.

Em celular, transformar linhas complexas em cartões ou layout responsivo.

Evitar rolagem horizontal desnecessária.

---

## 7. NAVEGAÇÃO PRINCIPAL

Criar menu simples e amigável:

1. **Painel**
2. **Dia Atual**
3. **Vendas**
4. **Rodadas e Prêmios**
5. **Vendedores**
6. **Fechamento de Caixa**
7. **Relatórios**
8. **Configurações**
9. **Auditoria** — somente administrador
10. **Backup** — somente administrador

No topo exibir:

- nome `Show de Prêmios`;
- data selecionada;
- usuário logado;
- botão de sair.

---

## 8. LOGIN E PERFIS

Toda a área `/showdepremios` deve exigir autenticação.

Criar perfis:

### Administrador

Pode:

- configurar sistema;
- cadastrar/inativar vendedores;
- abrir/reabrir/fechar dias;
- editar qualquer lançamento;
- gerenciar usuários;
- consultar auditoria;
- exportar backup;
- restaurar backup;
- gerar relatórios.

### Operador

Pode:

- lançar vendas;
- trabalhar com rodadas;
- informar prêmios;
- registrar caixa;
- fechar dia, se autorizado.

### Consulta

Somente leitura:

- painel;
- histórico;
- relatórios.

No primeiro acesso, criar fluxo seguro de configuração do primeiro administrador.

Não deixar login/senha padrão no código.

Após concluir a instalação, desabilitar ou remover o instalador.

---

## 9. VENDEDORES(AS)

### Comportamento inicial

Na instalação nova, a operação deve iniciar visualmente com **2 posições de vendedores(as) sem nome**.

Essas posições são apenas campos vazios da interface.

Quando o usuário informar um nome, criar o cadastro real.

### Cadastro

Campos:

- Nome
- Apelido opcional
- Status: ATIVO / INATIVO
- Data de início
- Data de inativação
- Observação opcional

### Regras

- Botão `+ Adicionar vendedor(a)`.
- Não exigir alteração de fórmula ou estrutura.
- O sistema deve crescer dinamicamente.
- Não existe limite prático de vendedores(as).
- Inativar vendedor(a) em vez de excluir.
- Vendedor(a) inativo(a) não aparece em novos lançamentos.
- Histórico antigo deve continuar intacto.
- Se alguém for inativado durante o dia, preservar vendas já lançadas.
- Permitir reativação.

Não excluir fisicamente vendedores com histórico.

---

## 10. REGRA DE VENDA — MUITO IMPORTANTE

Na interface usar sempre o termo:

Termo obrigatório: **Quantidade vendida**.

Não usar o termo “cartelas” como nome do campo principal.

### Regra inicial

- 1 unidade = **R$ 2,00**
- pacote com 3 unidades = **R$ 5,00**

O usuário informa somente a **quantidade vendida**.

O sistema calcula automaticamente o valor.

Fórmula inicial:

```text
pacotes = floor(quantidade / 3)
avulsas = quantidade % 3
valor = pacotes * 5,00 + avulsas * 2,00
```

Exemplos obrigatórios:

| Quantidade | Valor |
| ---: | ---: |
| 0 | R$ 0,00 |
| 1 | R$ 2,00 |
| 2 | R$ 4,00 |
| 3 | R$ 5,00 |
| 4 | R$ 7,00 |
| 5 | R$ 9,00 |
| 6 | R$ 10,00 |
| 7 | R$ 12,00 |
| 11 | R$ 19,00 |
| 15 | R$ 25,00 |
| 23 | R$ 39,00 |

A tela pode mostrar, em informação auxiliar:

`3 pacotes + 2 unidades avulsas`

para uma venda de 11 unidades.

---

## 11. PREÇOS PARAMETRIZÁVEIS

Em **Configurações**, criar:

### Venda avulsa

- quantidade padrão: 1
- preço: R$ 2,00

### Pacote promocional

- quantidade: 3
- preço: R$ 5,00

Permitir alterar esses valores no futuro.

### Preservação histórica

Alterar o preço no futuro **não pode mudar vendas antigas**.

Portanto:

- versionar regras de preço por vigência;
- salvar no lançamento o valor calculado;
- salvar a regra utilizada ou snapshot de preços.

Exemplo:
se em 2027 o preço mudar, vendas de 2026 continuam com seus valores originais.

---

## 12. ABERTURA DO DIA

Criar botão:

Botão: **+ Abrir novo dia**.

Ao clicar:

- sugerir a data atual;
- permitir outra data;
- impedir duplicidade;
- usar `dd/mm/aaaa`;
- carregar vendedores(as) ativos(as);
- iniciar situação `ABERTO`;
- solicitar, se desejado:
  - caixa/troco inicial;
  - responsável;
  - observação.

No Painel, uma nova data deve aparecer automaticamente após ser criada.

Não existe necessidade de “criar uma planilha” física; a data passa a ser um registro do sistema.

---

## 13. TELA “DIA ATUAL”

Exibir no topo:

- Data
- Status: ABERTO / FECHADO
- Responsável
- Caixa inicial
- Total vendido
- Quantidade total vendida
- Prêmios pagos
- Lucro
- Margem
- Caixa esperado
- Caixa contado
- Diferença

Abaixo, atalhos:

- `Lançar vendas`
- `Nova rodada`
- `Fechar rodada`
- `Fechar dia`
- `Relatório do dia`

---

## 14. LANÇAMENTO DE VENDAS

O lançamento deve ser rápido.

Campos principais:

- Data — automática pela data ativa
- Rodada
- Vendedor(a)
- **Quantidade vendida**
- Valor da venda — automático
- Regra aplicada — automática
- Observação — opcional

Permitir:

- edição;
- exclusão lógica/cancelamento;
- duplicar lançamento, se útil;
- teclado rápido;
- Enter para avançar;
- boa operação em celular.

### Alternativa operacional recomendada

Na tela de uma rodada aberta, mostrar todos os vendedores ativos em uma grade/cartões:

```text
Vendedor(a)        Quantidade vendida       Valor
Alexandre          [ 11 ]                   R$ 19,00
Silvinha           [ 15 ]                   R$ 25,00
Thaís              [  7 ]                   R$ 12,00
```

Ao alterar quantidade, recalcular imediatamente.

Botão:

Botão: **Salvar vendas da rodada**.

Isso deve gerar/atualizar os registros individuais.

---

## 15. RODADAS

Cada dia possui várias rodadas.

Campos:

- Número da rodada
- Data
- Status:
  - ABERTA
  - FECHADA
  - CANCELADA
- Total de quantidade vendida
- Total de vendas
- 1º prêmio pago
- 2º prêmio pago
- Total de prêmios
- Lucro da rodada
- Margem
- Próxima premiação sugerida
- Observação

Botão:

Botão: **+ Nova rodada**.

Numerar automaticamente.

Não permitir duas rodadas abertas simultaneamente no mesmo dia, salvo configuração administrativa específica.

---

## 16. PRÊMIOS

Trabalhar com:

- **1º Prêmio — Principal**
- **2º Prêmio — Secundário**

O 1º prêmio deve ser maior que o 2º, salvo edição administrativa consciente.

### Parâmetros iniciais

Em Configurações:

- Percentual das vendas reservado à próxima premiação: **50%**
- Percentual do 1º prêmio: **65%**
- Percentual do 2º prêmio: **35%**
- Arredondamento: **R$ 10,00**

Todos editáveis.

### Cálculo

Depois de fechar uma rodada:

```text
premiacao_total_proxima =
arredondar_para_multiplo(
    vendas_da_rodada * percentual_premiacao,
    arredondamento
)
```

Depois:

```text
primeiro_premio =
arredondar_para_multiplo(
    premiacao_total_proxima * percentual_primeiro_premio,
    arredondamento
)

segundo_premio =
premiacao_total_proxima - primeiro_premio
```

Garantir que:

- valores não sejam negativos;
- 1º prêmio seja >= 2º prêmio;
- soma dos dois seja exatamente a premiação total;
- alterações manuais fiquem registradas em auditoria.

Mostrar destaque:

### Sugestão para a próxima rodada

- 1º prêmio: R$ X
- 2º prêmio: R$ Y
- Total: R$ Z

Adicionar botão:

Botão: **Aplicar sugestão na próxima rodada**.

O sistema não deve substituir silenciosamente valor informado manualmente.

---

## 17. PRIMEIRA RODADA DO DIA

Para a primeira rodada:

- permitir informar os prêmios manualmente;
- opcionalmente mostrar a última sugestão do dia anterior;
- oferecer botão `Usar sugestão anterior`.

Criar configuração:

`Sugerir última premiação do dia anterior no início do novo dia`

Padrão: ATIVADO.

Não aplicar automaticamente sem mostrar ao usuário.

---

## 18. LUCRO E MARGEM

Por rodada:

```text
lucro = vendas - 1º prêmio - 2º prêmio
```

```text
margem = lucro / vendas
```

Se vendas = 0:

`margem = 0`

Por dia:

- soma das vendas;
- soma dos prêmios;
- lucro do dia;
- margem do dia.

Por período:

- mesmas métricas consolidadas.

Destacar prejuízo em vermelho suave.

Destacar resultado positivo em verde suave.

---

## 19. CAIXA

Criar controle completo.

Campos do dia:

- Caixa/Troco inicial
- Vendas
- Prêmios pagos
- Retiradas
- Outras entradas
- Outras saídas
- Caixa esperado
- Caixa contado
- Diferença
- Responsável pelo fechamento
- Observação

Cálculo:

```text
caixa_esperado =
caixa_inicial
+ vendas
+ outras_entradas
- premios_pagos
- retiradas
- outras_saidas
```

```text
diferenca = caixa_contado - caixa_esperado
```

Status:

- `PENDENTE` — sem caixa contado
- `OK` — diferença dentro da tolerância
- `DIVERGÊNCIA` — diferença acima da tolerância

Criar configuração:

`Tolerância de divergência de caixa`

Padrão: R$ 0,01.

Exigir justificativa para divergência antes de fechar o dia.

---

## 20. RETIRADAS E MOVIMENTAÇÕES

Não colocar apenas um número acumulado.

Criar movimentações individuais:

- data/hora;
- tipo:
  - retirada;
  - outra entrada;
  - outra saída;
- valor;
- motivo;
- usuário.

Somar automaticamente no fechamento.

Registrar tudo na auditoria.

---

## 21. FECHAMENTO DE RODADA

Ao fechar:

1. validar vendas;
2. calcular totais;
3. registrar prêmios efetivamente pagos;
4. calcular lucro e margem;
5. calcular sugestão para a próxima rodada;
6. travar edição comum da rodada;
7. permitir reabertura somente para perfil autorizado;
8. registrar auditoria.

---

## 22. FECHAMENTO DO DIA

Antes de fechar:

- não pode haver rodada aberta;
- caixa contado deve estar informado;
- divergência precisa estar resolvida ou justificada;
- responsável deve estar informado.

Ao fechar:

- gravar horário;
- gravar usuário;
- congelar os números do fechamento;
- atualizar dashboard;
- disponibilizar relatório final.

Administrador pode reabrir, mas:

- exigir motivo;
- registrar antes/depois na auditoria.

---

## 23. DASHBOARD

Criar Dashboard limpo, sem excesso de gráficos.

Filtros:

- Hoje
- Ontem
- Esta semana
- Este mês
- Período personalizado

KPIs:

1. Vendas
2. Quantidade vendida
3. Prêmios pagos
4. Lucro
5. Margem
6. Caixa inicial
7. Caixa final/contado
8. Divergência de caixa
9. Número de rodadas
10. Vendedores(as) ativos(as)
11. Venda média por rodada
12. Quantidade média por vendedor(a)

Gráficos:

- Vendas × Prêmios por dia
- Lucro por dia
- Quantidade vendida por dia
- Caixa contado/final por dia
- Vendas por vendedor(a)
- Quantidade por vendedor(a)

Ranking:

- Posição
- Vendedor(a)
- Quantidade vendida
- Valor vendido
- Participação %
- Média por dia
- Número de dias com vendas

Não poluir a tela.

Usar tooltips.

---

## 24. RELATÓRIOS

Criar filtros por:

- dia;
- período;
- vendedor(a);
- rodada;
- status do dia;
- status da rodada.

Relatórios:

### Relatório diário

- resumo do dia;
- todas as rodadas;
- vendas por vendedor(a);
- quantidade;
- prêmios;
- lucro;
- caixa;
- divergências.

### Relatório por período

- totais consolidados;
- evolução diária;
- ranking;
- melhores e piores dias;
- margem.

### Relatório por vendedor(a)

- quantidade;
- valor vendido;
- dias trabalhados;
- média;
- participação;
- posição no ranking.

### Relatório de rodadas

- rodada;
- vendas;
- quantidade;
- prêmio 1;
- prêmio 2;
- lucro;
- margem;
- sugestão seguinte.

### Relatório de caixa

- caixa inicial;
- movimentações;
- vendas;
- prêmios;
- esperado;
- contado;
- diferença.

---

## 25. RELATÓRIO GERENCIAL FINAL

Criar uma página chamada:

Nome da página: **Relatório Gerencial**.

Deve ser adequada para gerente/administrador e para arquivamento.

Cabeçalho:

- SHOW DE PRÊMIOS
- Período
- Data/hora da emissão
- Responsável pela emissão

Indicadores:

- Vendas totais
- Quantidade total vendida
- Prêmios pagos
- Lucro
- Margem
- Rodadas
- Caixa inicial
- Caixa final
- Retiradas
- Divergência

Seções:

1. Resumo executivo
2. Resultado diário
3. Resultado por rodada
4. Desempenho dos vendedores(as)
5. Ranking
6. Premiações
7. Conciliação de caixa
8. Divergências/justificativas
9. Observações
10. Responsáveis

Rodapé:

- página;
- data/hora de geração;
- nome do sistema.

---

## 26. IMPRESSÃO E PDF

Criar:

- botão `Imprimir`
- botão `Exportar PDF`

### Impressão

Usar CSS específico `@media print`.

Ao imprimir:

- esconder menu;
- esconder botões;
- esconder filtros;
- fundo branco;
- fontes legíveis;
- evitar cortes ruins entre páginas;
- cabeçalhos de tabela repetidos;
- paginação adequada.

Formato preferencial:

- A4
- relatório gerencial pode usar paisagem quando necessário.

### PDF

Preferir geração server-side com Dompdf ou equivalente confiável.

Nome do arquivo:

```text
Show_de_Premios_Relatorio_06-09-2026.pdf
```

ou para período:

```text
Show_de_Premios_Relatorio_06-09-2026_a_30-09-2026.pdf
```

O PDF deve usar padrão brasileiro.

---

## 27. EXPORTAÇÃO DE DADOS

Além do PDF, disponibilizar quando viável:

- CSV de vendas
- CSV de rodadas
- CSV de vendedores
- CSV de fechamento
- Excel `.xlsx` consolidado, se houver biblioteca compatível sem tornar a hospedagem instável

Usar UTF-8 BOM nos CSVs para boa abertura no Excel brasileiro.

Separador adequado para Excel pt-BR.

---

## 28. AUDITORIA

Criar log de auditoria.

Registrar:

- login;
- logout;
- criação;
- edição;
- cancelamento;
- fechamento;
- reabertura;
- alteração de preço;
- alteração de parâmetros;
- cadastro/inativação de vendedor;
- retirada;
- alteração de caixa;
- alteração manual de prêmio;
- restauração de backup.

Campos mínimos:

- data/hora;
- usuário;
- ação;
- entidade;
- ID;
- valor anterior;
- valor novo;
- IP;
- user-agent quando viável.

Não permitir edição do log por operador comum.

---

## 29. BACKUP

Criar área administrativa:

Área administrativa: **Backup**.

Funções:

- Exportar backup completo dos dados em JSON ou formato seguro;
- incluir versão do esquema;
- incluir data/hora;
- permitir restauração somente por administrador;
- antes da restauração, gerar backup automático do estado atual;
- exigir confirmação forte;
- validar arquivo antes de importar.

Se cron estiver disponível:

- implementar opção de backup automático diário;
- manter quantidade configurável de cópias;
- proteger backups contra acesso público.

Nunca deixar backup baixável sem autenticação.

---

## 30. ESTRUTURA DE BANCO SUGERIDA

Pode adaptar nomes, mas manter conceito equivalente.

### users

- id
- name
- email/login
- password_hash
- role
- active
- created_at
- updated_at

### sellers

- id
- name
- nickname
- active
- started_at
- ended_at
- notes
- created_at
- updated_at

### pricing_rules

- id
- effective_from
- effective_to nullable
- single_quantity
- single_price
- bundle_quantity
- bundle_price
- active
- created_at

### operation_days

- id
- operation_date UNIQUE
- status
- initial_cash
- responsible_user_id
- notes
- opened_at
- closed_at
- created_at
- updated_at

### rounds

- id
- operation_day_id
- round_number
- status
- prize_1
- prize_2
- suggested_total_next
- suggested_prize_1_next
- suggested_prize_2_next
- notes
- opened_at
- closed_at
- created_at
- updated_at
- UNIQUE(operation_day_id, round_number)

### sales

- id
- operation_day_id
- round_id
- seller_id
- quantity
- amount
- pricing_rule_id
- pricing_snapshot_json
- notes
- created_by
- created_at
- updated_at
- cancelled_at nullable

### cash_movements

- id
- operation_day_id
- type
- amount
- reason
- created_by
- created_at

### cash_closings

- id
- operation_day_id UNIQUE
- expected_cash
- counted_cash
- difference
- status
- justification
- responsible_user_id
- closed_at
- created_at
- updated_at

### settings

- key
- value
- type
- updated_by
- updated_at

### audit_logs

- id
- user_id
- action
- entity_type
- entity_id
- old_values_json
- new_values_json
- ip_address
- user_agent
- created_at

Usar índices adequados.

Usar foreign keys quando suportadas.

Usar InnoDB.

---

## 31. INTEGRIDADE DOS DADOS

Usar transações em operações críticas:

- fechamento de rodada;
- reabertura;
- fechamento do dia;
- restauração de backup.

Nunca confiar apenas em cálculos do JavaScript.

Todos os cálculos financeiros devem ser validados no servidor.

Usar DECIMAL para dinheiro.

Nunca usar FLOAT/DOUBLE para valores monetários.

---

## 32. SEGURANÇA

Implementar obrigatoriamente:

- `password_hash()` e `password_verify()`;
- preferir Argon2id quando disponível;
- fallback seguro para bcrypt;
- prepared statements;
- proteção CSRF;
- escaping contra XSS;
- validação server-side;
- cookies de sessão:
  - HttpOnly
  - Secure em HTTPS
  - SameSite=Lax ou Strict
- regenerar session ID após login;
- logout seguro;
- limite de tentativas de login;
- bloqueio temporário;
- headers de segurança;
- `X-Content-Type-Options: nosniff`;
- `X-Frame-Options` ou CSP `frame-ancestors`;
- política CSP compatível;
- `Referrer-Policy`;
- proteger `.env`;
- bloquear directory listing;
- bloquear acesso direto a arquivos internos;
- noindex/nofollow para o sistema;
- não registrar senhas em logs.

Se houver funções sensíveis, exigir confirmação.

---

## 33. USABILIDADE

O usuário não deve precisar entender banco, fórmulas ou código.

Mensagens devem ser humanas.

Exemplo:

Em vez de:

`Constraint violation`

mostrar:

`Já existe um dia aberto para 06/09/2026.`

Em vez de:

`Invalid input`

mostrar:

`Informe uma quantidade vendida válida.`

Usar confirmações apenas em ações destrutivas.

Exibir mensagens curtas de sucesso.

---

## 34. CAMPOS AMARELOS E LEGENDA

No sistema, manter uma pequena legenda discreta:

`✎ Fundo amarelo = campo que pode ser alterado`

Não repetir um texto enorme em todas as páginas.

Campos automáticos:

`Calculado automaticamente`

via tooltip ou pequeno ícone.

---

## 35. VALIDAÇÕES IMPORTANTES

- quantidade >= 0
- quantidade inteira
- dinheiro >= 0 quando aplicável
- não aceitar data inválida
- não aceitar dia duplicado
- não aceitar rodada duplicada
- não permitir venda sem vendedor
- não permitir venda em dia fechado
- não permitir venda em rodada fechada
- não permitir prêmio negativo
- não fechar dia com rodada aberta
- não apagar vendedor com histórico
- não alterar venda histórica somente porque preço atual mudou

---

## 36. TESTES AUTOMÁTICOS OBRIGATÓRIOS

Criar testes para a regra de vendas:

```text
0  => R$ 0,00
1  => R$ 2,00
2  => R$ 4,00
3  => R$ 5,00
4  => R$ 7,00
5  => R$ 9,00
6  => R$ 10,00
7  => R$ 12,00
11 => R$ 19,00
15 => R$ 25,00
23 => R$ 39,00
```

Testar:

- mudança futura de preço não altera histórico;
- vendedor inativado preserva histórico;
- fechamento de rodada;
- sugestão de prêmio;
- fechamento de caixa;
- divergência;
- fechamento e reabertura;
- permissões;
- datas pt-BR;
- exportação PDF;
- período iniciado em 06/09/2026.

---

## 37. EXPERIÊNCIA DE OPERAÇÃO DE UMA RODADA

O fluxo ideal deve exigir poucos cliques:

1. Abrir o dia.
2. Criar/abrir a rodada.
3. Informar quantidade vendida de cada vendedor(a).
4. Sistema mostra valor de cada venda automaticamente.
5. Salvar.
6. Informar 1º e 2º prêmio pagos.
7. Fechar rodada.
8. Sistema mostra:
   - vendas;
   - quantidade;
   - prêmios;
   - lucro;
   - margem;
   - sugestão de premiação da próxima rodada.
9. Botão:
   `Criar próxima rodada usando sugestão`

Esse deve ser o principal fluxo do sistema.

---

## 38. EXPERIÊNCIA DE FECHAMENTO DO DIA

Tela de fechamento deve mostrar em uma única visão:

### Operação

- vendas
- quantidade
- rodadas
- prêmios
- lucro
- margem

### Caixa

- inicial
- vendas
- outras entradas
- prêmios
- retiradas
- outras saídas
- esperado
- contado
- diferença

### Responsável

- nome
- observação
- justificativa se houver divergência

Botão final:

Botão final: **FECHAR DIA**.

Após fechar:

- mostrar sucesso;
- gerar link para relatório;
- permitir PDF.

---

## 39. PAINEL INICIAL

Ao entrar no sistema:

Se houver dia aberto:

mostrar grande:

`Dia em andamento — 06/09/2026`

com atalhos.

Se não houver:

mostrar:

`Nenhum dia aberto`

e botão:

`+ Abrir novo dia`

Abaixo:

- últimos dias;
- resultado recente;
- gráfico simples;
- alertas de divergência;
- vendedores ativos.

---

## 40. ACESSIBILIDADE

- bom contraste;
- labels de formulário;
- navegação por teclado;
- foco visível;
- botões com texto além de ícone quando importante;
- `aria-label` onde necessário;
- não depender apenas de cor para indicar erro;
- tamanho de toque adequado em celular.

---

## 41. PERFORMANCE

- evitar bibliotecas gigantes sem necessidade;
- paginação de históricos;
- índices no banco;
- consultas agregadas eficientes;
- lazy load onde fizer sentido;
- assets minificados em produção;
- cache de arquivos estáticos;
- não cachear páginas autenticadas com dados sensíveis.

---

## 42. PWA — OPCIONAL MAS DESEJÁVEL

Se não complicar a hospedagem, transformar em PWA instalável:

- manifest;
- ícone;
- tema;
- atalho na tela inicial.

Não permitir edição offline complexa se houver risco de conflito.

Se implementar offline, limitar a visualização/cache seguro e documentar.

---

## 43. INSTALAÇÃO

Criar documentação objetiva.

Fornecer:

- requisitos;
- estrutura de diretórios;
- criação do banco;
- importação/migrations;
- configuração `.env`;
- permissões necessárias;
- configuração do domínio/caminho;
- `.htaccess`;
- criação do primeiro administrador;
- como atualizar;
- como fazer backup;
- como restaurar.

Se possível, criar instalador inicial seguro.

Depois de instalado:

- bloquear o instalador.

---

## 44. ESTRUTURA DE DIRETÓRIOS

Se usar PHP independente, uma estrutura aceitável é:

```text
/showdepremios
    /app
        /Controllers
        /Models
        /Services
        /Repositories
        /Views
        /Middleware
    /config
    /database
        /migrations
        /seeds
    /public ou raiz pública controlada
    /assets
        /css
        /js
        /img
    /storage
        /logs
        /backups
        /tmp
    /vendor
    index.php
    .htaccess
    .env
    .env.example
    README.md
```

Se a hospedagem não permitir document root separado, proteger explicitamente diretórios internos com `.htaccess`.

---

## 45. MIGRAÇÕES E SEED INICIAL

Criar migrations.

Seed inicial:

Configurações:

```text
single_quantity = 1
single_price = 2.00
bundle_quantity = 3
bundle_price = 5.00
prize_pool_percent = 50
prize_1_percent = 65
prize_2_percent = 35
prize_rounding = 10.00
cash_tolerance = 0.01
locale = pt-BR
timezone = America/Sao_Paulo
history_start_date = 2026-09-06
carry_previous_prize_suggestion = true
```

Não inserir histórico de vendas.

Não inserir vendedores fictícios.

Na interface, mostrar duas posições vazias inicialmente.

---

## 46. RELATÓRIO E DASHBOARD NÃO DEVEM DEPENDER DE EXCEL

O sistema é a fonte principal.

Excel passa a ser apenas eventual formato de exportação.

Todos os números devem vir do banco de dados.

---

## 47. MELHORIAS DE SISTEMA A IMPLEMENTAR

Além do mínimo acima, incluir melhorias úteis e discretas:

- busca rápida;
- filtros persistentes por sessão;
- confirmação de fechamento;
- badges de status;
- indicador de dia aberto;
- resumo do dia sempre visível;
- ranking;
- validação de caixa;
- histórico de alterações;
- preço por vigência;
- autosave apenas onde for seguro;
- prevenção contra duplo clique;
- proteção contra envio duplicado de formulário;
- feedback visual de salvamento;
- skeleton/loading simples;
- mensagens de erro amigáveis;
- botão voltar;
- breadcrumbs somente se ajudarem;
- página 404 interna amigável;
- página de erro sem expor stack trace em produção.

---

## 48. O QUE NÃO FAZER

Não:

- importar dados anteriores a 06/09/2026;
- usar “Cartelas” como rótulo principal da quantidade;
- criar dezenas de páginas redundantes;
- usar visual escuro;
- usar cores agressivas;
- usar campos editáveis sem identificação visual;
- excluir histórico ao inativar vendedor;
- recalcular venda histórica com preço novo;
- armazenar dinheiro em FLOAT;
- confiar apenas no JavaScript;
- alterar o site principal sem necessidade;
- deixar instalador aberto;
- deixar backups públicos;
- deixar credenciais no Git;
- apresentar erros técnicos crus ao usuário;
- fazer uma interface parecida com uma planilha gigantesca.

---

## 49. CRITÉRIOS DE ACEITE

O projeto só está concluído quando:

1. `/showdepremios` abre corretamente em HTTPS.
2. Login funciona.
3. Perfis funcionam.
4. Sistema começa em 06/09/2026 sem histórico anterior.
5. Existem 2 posições iniciais vazias para vendedor(a).
6. Adicionar vendedor funciona sem alterar código.
7. Inativar vendedor preserva histórico.
8. Quantidade vendida calcula corretamente o preço.
9. 3 unidades = R$ 5,00.
10. 11 unidades = R$ 19,00.
11. Preço pode ser alterado por configuração.
12. Histórico preserva o preço antigo.
13. É possível abrir um dia.
14. É possível criar rodadas.
15. É possível lançar vendas por vendedor(a).
16. É possível fechar rodada.
17. Próxima premiação é sugerida automaticamente.
18. 1º prêmio é maior ou igual ao 2º.
19. É possível aplicar a sugestão na próxima rodada.
20. Lucro e margem estão corretos.
21. Caixa esperado está correto.
22. Caixa contado e divergência funcionam.
23. Dia pode ser fechado com validações.
24. Dashboard atualiza sem intervenção manual.
25. Ranking funciona.
26. Relatório gerencial funciona.
27. Impressão fica limpa.
28. PDF é gerado corretamente.
29. Auditoria registra ações.
30. Backup pode ser exportado.
31. Interface é responsiva.
32. Datas aparecem em `dd/mm/aaaa`.
33. Valores aparecem em `R$`.
34. Campos editáveis aparecem em amarelo claro.
35. Nenhuma senha ou segredo está hardcoded.
36. O restante de `mskpoeira.com.br` permanece funcionando.

---

## 50. ENTREGA FINAL DO ANTIGRAVITY

Ao concluir, não responda apenas “pronto”.

Entregue:

### 1. Resumo técnico

- stack utilizado;
- versão;
- banco;
- bibliotecas.

### 2. Arquivos criados

Lista dos arquivos/pastas principais.

### 3. Banco de dados

- migrations;
- tabelas;
- índices.

### 4. Segurança

Explique as proteções implementadas.

### 5. Implantação

Informe exatamente:

- onde os arquivos foram colocados;
- qual banco foi criado/usado;
- quais variáveis precisam ser preenchidas;
- se algum `.htaccess` foi alterado.

### 6. Acesso

Informe a URL:

`https://mskpoeira.com.br/showdepremios`

Não exiba senha em texto aberto.

### 7. Testes executados

Liste os testes e resultados.

### 8. Pendências

Se alguma função não pôde ser concluída por limitação da hospedagem, diga claramente:

- qual;
- por quê;
- solução recomendada.

### 9. Backup

Informe onde fica e como restaurar.

### 10. Manual rápido

Explique em no máximo 10 passos como:

- abrir dia;
- cadastrar vendedor;
- criar rodada;
- lançar quantidade;
- fechar rodada;
- aplicar prêmio seguinte;
- fechar caixa;
- fechar dia;
- consultar dashboard;
- gerar relatório PDF.

---

## 51. ORDEM DE IMPLEMENTAÇÃO

Execute por fases, testando antes de avançar:

### Fase 1

Inspeção da hospedagem e planejamento.

### Fase 2

Banco, migrations, autenticação e segurança.

### Fase 3

Vendedores, preços e configurações.

### Fase 4

Dias, rodadas e vendas.

### Fase 5

Premiações e cálculos.

### Fase 6

Caixa e fechamento.

### Fase 7

Dashboard.

### Fase 8

Relatórios, impressão e PDF.

### Fase 9

Auditoria e backup.

### Fase 10

Responsividade, acessibilidade e acabamento visual.

### Fase 11

Testes completos.

### Fase 12

Implantação em `/showdepremios`.

---

## 52. REGRA DE QUALIDADE

Não implemente apenas uma “demo”.

Entregue um sistema funcional, persistente, seguro e utilizável em produção.

Evite TODOs não resolvidos.

Evite dados fake.

Evite botões sem função.

Evite telas meramente ilustrativas.

Todos os cálculos financeiros devem funcionar com dados reais.

Faça a experiência simples o suficiente para ser usada durante a operação do Show de Prêmios sem treinamento técnico.

---

## 53. VERIFICAÇÃO FINAL OBRIGATÓRIA

Antes de encerrar:

1. Fazer login.
2. Abrir 06/09/2026.
3. Cadastrar pelo menos dois vendedores de teste.
4. Criar rodada 1.
5. Testar quantidades 11 e 15.
6. Confirmar:
   - 11 = R$ 19,00
   - 15 = R$ 25,00
7. Informar prêmios.
8. Fechar rodada.
9. Conferir próxima premiação.
10. Criar rodada 2 usando sugestão.
11. Fechar rodada 2.
12. Informar caixa.
13. Fechar dia.
14. Abrir dashboard.
15. Gerar relatório.
16. Exportar PDF.
17. Conferir auditoria.
18. Excluir os dados de teste criados para homologação OU marcar claramente como ambiente de teste, sem contaminar produção.
19. Confirmar que nenhum dado anterior a 06/09/2026 foi importado.
20. Confirmar que o site principal continua íntegro.

**Somente depois desses testes considerar a implantação concluída.**
