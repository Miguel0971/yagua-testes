"""Teste funcional HTTP com banco temporário. Requer Python 3 + PHP PDO_SQLite."""
import os,pathlib,subprocess,tempfile,socket,urllib.request,urllib.parse,urllib.error,http.cookiejar,re,sqlite3,time,unittest,secrets,datetime
ROOT=pathlib.Path(__file__).resolve().parents[1]
PHP=os.environ.get('PHP_BIN','php')
class Browser:
 def __init__(self,base):
  self.base=base;self.http=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()));self.page='';self.status=0
 def go(self,path='',data=None):
  req=urllib.request.Request(self.base+urllib.parse.quote(path,safe='?=&'),data=urllib.parse.urlencode(data,doseq=True).encode() if data is not None else None)
  try:r=self.http.open(req)
  except urllib.error.HTTPError as e:r=e
  self.status=r.status;self.page=r.read().decode();return self.page
 def token(self):return re.search(r'name="csrf" value="([^"]+)"',self.page)[1]
 def post(self,action,path='',**data):return self.go(path,{'csrf':self.token(),'action':action,**data})
class Integration(unittest.TestCase):
 @classmethod
 def setUpClass(cls):
  cls.temp=tempfile.TemporaryDirectory();cls.database=cls.temp.name+'/yagua.sqlite'
  env={**os.environ,'YAGUA_CS_DB':cls.database,'YAGUA_CS_INITIAL_PASSWORD':'3321'}
  subprocess.run([PHP,str(ROOT/'install.php')],env=env,check=True,stdout=subprocess.DEVNULL)
  with socket.socket() as s:s.bind(('127.0.0.1',0));port=s.getsockname()[1]
  cls.log=open(cls.temp.name+'/server.log','w+')
  cls.server=subprocess.Popen([PHP,'-d','session.save_path='+cls.temp.name,'-S',f'127.0.0.1:{port}','-t',str(ROOT)],env=env,stdout=cls.log,stderr=cls.log)
  cls.url=f'http://127.0.0.1:{port}/index.php'
  for _ in range(50):
   try:urllib.request.urlopen(cls.url);break
   except OSError:time.sleep(.05)
 @classmethod
 def tearDownClass(cls):
  cls.server.terminate();cls.server.wait();cls.log.seek(0);log=cls.log.read();cls.log.close();cls.temp.cleanup()
  assert 'Fatal error' not in log and 'Yagua CS view:' not in log,log
 def scalar(self,q,params=()):
  with sqlite3.connect(self.database) as db:return db.execute(q,params).fetchone()[0]
 def version(self,table,id=1):return self.scalar(f'select version from {table} where id=?',(id,))
 def test_team_workflow(self):
  a=Browser(self.url);a.go();self.assertIn('Entrar no workspace',a.page)
  self.assertEqual(self.scalar('select count(*) from users'),1);self.assertEqual(self.scalar('select count(*) from clients'),0)
  a.post('login',login='admin',password='wrong');self.assertIn('Login ou senha incorretos',a.page)
  a.post('login',login='admin',password='3321');self.assertIn('primeiro acesso',a.page)
  a.post('password',currentPassword='3321',newPassword='Admin-test-123',confirmPassword='Admin-test-123');self.assertIn('Sua próxima conversa',a.page)
  a.post('technician_save',nome='Contato Teste A');a.post('technician_save',nome='Contato Teste B')
  a.post('user_save',nome='Pessoa CS',login='equipe',password='Equipe-test-123',role='USUARIO')
  fields={'nome':'Árvore Cliente','observacoes':'Contexto <script>','ownerId':'2','status':'active','priority':'high','nextContact':'2025-09-30','cadence':'7','technicians[]':['1','2']}
  a.post('client_save','?view=client-edit',**fields);self.assertIn('Árvore Cliente',a.page);self.assertIn('Contexto &lt;script&gt;',a.page)
  a.post('client_save','?view=client-edit',**{**fields,'nome':'Outro cliente','ownerId':'1','technicians[]':['2']})
  a.go('?view=technicians');self.assertIn('Vincular a um cliente',a.page)
  a.post('technician_save',nome='Contato vinculado',linkClientId='2',email='pessoa@example.com')
  linked=self.scalar("select id from technicians where nome='Contato vinculado'")
  self.assertEqual(self.scalar('select count(*) from client_technicians where clientId=2 and technicianId=?',(linked,)),1)
  self.assertEqual(self.scalar("select userId from updates where mensagem='Vinculou o contato Contato vinculado ao cliente.'"),1)
  a.post('technician_delete',id=linked)
  before=self.scalar('select count(*) from technicians')
  a.post('technician_save',nome='Não salvar',linkClientId='999999')
  self.assertEqual(self.scalar('select count(*) from technicians'),before)
  b=Browser(self.url);b.go();b.post('login',login='equipe',password='Equipe-test-123')
  b.post('password',currentPassword='Equipe-test-123',newPassword='Equipe-new-123',confirmPassword='Equipe-new-123')
  self.assertNotIn('href="?view=team"',b.page)
  b.go('?view=team');self.assertIn('somente para administradores',b.page)
  b.post('client_save',**fields);self.assertEqual(self.scalar('select count(*) from clients'),2)
  b.go('?client=1');token=secrets.token_hex(32)
  b.post('update','?client=1',clientId=1,kind='contact',technicianId=1,contactAt='2025-09-30T14:00',mensagem='Contato recente <script>alert(1)</script>',requestToken=token)
  self.assertIn('Contato registrado',b.page);self.assertIn('&lt;script&gt;',b.page)
  b.post('update','?client=1',clientId=1,kind='contact',technicianId=1,contactAt='2025-09-30T14:00',mensagem='Duplicata',requestToken=token)
  self.assertEqual(self.scalar("select count(*) from updates where kind='contact'"),1)
  a.go('?client=1');a.post('update','?client=1',clientId=1,kind='contact',technicianId=2,contactAt='2025-08-01T10:00',mensagem='Contato retroativo',requestToken=secrets.token_hex(32))
  a.post('update','?client=1',clientId=1,kind='comment',mensagem='Conversa interna da equipe',requestToken=secrets.token_hex(32))
  self.assertEqual(self.scalar("select max(contactAt) from updates where kind='contact'"),'2025-09-30 17:00:00')
  self.assertIn('30/09/2025 14:00',a.page)
  a.go('?q=árv');self.assertIn('Árvore Cliente',a.page);self.assertNotIn('Outro cliente',a.page)
  a.go('?q=2');self.assertIn('Outro cliente',a.page);self.assertNotIn('Árvore Cliente',a.page)
  a.go('?layout=board');self.assertIn('data-status="waiting"',a.page)
  version=self.version('clients');b.go('?client=1')
  b.post('client_status','?client=1',id=1,version=version,status='waiting');self.assertEqual(self.scalar('select status from clients where id=1'),'waiting')
  a.post('client_status','?client=1',id=1,version=version,status='done');self.assertIn('Outra pessoa atualizou',a.page)
  self.assertEqual(self.scalar('select status from clients where id=1'),'waiting')
  b.go('?view=client-edit&id=1');b.post('client_save','?view=client-edit&id=1',id=1,version=self.version('clients'),**{**fields,'nome':'Nome proibido','observacoes':'Descrição atualizada','status':'waiting'})
  self.assertEqual(self.scalar('select nome from clients where id=1'),'Árvore Cliente');self.assertIn('Descrição atualizada',b.page)
  taskfields={'clientId':1,'title':'Alinhar retorno','description':'Contexto da subtarefa','assigneeId':2,'dueDate':'2025-09-20','priority':'urgent','requestToken':secrets.token_hex(32)}
  b.post('task_save','?view=task&client=1',**taskfields);self.assertIn('Subtarefa salva',b.page)
  b.post('task_save','?view=task&client=1',**taskfields);self.assertEqual(self.scalar('select count(*) from tasks'),1)
  b.go('?view=tasks');self.assertIn('Alinhar retorno',b.page);self.assertIn('Prazos vencidos',b.page)
  a.go('?view=tasks');self.assertNotIn('Alinhar retorno',a.page)
  a.go('?view=agenda');self.assertIn('Alinhar retorno',a.page);self.assertIn('Próximos contatos',a.page)
  b.go('?view=task&id=1');self.assertIn('Contexto da subtarefa',b.page)
  b.post('task_save','?view=task&id=1',id=1,version=self.version('tasks'),**{**taskfields,'title':'Alinhar visita','dueDate':'2025-10-01'})
  b.post('task_toggle','?client=1',id=1,version=self.version('tasks'),completed='1');self.assertIsNotNone(self.scalar('select completedAt from tasks where id=1'))
  b.go('?view=tasks');self.assertNotIn('Alinhar visita',b.page)
  b.go('?view=tasks&completed=1');self.assertIn('Alinhar visita',b.page)
  b.post('task_toggle','?client=1',id=1,version=self.version('tasks'),completed='0');self.assertIsNone(self.scalar('select completedAt from tasks where id=1'))
  b.post('task_delete','?client=1',id=1,version=self.version('tasks'));self.assertIsNotNone(self.scalar('select deletedAt from tasks where id=1'));self.assertIn('Removeu a subtarefa',b.page)
  b.post('client_archive','?client=1',id=1,version=self.version('clients'));self.assertIsNone(self.scalar('select deletedAt from clients where id=1'))
  a.go();a.post('user_save',id=2,nome='Pessoa Renomeada',login='equipe',role='USUARIO',password='')
  a.post('technician_save',id=1,nome='Contato Renomeado');a.go('?client=1');self.assertIn('Pessoa CS',a.page);self.assertIn('Contato Teste A',a.page)
  a.post('technician_delete',id=1);self.assertIn('Contato removido',a.page)
  a.post('technician_delete',id=2);self.assertIn('Vincule outro contato',a.page)
  a.go('?client=1');a.post('client_archive',id=1,version=self.version('clients'))
  b.go('?client=1');self.assertIn('Cliente não encontrado',b.page)
  a.go('?archived=1');self.assertIn('Árvore Cliente',a.page)
  a.go('?client=1');self.assertIn('Conversa interna da equipe',a.page)
  a.post('client_restore',id=1,version=self.version('clients'));self.assertIsNone(self.scalar('select deletedAt from clients where id=1'))
  b.go('',{'action':'client_status','id':1,'version':self.version('clients'),'status':'done','csrf':'invalid'});self.assertEqual(b.status,403)
  a.go();a.post('user_save',id=1,nome='Admin',login='admin',role='USUARIO',password='');self.assertIn('pelo menos um administrador',a.page)
  a.go();a.post('user_save',id=2,nome='Pessoa CS',login='equipe',role='USUARIO',password='Reset-test-123')
  b.go();self.assertIn('Entrar no workspace',b.page)
  a.go('?client=1&feed=comment');self.assertIn('Conversa interna da equipe',a.page);self.assertNotIn('Contato retroativo',a.page)
  self.assertNotEqual(self.scalar('select passwordHash from users where id=1'),'Admin-test-123')
  a.go('?view=client-edit');before=self.scalar('select count(*) from clients');techBefore=self.scalar('select count(*) from technicians')
  inline={**fields,'nome':'Cliente com contatos novos','technicians[]':['2'],'newTechnicianCount':'2','technicianCount':'1',
   'newTechnicians[0][nome]':'Contato do Cliente Um','newTechnicians[0][whatsapp]':'(11) 99999-1234','newTechnicians[0][email]':'um@example.com',
   'newTechnicians[1][nome]':'Contato do Cliente Dois','newTechnicians[1][whatsapp]':'','newTechnicians[1][email]':'dois@example.com'}
  a.post('client_save','?view=client-edit',**inline);self.assertEqual(self.scalar('select count(*) from clients'),before+1)
  self.assertEqual(self.scalar('select count(*) from technicians'),techBefore+2)
  self.assertIn('https://wa.me/5511999991234',a.page);self.assertIn('mailto:dois@example.com',a.page)
  self.assertEqual(self.scalar('select count(*) from client_technicians where clientId=(select max(id) from clients)'),3)
  bad={**inline,'nome':'Cliente inválido','newTechnicians[1][email]':'inválido'}
  a.post('client_save','?view=client-edit',**bad);self.assertIn('e-mail válido',a.page)
  self.assertEqual(self.scalar('select count(*) from clients'),before+1);self.assertEqual(self.scalar('select count(*) from technicians'),techBefore+2)
  a.post('client_save','?view=client-edit',**{**inline,'newTechnicianCount':'3'});self.assertIn('apenas parte dos contatos',a.page)
  self.assertEqual(self.scalar('select count(*) from clients'),before+1)
  b.go();b.post('login',login='equipe',password='Reset-test-123');b.post('password',currentPassword='Reset-test-123',newPassword='Final-test-123',confirmPassword='Final-test-123')
  b.post('client_save','?view=client-edit',**inline);self.assertEqual(self.scalar('select count(*) from technicians'),techBefore+2)
 def test_migration_preserves_existing_data(self):
  with tempfile.TemporaryDirectory() as temp:
   path=temp+'/old.sqlite'
   schema=(ROOT/'schema.sql').read_text().replace("whatsapp TEXT NOT NULL DEFAULT '', email TEXT NOT NULL DEFAULT '', ",'').replace('user_version=2','user_version=1')
   with sqlite3.connect(path) as db:
    db.executescript(schema)
    db.execute("INSERT INTO users(nome,login,passwordHash,role) VALUES ('Old admin','admin','preserved-hash','ADMIN')")
    db.execute("INSERT INTO clients(nome,createdAt,updatedAt) VALUES ('Cliente existente','2025-01-01','2025-01-01')")
    db.execute("INSERT INTO technicians(nome) VALUES ('Contato existente')")
    db.execute('INSERT INTO client_technicians VALUES (1,1)')
    db.execute("INSERT INTO updates(clientId,kind,userId,userNameAtTime,mensagem,createdAt) VALUES (1,'comment',1,'Old admin','Histórico preservado','2025-01-01')")
   env={**os.environ,'YAGUA_CS_DB':path}
   subprocess.run([PHP,str(ROOT/'migrate.php')],env=env,check=True,capture_output=True)
   with sqlite3.connect(path) as db:
    self.assertEqual(db.execute('PRAGMA user_version').fetchone()[0],2)
    self.assertEqual(db.execute('select nome,whatsapp,email from technicians').fetchone(),('Contato existente','',''))
    self.assertEqual(db.execute('select passwordHash from users').fetchone()[0],'preserved-hash')
    self.assertEqual(db.execute('select mensagem from updates').fetchone()[0],'Histórico preservado')
    self.assertEqual(db.execute('select * from client_technicians').fetchone(),(1,1))
   backups=list(pathlib.Path(temp).glob('*.backup-*'));self.assertEqual(len(backups),1)
   with sqlite3.connect(str(backups[0])) as db:self.assertEqual(db.execute('PRAGMA user_version').fetchone()[0],1)
   subprocess.run([PHP,str(ROOT/'migrate.php')],env=env,check=True,capture_output=True)
   self.assertEqual(len(list(pathlib.Path(temp).glob('*.backup-*'))),1)

if __name__=='__main__':unittest.main(verbosity=2)
