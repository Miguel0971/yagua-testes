CREATE TABLE IF NOT EXISTS board_statuses (
 key TEXT PRIMARY KEY, label TEXT NOT NULL, color TEXT NOT NULL,
 sortOrder INTEGER NOT NULL DEFAULT 0, isClosed INTEGER NOT NULL DEFAULT 0 CHECK(isClosed IN (0,1)),
 builtin INTEGER NOT NULL DEFAULT 0 CHECK(builtin IN (0,1))
);
INSERT INTO board_statuses(key,label,color,sortOrder,isClosed,builtin) VALUES
 ('active','Em acompanhamento','ocean',10,0,1),
 ('waiting','Aguardando cliente','amber',20,0,1),
 ('attention','Ação necessária','rose',30,0,1),
 ('done','Concluído','forest',40,1,1)
 ON CONFLICT(key) DO NOTHING;
