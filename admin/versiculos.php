<?php
/**
 * Consulta/edição de versículos: filtros por tradução, livro e capítulo.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';
boot_session();
require_editor();
require_once BASE_PATH . '/utils/importador.php';

$fTrans = (int) ($_POST['translation'] ?? $_GET['translation'] ?? 0);
$fBook  = (int) ($_POST['book'] ?? $_GET['book'] ?? 0);
$fChap  = (int) ($_POST['chapter'] ?? $_GET['chapter'] ?? 0);

$hasHeading = (bool) db_one("SHOW COLUMNS FROM verses LIKE 'heading'");
$ok = isset($_GET['saved']) ? 'Título e versículo guardados com sucesso.' : null;
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $vid = (int) ($_POST['verse_id'] ?? 0);
    $text = trim((string) ($_POST['text'] ?? ''));
    $heading = trim((string) ($_POST['heading'] ?? ''));
    if ($vid && $text !== '') {
        $verseExists = db_one('SELECT id FROM verses WHERE id = ?', [$vid]);
        if (!$verseExists) {
            $error = 'O versículo seleccionado já não existe.';
        } else {
            if ($hasHeading) {
                db_exec('UPDATE verses SET heading = ?, text = ?, search_text = ? WHERE id = ?',
                    [$heading !== '' ? $heading : null, $text, Importer::toSearchText($text), $vid]);
            } else {
                db_exec('UPDATE verses SET text = ?, search_text = ? WHERE id = ?',
                    [$text, Importer::toSearchText($text), $vid]);
            }
            log_activity(current_user()['id'], 'verse_edit', '#' . $vid);
            $query = http_build_query([
                'translation' => $fTrans,
                'book' => $fBook,
                'chapter' => $fChap,
                'saved' => 1,
                'verse' => $vid,
            ]);
            redirect('/admin/versiculos.php?' . $query);
        }
    } else {
        $error = 'O texto do versículo não pode ficar vazio.';
    }
}

$translations = db_all('SELECT id, abbreviation, name FROM translations ORDER BY name');
$books = db_all('SELECT id, name FROM books ORDER BY book_order');
$verses = [];
$chaptersList = [];
if ($fTrans && $fBook) {
    $chaptersList = db_all('SELECT chapter_number FROM chapters WHERE book_id = ? ORDER BY chapter_number', [$fBook]);
    if ($fChap) {
        $verses = db_all(
            'SELECT v.id, v.verse_number, ' . ($hasHeading ? 'v.heading' : 'NULL AS heading') . ', v.text FROM verses v
             JOIN chapters c ON c.id = v.chapter_id
             WHERE v.translation_id = ? AND c.book_id = ? AND c.chapter_number = ?
             ORDER BY v.verse_number',
            [$fTrans, $fBook, $fChap]
        );
    }
}

require_once __DIR__ . '/_admin_header.php';
?>

<header class="page-header admin-page-header"><span class="page-kicker">Conteúdo</span><div class="page-title-row"><span class="page-icon"><i class="fas fa-scroll"></i></span><div><h1>Versículos</h1><p>Consultar e corrigir versículos de qualquer tradução.</p></div></div></header>

<?php if ($ok): ?><div class="alert alert-success"><?= e($ok) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if (!$hasHeading): ?><div class="alert alert-warning">Execute a migração <code>database/migrations/20261023_verse_headings.sql</code> para editar títulos de secção.</div><?php endif; ?>

<section class="card">
  <form method="get" style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:flex-end">
    <label class="field" style="margin:0;flex:1;min-width:180px"><span>Tradução</span>
      <select name="translation" onchange="this.form.submit()">
        <option value="">—</option>
        <?php foreach ($translations as $t): ?>
          <option value="<?= (int) $t['id'] ?>" <?= $fTrans === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['abbreviation']) ?> — <?= e($t['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="field" style="margin:0;flex:1;min-width:160px"><span>Livro</span>
      <select name="book" onchange="this.form.submit()">
        <option value="">—</option>
        <?php foreach ($books as $b): ?>
          <option value="<?= (int) $b['id'] ?>" <?= $fBook === (int) $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="field" style="margin:0;width:120px"><span>Capítulo</span>
      <select name="chapter" onchange="this.form.submit()">
        <option value="">—</option>
        <?php foreach ($chaptersList as $c): ?>
          <option value="<?= (int) $c['chapter_number'] ?>" <?= $fChap === (int) $c['chapter_number'] ? 'selected' : '' ?>><?= (int) $c['chapter_number'] ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </form>
</section>

<?php if ($fTrans && $fBook && $fChap): ?>
  <?php if (!$verses): ?>
    <div class="empty-state"><i class="fas fa-folder-open"></i>Sem versículos nesta selecção.</div>
  <?php else: ?>
    <?php foreach ($verses as $v): ?>
      <section class="card" style="padding:.9rem 1.1rem">
        <form method="post" style="display:grid;grid-template-columns:auto 1fr auto;gap:.6rem;align-items:start">
          <?= csrf_field() ?>
          <input type="hidden" name="verse_id" value="<?= (int) $v['id'] ?>">
          <input type="hidden" name="translation" value="<?= $fTrans ?>">
          <input type="hidden" name="book" value="<?= $fBook ?>">
          <input type="hidden" name="chapter" value="<?= $fChap ?>">
          <span class="badge badge-accent" style="margin-top:.4rem"><?= (int) $v['verse_number'] ?></span>
          <div style="display:grid;gap:.45rem"><input name="heading" maxlength="255" value="<?= e($v['heading'] ?? '') ?>" placeholder="Título antes deste versículo (opcional)" <?= !$hasHeading ? 'disabled' : '' ?>><textarea name="text" rows="2"><?= e($v['text']) ?></textarea></div>
          <button class="btn btn-sm btn-primary" type="submit" style="margin-top:.3rem"><i class="fas fa-floppy-disk"></i></button>
        </form>
      </section>
    <?php endforeach; ?>
  <?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/_admin_footer.php'; ?>
