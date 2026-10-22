-- Planos de leitura para instalações existentes.
CREATE TABLE IF NOT EXISTS reading_plans (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(80) NOT NULL UNIQUE,
  title VARCHAR(150) NOT NULL,
  description VARCHAR(255) NOT NULL,
  duration_days SMALLINT UNSIGNED NOT NULL,
  start_book_order TINYINT UNSIGNED NOT NULL,
  end_book_order TINYINT UNSIGNED NOT NULL,
  icon VARCHAR(40) NOT NULL DEFAULT 'book-open',
  accent VARCHAR(20) NOT NULL DEFAULT 'purple',
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order TINYINT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_reading_plans (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  plan_id INT UNSIGNED NOT NULL,
  started_at DATE NOT NULL,
  status ENUM('active','completed','paused') NOT NULL DEFAULT 'active',
  completed_at DATETIME NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_plan (user_id, plan_id),
  KEY idx_user_plans (user_id, status, updated_at),
  CONSTRAINT fk_user_plan_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_plan_plan FOREIGN KEY (plan_id) REFERENCES reading_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reading_plan_progress (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_plan_id INT UNSIGNED NOT NULL,
  day_number SMALLINT UNSIGNED NOT NULL,
  completed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_plan_day (user_plan_id, day_number),
  CONSTRAINT fk_progress_user_plan FOREIGN KEY (user_plan_id) REFERENCES user_reading_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO reading_plans (slug, title, description, duration_days, start_book_order, end_book_order, icon, accent, sort_order) VALUES
  ('joao-21-dias', 'João em 21 dias', 'Conheça a vida, os sinais e os ensinamentos de Jesus, um capítulo por dia.', 21, 43, 43, 'heart', 'purple', 1),
  ('salmos-30-dias', 'Salmos em 30 dias', 'Uma jornada diária de oração, louvor e confiança através dos 150 salmos.', 30, 19, 19, 'music', 'gold', 2),
  ('novo-testamento-90-dias', 'Novo Testamento em 90 dias', 'Percorra os Evangelhos, Actos, as cartas e Apocalipse em três meses.', 90, 40, 66, 'book-bible', 'blue', 3);
