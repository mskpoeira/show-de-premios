# Validação de Cartelas e Conformidade LGPD

- **Validação Pública** (`/v/{token}` e `/validar`):
  - Retorna **APENAS** o número da cartela, evento, código e situação (`CARTELA VÁLIDA` ou `CARTELA INVÁLIDA`).
  - **NUNCA** envia nome, CPF, telefone ou e-mail no HTML ou no JSON para usuários públicos.
- **Validação Autenticada** (Operador logado):
  - Retorna os dados completos do comprador e histórico de pagamento.
  - Grava evento compulsório na tabela `ticket_access_logs`.
