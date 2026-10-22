-- =====================================================================
-- BÍBLIA WEB — Base de dados (MySQL 8 / MariaDB 10.4+, XAMPP)
-- Importar via phpMyAdmin (Importar → escolher este ficheiro)
-- =====================================================================
-- Utilizador administrador criado automaticamente:
--   E-mail: admin@biblia.local
--   Password: admin123   (re-hash automático no primeiro login)
-- =====================================================================

CREATE DATABASE IF NOT EXISTS biblia_web
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE biblia_web;

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Utilizadores
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS users;
CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(120)  NOT NULL,
  email         VARCHAR(190)  NOT NULL,
  password_hash VARCHAR(255)  NOT NULL,
  role          ENUM('admin','editor','user') NOT NULL DEFAULT 'user',
  status        ENUM('active','inactive')     NOT NULL DEFAULT 'active',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  last_login    DATETIME NULL,
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS password_resets;
CREATE TABLE password_resets (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  token      CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used       TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_reset_token (token),
  KEY idx_reset_expires (expires_at),
  CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Configurações da aplicação (chave/valor)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS settings;
CREATE TABLE settings (
  skey       VARCHAR(60) PRIMARY KEY,
  svalue     TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Traduções / Versões
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS translations;
CREATE TABLE translations (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(150) NOT NULL,
  abbreviation VARCHAR(20)  NOT NULL,
  language     VARCHAR(60)  NOT NULL DEFAULT 'Português',
  description  TEXT NULL,
  copyright    VARCHAR(255) NULL,
  version      VARCHAR(30)  NULL,
  source       VARCHAR(255) NULL,
  status       ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_trans_abbr (abbreviation)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Categorias dos livros (permite reorganizar no futuro)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS book_groups;
CREATE TABLE book_groups (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(60) NOT NULL,
  testament  ENUM('AT','NT') NOT NULL,
  group_order TINYINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Livros (partilhados por todas as traduções)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS books;
CREATE TABLE books (
  id            SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(80) NOT NULL,
  abbreviation  VARCHAR(20) NOT NULL,
  slug          VARCHAR(80) NOT NULL,
  testament     ENUM('AT','NT') NOT NULL,
  book_order    SMALLINT UNSIGNED NOT NULL,
  chapters_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  group_id      INT UNSIGNED NULL,
  UNIQUE KEY uq_books_name (name),
  UNIQUE KEY uq_books_slug (slug),
  KEY idx_books_order (testament, book_order),
  CONSTRAINT fk_books_group FOREIGN KEY (group_id) REFERENCES book_groups(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Capítulos
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS chapters;
CREATE TABLE chapters (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  book_id        SMALLINT UNSIGNED NOT NULL,
  chapter_number SMALLINT UNSIGNED NOT NULL,
  UNIQUE KEY uq_chapter (book_id, chapter_number),
  CONSTRAINT fk_chapters_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Versículos (search_text = minúsculas, sem acentos, para pesquisa)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS verses;
CREATE TABLE verses (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  translation_id INT UNSIGNED NOT NULL,
  book_id        SMALLINT UNSIGNED NOT NULL,
  chapter_id     INT UNSIGNED NOT NULL,
  verse_number   SMALLINT UNSIGNED NOT NULL,
  text           TEXT NOT NULL,
  search_text    TEXT NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_verse (translation_id, chapter_id, verse_number),
  KEY idx_verse_trans_book (translation_id, book_id),
  FULLTEXT KEY ft_verses (search_text),
  CONSTRAINT fk_verses_trans   FOREIGN KEY (translation_id) REFERENCES translations(id) ON DELETE CASCADE,
  CONSTRAINT fk_verses_book    FOREIGN KEY (book_id)        REFERENCES books(id)        ON DELETE CASCADE,
  CONSTRAINT fk_verses_chapter FOREIGN KEY (chapter_id)     REFERENCES chapters(id)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Favoritos e notas
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS favorites;
CREATE TABLE favorites (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  verse_id   INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_favorite (user_id, verse_id),
  KEY idx_fav_user (user_id, created_at),
  CONSTRAINT fk_fav_user  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
  CONSTRAINT fk_fav_verse FOREIGN KEY (verse_id) REFERENCES verses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS notes;
CREATE TABLE notes (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  verse_id   INT UNSIGNED NOT NULL,
  note       TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_note (user_id, verse_id),
  KEY idx_note_user (user_id, updated_at),
  CONSTRAINT fk_note_user  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
  CONSTRAINT fk_note_verse FOREIGN KEY (verse_id) REFERENCES verses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Ministrações — estudos e interpretações pessoais da Palavra
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS ministracoes;
CREATE TABLE ministracoes (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id        INT UNSIGNED NOT NULL,
  title          VARCHAR(180) NOT NULL,
  body           MEDIUMTEXT NOT NULL,
  verse_id       INT UNSIGNED NULL,
  book_id        SMALLINT UNSIGNED NULL,
  chapter_number SMALLINT UNSIGNED NULL,
  share_token    VARCHAR(32) NULL,
  refs_json      TEXT NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_min_user (user_id, updated_at),
  KEY idx_min_book (book_id, chapter_number),
  UNIQUE KEY uq_min_share (share_token),
  CONSTRAINT fk_min_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_min_verse FOREIGN KEY (verse_id) REFERENCES verses(id) ON DELETE SET NULL,
  CONSTRAINT fk_min_book  FOREIGN KEY (book_id)  REFERENCES books(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Planos de leitura
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS reading_plan_progress;
DROP TABLE IF EXISTS user_reading_plans;
DROP TABLE IF EXISTS reading_plans;
CREATE TABLE reading_plans (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug             VARCHAR(80) NOT NULL UNIQUE,
  title            VARCHAR(150) NOT NULL,
  description      VARCHAR(255) NOT NULL,
  duration_days    SMALLINT UNSIGNED NOT NULL,
  start_book_order TINYINT UNSIGNED NOT NULL,
  end_book_order   TINYINT UNSIGNED NOT NULL,
  icon             VARCHAR(40) NOT NULL DEFAULT 'book-open',
  accent           VARCHAR(20) NOT NULL DEFAULT 'purple',
  active           TINYINT(1) NOT NULL DEFAULT 1,
  sort_order       TINYINT UNSIGNED NOT NULL DEFAULT 1,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_reading_plans (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED NOT NULL,
  plan_id      INT UNSIGNED NOT NULL,
  started_at   DATE NOT NULL,
  status       ENUM('active','completed','paused') NOT NULL DEFAULT 'active',
  completed_at DATETIME NULL,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_plan (user_id, plan_id),
  KEY idx_user_plans (user_id, status, updated_at),
  CONSTRAINT fk_user_plan_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_plan_plan FOREIGN KEY (plan_id) REFERENCES reading_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reading_plan_progress (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_plan_id INT UNSIGNED NOT NULL,
  day_number   SMALLINT UNSIGNED NOT NULL,
  completed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_plan_day (user_plan_id, day_number),
  CONSTRAINT fk_progress_user_plan FOREIGN KEY (user_plan_id) REFERENCES user_reading_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO reading_plans (slug, title, description, duration_days, start_book_order, end_book_order, icon, accent, sort_order) VALUES
  ('joao-21-dias', 'João em 21 dias', 'Conheça a vida, os sinais e os ensinamentos de Jesus, um capítulo por dia.', 21, 43, 43, 'heart', 'purple', 1),
  ('salmos-30-dias', 'Salmos em 30 dias', 'Uma jornada diária de oração, louvor e confiança através dos 150 salmos.', 30, 19, 19, 'music', 'gold', 2),
  ('novo-testamento-90-dias', 'Novo Testamento em 90 dias', 'Percorra os Evangelhos, Actos, as cartas e Apocalipse em três meses.', 90, 40, 66, 'book-bible', 'blue', 3);

-- ---------------------------------------------------------------------
-- Histórico de leitura e preferências
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS reading_history;
CREATE TABLE reading_history (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id        INT UNSIGNED NOT NULL,
  translation_id INT UNSIGNED NOT NULL,
  book_id        SMALLINT UNSIGNED NOT NULL,
  chapter_number SMALLINT UNSIGNED NOT NULL,
  read_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_hist_user (user_id, read_at),
  CONSTRAINT fk_hist_user  FOREIGN KEY (user_id)        REFERENCES users(id)        ON DELETE CASCADE,
  CONSTRAINT fk_hist_trans FOREIGN KEY (translation_id) REFERENCES translations(id) ON DELETE CASCADE,
  CONSTRAINT fk_hist_book  FOREIGN KEY (book_id)        REFERENCES books(id)        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS user_settings;
CREATE TABLE user_settings (
  user_id        INT UNSIGNED PRIMARY KEY,
  translation_id INT UNSIGNED NULL,
  theme          ENUM('light','dark') NOT NULL DEFAULT 'light',
  font_size      TINYINT UNSIGNED NOT NULL DEFAULT 17,
  line_spacing   TINYINT UNSIGNED NOT NULL DEFAULT 18,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_us_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_us_trans FOREIGN KEY (translation_id) REFERENCES translations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Importações (relatórios do assistente de importação)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS imports;
CREATE TABLE imports (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  translation_id  INT UNSIGNED NULL,
  user_id         INT UNSIGNED NULL,
  filename        VARCHAR(255) NULL,
  format          VARCHAR(10)  NULL,
  status          ENUM('pending','validated','running','completed','failed') NOT NULL DEFAULT 'pending',
  total_books     INT UNSIGNED NOT NULL DEFAULT 0,
  total_chapters  INT UNSIGNED NOT NULL DEFAULT 0,
  total_verses    INT UNSIGNED NOT NULL DEFAULT 0,
  imported_verses INT UNSIGNED NOT NULL DEFAULT 0,
  errors_count    INT UNSIGNED NOT NULL DEFAULT 0,
  duplicates_count INT UNSIGNED NOT NULL DEFAULT 0,
  warnings_count  INT UNSIGNED NOT NULL DEFAULT 0,
  report          JSON NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  finished_at     DATETIME NULL,
  KEY idx_imports_status (status),
  KEY idx_imports_created (created_at),
  CONSTRAINT fk_imp_trans FOREIGN KEY (translation_id) REFERENCES translations(id) ON DELETE SET NULL,
  CONSTRAINT fk_imp_user  FOREIGN KEY (user_id)        REFERENCES users(id)        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS import_errors;
CREATE TABLE import_errors (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  import_id  INT UNSIGNED NOT NULL,
  location   VARCHAR(120) NULL,
  problem    VARCHAR(255) NOT NULL,
  severity   ENUM('error','warning') NOT NULL DEFAULT 'warning',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ierr_import (import_id),
  CONSTRAINT fk_ierr_import FOREIGN KEY (import_id) REFERENCES imports(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Pesquisas e actividade (para o dashboard)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS search_logs;
CREATE TABLE search_logs (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id        INT UNSIGNED NULL,
  translation_id INT UNSIGNED NULL,
  query          VARCHAR(190) NOT NULL,
  results_count  INT UNSIGNED NOT NULL DEFAULT 0,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_search_created (created_at),
  KEY idx_search_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS activity_log;
CREATE TABLE activity_log (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NULL,
  action     VARCHAR(60) NOT NULL,
  detail     VARCHAR(255) NULL,
  ip         VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_act_created (created_at),
  KEY idx_act_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- DADOS INICIAIS
-- =====================================================================

-- Configurações por omissão
INSERT INTO settings (skey, svalue) VALUES
  ('site_name', 'Bíblia Web'),
  ('default_translation', 'ARC'),
  ('registration_enabled', '1'),
  ('max_upload_mb', '25'),
  ('items_per_page', '50');

-- Utilizador administrador (password: admin123 — re-hash no primeiro login)
INSERT INTO users (name, email, password_hash, role, status) VALUES
  ('Administrador', 'admin@biblia.local', 'PLAINTEXT:admin123', 'admin', 'active'),
  ('Editor Demo',   'editor@biblia.local', 'PLAINTEXT:editor123', 'editor', 'active');

-- Categorias
INSERT INTO book_groups (name, testament, group_order) VALUES
  ('Pentateuco',        'AT', 1),
  ('Históricos',        'AT', 2),
  ('Poéticos',          'AT', 3),
  ('Profetas Maiores',  'AT', 4),
  ('Profetas Menores',  'AT', 5),
  ('Evangelhos',        'NT', 1),
  ('História',          'NT', 2),
  ('Cartas Paulinas',   'NT', 3),
  ('Cartas Gerais',     'NT', 4),
  ('Profecia',          'NT', 5);

-- 66 livros (ordem canónica). group_id: 1..5 AT, 6..10 NT
INSERT INTO books (name, abbreviation, slug, testament, book_order, chapters_count, group_id) VALUES
  ('Génesis',        'Gn',   'genesis',       'AT',   1,  50, 1),
  ('Êxodo',          'Ex',   'exodo',         'AT',   2,  40, 1),
  ('Levítico',       'Lv',   'levitico',      'AT',   3,  27, 1),
  ('Números',        'Nm',   'numeros',       'AT',   4,  36, 1),
  ('Deuteronómio',   'Dt',   'deuteronomio',  'AT',   5,  34, 1),
  ('Josué',          'Js',   'josue',         'AT',   6,  24, 2),
  ('Juízes',         'Jz',   'juizes',        'AT',   7,  21, 2),
  ('Rute',           'Rt',   'rute',          'AT',   8,   4, 2),
  ('1 Samuel',       '1Sm',  '1-samuel',      'AT',   9,  31, 2),
  ('2 Samuel',       '2Sm',  '2-samuel',      'AT',  10,  24, 2),
  ('1 Reis',         '1Rs',  '1-reis',        'AT',  11,  22, 2),
  ('2 Reis',         '2Rs',  '2-reis',        'AT',  12,  25, 2),
  ('1 Crónicas',     '1Cr',  '1-cronicas',    'AT',  13,  29, 2),
  ('2 Crónicas',     '2Cr',  '2-cronicas',    'AT',  14,  36, 2),
  ('Esdras',         'Ed',   'esdras',        'AT',  15,  10, 2),
  ('Neemias',        'Ne',   'neemias',       'AT',  16,  13, 2),
  ('Ester',          'Et',   'ester',         'AT',  17,  10, 2),
  ('Job',            'Jó',   'job',           'AT',  18,  42, 3),
  ('Salmos',         'Sl',   'salmos',        'AT',  19, 150, 3),
  ('Provérbios',     'Pv',   'proverbios',    'AT',  20,  31, 3),
  ('Eclesiastes',    'Ec',   'eclesiastes',   'AT',  21,  12, 3),
  ('Cânticos',       'Ct',   'canticos',      'AT',  22,   8, 3),
  ('Isaías',         'Is',   'isaias',        'AT',  23,  66, 4),
  ('Jeremias',       'Jr',   'jeremias',      'AT',  24,  52, 4),
  ('Lamentações',    'Lm',   'lamentacoes',   'AT',  25,   5, 4),
  ('Ezequiel',       'Ez',   'ezequiel',      'AT',  26,  48, 4),
  ('Daniel',         'Dn',   'daniel',        'AT',  27,  12, 4),
  ('Oseias',         'Os',   'oseias',        'AT',  28,  14, 5),
  ('Joel',           'Jl',   'joel',          'AT',  29,   3, 5),
  ('Amós',           'Am',   'amos',          'AT',  30,   9, 5),
  ('Obadias',        'Ob',   'obadias',       'AT',  31,   1, 5),
  ('Jonas',          'Jn',   'jonas',         'AT',  32,   4, 5),
  ('Miqueias',       'Mq',   'miqueias',      'AT',  33,   7, 5),
  ('Naum',           'Na',   'naum',          'AT',  34,   3, 5),
  ('Habacuque',      'Hc',   'habacuque',     'AT',  35,   3, 5),
  ('Sofonias',       'Sf',   'sofonias',      'AT',  36,   3, 5),
  ('Ageu',           'Ag',   'ageu',          'AT',  37,   2, 5),
  ('Zacarias',       'Zc',   'zacarias',      'AT',  38,  14, 5),
  ('Malaquias',      'Ml',   'malaquias',     'AT',  39,   4, 5),
  ('Mateus',         'Mt',   'mateus',        'NT',  40,  28, 6),
  ('Marcos',         'Mc',   'marcos',        'NT',  41,  16, 6),
  ('Lucas',          'Lc',   'lucas',         'NT',  42,  24, 6),
  ('João',           'Jo',   'joao',          'NT',  43,  21, 6),
  ('Actos',          'At',   'actos',         'NT',  44,  28, 7),
  ('Romanos',        'Rm',   'romanos',       'NT',  45,  16, 8),
  ('1 Coríntios',    '1Co',  '1-corintios',   'NT',  46,  16, 8),
  ('2 Coríntios',    '2Co',  '2-corintios',   'NT',  47,  13, 8),
  ('Gálatas',        'Gl',   'galatas',       'NT',  48,   6, 8),
  ('Efésios',        'Ef',   'efesios',       'NT',  49,   6, 8),
  ('Filipenses',     'Fp',   'filipenses',    'NT',  50,   4, 8),
  ('Colossenses',    'Cl',   'colossenses',   'NT',  51,   4, 8),
  ('1 Tessalonicenses','1Ts','1-tessalonicenses','NT',52,  5, 8),
  ('2 Tessalonicenses','2Ts','2-tessalonicenses','NT',53,  3, 8),
  ('1 Timóteo',      '1Tm',  '1-timoteo',     'NT',  54,   6, 8),
  ('2 Timóteo',      '2Tm',  '2-timoteo',     'NT',  55,   4, 8),
  ('Tito',           'Tt',   'tito',          'NT',  56,   3, 8),
  ('Filemom',        'Fm',   'filemom',       'NT',  57,   1, 8),
  ('Hebreus',        'Hb',   'hebreus',       'NT',  58,  13, 8),
  ('Tiago',          'Tg',   'tiago',         'NT',  59,   5, 9),
  ('1 Pedro',        '1Pe',  '1-pedro',       'NT',  60,   5, 9),
  ('2 Pedro',        '2Pe',  '2-pedro',       'NT',  61,   3, 9),
  ('1 João',         '1Jo',  '1-joao',        'NT',  62,   5, 9),
  ('2 João',         '2Jo',  '2-joao',        'NT',  63,   1, 9),
  ('3 João',         '3Jo',  '3-joao',        'NT',  64,   1, 9),
  ('Judas',          'Jd',   'judas',         'NT',  65,   1, 9),
  ('Apocalipse',     'Ap',   'apocalipse',    'NT',  66,  22, 10);

-- 1.189 capítulos gerados a partir de chapters_count
DROP TEMPORARY TABLE IF EXISTS tmp_numbers;
CREATE TEMPORARY TABLE tmp_numbers (n SMALLINT UNSIGNED PRIMARY KEY);
INSERT INTO tmp_numbers (n) VALUES
 (1),(2),(3),(4),(5),(6),(7),(8),(9),(10),
 (11),(12),(13),(14),(15),(16),(17),(18),(19),(20),
 (21),(22),(23),(24),(25),(26),(27),(28),(29),(30),
 (31),(32),(33),(34),(35),(36),(37),(38),(39),(40),
 (41),(42),(43),(44),(45),(46),(47),(48),(49),(50),
 (51),(52),(53),(54),(55),(56),(57),(58),(59),(60),
 (61),(62),(63),(64),(65),(66),(67),(68),(69),(70),
 (71),(72),(73),(74),(75),(76),(77),(78),(79),(80),
 (81),(82),(83),(84),(85),(86),(87),(88),(89),(90),
 (91),(92),(93),(94),(95),(96),(97),(98),(99),(100),
 (101),(102),(103),(104),(105),(106),(107),(108),(109),(110),
 (111),(112),(113),(114),(115),(116),(117),(118),(119),(120),
 (121),(122),(123),(124),(125),(126),(127),(128),(129),(130),
 (131),(132),(133),(134),(135),(136),(137),(138),(139),(140),
 (141),(142),(143),(144),(145),(146),(147),(148),(149),(150);

INSERT INTO chapters (book_id, chapter_number)
SELECT b.id, n.n
FROM books b
JOIN tmp_numbers n ON n.n <= b.chapters_count
ORDER BY b.id, n.n;

DROP TEMPORARY TABLE IF EXISTS tmp_numbers;

-- ---------------------------------------------------------------------
-- Tradução de demonstração (Almeida — domínio público, apenas amostras)
-- ---------------------------------------------------------------------
INSERT INTO translations (name, abbreviation, language, description, copyright, version, source, status) VALUES
  ('Almeida Revista e Corrigida (demonstração)', 'ARC', 'Português',
   'Tradução de demonstração com versículos de exemplo em domínio público, para testar a aplicação. Importe uma tradução completa através do painel administrativo.',
   'Domínio público (João Ferreira de Almeida, 1819)', 'demo',
   'Dados de exemplo incluídos no projecto', 'active');

SET @arc := (SELECT id FROM translations WHERE abbreviation = 'ARC');
SET @gn  := (SELECT id FROM books WHERE slug = 'genesis');
SET @sl  := (SELECT id FROM books WHERE slug = 'salmos');
SET @jo  := (SELECT id FROM books WHERE slug = 'joao');
SET @rm  := (SELECT id FROM books WHERE slug = 'romanos');

INSERT INTO verses (translation_id, book_id, chapter_id, verse_number, text, search_text) VALUES
  (@arc, @gn, (SELECT id FROM chapters WHERE book_id=@gn AND chapter_number=1), 1,
   'No princípio criou Deus os céus e a terra.', 'no principio criou deus os ceus e a terra'),
  (@arc, @gn, (SELECT id FROM chapters WHERE book_id=@gn AND chapter_number=1), 2,
   'E a terra era sem forma e vazia; e havia trevas sobre a face do abismo; mas o Espírito de Deus se movia sobre a face das águas.',
   'e a terra era sem forma e vazia e havia trevas sobre a face do abismo mas o espirito de deus se movia sobre a face das aguas'),
  (@arc, @gn, (SELECT id FROM chapters WHERE book_id=@gn AND chapter_number=1), 3,
   'E disse Deus: Haja luz; e houve luz.', 'e disse deus haja luz e houve luz'),
  (@arc, @gn, (SELECT id FROM chapters WHERE book_id=@gn AND chapter_number=1), 4,
   'E viu Deus que era boa a luz; e fez separação entre a luz e as trevas.',
   'e viu deus que era boa a luz e fez separacao entre a luz e as trevas'),
  (@arc, @gn, (SELECT id FROM chapters WHERE book_id=@gn AND chapter_number=1), 5,
   'E Deus chamou à luz Dia; e às trevas chamou Noite. E foi a tarde e a manhã, o dia primeiro.',
   'e deus chamou a luz dia e as trevas chamou noite e foi a tarde e a manha o dia primeiro'),

  (@arc, @sl, (SELECT id FROM chapters WHERE book_id=@sl AND chapter_number=23), 1,
   'O SENHOR é o meu pastor; nada me faltará.', 'o senhor e o meu pastor nada mefaltara'),
  (@arc, @sl, (SELECT id FROM chapters WHERE book_id=@sl AND chapter_number=23), 2,
   'Deitar-me faz em verdes pastos, guia-me mansamente a águas tranquilas.',
   'deitar me faz em verdes pastos guia me mansamente a aguas tranquilas'),
  (@arc, @sl, (SELECT id FROM chapters WHERE book_id=@sl AND chapter_number=23), 3,
   'Refrigera a minha alma; guia-me pelas veredas da justiça por amor do seu nome.',
   'refrigera a minha alma guia me pelas veredas da justica por amor do seu nome'),
  (@arc, @sl, (SELECT id FROM chapters WHERE book_id=@sl AND chapter_number=23), 4,
   'Ainda que eu andasse pelo vale da sombra da morte, não temeria mal algum, porque tu estás comigo; a tua vara e o teu cajado me consolam.',
   'ainda que eu andasse pelo vale da sombra da morte nao temeria mal algum porque tu estas comigo a tua vara e o teu cajado me consolam'),
  (@arc, @sl, (SELECT id FROM chapters WHERE book_id=@sl AND chapter_number=23), 5,
   'Preparas uma mesa perante mim na presença dos meus inimigos, unges a minha cabeça com óleo, o meu cálice transborda.',
   'preparas uma mesa perante mim na presenca dos meus inimigos unges a minha cabeca com oleo o meu calice transborda'),
  (@arc, @sl, (SELECT id FROM chapters WHERE book_id=@sl AND chapter_number=23), 6,
   'Certamente que a bondade e a misericórdia me seguirão todos os dias da minha vida; e habitarei na casa do SENHOR por longos dias.',
   'certamente que a bondade e a misericordia me seguiro todos os dias da minha vida e habitarei na casa do senhor por longos dias'),

  (@arc, @jo, (SELECT id FROM chapters WHERE book_id=@jo AND chapter_number=1), 1,
   'No princípio era o Verbo, e o Verbo estava com Deus, e o Verbo era Deus.',
   'no principio era o verbo e o verbo estava com deus e o verbo era deus'),
  (@arc, @jo, (SELECT id FROM chapters WHERE book_id=@jo AND chapter_number=1), 2,
   'Ele estava no princípio com Deus.', 'ele estava no principio com deus'),

  (@arc, @jo, (SELECT id FROM chapters WHERE book_id=@jo AND chapter_number=3), 14,
   'E, como Moisés levantou a serpente no deserto, assim importa que o Filho do homem seja levantado;',
   'e como moises levantou a serpente no deserto assim importa que o filho do homem seja levantado'),
  (@arc, @jo, (SELECT id FROM chapters WHERE book_id=@jo AND chapter_number=3), 15,
   'Para que todo aquele que nele crê não pereça, mas tenha a vida eterna.',
   'para que todo aquele que nele cre nao perca mas tenha a vida eterna'),
  (@arc, @jo, (SELECT id FROM chapters WHERE book_id=@jo AND chapter_number=3), 16,
   'Porque Deus amou o mundo de tal maneira que deu o seu Filho unigénito, para que todo aquele que nele crê não pereça, mas tenha a vida eterna.',
   'porque deus amou o mundo de tal maneira que deu o seu filho unigenito para que todo aquele que nele cre nao perca mas tenha a vida eterna'),
  (@arc, @jo, (SELECT id FROM chapters WHERE book_id=@jo AND chapter_number=3), 17,
   'Porque Deus enviou o seu Filho ao mundo não para que condene o mundo, mas para que o mundo seja salvo por ele.',
   'porque deus enviou o seu filho ao mundo nao para que condene o mundo mas para que o mundo seja salvo por ele'),
  (@arc, @jo, (SELECT id FROM chapters WHERE book_id=@jo AND chapter_number=3), 18,
   'Quem crê nele não é condenado; mas quem não crê já está condenado, porquanto não crê no nome do unigénito Filho de Deus.',
   'quem cre nele nao e condenado mas quem nao cre ja esta condenado porquanto nao cre no nome do unigenito filho de deus'),

  (@arc, @rm, (SELECT id FROM chapters WHERE book_id=@rm AND chapter_number=8), 28,
   'E sabemos que todas as coisas contribuem juntamente para o bem daqueles que amam a Deus, daqueles que são chamados segundo o seu propósito.',
   'e sabemos que todas as coisas contribuem juntamente para o bem daqueles que amam a deus daqueles que sao chamados segundo o seu proposito'),
  (@arc, @rm, (SELECT id FROM chapters WHERE book_id=@rm AND chapter_number=8), 38,
   'Porque estou certo de que nem a morte, nem a vida, nem os anjos, nem os principados, nem as potestades, nem o presente, nem o porvir,',
   'porque estou certo de que nem a morte nem a vida nem os anjos nem os principados nem as potestades nem o presente nem o porvir'),
  (@arc, @rm, (SELECT id FROM chapters WHERE book_id=@rm AND chapter_number=8), 39,
   'Nem a altura, nem a profundidade, nem alguma outra criatura nos poderá separar do amor de Deus, que está em Cristo Jesus nosso Senhor.',
   'nem a altura nem a profundidade nem alguma outra criatura nos podera separar do amor de deus que esta em cristo jesus nosso senhor');

-- Actividade de exemplo para o dashboard
INSERT INTO activity_log (user_id, action, detail) VALUES
  (1, 'install', 'Base de dados instalada com dados iniciais');
