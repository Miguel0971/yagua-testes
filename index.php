<?php
declare(strict_types=1);
require __DIR__.'/core.php';require __DIR__.'/work.php';require __DIR__.'/ui.php';
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self' data:; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
$error=null;$fatal=false;$user=null;
try{
if(isPostgres() && (int)sql('SELECT version FROM schema_meta')->fetchColumn()<2)throw new RuntimeException('Atualização pendente.',1002);
require __DIR__.'/cloud/session.php';startYaguaSession();
if(isset($_SESSION['yagua_last'])&&time()-$_SESSION['yagua_last']>3600){$_SESSION=[];session_regenerate_id(true);}
$_SESSION['yagua_last']=time();$_SESSION['yagua_csrf']??=bin2hex(random_bytes(32));
$user=currentUser();db();if($_SERVER['REQUEST_METHOD']==='POST')handlePost($user);
}
catch(DomainException $e){$error=$e->getMessage();if(str_contains($_SERVER['HTTP_ACCEPT']??'','application/json')){http_response_code(422);header('Content-Type: application/json');echo json_encode(['ok'=>false,'message'=>$error]);exit;}}
catch(Throwable $e){error_log('Yagua CS: '.$e->getMessage());$error=$e->getCode()===1002?'Atualização do banco pendente. Na Vercel, execute cloud/upgrade-list.sql no Neon. No SQLite local, execute migrate.php.':'Não foi possível acessar os dados. Verifique a instalação ou tente novamente.';$fatal=true;http_response_code(503);}
$admin=$user&&$user['role']==='ADMIN';$view=is_string($_GET['view']??null)?$_GET['view']:'clients';
if($user&&$user['mustChangePassword'])$view='password';
$flash=$_SESSION['flash']??null;unset($_SESSION['flash']);
$labels=['clients'=>'Clientes','task'=>'Subtarefa','tasks'=>'Minhas tarefas','agenda'=>'Agenda','team'=>'Equipe','technicians'=>'Contatos','client-edit'=>'Acompanhamento','password'=>'Minha conta','statuses'=>'Status do quadro'];
?>
<!doctype html><html lang="pt-BR" data-accent="<?=h($user['colorTheme']??'ocean')?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="description" content="Yágua CS: clientes, contatos e próximos passos em um só lugar."><meta name="csrf-token" content="<?=h($_SESSION['yagua_csrf']??'')?>"><title><?=h($labels[$view]??'Clientes')?> · Yágua CS</title><link rel="icon" href="assets/favicon.svg" type="image/svg+xml"><script src="assets/theme.js?v=5"></script><link rel="stylesheet" href="assets/app.css?v=5"><link rel="stylesheet" href="assets/theme.css?v=5"><script defer src="assets/app.js?v=5"></script></head>
<body class="<?=$user?'':'auth-body'?>">
<?php if($user):?>
<aside class="sidebar" id="sidebar"><a class="brand" href="index.php"><?=icon('drop')?><span>yágua<small>MONITORAMENTO INTELIGENTE</small></span></a><div class="workspace-label"><span class="workspace-symbol">CS</span><span>Customer Success<small>Espaço da equipe</small></span></div><nav aria-label="Navegação principal"><span class="nav-label">ACOMPANHAMENTO</span><?php foreach(['clients'=>['grid','Clientes'],'tasks'=>['tasks','Minhas tarefas'],'agenda'=>['calendar','Agenda']] as $key=>$nav):?><a href="?view=<?=h($key)?>" class="nav-link <?=($view===$key||($key==='clients'&&$view==='client-edit'))?'active':''?>"><?=icon($nav[0])?><span><?=h($nav[1])?></span></a><?php endforeach;?><?php if($admin):?><span class="nav-label admin-label">ADMINISTRAÇÃO</span><a class="nav-link <?=$view==='team'?'active':''?>" href="?view=team"><?=icon('users')?>Equipe</a><a class="nav-link <?=$view==='technicians'?'active':''?>" href="?view=technicians"><?=icon('settings')?>Contatos</a><a class="nav-link" href="?view=statuses"><?=icon('board')?>Status do quadro</a><a class="nav-link" href="?archived=1"><?=icon('archive')?>Arquivados</a><?php endif;?></nav><div class="sidebar-bottom"><div class="team-note"><?=icon('chat')?><span>Contexto compartilhado.<br>Próximos passos claros.</span></div><a class="profile" href="?view=password"><?=avatar($user['nome'],(int)$user['id'])?><span><?=h($user['nome'])?><small><?=$admin?'Administrador':'Equipe CS'?></small></span><?=icon('settings')?></a><form method="post"><?php formAction('logout');?><button class="logout"><?=icon('logout')?> Sair da conta</button></form></div></aside>
<div class="app-shell"><header class="topbar"><button class="icon-button mobile-menu" id="menu-toggle" aria-controls="sidebar" aria-expanded="false" aria-label="Abrir navegação"><?=icon('menu')?></button><div class="breadcrumb">Workspace <span>/</span> <strong><?=h($labels[$view]??'Clientes')?></strong></div><span class="top-date"><?=date('d/m/Y')?></span><form method="post" class="palette-form"><?php formAction('appearance');?><label><span class="sr-only">Cor da plataforma</span><select name="colorTheme" aria-label="Cor da plataforma"><?php options(PALETTES,$user['colorTheme']??'ocean');?></select></label><button class="button secondary compact">Aplicar cor</button></form><button type="button" class="theme-toggle" aria-label="Ativar tema escuro" aria-pressed="false">Tema escuro</button></header><main id="main">
<?php else:?><main class="auth-main"><a class="brand auth-brand" href="index.php"><?=icon('drop')?><span>yágua<small>MONITORAMENTO INTELIGENTE</small></span></a><?php endif;?>
<?php if($flash):?><div class="toast success" role="status"><?=icon('check')?><?=h($flash)?></div><?php endif;?><?php if($error):?><div class="notice error" role="alert"><?=h($error)?></div><?php endif;?>
<?php if($fatal):?><section class="panel auth-card"><span class="eyebrow">YÁGUA CS</span><h1>Vamos preparar seu espaço</h1><p>Execute a instalação descrita no LEIA-ME para conectar o banco e criar a primeira conta.</p></section>
<?php elseif(!$user):?><section class="panel auth-card"><span class="eyebrow">CUSTOMER SUCCESS</span><h1>Boas conversas.<br>Relações mais próximas.</h1><p>Acesse o espaço de acompanhamento dos clientes Yágua.</p><form method="post" class="form-stack"><?php formAction('login');?><label>Login<input name="login" required maxlength="80" autocomplete="username" value="<?=h(postVal('login'))?>" autofocus></label><label>Senha<input name="password" type="password" required maxlength="72" autocomplete="current-password"></label><button class="button primary">Entrar no workspace</button></form></section><p class="auth-footer">Yágua · Central de relacionamento</p><button type="button" class="theme-toggle" aria-label="Ativar tema escuro" aria-pressed="false">Tema escuro</button>
<?php elseif($view==='password'):?><section class="page-heading"><div><span class="eyebrow">MINHA CONTA</span><h1>Uma conta, toda a equipe por perto.</h1></div></section><section class="panel narrow"><h2>Alterar senha</h2><?php if($user['mustChangePassword']):?><p class="muted">Defina uma nova senha para concluir seu primeiro acesso.</p><?php endif;?><form method="post" class="form-stack"><?php formAction('password');?><label>Senha atual<input name="currentPassword" type="password" required autocomplete="current-password" maxlength="72"></label><label>Nova senha<input name="newPassword" type="password" required minlength="8" maxlength="72" autocomplete="new-password"><small>Pelo menos 8 caracteres; máximo de 72 bytes.</small></label><label>Confirmar nova senha<input name="confirmPassword" type="password" required minlength="8" maxlength="72" autocomplete="new-password"></label><button class="button primary">Salvar senha</button></form></section>
<?php else:try {
$users=sql('SELECT id,nome,login,role FROM users ORDER BY nome')->fetchAll();
if(in_array($view,['team','technicians','statuses'],true))requireAdmin($user);
if($view==='clients'&&isset($_GET['client']))require __DIR__.'/views/detail.php';
elseif($view==='clients')require __DIR__.'/views/clients.php';
elseif($view==='client-edit')require __DIR__.'/views/client-form.php';
elseif($view==='task')require __DIR__.'/views/task.php';
elseif($view==='tasks'||$view==='agenda')require __DIR__.'/views/worklists.php';
elseif($view==='statuses')require __DIR__.'/views/statuses.php';
elseif($view==='team'||$view==='technicians')require __DIR__.'/views/admin.php';
else throw new DomainException('Página não encontrada.');
}catch(DomainException $e){?><div class="notice error"><?=h($e->getMessage())?> <a href="index.php">Voltar aos clientes</a></div><?php }catch(Throwable $e){error_log('Yagua CS view: '.$e->getMessage());?><div class="notice error">Não foi possível carregar esta página. Tente novamente.</div><?php }endif;?>
</main><?php if($user):?></div><?php endif;?>
<dialog id="confirmation"><form method="dialog"><span class="eyebrow">CONFIRMAÇÃO</span><h2>Confirmar ação</h2><p id="confirmation-message"></p><div class="actions"><button class="button secondary" value="cancel" autofocus>Cancelar</button><button class="button danger" value="confirm">Confirmar</button></div></form></dialog><div id="live-message" class="toast floating" role="status" hidden></div>
</body></html>
