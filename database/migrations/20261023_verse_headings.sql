-- Títulos editoriais apresentados antes do versículo inicial de cada secção.
-- São específicos de cada tradução e não fazem parte do texto bíblico.
ALTER TABLE verses
    ADD COLUMN IF NOT EXISTS heading VARCHAR(255) NULL AFTER verse_number;
