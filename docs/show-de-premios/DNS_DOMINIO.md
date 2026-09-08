# Configuração de DNS e Domínio — Show de Prêmios

## Zona DNS Autoritativa
- **Domínio Raiz**: mskpoeira.com.br
- **Servidores DNS Autoritativos**:
  - d.sec.dns.br
  - .sec.dns.br
- **Órgão Responsável**: Registro.br

## Apontamentos DNS do Subdomínio
- **Subdomínio**: showdepremios.mskpoeira.com.br
- **Tipo de Registro**: A
- **Destino / IP**: 77.37.40.81
- **TTL**: Padrão (3600s / 1 hora)

## Certificado TLS / HTTPS
- **Gerenciador de Certificado**: Caddy Server Automático (ACME / Let's Encrypt / ZeroSSL)
- **Protocolos Suportados**: TLS 1.2, TLS 1.3, HTTP/2
- **Redirecionamento HTTP -> HTTPS**: Automático via Caddy
