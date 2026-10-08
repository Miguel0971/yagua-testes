# Atualização 1.5.0: importação e permissões

Leia **ATUALIZAR-IMPORTACAO.md**. Se já usa a Lista e os status personalizados, esta revisão não exige nova migração de banco.

# Atualização: Lista, status personalizados e cores

**Já usa o aplicativo? Siga ATUALIZAR-LISTA.md antes de substituir os arquivos.** Há uma migração do banco obrigatória nesta versão. Não reinstale o sistema.

# Publicação na Vercel

Para GitHub + Vercel + PostgreSQL, siga **PUBLICAR-VERCEL.md**. Os passos abaixo continuam destinados à instalação local PHP + SQLite.

# Yágua CS — monitoramento inteligente

Aplicação independente para centralizar o acompanhamento dos clientes pelo CS, Thiago, Renan e demais pessoas da equipe. PHP 8.0+, PDO_SQLite e JavaScript sem dependências externas. Interface própria em azul, branco e ciano, com fluxo inspirado em tarefas colaborativas.


## Atualização: contatos do cliente, contatos e tema escuro

Se o app já está instalado, **não execute install.php e não apague o banco**.

1. Substitua os arquivos da pasta `/opt/lampp/htdocs/yagua-cs` pelos desta versão.
2. Atualize o banco existente como o usuário do Apache:

   ```bash
   sudo -u daemon /opt/lampp/bin/php /opt/lampp/htdocs/yagua-cs/migrate.php
   ```

   O comando cria primeiro uma cópia consistente do SQLite na pasta privada, com nome `yagua.sqlite.backup-DATA-SUFIXO`. Depois adiciona os campos que faltarem para contatos, cores de usuário e status personalizados. Clientes, contas, senhas, vínculos, histórico, tarefas e IDs são preservados. Rodar novamente apenas informa que o banco já está atualizado. O backup automático requer SQLite 3.27 ou superior; se a versão for menor, a atualização para sem alterar os dados.
3. Reabra o app. Se a página estava aberta, atualize-a antes de preencher formulários.

Para instalação nova, siga a seção de instalação abaixo; o banco já será criado na versão atual.

### Contatos no cadastro do cliente

- Em Novo cliente ou Editar acompanhamento, use **Buscar contato existente**. A pesquisa ignora maiúsculas e acentos e preserva os contatos selecionados quando você troca a busca.
- Preencha os campos de **Novo contato do cliente** e clique em **Adicionar outro contato** para incluir outras pessoas. Pode misturar contatos existentes e novos no mesmo salvamento.
- Nome é obrigatório para um novo contato preenchido. WhatsApp e e-mail são opcionais: cadastre um, ambos ou deixe-os em branco. Uma linha totalmente vazia é ignorada.
- Ao salvar, os contatos novos são criados e vinculados ao cliente na mesma transação. Qualquer erro desfaz a operação inteira, sem criar contatos soltos ou alterações parciais.
- Esses registros representam os contatos do cliente; não são contas da equipe do CS e não recebem login. Um contato existente pode ser vinculado a mais de um cliente quando necessário.
- No WhatsApp brasileiro, informe DDD e número: o sistema acrescenta 55. Para outros países, informe + seguido do código do país. Números internacionais são armazenados normalizados. Exemplo: `(11) 99999-9999` vira `5511999999999`.
- Os detalhes do cliente e a administração de contatos exibem links de WhatsApp e e-mail. Os links só abrem a conversa/composição; não enviam mensagens automaticamente.
- Edite WhatsApp e e-mail de um contato em **Contatos → Editar**. Cadastro, edição e remoção de contatos estão disponíveis para toda a equipe.
- Não há um número fixo de novos contatos na interface. Como qualquer formulário PHP, envios muito grandes dependem de `max_input_vars`, `post_max_size` e memória no servidor. O app detecta divergência na quantidade recebida e rejeita a gravação parcial; aumente esses limites ou salve em lotes quando necessário.

### Tema

Use **Tema escuro / Tema claro** no topo de qualquer página (também disponível no login). A preferência é salva apenas nesse navegador, persiste entre páginas e recargas e acompanha outras abas abertas. Sem escolha anterior, respeita a preferência do sistema. O tema muda somente a apresentação, sem alterar os dados do banco.

## O que está pronto

- **Lista editável (padrão):** clique em Status, Responsável, Prioridade ou Próximo contato para editar e salvar sem sair da lista.
- **Cores por usuário:** sete paletas no topo da página, compatíveis com tema claro e escuro.
- **Status personalizados:** administradores criam etapas com nome, cor, ordem e tipo aberto/concluído. Cada coluna do quadro abre também em lista.
- **Clientes em cards:** nome, contatos, último contato, quantidade de dias sem contato, última atualização, responsável do CS, progresso das subtarefas e próximo contato.
- **Quadro por status:** Em acompanhamento, Aguardando cliente, Ação necessária e Concluído. Arrastar cards no computador ou usar o seletor de cada card (também funciona por teclado/celular).
- **Busca e filtros:** nome parcial ou ID exato, status, responsável, clientes que precisam de contato, retornos vencidos e ordenação por tempo sem contato, atualização recente, clientes novos ou nome.
- **Acompanhamento por cliente:** descrição editável, responsável, contatos, status, prioridade, data do próximo contato e frequência de acompanhamento.
- **Atualizações em conversa:** comentários internos e registros de contato, com autor, data e conteúdo. Filtros para contatos, comentários ou todas as atividades.
- **Subtarefas:** criar, editar, atribuir responsável, definir descrição, prazo e prioridade, concluir, reabrir e remover com confirmação. Alterações entram no histórico do cliente.
- **Minhas tarefas:** subtarefas atribuídas à pessoa conectada, separadas em vencidas, hoje, próximas, sem prazo e concluídas (opcional).
- **Agenda da equipe:** próximos contatos e subtarefas por prazo, com destaque para atrasos.
- **Administração:** contas de usuários, contatos, clientes e arquivamento/restauração.
- **Persistência real:** banco SQLite privado, IDs automáticos e registros preservados; sem dados de demonstração.

## Instalação no seu XAMPP Linux

Ambiente informado: projeto público em `/opt/lampp/htdocs`, PHP em `/opt/lampp/bin/php` e Apache executando como `daemon`.

1. Extraia o ZIP e copie a pasta `yagua-cs` inteira para:

   ```text
   /opt/lampp/htdocs/yagua-cs
   ```

   O arquivo de entrada deverá ficar em `/opt/lampp/htdocs/yagua-cs/index.php`. Não precisa copiar ou modificar arquivos do painel anterior.

2. Crie a pasta privada do novo banco:

   ```bash
   sudo install -d -m 700 -o daemon -g daemon /srv/yagua-cs-private
   ```

3. Execute a instalação com o PHP e o usuário do XAMPP:

   ```bash
   sudo -u daemon /opt/lampp/bin/php /opt/lampp/htdocs/yagua-cs/install.php
   ```

4. Quando solicitar a senha inicial, digite **3321** (ou outra senha inicial de sua escolha). Será criado exclusivamente o usuário `admin`, com hash da senha e troca obrigatória no primeiro acesso.

5. Abra **`/yagua-cs/index.php` usando o domínio e a porta do seu XAMPP atual**. Exemplo apenas se esse for seu endereço: `http://localhost:8080/yagua-cs/index.php`.

6. Entre como `admin`, altere a senha e faça os cadastros:
   - Em **Equipe**, crie as contas das pessoas do CS, Thiago e Renan, com logins e senhas iniciais individuais.
   - Em **Contatos**, cadastre quem participa dos contatos contatos.
   - Em **Novo cliente**, vincule os contatos, escolha o responsável do CS e defina os próximos passos.

O caminho padrão é `/srv/yagua-cs-private/yagua.sqlite`. Neste ambiente, não é necessário alterar a configuração do Apache: o app já utiliza esse caminho. A variável antiga `CLIENT_MONITOR_DB` não interfere nesta aplicação.

### Permissões dos arquivos

O Apache precisa ler os arquivos da aplicação, sem permissão de escrita neles. Se você copiou com permissões restritas, ajuste somente a nova pasta:

```bash
sudo find /opt/lampp/htdocs/yagua-cs -type d -exec chmod 755 {} \;
sudo find /opt/lampp/htdocs/yagua-cs -type f -exec chmod 644 {} \;
```

O banco privado é diferente: a pasta deve continuar com 700 e o SQLite com 600, pertencentes a `daemon`. O instalador define 600 no arquivo do banco.

### Se já existir um banco

O instalador recusa sobrescrever o arquivo existente. Para atualizar esta aplicação, mantenha o banco, substitua os arquivos e execute migrate.php conforme a seção de atualização acima. Não execute a instalação novamente nem apague o SQLite.

Esta versão utiliza **um banco novo e independente**. Cadastros e histórico feitos no módulo anterior não são importados automaticamente; o arquivo anterior continua intacto. Se houver dados reais a aproveitar, planeje a importação antes de começar uma carteira nova.

### Outro ambiente / desenvolvimento local

É possível definir `YAGUA_CS_DB` com um caminho absoluto privado. O mesmo caminho deve estar disponível no terminal e no processo PHP que atende as páginas. O app usa o caminho padrão acima quando a variável não existe.

```bash
mkdir -p /tmp/yagua-cs-private
export YAGUA_CS_DB=/tmp/yagua-cs-private/yagua.sqlite
php install.php
php -S 127.0.0.1:8080
```

Abra `http://127.0.0.1:8080`. Esse exemplo usa armazenamento temporário e servidor de desenvolvimento; não o utilize para dados reais de produção.

Para instalação não interativa, `YAGUA_CS_INITIAL_PASSWORD` define a senha inicial somente no processo do instalador. Não mantenha essa variável no servidor web. Atrás de proxy que termina HTTPS, use `YAGUA_CS_HTTPS=1` no processo PHP para cookies Secure. O site de produção deve ser servido em HTTPS.

## Como usar no dia a dia

1. Abra Clientes e priorize os cards com maior tempo sem contato ou use **Precisam de contato**.
2. Abra um card e confira descrição, responsável, contatos e tarefas.
3. Após conversar com o cliente, publique uma atualização do tipo **Contato com cliente**, com contato e data/hora reais.
4. Para alinhar com outras pessoas da equipe, publique um **Comentário interno**.
5. Transforme pendências em subtarefas, com responsável e prazo.
6. Defina o próximo contato na edição do acompanhamento. Mova o status no quadro conforme o trabalho avança.
7. Consulte Minhas tarefas para sua fila pessoal e Agenda para a visão da equipe.

## Regras de dados

- **Contador de dias:** usa a data do contato mais recente, em dias de calendário no horário de Brasília. Um contato de hoje mostra “Contato hoje”. Nenhum registro mostra “Sem contato”. Não se confunde com zero dias.
- **Frequência:** começa em sete dias, editável de 1 a 365 por cliente. O prazo parte do último contato real; sem contatos, parte do cadastro. Uma data de próximo contato anterior ao prazo da frequência prevalece.
- **Alertas automáticos:** EM DIA antes de 60% do intervalo; ATENÇÃO em 60%; ATENÇÃO MÁXIMA em 85% ou no dia do vencimento; URGENTE a partir do dia seguinte ao vencimento. Os tons ficam progressivamente mais vermelhos, com vermelho escuro para atrasos. O cálculo usa dias de calendário em America/Sao_Paulo. Para um intervalo de sete dias: dias 0–4 em dia, dia 5 atenção, dias 6–7 atenção máxima, dia 8 em diante urgente.
- **Precisam de contato:** inclui os três níveis de alerta. Clientes concluídos ou arquivados não geram alertas. Comentários internos não reiniciam o prazo; registre um contato com o cliente para atualizar a data. Uma data de retorno vencida deve ser ajustada ou removida ao combinar o próximo acompanhamento.
- **Contatos:** na aba Contatos, é possível criar ou editar uma pessoa e adicionar um vínculo com um cliente ativo no próprio formulário. O vínculo preserva os anteriores e registra o autor no histórico do cliente.
- **Último contato:** maior `contactAt`; empates usam maior ID. Registrar um contato retroativo não reduz a data do contato mais recente.
- **Comentários e alterações de tarefa:** aparecem na atividade, mas **não alteram o último contato**.
- **Atualizações:** ordenadas pela data de publicação, mais recentes primeiro. Contatos também mostram separadamente a data em que aconteceram.
- **Horário:** entrada e exibição em America/Sao_Paulo. Instantes são guardados em UTC; prazos são datas de calendário. Contatos futuros acima de cinco minutos são rejeitados.
- **Concluir acompanhamento:** altera o status do cliente; as subtarefas continuam explícitas e não são concluídas automaticamente.
- **Histórico:** mantém IDs e snapshots dos nomes dos autores/contatos. Renomear ou remover um contato não reescreve os contatos anteriores.
- **Arquivar:** retira o cliente da carteira ativa. Dados, atualizações e tarefas permanecem no banco e podem ser restaurados por qualquer usuário da equipe.
- **Subtarefas removidas:** exclusão lógica, com ação registrada no histórico.
- **Colaboração:** edições concorrentes de cliente/subtarefa verificam a versão. Se outra pessoa salvou primeiro, o app pede recarregamento antes de sobrescrever.
- **Reenvio:** contatos, comentários e criação de subtarefas usam identificador de operação para impedir duplicação do mesmo formulário.

## Acessos

| Ação | Equipe | Admin |
| --- | --- | --- |
| Consultar clientes ativos, contatos e tarefas | Sim | Sim |
| Registrar contato ou comentário | Sim | Sim |
| Editar descrição, responsável, frequência, próximo contato, prioridade e status | Sim | Sim |
| Criar, editar, concluir, reabrir e remover subtarefas | Sim | Sim |
| Criar/renomear clientes e alterar vínculos de contatos | Sim | Sim |
| Arquivar/restaurar clientes e consultar arquivados | Sim | Sim |
| Gerenciar contatos e importar planilhas | Sim | Sim |
| Gerenciar contas e status do quadro | Não | Sim |

Contato é uma pessoa vinculada a um ou mais clientes. Responsável do CS e responsável de subtarefa são contas de usuário. Uma mesma pessoa pode ter ambos os cadastros, conforme a rotina da equipe.

## Arquivos e segurança

`index.php` controla a sessão e apresenta as páginas. `core.php` contém autenticação, acesso ao banco e cadastros administrativos. `work.php` implementa clientes, atualizações e subtarefas. `ui.php` e `views/` organizam os componentes/telas. `assets/` contém CSS, JavaScript e favicon. `schema.sql` define o banco; `install.php` e `migrate.php` executam apenas pelo terminal. A versão atual do banco SQLite é 3; a do PostgreSQL é 2. `import.php` faz a importação de clientes via planilha com prévia e confirmação.

Senhas com hash; cookies HttpOnly/SameSite; proteção CSRF; consultas parametrizadas; conteúdo escapado; sessão expira após uma hora sem atividade; cinco falhas de login por IP ou login bloqueiam novas tentativas por até 15 minutos. Troca/redefinição de senha invalida as outras sessões. O último administrador não pode ser rebaixado. Use `display_errors=Off` e `log_errors=On` em produção.

Faça backup regular do banco privado. Com a aplicação em uso, prefira a API de backup do SQLite ou o comando `.backup` da ferramenta sqlite3. Não coloque cópias do banco dentro da pasta pública.

## Testes

A suíte usa Python 3 padrão e PHP com PDO_SQLite, SimpleXML e zlib, cria um banco temporário, testa os fluxos HTTP e o remove ao terminar:

```bash
PHP_BIN=/opt/lampp/bin/php python3 tests/integration.py
```

Cobertura: autenticação, troca de senha, permissões, cadastro de cliente, busca, contato retroativo, comentário sem alterar o contador, prevenção de duplicação, status, edição concorrente, tarefas atribuídas, edição, conclusão/reabertura, remoção, agenda, autor preservado, contato removido, arquivamento/restauração, CSRF e redefinição de senha.

O código usa sintaxe de PHP 8.0. A execução automatizada disponível nesta entrega foi feita em PHP 8.3; faça também o teste acima no seu PHP 8.0.30 antes de colocar em uso.

A verificação em navegador Chromium também passou: login e troca de senha, criação de contato e cliente, contato, comentário, criação/conclusão/reabertura de subtarefa, arrastar no quadro, tarefas pessoais, agenda e confirmação de arquivamento. Foram conferidas telas desktop e mobile, inclusive ausência de rolagem horizontal indesejada. Dados usados nessa verificação eram temporários e não fazem parte da entrega.

Verificações desta atualização: migração da versão 1 com backup consistente e preservação de dados; migração idempotente; mistura de contatos existentes e novos; WhatsApp normalizado; e-mail inválido com rollback total; detecção de formulário truncado; permissões; busca com acentos; inclusão/remoção de linhas; links; tema persistente e layout mobile.

## Atualização de interface e alertas

Para quem já usa a versão com WhatsApp/e-mail e tema escuro: substitua os arquivos do aplicativo pelo pacote novo. Esta atualização não modifica o esquema do banco. Se estiver em uma versão anterior, execute `migrate.php` conforme as instruções acima. Não execute o instalador novamente. Recarregue a página após copiar os arquivos.

Teste dos limites dos alertas: `php tests/followup.php`.
