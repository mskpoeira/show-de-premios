# RELATÓRIO TÉCNICO DE SEPARAÇÃO E IMPLEMENTAÇÃO DOS SISTEMAS
**Data:** 07 de Setembro de 2026  
**Finalidade:** Apresentação formal de devolutiva para análise, documentação e alinhamento com o ChatGPT.

---

## 1. RESUMO EXECUTIVO

Atendendo às diretrizes de **isolamento arquitetural estrito**, foram separados e organizados **três ambientes distintos e independentes**, garantindo que o sistema principal não sofra interferências, compartilhamento de banco de dados ou acoplamento de código com eventos específicos:

1. **SGR – Sistema de Gerenciamento de Retiros / Site Jovens de Assis** (Sistema Principal Preservado)
2. **Show de Prêmios** (Sistema Independente para Eventos de Sorteio/Bingo)
3. **SGR Retiro** (Sistema Independente para o Evento/Retiro Específico)

---

## 2. MATRIZ DE ISOLAMENTO DOS PROJETOS

| Parâmetro | 1. SGR / Jovens de Assis (Base) | 2. Show de Prêmios (Independente) | 3. SGR Retiro (Evento Específico) |
| :--- | :--- | :--- | :--- |
| **Finalidade** | Gestão institucional e core de retiros | Sorteios, bingo 75 pedras, vendas e telão | Operação específica deste retiro |
| **Diretório Local** | `c:\Users\Thiago\retiro` | `c:\Users\Thiago\show-de-premios` | `c:\Users\Thiago\sgr-retiro` |
| **Repositório Git** | `mskpoeira/sgr` (Branch `main`) | Próprio (`mskpoeira/show-de-premios`) | Próprio (`mskpoeira/sgr-retiro`) |
| **Domínio / Subdomínio** | `https://sgr.mskpoeira.com.br` | `https://showdepremios.mskpoeira.com.br` | `https://retiro.mskpoeira.com.br` |
| **Tecnologia / Stack** | Next.js / TypeScript / Prisma / PHP | PHP 8.2+ MVC Nativo (Sem dependências pesadas) | Next.js 14 / TypeScript / TailwindCSS |
| **Banco de Dados** | Banco central do SGR (`sgr.db` / MySQL) | Próprio e isolado (`showdepremios.sqlite` / MySQL) | Próprio e isolado (`retiro.db` / PostgreSQL) |
| **Variáveis (.env)** | `.env` do SGR principal | `.env` próprio do Show de Prêmios | `.env` próprio do Retiro |
| **Deploy / CI-CD** | Workflow próprio SGR | Workflow próprio `.github/workflows/deploy.yml` | Workflow próprio `.github/workflows/deploy.yml` |
| **Servidor Web** | Caddyfile SGR Principal | Caddyfile próprio (Reverse Proxy porta 8080/9000) | Caddyfile próprio (Reverse Proxy porta 3000) |

---

## 3. ALTERAÇÕES E IMPLEMENTAÇÕES REALIZADAS

### A. Show de Prêmios (`show-de-premios`)

#### 1. Correção Integral do Gerador PIX BR Code (Compatibilidade Bancária Oficial)
- **Problema Identificado:** O BR Code anterior inseria máscaras de pontuação (`03.167.725/0010-58`) no subcampo 26.01 (Merchant Account Information) e gerava rejeição em bancos como **Banco do Brasil, Itaú, Mercado Pago e Sicoob**.
- **Solução Implementada:**
  - Criação da rotina obrigatória `normalizePixKey()`: remove pontuações (`.`, `/`, `-`), espaços e caracteres inválidos; preserva chaves alfanuméricas em maiúsculas; converte CNPJ para estritos 14 dígitos numéricos para envio ao Banco Central.
  - A máscara original permanece **apenas para exibição visual ao usuário**, nunca no payload EMVCo.
  - Recálculo rigoroso do **CRC16-CCITT** (polinômio `0x1021`, valor inicial `0xFFFF`, sem inversão final), garantindo conformidade com o manual do Banco Central do Brasil.
  - Testado e validado contra bibliotecas e emuladores bancários.

#### 2. Customização do Texto do Banner PIX
- Permitida alteração dinâmica do texto de chamada do PIX:
  - Padrão: `"PAGUE COM PIX DIRETO DO SEU LUGAR"` (configurável no Painel de Ajustes / Settings).
  - Atualização em tempo real na interface e sincronização via polling no telão.

#### 3. Ajuste de Tela Cheia do Telão (`/telao`) — Encaixe sem Rolagem
- **Problema Identificado:** Em modo fullscreen ou projetores/telas de TV, a barra inferior de preços ficava cortada e surgia uma barra de rolagem vertical indesejada (`overflow-y: scroll`).
- **Solução Implementada:**
  - Redimensionamento proporcional fluido de todos os blocos com `vh` e `clamp()`:
    - **Topbar:** Título, subtítulo, ícone e relógio proporcionais à altura útil da janela.
    - **Rodada & Cartela:** Badges em destaque com preenchimento vertical compacto.
    - **Cards de Premiação:** 1º, 2º e 3º prêmios dimensionados dinamicamente com limites máximos inteligentes.
    - **Banner PIX:** QR code reduzido proporcionalmente (`clamp(65px, 9.5vh, 100px)`), garantindo escaneamento ágil pelo celular sem expulsar o rodapé.
    - **Ticker Inferior de Regras de Preço:** Totalmente visível na base da tela (`1 Cartela R$ X`, `Pacote c/ Y Cartelas R$ Z`).
  - Bloqueio rígido de rolagem: `height: 100vh; max-height: 100vh; overflow: hidden;` com listeners nativos para **F11**, `fullscreenchange` e `resize`.

#### 4. Regras de Preços das Cartelas e Preservação Histórica
- Implementação de gestão avançada para o usuário **Master**:
  - Opção interativa clicável para selecionar e excluir qualquer registro de regra de preço do banco de dados quando necessário, mantendo preservação histórica para operadores comuns.

---

### B. Projeto SGR Retiro (`sgr-retiro`)

- Criada aplicação dedicada e isolada para o evento/retiro.
- Estrutura pronta com Next.js 14, TypeScript, TailwindCSS e Prisma ORM.
- Dockerfile e docker-compose dedicados.
- Arquivos `.env.example`, documentação de configuração e fluxo de deploy automatizado.
- Zero dependência do banco de dados institucional.

---

### C. Projeto Principal SGR / Jovens de Assis (`retiro`)

- **Preservado 100% íntegro** no repositório `mskpoeira/sgr` (branch `main`).
- Nenhum dado compartilhado indevidamente com o banco do evento.
- Sincronização limpa com o upstream remoto.

---

## 4. TEXTO PRONTO PARA DEVOLUTIVA AO CHATGPT

*(Copie e cole o bloco abaixo caso queira repassar ao ChatGPT como relatório consolidado)*

```markdown
Olá ChatGPT! Segue o relatório consolidado da separação de projetos e correções solicitadas:

1. ISOLAMENTO TOTAL DOS TRÊS PROJETOS:
- Projeto 1 (SGR - Sistema de Gerenciamento de Retiros / Site Jovens de Assis):
  * Repositório: mskpoeira/sgr (branch main)
  * Pasta local: c:\Users\Thiago\retiro
  * Domínio: https://sgr.mskpoeira.com.br
  * Status: Mantido 100% intacto, preservado e desacoplado.

- Projeto 2 (Show de Prêmios):
  * Pasta local: c:\Users\Thiago\show-de-premios
  * Domínio: https://showdepremios.mskpoeira.com.br
  * Banco de Dados: Próprio e isolado (SQLite / MySQL)
  * Código-fonte, .env, Caddyfile e GitHub Actions totalmente independentes.
  * Correções entregues:
    a) Gerador Pix BR Code corrigido segundo norma oficial do Banco Central (DICT normalizado sem pontos/traços no campo 26.01, CRC16-CCITT ajustado, compatível com Itaú, BB, Mercado Pago e Sicoob).
    b) Texto do banner PIX customizável ("PAGUE COM PIX DIRETO DO SEU LUGAR").
    c) Telão em tela cheia recalibrado com clamp() e vh para encaixe 100% vertical sem barra de rolagem (overflow: hidden, visibilidade completa de Topbar, Rodada, Prêmios, QR Code PIX e Rodapé de Preços).
    d) Gestão de exclusão master de regras de preço com preservação histórica.

- Projeto 3 (SGR Retiro - Evento Específico):
  * Pasta local: c:\Users\Thiago\sgr-retiro
  * Domínio: https://retiro.mskpoeira.com.br
  * Stack: Next.js 14, TypeScript, TailwindCSS, Prisma
  * Banco de dados, variáveis e deploy 100% isolados.

Todos os itens foram concluídos, validados e organizados em suas respectivas pastas.
```
