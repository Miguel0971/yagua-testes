# Atualizar o Yágua CS já publicado

Esta versão adiciona Lista editável, status personalizados e sete paletas. Preserve seu banco atual e suas variáveis da Vercel. Não execute install.php nem import-sqlite.php novamente.

## 1. Atualizar o banco no Neon antes de publicar

1. Extraia o ZIP e abra a pasta yagua-cs.
2. No painel do Neon, abra o SQL Editor e selecione o mesmo projeto, branch e banco usados pelo aplicativo (os da DATABASE_URL da Vercel).
3. Abra o arquivo `cloud/upgrade-list.sql` deste pacote em um editor de texto.
4. Copie todo o conteúdo, cole no SQL Editor e execute.
5. Aguarde a conclusão sem erros antes de seguir. Se ocorrer erro, guarde a mensagem e não publique os arquivos ainda.

O script adiciona uma tabela de status e campos para a preferência de cor e a etapa do cliente. Preserva IDs, clientes, contatos, senhas, histórico e subtarefas. Pode ser executado novamente: não duplica os status iniciais. O código anterior continua funcionando após esta migração.

Alternativa para quem já usa o terminal com DATABASE_URL configurada: `php cloud/migrate.php`.

## 2. Publicar os arquivos

1. Substitua os arquivos no repositório GitHub pelo conteúdo da pasta yagua-cs do ZIP, mantendo vercel.json e package.json na raiz que a Vercel já utiliza. Não crie uma pasta adicional dentro da raiz do projeto.
2. Envie todos os arquivos atualizados, incluindo features.php, views/, assets/ e cloud/.
3. Faça o commit e aguarde o novo deploy da Vercel concluir.
4. Abra o aplicativo e atualize com Ctrl+F5. Entre com seu login e senha atuais.

Não é necessário mudar DATABASE_URL nem criar outro banco. Se aparecer “Atualização do banco necessária”, confira se o SQL da etapa 1 foi executado no mesmo banco usado pelo deploy.

## 3. Usar as novidades

- **Lista → Cards → Quadro:** Lista é a visualização inicial. Busca e filtros continuam disponíveis.
- **Editar na lista:** clique na célula de Status, Responsável, Prioridade ou Próximo contato, altere o valor e clique em Salvar. Cancelar ou Esc fecha a edição. As mudanças entram no histórico com o autor e atualizam os indicadores. Uma alteração interna não conta como novo contato com o cliente.
- **Abrir o cliente:** clique no nome para acessar descrição, conversas e subtarefas.
- **Quadro em lista:** cada coluna tem “Ver em lista”, que mostra os clientes daquele status.
- **Status personalizados (admin):** abra “Status do quadro” no menu ou “Gerenciar status” em Clientes. Defina nome, cor, ordem e se a etapa é concluída. Quanto menor a ordem, mais à esquerda aparece a coluna.
- **Etapas concluídas:** encerram os alertas de acompanhamento do cliente. Para mudar o tipo de uma etapa personalizada já usada, mova primeiro seus clientes, incluindo os arquivados.
- **Remover etapa:** permitido apenas para etapas personalizadas sem clientes vinculados, com confirmação. As etapas originais podem ser renomeadas, reordenadas e recoloridas; seu tipo é preservado.
- **Cores:** escolha Oceano, Azul, Violeta, Rosa, Âmbar, Verde ou Grafite no topo e clique em “Aplicar cor”. A escolha é individual e acompanha a conta. Tema claro/escuro continua sendo uma preferência do navegador.
- **Urgência:** a escala vermelha dos alertas permanece independente da cor escolhida.

## Instalação local com SQLite

Se utiliza o XAMPP, substitua os arquivos e execute como o usuário do Apache:

```bash
sudo -u daemon /opt/lampp/bin/php /opt/lampp/htdocs/yagua-cs/migrate.php
```

Se seu diretório tiver outro nome, ajuste o caminho. O comando faz backup antes da migração e preserva os dados. Não use o script SQL do PostgreSQL no SQLite.

## Validação desta versão

Testes HTTP com SQLite cobrem permissões, atualização simultânea, edição das quatro colunas, cores individuais, status abertos/concluídos e preservação do histórico. A migração PostgreSQL foi executada em PGlite, inclusive repetição do script e preservação dos dados. A inspeção visual em navegador não pôde ser concluída neste ambiente; confira a aparência no seu navegador após publicar.
