"""PostgreSQL integration. Creates/drops ONLY uniquely named test databases.
PHP_BIN=php TEST_PG_ADMIN_URL=postgresql://.../postgres python3 tests/postgres.py
Requires a local test PostgreSQL role with CREATEDB. Never use a production URL.
"""
import os,json,subprocess,tempfile,pathlib,socket,urllib.request,time,unittest,sqlite3,secrets
import integration
ROOT=integration.ROOT
PHP=integration.PHP
ADMIN=os.environ.get('TEST_PG_ADMIN_URL','')
def query(url,sql,params=(),mode='scalar'):
 r=subprocess.run([PHP,str(ROOT/'tests/pg-query.php')],input=json.dumps({'sql':sql,'params':params,'mode':mode}),capture_output=True,text=True,env={**os.environ,'DATABASE_URL':url},check=True)
 return json.loads(r.stdout)
def urlFor(name):
 from urllib.parse import urlsplit,urlunsplit
 u=urlsplit(ADMIN);return urlunsplit((u.scheme,u.netloc,'/'+name,u.query,''))
class PostgresWorkflow(integration.Integration):
 @classmethod
 def setUpClass(cls):
  if not ADMIN:raise unittest.SkipTest('Defina TEST_PG_ADMIN_URL para um PostgreSQL local de testes.')
  cls.name='yagua_test_'+secrets.token_hex(6);cls.database=urlFor(cls.name)
  query(ADMIN,'CREATE DATABASE '+cls.name)
  cls.temp=tempfile.TemporaryDirectory();path=cls.temp.name+'/source.sqlite'
  env={**os.environ,'YAGUA_CS_DB':path,'YAGUA_CS_INITIAL_PASSWORD':'3321'};env.pop('DATABASE_URL',None)
  subprocess.run([PHP,str(ROOT/'install.php')],env=env,check=True,capture_output=True)
  cls.env={**os.environ,'DATABASE_URL':cls.database};cls.env.pop('YAGUA_CS_HTTPS',None);cls.env.pop('VERCEL',None)
  subprocess.run([PHP,str(ROOT/'cloud/import-sqlite.php'),path],env=cls.env,check=True,capture_output=True)
  with socket.socket() as s:s.bind(('127.0.0.1',0));port=s.getsockname()[1]
  cls.log=open(cls.temp.name+'/server.log','w+')
  cls.server=subprocess.Popen([PHP,'-S',f'127.0.0.1:{port}','-t',str(ROOT),str(ROOT/'tests/router.php')],env=cls.env,stdout=cls.log,stderr=cls.log)
  cls.url=f'http://127.0.0.1:{port}/index.php'
  for _ in range(50):
   try:urllib.request.urlopen(cls.url);break
   except OSError:time.sleep(.05)
 @classmethod
 def tearDownClass(cls):
  try:super().tearDownClass()
  finally:query(ADMIN,'DROP DATABASE '+cls.name+' WITH (FORCE)')
 def scalar(self,q,params=()):return query(self.database,q,params)
 def test_migration_preserves_existing_data(self):
  # The inherited test checks the local v1→v2 migration separately.
  super().test_migration_preserves_existing_data()
 def test_z_shared_session_and_private_routes(self):
  a=integration.Browser(self.url);a.go();a.post('login',login='admin',password='Admin-test-123')
  self.assertIn('Clientes',a.page)
  self.assertGreater(self.scalar('SELECT COUNT(*) FROM sessions'),0)
  # Second independent PHP process has no access to any local session directory.
  with socket.socket() as s:s.bind(('127.0.0.1',0));port=s.getsockname()[1]
  second=subprocess.Popen([PHP,'-d','session.save_path=/nonexistent','-S',f'127.0.0.1:{port}','-t',str(ROOT),str(ROOT/'tests/router.php')],env=self.env,stdout=self.log,stderr=self.log)
  try:
   for _ in range(50):
    try:urllib.request.urlopen(f'http://127.0.0.1:{port}/');break
    except OSError:time.sleep(.05)
   a.base=f'http://127.0.0.1:{port}/index.php';a.go();self.assertIn('Sair da conta',a.page)
   for path in ['/core.php','/cloud/schema.sql','/cloud/install.php','/.env','/tests/pg-query.php']:
    a.base=f'http://127.0.0.1:{port}'+path;a.go();self.assertEqual(a.status,404,path)
  finally:second.terminate();second.wait()
class ImportAndInstall(unittest.TestCase):
 def setUp(self):
  if not ADMIN:raise unittest.SkipTest('Defina TEST_PG_ADMIN_URL.')
  self.name='yagua_test_'+secrets.token_hex(6);self.url=urlFor(self.name);query(ADMIN,'CREATE DATABASE '+self.name)
  self.env={**os.environ,'DATABASE_URL':self.url,'YAGUA_CS_INITIAL_PASSWORD':'Initial-test-123'}
 def tearDown(self):query(ADMIN,'DROP DATABASE '+self.name+' WITH (FORCE)')
 def test_install_is_atomic_and_never_overwrites(self):
  r=subprocess.run([PHP,str(ROOT/'cloud/install.php')],env=self.env,capture_output=True,text=True);self.assertEqual(r.returncode,0,r.stderr)
  self.assertEqual(query(self.url,'SELECT COUNT(*) FROM users'),1)
  self.assertEqual(query(self.url,'SELECT mustChangePassword FROM users'),1)
  self.assertEqual(query(self.url,'SELECT COUNT(*) FROM clients'),0)
  r=subprocess.run([PHP,str(ROOT/'cloud/install.php')],env=self.env,capture_output=True);self.assertNotEqual(r.returncode,0)
  self.assertEqual(query(self.url,'SELECT COUNT(*) FROM users'),1)
 def test_import_history_sequences_and_refuse_overwrite(self):
  with tempfile.TemporaryDirectory() as temp:
   path=temp+'/old.sqlite'
   env={**os.environ,'YAGUA_CS_DB':path,'YAGUA_CS_INITIAL_PASSWORD':'Import-test-123'};env.pop('DATABASE_URL',None)
   subprocess.run([PHP,str(ROOT/'install.php')],env=env,capture_output=True,check=True)
   with sqlite3.connect(path) as db:
    db.execute("INSERT INTO clients(id,nome,createdAt,updatedAt) VALUES(12,'Cliente antigo','2026-01-01','2026-01-01')")
    db.execute("INSERT INTO technicians(id,nome,whatsapp,email) VALUES(8,'Pessoa antiga','5511999999999','pessoa@example.com')")
    db.execute('INSERT INTO client_technicians VALUES(12,8)')
    db.execute("INSERT INTO updates(id,clientId,kind,userId,technicianId,userNameAtTime,technicianNameAtTime,mensagem,contactAt,createdAt) VALUES(20,12,'contact',1,8,'Autor original','Nome original','Histórico','2026-01-01','2026-01-02')")
    db.execute("INSERT INTO tasks(id,clientId,title,createdBy,createdAt,updatedAt,requestToken) VALUES(5,12,'Ação antiga',1,'2026-01-01','2026-01-01','original-token')")
    oldhash=db.execute('SELECT passwordHash FROM users').fetchone()[0]
   r=subprocess.run([PHP,str(ROOT/'cloud/import-sqlite.php'),path],env=self.env,capture_output=True,text=True);self.assertEqual(r.returncode,0,r.stderr)
   self.assertEqual(query(self.url,'SELECT passwordHash FROM users'),oldhash)
   self.assertEqual(query(self.url,'SELECT technicianNameAtTime FROM updates'),'Nome original')
   self.assertEqual(query(self.url,'SELECT id FROM tasks'),5)
   self.assertEqual(query(self.url,"INSERT INTO clients(nome,createdAt,updatedAt) VALUES('Novo','2026-01-01','2026-01-01') RETURNING id"),13)
   r=subprocess.run([PHP,str(ROOT/'cloud/import-sqlite.php'),path],env=self.env,capture_output=True);self.assertNotEqual(r.returncode,0)
   self.assertEqual(query(self.url,'SELECT COUNT(*) FROM clients'),2)
   with sqlite3.connect(path) as db:self.assertEqual(db.execute('SELECT COUNT(*) FROM clients').fetchone()[0],1)
if __name__=='__main__':unittest.main(verbosity=2)
