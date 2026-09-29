# Show de Prêmios

Aplicação Web autocontida para operação de sorteios, vendas, rodadas, prêmios, cartelas, caixa e relatórios.

## Isolamento

Este repositório possui runtime, armazenamento e deploy próprios.

Não utiliza código, banco, volume, rede Docker, proxy, autenticação, API, workflow, navegação, redirect ou fallback de qualquer outro projeto.

## Banco

O padrão de produção é SQLite persistido exclusivamente em `storage/database.sqlite`.

## Deploy

O workflow cria e utiliza somente:
- diretório `/app/show-de-premios`;
- rede Docker `showdepremios`;
- contêiner `showdepremios-app`;
- imagens `showdepremios-app:*`;
- armazenamento `/app/show-de-premios/storage`.

O bind HTTP local padrão é `127.0.0.1:38080` e pode ser alterado pela variável `SHOW_DE_PREMIOS_BIND`.
