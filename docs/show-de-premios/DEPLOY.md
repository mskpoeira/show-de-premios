# Guia de Deploy — Show de Prêmios

## Repositório Oficial
- **Repositório GitHub**: mskpoeira/show-de-premios
- **URL**: https://github.com/mskpoeira/show-de-premios.git
- **Branch de Produção**: main

## Arquitetura de Deploy
A aplicação roda encapsulada no container Docker showdepremios-app no VPS Hostinger (IP 77.37.40.81).

### Comandos de Operação no Servidor
Para iniciar ou atualizar o container do Show de Prêmios no VPS:
`ash
cd /app/sgr
# Build e inicialização do container
sudo docker compose -f docker-compose.prod.yml build showdepremios
sudo docker compose -f docker-compose.prod.yml up -d --no-deps showdepremios

# Recarregamento das rotas no Caddy
sudo docker exec sgr-proxy-prod caddy validate --config /etc/caddy/Caddyfile
sudo docker exec sgr-proxy-prod caddy reload --config /etc/caddy/Caddyfile
`

### Permissões de Storage
O entrypoint do container garante automaticamente a propriedade e permissões:
`ash
chown -R www-data:www-data /var/www/html/showdepremios/storage
chmod -R 775 /var/www/html/showdepremios/storage
`
