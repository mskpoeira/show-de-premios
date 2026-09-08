# ARQUITETURA OFICIAL MSKPOEIRA

Existem três sistemas oficiais.

| Sistema | Domínio |
|---|---|
| Jovens de Assis | https://jovens.mskpoeira.com.br |
| SGR – Sistema de Gerenciamento de Retiros | https://sgr.mskpoeira.com.br |
| Show de Prêmios | https://showdepremios.mskpoeira.com.br |

## Jovens de Assis

Site institucional/público.

Não é SGR.

Não é Show de Prêmios.

## SGR

Sistema administrativo de gerenciamento de retiros.

Não é o site Jovens de Assis.

Não é Show de Prêmios.

## Show de Prêmios

Sistema próprio de bingo, sorteios, vendas, cartelas, prêmios, PIX e telão.

Não pertence ao SGR.

Não pertence ao site Jovens de Assis.

## Regra arquitetural

Código, bancos de dados, Docker Compose, variáveis de ambiente, volumes, backups e pipelines de deploy não devem ser compartilhados entre projetos sem uma integração formalmente documentada.

É permitido utilizar o mesmo VPS Hostinger fisicamente.

Isso NÃO significa que os sistemas devem compartilhar aplicação, container, banco, arquivos ou deploy.

Cada domínio deve ter seu próprio roteamento e serviço claramente identificado.
