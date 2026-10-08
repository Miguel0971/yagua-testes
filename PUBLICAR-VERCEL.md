# Yágua CS — GitHub + Vercel + PostgreSQL

Atualização 1.5.0: veja **ATUALIZAR-IMPORTACAO.md**. Quem já instalou a Lista não precisa migrar o banco novamente.

**Sistema já publicado:** siga ATUALIZAR-LISTA.md. Execute `cloud/upgrade-list.sql` no banco atual antes de publicar os arquivos desta versão. As etapas de instalação/importação abaixo são apenas para a primeira publicação.

Esta versão preserva o aplicativo PHP, as telas e as funcionalidades existentes. Na Vercel, usa `vercel-php@0.9.0` (runtime comunitário, PHP 8.5), Node 22 e PostgreSQL externo. Não é uma versão Next.js. Sem `DATABASE_URL`, continua disponível a instalação local em SQLite descrita no LEIA-ME.

Inclui clientes, contatos com WhatsApp/e-mail, vínculo no cadastro, histórico com autores, comentários, subtarefas, agenda, equipe, permissões, tema escuro e alertas crescentes. Banco e sessões ficam no PostgreSQL; nenhum dado depende do disco temporário da Vercel.

## 1. Preparar os arquivos

Extraia o ZIP em uma pasta nova. Entre na pasta `yagua-cs`, aquela que contém `vercel.json`, `package.json`, `api/` e `cloud/`.

Não copie o SQLite, backups ou arquivos com credenciais para essa pasta. `.gitignore` e `.vercelignore` já estão incluídos.

No Ubuntu, instale o PHP do sistema com os drivers para os comandos de instalação/importação:

```bash
sudo apt update
sudo apt install php-cli php-pgsql php-sqlite3 git
/usr/bin/php -r 'echo implode(", ", PDO::getAvailableDrivers()), PHP_EOL;'
```

A saída deve incluir `pgsql` e `sqlite`. Use `/usr/bin/php` nos comandos abaixo: o PHP do XAMPP pode não incluir PostgreSQL. Isso não altera nem reinicia seu XAMPP.

## 2. Criar um PostgreSQL externo

Crie um banco dedicado ao Yágua CS em um provedor PostgreSQL, por exemplo [Neon](https://neon.com). No painel, obtenha a URL de conexão **direta** do banco, no formato:

```text
postgresql://USUARIO:SENHA@HOST:5432/BANCO?sslmode=require
```

A URL é uma credencial. Não a envie ao GitHub nem a coloque em arquivos PHP. Use a URL pronta fornecida pelo painel; caracteres especiais da senha precisam estar codificados na URL. Se houver outros parâmetros na URL, mantenha `sslmode=require`.

Use um banco novo e vazio, sem tabelas de outras aplicações. A conexão direta é o caminho validado. O aplicativo mantém duas conexões por requisição (dados e sessão), sem conexões persistentes. Para uma equipe pequena isso simplifica a operação; monitore o limite de conexões ao ampliar o uso.

No terminal, dentro da pasta `yagua-cs`, leia a URL sem gravá-la no histórico do shell:

```bash
read -r -s -p 'Cole a URL PostgreSQL: ' DATABASE_URL
export DATABASE_URL
printf '\n'
```

Escolha **apenas uma** das opções da etapa seguinte.

## 3A. Levar seus dados atuais do XAMPP

Use esta opção se já cadastrou clientes, contatos ou conversas. Não execute o instalador de banco novo antes da importação.

Combine uma pausa de uso com a equipe para evitar registros novos no sistema antigo durante a mudança. Mantenha o banco original e seu backup. O importador lê um retrato consistente do SQLite e não escreve nele.

O caminho padrão da instalação atual é `/srv/yagua-cs-private/yagua.sqlite`. Para importar, usando a URL definida na etapa anterior:

```bash
sudo --preserve-env=DATABASE_URL /usr/bin/php cloud/import-sqlite.php /srv/yagua-cs-private/yagua.sqlite
```

O `sudo` permite ler o arquivo privado pertencente ao usuário `daemon`. Se você configurou outro caminho no sistema antigo, use esse caminho.

O comando preserva IDs, vínculos, descrições, status, prazos, subtarefas, autores históricos, nomes registrados na época e hashes de senha. Aceita as versões 1, 2 e 3 do banco do **Yágua CS independente**. Não importa diretamente o banco do GetGap antigo.

A importação acontece em uma transação: falhas desfazem os dados e tabelas criados no destino. O comando recusa um destino já preparado, inclusive depois de uma primeira importação bem-sucedida. Não mistura bases nem duplica dados.

Entre na versão online com seu login e senha atuais. Sessões antigas não são transferidas: todos precisarão entrar novamente. Depois de validar a publicação, use somente o sistema online para evitar duas bases divergentes.

## 3B. Começar sem dados

Use esta opção apenas para uma instalação nova:

```bash
/usr/bin/php cloud/install.php
```

Digite uma senha inicial de pelo menos oito caracteres quando solicitado. Ela não fica fixa no código. Será criado somente o usuário `admin`, com senha armazenada em hash e alteração obrigatória no primeiro acesso. Nenhum cliente ou contato fictício é criado.

Depois da opção 3A ou 3B:

```bash
unset DATABASE_URL
```

Guarde a URL no gerenciador de senhas; ela será usada na Vercel.

## 4. Subir o código no GitHub

Crie um repositório **privado** chamado `yagua-cs` no GitHub, inicialmente sem README. Dentro da pasta do projeto:

```bash
git init
git branch -M main
git add .
git status
git commit -m "Yagua CS preparado para Vercel e PostgreSQL"
git remote add origin https://github.com/SEU_USUARIO/yagua-cs.git
git push -u origin main
```

Troque `SEU_USUARIO` pelo seu usuário/organização. Use o método de autenticação indicado pelo GitHub (token ou SSH); a senha comum da conta não autentica o Git via HTTPS.

Na raiz do repositório devem aparecer `vercel.json`, `api/`, `cloud/`, `assets/`, `views/` e os arquivos PHP. Não envie somente o ZIP. Se decidir manter a pasta `yagua-cs` dentro do repositório, selecione essa pasta como Root Directory na Vercel.

## 5. Importar na Vercel

1. Abra [Vercel](https://vercel.com), acesse **Add New → Project** e importe o repositório.
2. Escolha **Framework Preset: Other**.
3. **Root Directory:** a pasta que contém `vercel.json` (raiz se você seguiu a etapa 4).
4. Use **Node.js 22.x**, conforme `package.json`.
5. Deixe **Build Command** e **Output Directory** sem personalização. Não use `npm run build`, `next build` ou uma pasta `dist`: este aplicativo é PHP.
6. Adicione as variáveis abaixo para o ambiente **Production**.
7. Clique em **Deploy**.

| Variável | Valor |
|---|---|
| `DATABASE_URL` | URL completa do mesmo PostgreSQL que você preparou/importou, com `sslmode=require` |
| `YAGUA_CS_HTTPS` | `1` |

Não cadastre a senha inicial de admin na Vercel. A instalação/importação já foi feita pelo terminal.

Para **Preview**, use outro banco de testes e configure a variável naquele ambiente. Não compartilhe a base de produção com previews. Sem `DATABASE_URL`, um deploy Vercel recusa o acesso aos dados; não cria um SQLite temporário silenciosamente.

O projeto é para uso empresarial. Confira um plano Vercel que permita uso comercial: o Hobby é destinado a uso pessoal não comercial. Custos do banco dependem do provedor e uso; este pacote não contrata serviços.

## 6. Conferir a publicação

Abra a URL HTTPS gerada e verifique:

- Login, troca de senha (instalação nova) e navegação após atualizar a página.
- Clientes importados, contatos, datas e histórico.
- Cadastro e vínculo de um contato, nova conversa e uma subtarefa.
- Tema escuro, cards/quadro, agenda e alertas.
- Uma conta comum pode gerenciar clientes e contatos, mas não contas de usuários nem status personalizados.
- `/core.php`, `/cloud/schema.sql`, `/cloud/install.php` e `/.env` retornam 404.

A configuração publica os cinco arquivos estáticos de `assets/` e encaminha as telas para `api/index.php`. Arquivos internos não são rotas de execução/download. Não remova a lista de rotas do `vercel.json`.

## 7. Atualizações posteriores

Depois de editar o código:

```bash
git add .
git commit -m "Atualiza Yagua CS"
git push
```

A integração GitHub/Vercel publica novos commits. O PostgreSQL externo mantém seus dados entre deploys. Não rode o instalador/importador novamente a cada publicação. Esta versão não executa migrações durante o build.

Configure backups/recuperação no provedor do PostgreSQL. Um rollback de código na Vercel não restaura dados do banco.

## Diagnóstico

| Sintoma | Verificação |
|---|---|
| `could not find driver` no terminal | Use `/usr/bin/php`; confirme `pdo_pgsql` instalado para essa versão. |
| Falha de conexão | URL, senha, nome do banco, SSL e disponibilidade do provedor. Use conexão direta. |
| Tabela `sessions` inexistente | A instalação/importação precisa ser executada no mesmo banco de `DATABASE_URL`. |
| Mensagem de dados indisponíveis | Veja os logs da função na Vercel. Não publique credenciais ao compartilhar logs. |
| Login não permanece | Confirme HTTPS e cookies habilitados; sessões são gravadas no PostgreSQL. |
| Build procura `dist` ou `next` | Use preset Other, remova personalizações de build/output e confira a pasta raiz. |
| Banco já preparado | Não refaça instalação/importação; use as credenciais existentes. Para outro teste, crie outro banco vazio. |
| Root Directory errada | `vercel.json` precisa estar na pasta escolhida. |

## Testes reproduzíveis

Em um PostgreSQL local descartável, com uma função/usuário que possa criar bancos:

```bash
PHP_BIN=/usr/bin/php TEST_PG_ADMIN_URL='postgresql://USUARIO@127.0.0.1:5432/postgres?sslmode=disable' python3 tests/postgres.py
/usr/bin/php tests/followup.php
```

`tests/postgres.py` cria bancos com nomes aleatórios `yagua_test_*`, executa os fluxos e remove somente esses bancos. Não use credenciais de produção. Os testes incluem importação, preservação de autores/hashes, sequência de IDs, recusa de sobrescrita, permissões, conversas, subtarefas, vínculo de contatos, CSRF, sessão entre processos e bloqueio de rotas internas.

Compatibilidade local SQLite:

```bash
PHP_BIN=/usr/bin/php python3 tests/integration.py
```

## Referências técnicas

- Runtime PHP comunitário: https://github.com/vercel-community/php
- GitHub/Vercel: https://vercel.com/docs/git/vercel-for-github
- Variáveis de ambiente: https://vercel.com/docs/environment-variables
- Limitação do SQLite local: https://vercel.com/kb/guide/is-sqlite-supported-in-vercel
- Planos: https://vercel.com/docs/plans/hobby

### Validação desta entrega

Testes funcionais aprovados com PostgreSQL 16 e PHP 8.3/8.5. O PHP 8.5 usado na segunda execução é o distribuído por `vercel-php@0.9.0`. O builder desse runtime também gerou a função Node 22 com os arquivos necessários. Compatibilidade SQLite e 17 cenários de alerta aprovados. O deploy em uma conta Vercel e a conexão com o seu provedor ainda precisam ser feitos seguindo este guia; os testes locais não substituem essa verificação final.

### Correção: Neon “Endpoint ID is not specified”

Atualize `cloud/database.php` para a versão deste pacote e publique um novo commit/deploy. O conector agora envia explicitamente `options=endpoint=...`, extraído do hostname Neon, para funcionar também quando o libpq do runtime não envia SNI. A URL `DATABASE_URL` existente pode ser mantida. O parâmetro explícito `options=endpoint%3D...` na URL também é reconhecido e validado. Não reinstale nem reimporte o banco para corrigir esse erro.

Teste local da configuração, sem acessar dados externos: `php tests/connection.php`. A montagem da conexão foi validada; a confirmação no seu banco ocorre ao abrir o novo deploy.
