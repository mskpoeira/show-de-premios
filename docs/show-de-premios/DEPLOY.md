# Deploy do Show de Prêmios

## Recursos exclusivos

- diretório: `/app/show-de-premios`
- rede Docker: `showdepremios`
- contêiner: `showdepremios-app`
- banco: `/app/show-de-premios/storage/database.sqlite`
- bind HTTP padrão: `127.0.0.1:38080`

## Processo

O GitHub Actions valida a aplicação, gera o release, cria uma imagem candidata, testa migrations sobre uma cópia do banco, faz backup da base real, executa a migration e promove a nova imagem com rollback automático.

Nenhum recurso de outro projeto participa do deploy.
