# Sistema Web Show de Prêmios

Sistema completo, isolado e seguro desenvolvido para operar em `https://mskpoeira.com.br/showdepremios`, substituindo o controle de planilhas por um sistema moderno, confiável e automatizado.

---

## 1. Resumo Técnico
- **Linguagem / Backend**: PHP 8.2+ estruturado, PDO com Prepared Statements e transações ACID.
- **Banco de Dados Suportado**: PostgreSQL (para ambiente VPS Hostinger / Coolify), MySQL/MariaDB ou SQLite local.
- **Frontend**: HTML5 Semântico, Vanilla CSS Responsivo com design tokens, JavaScript nativo para recálculos em tempo real.
- **Gráficos**: Chart.js para dashboards e KPIs.
- **Impressão e Relatórios**: Folhas de estilo dedicadas `@media print` para exportação de PDFs nítidos em formato A4.
- **Fuso Horário e Moeda**: `America/Sao_Paulo`, padrão monetário brasileiro `R$ 1.234,56` e datas `dd/mm/aaaa`.
- **Início do Histórico**: `06/09/2026`.

---

## 2. Estrutura de Diretórios
```
showdepremios/
├── app/
│   ├── Controllers/          # Autenticação, Painel, Dias, Rodadas, Vendas, Caixa, Relatórios, etc.
│   ├── Core/                 # Database PDO, Router, Session segura, Auth, Csrf, View, Response
│   ├── Middleware/           # AuthMiddleware, AdminMiddleware, OperatorMiddleware
│   ├── Services/             # Regras de Negócio (PricingService, PrizeSuggestionService, CashService, etc.)
│   └── Views/                # Layouts e telas operacionais com identificação de campos editáveis (amarelo)
├── database/
│   └── migrations.php        # Criação de tabelas, índices e sementes iniciais
├── public/
│   └── assets/               # CSS (style.css, print.css) e JS (app.js com calculadora em tempo real)
├── storage/
│   ├── logs/                 # Logs de erro e auditoria protegidos
│   └── backups/              # Backups em JSON com histórico pré-restauração
├── tests/
│   ├── test_rules.php        # Testes automatizados PHP
│   └── test_rules.ps1        # Harness de testes executável
├── index.php                 # Front controller principal com cabeçalhos de segurança
├── .htaccess                 # Bloqueio de pastas internas e roteamento amigável
├── .env.example              # Exemplo de configuração de banco de dados
├── Dockerfile                # Imagem de produção PHP 8.2 Apache
└── README.md                 # Manual e documentação técnica
```

---

## 3. Regras de Negócio Implementadas

### A. Regra de Vendas
- 1 unidade = R$ 2,00
- Pacote com 3 unidades = R$ 5,00
- Fórmula: `pacotes = floor(qtd / 3)`, `avulsas = qtd % 3`, `valor = (pacotes * 5,00) + (avulsas * 2,00)`
- Tabela oficial testada e validada:
  - 0 un. = R$ 0,00
  - 1 un. = R$ 2,00
  - 2 un. = R$ 4,00
  - 3 un. = R$ 5,00
  - 4 un. = R$ 7,00
  - 5 un. = R$ 9,00
  - 6 un. = R$ 10,00
  - 7 un. = R$ 12,00
  - 11 un. = R$ 19,00
  - 15 un. = R$ 25,00
  - 23 un. = R$ 39,00

### B. Sugestão de Premiações
- 50% das vendas da rodada reservadas para a próxima premiação.
- Arredondamento automático para múltiplo de R$ 10,00.
- Divisão padrão: 65% para o 1º prêmio e 35% para o 2º prêmio (1º prêmio $\ge$ 2º prêmio).
- Soma dos dois prêmios igual a 100% da premiação sugerida.

### C. Conciliação de Caixa
- `Caixa Esperado = Inicial + Vendas + Entradas - Prêmios - Retiradas - Saídas`.
- `Diferença = Contado - Esperado`.
- Tolerância padrão de R$ 0,01. Se a diferença exceder a tolerância, o sistema exige obrigatoriamente justificativa registrada em auditoria antes de fechar o dia.

---

## 4. Segurança
- Hash de senhas via Argon2id (ou bcrypt de custo elevado como fallback seguro).
- Tokens CSRF em todos os formulários POST.
- Cookies com flags `HttpOnly`, `SameSite=Lax` e `Secure` (em HTTPS).
- Proteção estrita de arquivos internos (`.env`, `storage/`, `app/`) via `.htaccess`.
- Cabeçalhos `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`.
- Trilha de auditoria imutável registrando IP, data/hora, usuário e valores anteriores/novos.

---

## 5. Roteamento e Implantação no mskpoeira.com.br

### Opção 1: No Docker Compose do Servidor (Recomendado)
Adicionar o serviço no `docker-compose.prod.yml`:
```yaml
  showdepremios:
    build:
      context: ./showdepremios
      dockerfile: Dockerfile
    container_name: showdepremios-app
    restart: always
    environment:
      - APP_BASE_URL=/showdepremios
      - DB_CONNECTION=pgsql
      - DB_HOST=db
      - DB_PORT=5432
      - DB_DATABASE=sgr_db
      - DB_USERNAME=sgr_user
      - DB_PASSWORD=sgr_secure_password
    depends_on:
      - db
```

E no `Caddyfile`:
```caddy
mskpoeira.com.br, www.mskpoeira.com.br {
    import security

    # Roteamento exclusivo do Show de Prêmios
    handle_path /showdepremios* {
        reverse_proxy showdepremios:80
    }

    # Redirecionamento das demais rotas institucionais
    handle {
        redir https://jovens.mskpoeira.com.br{uri}
    }
}
```

### Opção 2: Em Hospedagem Compartilhada / Diretório Apache
Basta copiar a pasta `showdepremios` para a raiz `public_html/showdepremios`. O `.htaccess` incluso gerencia o roteamento e a proteção automaticamente.

---

## 6. Manual Rápido de Operação (10 Passos)
1. **Primeiro Acesso**: Acesse `/showdepremios/setup` para definir a senha do administrador master.
2. **Abrir Dia**: Clique em `+ Abrir Novo Dia`, informe a data (a partir de 06/09/2026) e o caixa/troco inicial.
3. **Cadastrar Vendedores**: Acesse `Vendedores(as)` e cadastre a equipe que irá operar.
4. **Criar Rodada**: No dia aberto, clique em `+ Nova Rodada` e informe os prêmios previstos.
5. **Lançar Vendas**: Digite a quantidade vendida de cada vendedor na grade amarela (use `Enter` para avançar). O sistema calcula o valor em dinheiro instantaneamente.
6. **Salvar Vendas**: Clique em `Salvar Vendas da Rodada`.
7. **Fechar Rodada**: Ajuste os prêmios efetivamente pagos e clique em `Fechar Rodada`. O sistema exibirá o lucro da rodada e a sugestão para a próxima.
8. **Próxima Rodada**: Abra a rodada seguinte utilizando a sugestão pré-calculada.
9. **Registrar Movimentações**: Se houver sangria ou pagamento, registre em `Caixa`.
10. **Fechar Dia**: Ao término das rodadas, informe o valor contado em dinheiro na tela do dia e clique em `FECHAR DIA`. Imprima o `Relatório Gerencial` para arquivamento.
