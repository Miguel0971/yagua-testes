<?php
declare(strict_types=1);
/** Shared PostgreSQL sessions. A transaction lock serializes requests per session,
 * including when a transaction-mode connection pool is used. No local session files. */
final class PostgresSessions implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface {
    private PDO $connection;
    private ?string $locked=null;
    public function __construct(){ $this->connection=postgresConnection(); }
    public function open(string $path,string $name): bool {return true;}
    public function close(): bool {
        if($this->connection->inTransaction())$this->connection->commit();
        $this->locked=null;return true;
    }
    private function lock(string $id): string {
        $key=hash('sha256',$id);
        if($this->locked!==$key){
            $this->close();$this->connection->beginTransaction();
            $s=$this->connection->prepare('SELECT pg_advisory_xact_lock(724820, hashtext(?))');$s->execute([$key]);
            $this->locked=$key;
        }
        return $key;
    }
    public function read(string $id): string|false {
        $key=$this->lock($id);
        $s=$this->connection->prepare('SELECT payload FROM sessions WHERE id=? AND expires>?');$s->execute([$key,time()]);
        return $s->fetchColumn()?:'';
    }
    public function write(string $id,string $data): bool {
        $key=$this->lock($id);
        $s=$this->connection->prepare('INSERT INTO sessions(id,payload,expires) VALUES (?,?,?) ON CONFLICT(id) DO UPDATE SET payload=EXCLUDED.payload,expires=EXCLUDED.expires');
        return $s->execute([$key,$data,time()+3600]);
    }
    public function destroy(string $id): bool {
        $key=$this->lock($id);$s=$this->connection->prepare('DELETE FROM sessions WHERE id=?');$s->execute([$key]);
        return $this->close();
    }
    public function gc(int $max_lifetime): int|false {
        $s=$this->connection->prepare('DELETE FROM sessions WHERE expires<?');$s->execute([time()]);return $s->rowCount();
    }
    public function validateId(string $id): bool {
        $s=$this->connection->prepare('SELECT 1 FROM sessions WHERE id=? AND expires>?');$s->execute([hash('sha256',$id),time()]);return (bool)$s->fetchColumn();
    }
    public function updateTimestamp(string $id,string $data): bool {return $this->write($id,$data);}
}
function startYaguaSession(): void {
    ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
    ini_set('session.gc_maxlifetime','3600');ini_set('session.gc_probability','1');ini_set('session.gc_divisor','100');
    session_name('yagua_cs');
    if(isPostgres())session_set_save_handler(new PostgresSessions(),true);
    $secure=(bool)getenv('VERCEL') || getenv('YAGUA_CS_HTTPS')==='1' || (!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off');
    $path=getenv('VERCEL')?'/':rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'])),'/').'/';
    session_set_cookie_params(['httponly'=>true,'secure'=>$secure,'samesite'=>'Strict','path'=>$path]);
    if(!session_start())throw new RuntimeException('Não foi possível iniciar a sessão.');
}
