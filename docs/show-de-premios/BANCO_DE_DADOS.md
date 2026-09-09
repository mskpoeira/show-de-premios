# Banco de Dados — Show de Prêmios

## Arquitetura de Dados
O banco opera em SQLite encapsulado no volume Docker `showdepremios_data` (`/var/www/html/showdepremios/storage/database.sqlite`), com suporte a migração para MySQL/PostgreSQL.

### Entidades Principais
1. **events**: Evento ativo, datas, local, regras de empate e precificação.
2. **event_batches**: Lotes de cartelas com prefixo (`JDA`), numeração (`0001 a 9999`) e tipo (Aleatório Exclusivo / Grade Fixa).
3. **prizes**: 1 a 10 prêmios por rodada com regra de vitória e valores.
4. **buyers**: Cadastro do comprador com CPF e telefone normalizados (+55...) e e-mail opcional.
5. **orders & order_items**: Pedidos de compra e itens vinculados.
6. **payments**: Transações PIX (payload EMVCo TLV e CRC16-CCITT).
7. **tickets**: Cartelas oficiais com número (`AAA-0001`), código curto (`K7P4-X2MQ`) e token 128-bit.
8. **ticket_numbers**: Matriz 5x5 de números (1 a 75) e marcações de acerto (`is_hit`).
9. **ticket_game_state**: Estado de jogo em tempo real (`hits_count`, `remaining_count`).
10. **winner_events**: Registro de cartelas vencedoras e desempates simultâneos.
11. **ticket_access_logs**: Auditoria de acessos a dados pessoais de compradores (LGPD).
