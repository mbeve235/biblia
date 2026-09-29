<?php
/**
 * Gestão de livros — nome, abreviação, ordem, capítulos e categorias.
 */
require_once __DIR__ . '/_admin_header.php';
require_editor();

$error = null;
$ok = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $do = $_POST['do'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    try {
        if ($do === 'update' && $id) {
            db_exec(
                'UPDATE books SET name=?, abbreviation=?, slug=?, testament=?, book_order=?, chapters_count=?, group_id=? WHERE id=?',
                [trim((string) $_POST['name']), trim((string) $_POST['abbreviation']),
                 trim((string) $_POST['slug']) ?: slugify((string) $_POST['name']),
                 ($_POST['testament'] ?? 'AT') === 'NT' ? 'NT' : 'AT',
                 max(1, (int) $_POST['book_order']), max(0, (int) $_POST['chapters_count']),
                 ((int) $_POST['group_id']) ?: null, $id]
            );
            $ok = 'Livro actualizado.';
            log_activity(current_user()['id'], 'book_update', '#' . $id);
        }
        if ($do === 'sync_chapters' && $id) {
            $b = db_one('SELECT chapters_count FROM books WHERE id = ?', [$id]);
            if ($b) {
                $n = (int) $b['chapters_count'];
                db_exec('DELETE FROM chapters WHERE book_id = ? AND chapter_number > ?', [$id, $n]);
                for ($c = 1; $c <= $n; $c++) {
                    db_exec('INSERT IGNORE INTO chapters (book_id, chapter_number) VALUES (?, ?)', [$id, $c]);
                }
                $ok = 'Capítulos sincronizados (' . $n . ').';
            }
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$groups = db_all('SELECT * FROM book_groups ORDER BY testament, group_order');
$books = db_all('SELECT b.*, g.name AS group_name FROM books b LEFT JOIN book_groups g ON g.id = b.group_id ORDER BY b.book_order');
?>

<div class="reader-header">
  <div class="crumb">Administração</div>
  <h1><i class="fas fa-book" style="color:var(--accent)"></i> Livros (66)</h1>
  <p style="color:var(--text-soft)">Ordem canónica, testamento, categorias e número de capítulos.</p>
</div>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<?php if ($ok): ?><div class="alert alert-success"><?= e($ok) ?></div><?php endif; ?>

<?php foreach (['AT' => 'Antigo Testamento', 'NT' => 'Novo Testamento'] as $test => $label): ?>
  <section class="card">
    <div class="card-head"><h3><?= $label ?></h3></div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>#</th><th>Nome</th><th>Abrev.</th><th>Slug</th><th>Categoria</th><th>Capítulos</th><th></th></tr></thead>
        <tbody>
        <?php foreach (array_filter($books, fn($b) => $b['testament'] === $test) as $b): ?>
          <tr>
            <td colspan="7">
              <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
                <form method="post" style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;flex:1">
                  <?= csrf_field() ?>
                  <input type="hidden" name="do" value="update">
                  <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                  <input type="hidden" name="testament" value="<?= e($b['testament']) ?>">
                  <input type="number" name="book_order" value="<?= (int) $b['book_order'] ?>" style="width:64px" min="1" title="Ordem">
                  <input type="text" name="name" value="<?= e($b['name']) ?>" style="width:150px" title="Nome">
                  <input type="text" name="abbreviation" value="<?= e($b['abbreviation']) ?>" style="width:70px" title="Abreviação">
                  <input type="text" name="slug" value="<?= e($b['slug']) ?>" style="width:130px" title="Slug (URL)">
                  <select name="group_id" style="width:160px" title="Categoria">
                    <option value="0">—</option>
                    <?php foreach ($groups as $g): ?>
                      <option value="<?= (int) $g['id'] ?>" <?= (int) $b['group_id'] === (int) $g['id'] ? 'selected' : '' ?>>
                        <?= e($g['name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <input type="number" name="chapters_count" value="<?= (int) $b['chapters_count'] ?>" style="width:64px" min="0" title="Nº de capítulos">
                  <button class="btn btn-sm btn-primary" type="submit" title="Guardar"><i class="fas fa-floppy-disk"></i></button>
                </form>
                <form method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="do" value="sync_chapters">
                  <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                  <button class="btn btn-sm btn-ghost" type="submit" title="Criar/apagar capítulos até ao total definido">
                    <i class="fas fa-rotate"></i> Sincronizar capítulos
                  </button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endforeach; ?>

<?php require_once __DIR__ . '/_admin_footer.php'; ?>
