# Procedimentos de Backup e Restauração

- **Rotina Automática**: Backups gerados a cada intervalo operacional no diretório `/storage/backups/`.
- **Download do Banco**: Exportação completa do banco SQLite via painel restrito ao perfil MASTER.
- **Restauração**: Restauração controlada com validação prévia de integridade e registro na auditoria.
