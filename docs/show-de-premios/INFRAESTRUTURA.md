# Infraestrutura — Show de Prêmios

## Visão Geral do Ambiente de Produção

- **Aplicação**: Show de Prêmios
- **URL Oficial**: https://showdepremios.mskpoeira.com.br
- **Servidor / VPS**: Hostinger
- **Endereço IP Público do Servidor**: 77.37.40.81
- **Portas Públicas Expostas**: 
  - 80/TCP (HTTP com redirect automático para HTTPS)
  - 443/TCP (HTTPS / HTTP2)
- **Serviço de Proxy / Borda / TLS**: Caddy Server (container sgr-proxy-prod)
- **Container da Aplicação**: showdepremios-app (imagem sgr-showdepremios:latest)
- **Porta Interna da Aplicação**: 80/TCP (Apache HTTP em container PHP 8.2)
- **Upstream Caddy**: showdepremios:80
- **Banco de Dados**: SQLite (/var/www/html/showdepremios/storage/database.sqlite)
- **Volume Docker Persistente**: showdepremios_data montado em /var/www/html/showdepremios/storage

## Isolamento de Projetos

O sistema Show de Prêmios opera em seu próprio container e subdomínio dedicado, sem interferência nos outros projetos hospedados no mesmo VPS:
1. **Show de Prêmios**: showdepremios.mskpoeira.com.br -> container showdepremios-app:80
2. **SGR (Retiro)**: sgr.mskpoeira.com.br -> containers sgr-frontend-prod:3000 / sgr-backend-prod:3001
3. **Jovens de Assis**: jovens.mskpoeira.com.br -> container jovens-site-prod:3000
