# Arquitetura — Show de Prêmios

## Stack Tecnológica
- **Linguagem**: PHP 8.2
- **Servidor Web Interno**: Apache 2.4 com mod_rewrite e headers (porta 80)
- **Proxy Reverso / Borda**: Caddy Server (portas 80 e 443 com terminação TLS)
- **Banco de Dados**: SQLite 3 (PDO SQLite)
- **Local do Banco de Dados**: /var/www/html/showdepremios/storage/database.sqlite
- **Cache / Storage**: Local em volume Docker persistente showdepremios_data

## Estrutura do Caddyfile
`caddy
showdepremios.mskpoeira.com.br, www.showdepremios.mskpoeira.com.br {
    encode zstd gzip
    reverse_proxy showdepremios:80
}
`

## Rotas Principais
- / -> Redirecionamento para /login
- /login -> Tela de login administrativa e de operadores
- /telao -> Interface de exibição em tempo real do bingo / sorteio
- /telao/status -> API JSON com status da rodada, números sorteados e dados do Pix
- /admin -> Painel de controle do evento
