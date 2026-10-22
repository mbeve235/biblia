-- Marcações coloridas de versículos para instalações existentes.
CREATE TABLE IF NOT EXISTS verse_highlights (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  verse_id INT UNSIGNED NOT NULL,
  color ENUM('yellow','green','blue','rose') NOT NULL DEFAULT 'yellow',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_verse_highlight (user_id, verse_id),
  KEY idx_highlight_user (user_id, updated_at),
  CONSTRAINT fk_highlight_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_highlight_verse FOREIGN KEY (verse_id) REFERENCES verses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
