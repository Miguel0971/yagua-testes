<?php
declare(strict_types=1);
// PHP 8.0+ com PDO_SQLite. O banco deve ficar fora da raiz pública.
date_default_timezone_set('America/Sao_Paulo');
function dbPath(): string {
    $path = getenv('YAGUA_CS_DB') ?: '/srv/yagua-cs-private/yagua.sqlite';
    if (!$path || $path[0] !== DIRECTORY_SEPARATOR) {
        throw new RuntimeException('Configure YAGUA_CS_DB com um caminho absoluto fora da raiz pública.');
    }
    return $path;
}
require_once __DIR__.'/cloud/database.php';
function db(): PDO {
    static $pdo;
    if (!$pdo) {
        if(isPostgres())return $pdo=postgresConnection();
        if(getenv('VERCEL'))throw new RuntimeException('Configure DATABASE_URL na Vercel.');
        $path = dbPath();
        if (!is_file($path)) throw new RuntimeException('Execute a instalação do módulo pelo terminal.');
        $pdo = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $pdo->exec('PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000;');
        if ((int)$pdo->query('PRAGMA user_version')->fetchColumn() < 3) throw new RuntimeException('Execute migrate.php para atualizar o banco existente.', 1002);
    }
    return $pdo;
}
function sql(string $query, array $params = []): PDOStatement {
    $s = db()->prepare(isPostgres()?postgresSql($query):$query); $s->execute($params); return $s;
}
function insertedId(string $table): int {
    if(!in_array($table,['users','clients','technicians','updates','tasks'],true))throw new LogicException('Tabela inválida.');
    return (int)(isPostgres()?sql("SELECT currval(pg_get_serial_sequence(?, 'id'))",[$table])->fetchColumn():db()->lastInsertId());
}
function nowUtc(): string { return gmdate('Y-m-d H:i:s'); }
function h(mixed $value): string { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function stamp(?string $value): string {
    if (!$value) return 'Sem contato';
    return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('d/m/Y H:i');
}
function field(array $data, string $key, int $max = 160, bool $required = true): string {
    $v = $data[$key] ?? '';
    if (!is_string($v)) throw new DomainException('Campo inválido.');
    if (!in_array($key, ['password','currentPassword','newPassword','confirmPassword'], true)) $v = trim($v);
    if (($required && $v === '') || strlen($v) > $max) throw new DomainException('Preencha os campos obrigatórios e respeite o tamanho indicado.');
    return $v;
}
function positiveId(mixed $value): int {
    $v = filter_var($value, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    if ($v === false) throw new DomainException('Identificador inválido.');
    return $v;
}
function currentUser(): ?array {
    $id = $_SESSION['yagua_user'] ?? null;
    $user = $id ? (sql('SELECT id,nome,login,role,mustChangePassword,authVersion,colorTheme FROM users WHERE id=?', [$id])->fetch() ?: null) : null;
    if ($user && (int)$user['authVersion'] !== (int)($_SESSION['yagua_auth_version'] ?? 0)) {
        unset($_SESSION['yagua_user']); return null;
    }
    return $user;
}
function requireAdmin(?array $user): void {
    if (!$user || $user['role'] !== 'ADMIN') throw new DomainException('Ação disponível somente para administradores.');
}
function redirect(string $query = ''): void { header('Location: index.php' . ($query ? '?' . $query : ''), true, 303); exit; }
function csrfInput(): void { echo '<input type="hidden" name="csrf" value="'.h($_SESSION['yagua_csrf']).'">'; }
function transaction(callable $fn): mixed {
    if(isPostgres()){
        db()->beginTransaction();
        // Mirrors SQLite's serialized writes; preserves existing version checks.
        try { sql('SELECT pg_advisory_xact_lock(724819, 1)'); }
        catch(Throwable $e){db()->rollBack();throw $e;}
    } else db()->exec('BEGIN IMMEDIATE');
    try { $result = $fn(); db()->exec('COMMIT'); return $result; }
    catch (Throwable $e) { db()->exec('ROLLBACK'); throw $e; }
}
function client(int $id): array {
    $row = sql('SELECT * FROM clients WHERE id=?', [$id])->fetch();
    if (!$row) throw new DomainException('Cliente não encontrado.');
    return $row;
}
function handlePost(?array $user): void {
    if (!isset($_POST['csrf']) || !is_string($_POST['csrf']) || !hash_equals($_SESSION['yagua_csrf'], $_POST['csrf'])) {
        http_response_code(403); throw new DomainException('Sessão expirada. Recarregue a página e tente novamente.');
    }
    $action = field($_POST, 'action', 40);
    if ($action === 'login') {
        $login = field($_POST,'login',80); $password = field($_POST,'password',72);
        $address = getenv('VERCEL') ? ($_SERVER['HTTP_X_VERCEL_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown') : ($_SERVER['REMOTE_ADDR'] ?? 'unknown'); $since = time()-900;
        sql('DELETE FROM login_attempts WHERE attemptedAt<?', [$since]);
        if ((int)sql('SELECT COUNT(*) FROM login_attempts WHERE attemptedAt>=? AND (address=? OR login=? COLLATE NOCASE)',[$since,$address,$login])->fetchColumn() >= 5) {
            throw new DomainException('Muitas tentativas. Tente novamente em 15 minutos.');
        }
        $account = sql('SELECT * FROM users WHERE login=? COLLATE NOCASE',[$login])->fetch();
        $hash = $account['passwordHash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        if (!password_verify($password,$hash) || !$account) {
            sql('INSERT INTO login_attempts(address,login,attemptedAt) VALUES (?,?,?)',[$address,$login,time()]);
            throw new DomainException('Login ou senha incorretos.');
        }
        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) sql('UPDATE users SET passwordHash=? WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$account['id']]);
        session_regenerate_id(true); $_SESSION['yagua_user']=$account['id']; $_SESSION['yagua_auth_version']=$account['authVersion']; $_SESSION['yagua_csrf']=bin2hex(random_bytes(32));
        redirect();
    }
    if (!$user) throw new DomainException('Entre para continuar.');
    if ($action === 'logout') { $_SESSION=[]; session_regenerate_id(true); redirect(); }
    if ($action === 'password') {
        $old=field($_POST,'currentPassword',72); $new=field($_POST,'newPassword',72);
        $hash=sql('SELECT passwordHash FROM users WHERE id=?',[$user['id']])->fetchColumn();
        if (!password_verify($old,$hash)) throw new DomainException('Senha atual incorreta.');
        if (strlen($new)<8 || $old===$new) throw new DomainException('Use uma nova senha com pelo menos 8 caracteres.');
        if ($new!==field($_POST,'confirmPassword',72)) throw new DomainException('A confirmação da senha não confere.');
        sql('UPDATE users SET passwordHash=?,mustChangePassword=0,authVersion=authVersion+1 WHERE id=?',[password_hash($new,PASSWORD_DEFAULT),$user['id']]);
        session_regenerate_id(true); $_SESSION['yagua_csrf']=bin2hex(random_bytes(32));
        $_SESSION['yagua_auth_version']=(int)$user['authVersion']+1;
        $_SESSION['flash']='Senha alterada.'; redirect();
    }
    if ($user['mustChangePassword']) throw new DomainException('Altere sua senha antes de continuar.');
    handleWorkAction($action, $user);
    if($action==='technician_save') {
        $id=empty($_POST['id'])?null:positiveId($_POST['id']); [$name,$whatsapp,$email]=technicianFields($_POST);
        $clientId=empty($_POST['linkClientId'])?null:positiveId($_POST['linkClientId']);
        transaction(function()use($id,$name,$whatsapp,$email,$clientId,$user){
            if($clientId)activeClient($clientId);
            if($id){
                if(!sql('SELECT id FROM technicians WHERE id=? AND deletedAt IS NULL',[$id])->fetch())throw new DomainException('Contato não encontrado.');
                sql('UPDATE technicians SET nome=?,whatsapp=?,email=? WHERE id=?',[$name,$whatsapp,$email,$id]);
                foreach(sql('SELECT clientId FROM client_technicians WHERE technicianId=?',[$id])->fetchAll() as $link){
                    activity((int)$link['clientId'],$user,'Atualizou os dados do contato '.$name.'.');
                    sql('UPDATE clients SET version=version+1 WHERE id=?',[$link['clientId']]);
                }
            }else{
                sql('INSERT INTO technicians(nome,whatsapp,email) VALUES (?,?,?)',[$name,$whatsapp,$email]);
                $id=insertedId('technicians');
            }
            if($clientId && !sql('SELECT 1 FROM client_technicians WHERE clientId=? AND technicianId=?',[$clientId,$id])->fetchColumn()){
                sql('INSERT INTO client_technicians(clientId,technicianId) VALUES (?,?)',[$clientId,$id]);
                sql('UPDATE clients SET version=version+1 WHERE id=?',[$clientId]);
                activity($clientId,$user,'Vinculou o contato '.$name.' ao cliente.');
            }
        });
        $_SESSION['flash']='Contato salvo.'; redirect('view=technicians');
    }
    if($action==='technician_delete') {
        $id=positiveId($_POST['id']??null);
        transaction(function() use($id,$user) {
            $contact=sql('SELECT nome FROM technicians WHERE id=? AND deletedAt IS NULL',[$id])->fetch();
            if(!$contact)throw new DomainException('Contato não encontrado.');
            foreach(sql('SELECT clientId FROM client_technicians WHERE technicianId=?',[$id])->fetchAll() as $link){
                activity((int)$link['clientId'],$user,'Removeu o contato '.$contact['nome'].'.');
                sql('UPDATE clients SET version=version+1 WHERE id=?',[$link['clientId']]);
            }
            sql('UPDATE technicians SET deletedAt=? WHERE id=?',[nowUtc(),$id]);
            sql('DELETE FROM client_technicians WHERE technicianId=?',[$id]);
        });
        $_SESSION['flash']='Contato removido. Registros anteriores preservados.'; redirect('view=technicians');
    }
    if($action==='user_save') {
        requireAdmin($user);
        $id=empty($_POST['id'])?null:positiveId($_POST['id']); [$name,$whatsapp,$email]=technicianFields($_POST); $login=field($_POST,'login',80); $role=field($_POST,'role',10); $pass=field($_POST,'password',72,false);
        if(!preg_match('/^[a-zA-Z0-9._-]{3,80}$/D',$login)) throw new DomainException('Login: 3 a 80 letras, números, pontos, hífens ou sublinhados.');
        if(!in_array($role,['ADMIN','USUARIO'],true)) throw new DomainException('Tipo de usuário inválido.');
        if((!$id || $pass!=='') && strlen($pass)<8) throw new DomainException('A senha deve ter pelo menos 8 caracteres.');
        transaction(function() use($id,$name,$login,$role,$pass) {
            if(sql('SELECT id FROM users WHERE login=? COLLATE NOCASE AND id<>?',[$login,$id??0])->fetch()) throw new DomainException('Este login já está em uso.');
            if($id) {
                $existing=sql('SELECT * FROM users WHERE id=?',[$id])->fetch();
                if(!$existing) throw new DomainException('Usuário não encontrado.');
                if($existing['role']==='ADMIN' && $role!=='ADMIN' && (int)sql("SELECT COUNT(*) FROM users WHERE role='ADMIN'")->fetchColumn()<=1) throw new DomainException('Mantenha pelo menos um administrador.');
                sql('UPDATE users SET nome=?,login=?,role=? WHERE id=?',[$name,$login,$role,$id]);
                if($pass!=='') sql('UPDATE users SET passwordHash=?,mustChangePassword=1,authVersion=authVersion+1 WHERE id=?',[password_hash($pass,PASSWORD_DEFAULT),$id]);
            } else sql('INSERT INTO users(nome,login,passwordHash,role,mustChangePassword) VALUES (?,?,?,?,1)',[$name,$login,password_hash($pass,PASSWORD_DEFAULT),$role]);
        });
        $_SESSION['flash']='Usuário salvo.'; redirect('view=team');
    }
    throw new DomainException('Ação inválida.');
}
