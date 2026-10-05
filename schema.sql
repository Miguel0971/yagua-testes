PRAGMA foreign_keys=ON;
CREATE TABLE users (
 id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT NOT NULL,
 login TEXT NOT NULL UNIQUE COLLATE NOCASE, passwordHash TEXT NOT NULL,
 role TEXT NOT NULL CHECK(role IN ('ADMIN','USUARIO')), mustChangePassword INTEGER NOT NULL DEFAULT 0,
 authVersion INTEGER NOT NULL DEFAULT 1
);
CREATE TABLE clients (
 id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT NOT NULL, observacoes TEXT NOT NULL DEFAULT '',
 ownerId INTEGER REFERENCES users(id), status TEXT NOT NULL DEFAULT 'active' CHECK(status IN ('active','waiting','attention','done')),
 priority TEXT NOT NULL DEFAULT 'normal' CHECK(priority IN ('low','normal','high','urgent')),
 nextContact TEXT, cadence INTEGER NOT NULL DEFAULT 7 CHECK(cadence BETWEEN 1 AND 365),
 createdAt TEXT NOT NULL, updatedAt TEXT NOT NULL, deletedAt TEXT, version INTEGER NOT NULL DEFAULT 1
);
CREATE TABLE technicians (id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT NOT NULL, whatsapp TEXT NOT NULL DEFAULT '', email TEXT NOT NULL DEFAULT '', deletedAt TEXT);
CREATE TABLE client_technicians (
 clientId INTEGER NOT NULL REFERENCES clients(id), technicianId INTEGER NOT NULL REFERENCES technicians(id),
 PRIMARY KEY(clientId,technicianId)
);
CREATE TABLE updates (
 id INTEGER PRIMARY KEY AUTOINCREMENT, clientId INTEGER NOT NULL REFERENCES clients(id),
 kind TEXT NOT NULL CHECK(kind IN ('contact','comment','system')), userId INTEGER NOT NULL REFERENCES users(id),
 technicianId INTEGER REFERENCES technicians(id), userNameAtTime TEXT NOT NULL, technicianNameAtTime TEXT,
 mensagem TEXT NOT NULL, contactAt TEXT, createdAt TEXT NOT NULL, requestToken TEXT UNIQUE,
 CHECK(kind<>'contact' OR (contactAt IS NOT NULL AND technicianId IS NOT NULL))
);
CREATE INDEX updates_timeline ON updates(clientId,createdAt DESC,id DESC);
CREATE INDEX updates_contact ON updates(clientId,kind,contactAt DESC,id DESC);
CREATE TABLE tasks (
 id INTEGER PRIMARY KEY AUTOINCREMENT, clientId INTEGER NOT NULL REFERENCES clients(id), title TEXT NOT NULL,
 description TEXT NOT NULL DEFAULT '', assigneeId INTEGER REFERENCES users(id), dueDate TEXT,
 priority TEXT NOT NULL DEFAULT 'normal' CHECK(priority IN ('low','normal','high','urgent')),
 completedAt TEXT, completedBy INTEGER REFERENCES users(id), createdBy INTEGER NOT NULL REFERENCES users(id),
 createdAt TEXT NOT NULL, updatedAt TEXT NOT NULL, deletedAt TEXT, version INTEGER NOT NULL DEFAULT 1,
 requestToken TEXT NOT NULL UNIQUE
);
CREATE INDEX tasks_assignee ON tasks(assigneeId,dueDate);
CREATE INDEX tasks_client ON tasks(clientId,deletedAt,completedAt);
CREATE TABLE login_attempts (id INTEGER PRIMARY KEY AUTOINCREMENT,address TEXT NOT NULL,login TEXT NOT NULL,attemptedAt INTEGER NOT NULL);
CREATE INDEX login_attempts_time ON login_attempts(attemptedAt);
PRAGMA user_version=2;
