# Regras de Negócio — Show de Prêmios

1. **Numeração das Cartelas**:
   - Formato rígido `AAA-0001` a `AAA-9999`.
   - Transação atômica no banco para impedir duplicidades.
   - Ao atingir 9999, um novo lote com outro prefixo deve ser aberto.

2. **Habilitação de Cartelas**:
   - Apenas cartelas com status `VALID` ou `AWARDED` concorrem no sorteio.
   - Cartelas `RESERVED`, `PENDING`, `CANCELLED`, `REFUNDED` ou `INVALID` NUNCA participam ou ganham prêmios.

3. **Empate Simultâneo**:
   - Se duas ou mais cartelas completarem a condição de vitória na mesma pedra chamada, ambas são registradas como vencedoras simultâneas.
   - O software nunca define vencedor pela ordem interna de processamento.
