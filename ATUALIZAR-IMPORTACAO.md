# Atualização 1.5.0

## Publicar

Se o aplicativo já está na versão com Lista e status personalizados:

1. Extraia o ZIP.
2. Substitua os arquivos do repositório pelo conteúdo da pasta yagua-cs, na mesma raiz do projeto.
3. Inclua a nova pasta vendor/, import.php, views/import.php, o modelo em assets/ e o vercel.json atualizado. A biblioteca Excel já está incluída, não precisa executar Composer.
4. Faça o commit, aguarde o deploy e recarregue com Ctrl+F5.

Não há nova migração de banco, reinstalação ou mudança de senha. Se ainda não instalou a versão com Lista e status personalizados, siga primeiro a migração descrita em ATUALIZAR-LISTA.md.

## Importação

Em Clientes → Importar planilha, baixe o modelo CSV ou envie sua planilha Excel .xlsx. Arquivos .xls antigos precisam ser salvos como .xlsx ou CSV UTF-8. Use até 500 linhas por arquivo, 1 MB, primeira aba do Excel.

Colunas aceitas, em qualquer ordem:

nome_cliente, descricao, tec_responsavel, whatsapp_responsavel, email_responsavel, dias_de_contato, prox_contato

A coluna nome_cliente deve existir. As demais são opcionais. Células vazias, null, nulo ou NaN são ignoradas. Uma linha sem nome é pulada. Sem intervalo válido, usa 7 dias; sem data válida, não agenda o próximo contato. Datas aceitas: data do Excel, dd/mm/aaaa ou aaaa-mm-dd. Telefones devem estar como texto para preservar os dígitos.

O contato indicado em tec_responsavel pertence ao cliente; não cria uma conta de usuário nem define o responsável interno do CS. Se o nome do contato estiver vazio, WhatsApp/e-mail são ignorados com aviso. Cada linha cria um cliente e, quando informado, um contato próprio; nomes de contatos não são usados para mesclar pessoas.

A prévia mostra os valores que serão salvos e os avisos de campos inválidos. Nada é criado antes de Confirmar importação. Campos opcionais inválidos são ignorados, sem impedir os demais dados. Se houver um erro de banco durante a gravação, o lote inteiro é desfeito.

Por padrão, nomes de clientes já cadastrados são ignorados (inclusive arquivados), assim como repetições dentro do lote. Desmarque essa opção somente se quiser cadastros distintos com o mesmo nome. A importação não atualiza clientes existentes.

A operação grava o usuário no histórico e impede duplicação por reenvio da mesma confirmação. Ela não registra uma conversa nem reinicia artificialmente o contador de último contato. O resultado por linha fica visível ao finalizar. A prévia expira em 20 minutos.

Não são executadas fórmulas ou macros. Prefira somente valores: uma fórmula sem resultado salvo pode aparecer vazia. O Excel exige SimpleXML e zlib no PHP; o CSV continua disponível se essas extensões faltarem. No XAMPP, habilite SimpleXML se necessário.

## Permissões e remoção

Todos os usuários autenticados podem criar, importar, editar e remover clientes e contatos, além de consultar e restaurar clientes arquivados. Apenas administradores gerenciam contas e status do quadro.

O botão Excluir cliente aparece nos Cards e no Quadro. Nos detalhes também é possível excluir. A ação pede confirmação e move o cliente para Arquivados: histórico e subtarefas permanecem disponíveis. Remover um contato mantém a identificação dele nos registros anteriores, mesmo que fosse o único contato ativo do cliente.

## Visual

Navegação neutra, títulos diretos, métricas compactas, menos sombras e arredondamentos. As paletas individuais, tema escuro, urgências e Lista de linha única foram preservados.

## Validação

Testes HTTP com PHP e SQLite aprovados: importação CSV/Excel, datas, valores nulos e inválidos, duplicados, confirmação sem reenvio, criação/edição/remoção por usuário comum, histórico preservado, contas/status restritos a administradores e fluxos anteriores. Não houve alteração no esquema de PostgreSQL. Não foi possível fazer inspeção visual em navegador neste ambiente.
