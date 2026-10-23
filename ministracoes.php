<?php
/**
 * Ministrações — interpretações e estudos pessoais da Palavra.
 * Referências bíblicas escolhidas visualmente (livro → capítulo → versículos),
 * com o texto à vista. Privadas (só o autor vê); partilha opcional por link.
 */
require_once __DIR__ . '/includes/bootstrap.php';
boot_session();
$user = require_login();
require_once __DIR__ . '/includes/header.php';

$ok = $error = null;
$editItem = null;

/** Resolve a lista de referências {b,c,v} → linhas com texto (tradução por omissão). */
function resolve_min_refs(?string $json, int $defTransId): array
{
    if (!$json) return [];
    $raw = json_decode($json, true);
    if (!is_array($raw)) return [];
    $out = [];
    foreach (array_slice($raw, 0, 30) as $r) {
        if (!is_array($r)) continue;
        $slug = mb_strtolower(trim((string) ($r['b'] ?? '')));
        $ch   = (int) ($r['c'] ?? 0);
        $v    = (int) ($r['v'] ?? 0);
        if ($slug === '' || $ch < 1 || $ch > 150) continue;
        $book = db_one('SELECT id, name, slug FROM books WHERE slug = ? LIMIT 1', [$slug]);
        if (!$book) continue;
        $chapRow = db_one('SELECT id FROM chapters WHERE book_id = ? AND chapter_number = ?', [(int) $book['id'], $ch]);
        if (!$chapRow) continue;
        $verseNum = null; $text = null;
        if ($v >= 1) {
            $verse = db_one(
                'SELECT v.verse_number, v.text FROM verses v
                 WHERE v.translation_id = ? AND v.chapter_id = ? AND v.verse_number = ? LIMIT 1',
                [$defTransId, (int) $chapRow['id'], $v]
            );
            if ($verse) {
                $verseNum = (int) $verse['verse_number'];
                $text = $verse['text'];
            }
        }
        $out[] = [
            'b' => $book['slug'], 'name' => $book['name'], 'c' => $ch,
            'v' => $verseNum, 'text' => $text !== null ? excerpt($text, 140) : null,
        ];
    }
    return $out;
}

/** Caminho de leitura (URL) a partir de uma referência resolvida. */
function min_ref_url(array $r): string
{
    $qs = 'book=' . rawurlencode($r['b']) . '&chapter=' . (int) $r['c'];
    if (!empty($r['v'])) $qs .= '&verse=' . (int) $r['v'];
    return url('/leitura.php?' . $qs);
}

// ----------------- Acções -----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save') {
        $id    = (int) ($_POST['id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $body  = trim((string) ($_POST['body'] ?? ''));
        $refsIn = (string) ($_POST['refs'] ?? '');

        if (mb_strlen($title) < 3) {
            $error = 'O título deve ter pelo menos 3 caracteres.';
        } elseif (mb_strlen($body) < 10) {
            $error = 'Escreva a ministração (mín. 10 caracteres).';
        } else {
            // Valida as referências {b,c,v} recebidas do selector (só estrutura;
            // o texto é resolvido na exibição, com a tradução activa desse momento)
            $refs = [];
            $raw = json_decode($refsIn !== '' ? $refsIn : '[]', true);
            if (is_array($raw)) {
                foreach (array_slice($raw, 0, 30) as $r) {
                    if (!is_array($r)) continue;
                    $slug = mb_strtolower(trim((string) ($r['b'] ?? '')));
                    $c    = (int) ($r['c'] ?? 0);
                    $v    = (int) ($r['v'] ?? 0);
                    if ($slug === '' || $c < 1 || $c > 150) continue;
                    if (!db_one('SELECT id FROM books WHERE slug = ? LIMIT 1', [$slug])) continue;
                    $refs[] = ['b' => $slug, 'c' => $c, 'v' => $v > 0 ? $v : null];
                }
            }
            $refsJson = $refs ? json_encode($refs, JSON_UNESCAPED_UNICODE) : null;

            // Primeira referência com versículo alimenta as colunas legadas (compatibilidade)
            $verseId = null; $bookId = null; $chapter = null;
            $defTrans = db_one('SELECT id FROM translations WHERE status = "active" ORDER BY id LIMIT 1');
            foreach ($refs as $r) {
                $bk = db_one('SELECT id FROM books WHERE slug = ?', [$r['b']]);
                if (!$bk) continue;
                $bookId = (int) $bk['id'];
                $chapter = (int) $r['c'];
                if (!empty($r['v']) && $defTrans) {
                    $chRow = db_one('SELECT id FROM chapters WHERE book_id = ? AND chapter_number = ?', [$bookId, $chapter]);
                    if ($chRow) {
                        $vRow = db_one('SELECT id FROM verses WHERE chapter_id = ? AND verse_number = ? LIMIT 1', [$chRow['id'], (int) $r['v']]);
                        if ($vRow) $verseId = (int) $vRow['id'];
                    }
                }
                break;
            }

            if ($id > 0) {
                db_exec(
                    'UPDATE ministracoes SET title = ?, body = ?, refs_json = ?, verse_id = ?, book_id = ?, chapter_number = ? WHERE id = ? AND user_id = ?',
                    [$title, $body, $refsJson, $verseId, $bookId, $chapter, $id, $user['id']]
                );
                $ok = 'Ministração actualizada.';
            } else {
                db_exec(
                    'INSERT INTO ministracoes (user_id, title, body, refs_json, verse_id, book_id, chapter_number) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$user['id'], $title, $body, $refsJson, $verseId, $bookId, $chapter]
                );
                $ok = 'Ministração guardada.';
            }
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        db_exec('DELETE FROM ministracoes WHERE id = ? AND user_id = ?', [$id, $user['id']]);
        $ok = 'Ministração eliminada.';
    } elseif ($action === 'share') {
        $id = (int) ($_POST['id'] ?? 0);
        $row = db_one('SELECT id, share_token FROM ministracoes WHERE id = ? AND user_id = ?', [$id, $user['id']]);
        if ($row) {
            $token = $row['share_token'] ?: bin2hex(random_bytes(16));
            db_exec('UPDATE ministracoes SET share_token = ? WHERE id = ? AND user_id = ?', [$token, $id, $user['id']]);
            $ok = 'Partilha activada — o link público aparece abaixo do texto.';
        }
    } elseif ($action === 'unshare') {
        $id = (int) ($_POST['id'] ?? 0);
        db_exec('UPDATE ministracoes SET share_token = NULL WHERE id = ? AND user_id = ?', [$id, $user['id']]);
        $ok = 'Partilha desactivada — o link público deixou de funcionar.';
    } elseif ($action === 'edit') {
        $editItem = db_one(
            'SELECT * FROM ministracoes WHERE id = ? AND user_id = ?',
            [(int) ($_POST['id'] ?? 0), $user['id']]
        );
        if (!$editItem) $error = 'Ministração não encontrada.';
    }
}

$items = db_all(
    'SELECT m.*, b.name AS book_name, b.slug
     FROM ministracoes m
     LEFT JOIN books b ON b.id = m.book_id
     WHERE m.user_id = ?
     ORDER BY m.updated_at DESC',
    [$user['id']]
);

$defTrans = db_one('SELECT id, abbreviation FROM translations WHERE status = "active" ORDER BY id LIMIT 1');
$defAbbr  = $defTrans['abbreviation'] ?? '';
$transList = db_all('SELECT abbreviation, name FROM translations WHERE status = "active" ORDER BY name');

$editRefs = $editItem ? resolve_min_refs($editItem['refs_json'], (int) ($defTrans['id'] ?? 0)) : [];
?>

<header class="page-header">
  <span class="page-kicker">Pessoal</span>
  <div class="page-title-row"><span class="page-icon"><i class="fas fa-dove"></i></span><div><h1>Ministrações</h1><p>As suas interpretações e estudos da Palavra — privados, só para si.</p></div></div>
</header>

<?php if ($ok): ?><div class="alert alert-success"><?= e($ok) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<section class="card">
  <div class="card-head"><h3><i class="fas fa-<?= $editItem ? 'pen' : 'plus' ?>"></i> <?= $editItem ? 'Editar ministração' : 'Nova ministração' ?></h3></div>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int) ($editItem['id'] ?? 0) ?>">
    <input type="hidden" name="refs" id="refs-input" value="<?= e(json_encode(array_map(fn($r) => ['b' => $r['b'], 'c' => (int) $r['c'], 'v' => $r['v']], $editRefs), JSON_UNESCAPED_UNICODE)) ?>">

    <label class="field"><span>Título</span>
      <input type="text" name="title" id="min-title" required maxlength="180" value="<?= e($editItem['title'] ?? '') ?>" placeholder="Ex.: A graça em Romanos">
    </label>

    <div class="field">
      <span>Textos bíblicos <button type="button" class="btn btn-sm field-inline-action" id="btn-add-verse"><i class="fas fa-book-open"></i> Escolher versículos</button></span>
      <div id="min-refs" class="min-refs"><?= $editRefs ? '' : '<span class="min-refs-empty">Nenhuma referência — clique em “Escolher versículos”.</span>' ?></div>
    </div>

    <label class="field"><span>Ministração</span>
      <textarea name="body" id="min-body" rows="7" required placeholder="Escreva a sua interpretação, estudo ou pregação…"><?= e($editItem['body'] ?? '') ?></textarea>
    </label>

    <div class="card-actions">
      <?php if ($editItem): ?><a class="btn btn-ghost" href="<?= url('/ministracoes.php') ?>">Cancelar</a><?php endif; ?>
      <button class="btn btn-primary" type="submit"><i class="fas fa-floppy-disk"></i> <?= $editItem ? 'Actualizar' : 'Guardar' ?></button>
    </div>
  </form>
</section>

<?php if (!$items): ?>
  <div class="empty-state">
    <i class="fas fa-dove"></i>
    <p>Ainda não tem ministrações. Escreva a sua primeira interpretação acima.</p>
  </div>
<?php else: ?>
  <p class="section-meta"><?= count($items) ?> ministração(ões)</p>
  <?php foreach ($items as $m): ?>
    <?php
      $itemRefs = $m['refs_json']
          ? resolve_min_refs($m['refs_json'], (int) ($defTrans['id'] ?? 0))
          : [];
    ?>
    <article class="card min-card">
      <div class="card-head">
        <h3 class="entity-title">
          <span class="bk-badge"><?= brand_svg('', 12) ?></span>
          <?= e($m['title']) ?>
        </h3>
        <small class="muted"><?= fmt_datetime($m['updated_at']) ?></small>
      </div>

      <?php if ($itemRefs): ?>
        <div class="min-refs min-refs-spaced">
          <?php foreach ($itemRefs as $r): ?>
            <a class="min-chip" href="<?= e(min_ref_url($r)) ?>" title="<?= e($r['text'] ?? 'Ver o capítulo') ?>">
              <span class="bk-badge"><?= brand_svg('', 11) ?></span>
              <?= e($r['name']) ?> <?= (int) $r['c'] ?><?= $r['v'] ? ':' . (int) $r['v'] : '' ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php elseif ($m['verse_id'] || ($m['book_id'] && $m['chapter_number'])): ?>
        <?php
        $v = null;
        if ($m['verse_id']) {
            $v = db_one(
                'SELECT v.verse_number, c.chapter_number, b.name AS book_name, b.slug, t.abbreviation AS translation
                 FROM verses v JOIN chapters c ON c.id = v.chapter_id JOIN books b ON b.id = c.book_id
                 JOIN translations t ON t.id = v.translation_id
                 WHERE v.id = ?',
                [(int) $m['verse_id']]
            );
        }
        ?>
        <p class="min-ref">
          <?php if ($v): ?>
            <a href="<?= url('/leitura.php?translation=' . urlencode($v['translation']) . '&book=' . $v['slug'] . '&chapter=' . (int) $v['chapter_number'] . '&verse=' . (int) $v['verse_number']) ?>">
              <?= e($v['book_name']) ?> <?= (int) $v['chapter_number'] ?>:<?= (int) $v['verse_number'] ?>
            </a>
            <span class="badge badge-accent"><?= e($v['translation']) ?></span>
          <?php else: ?>
            <a href="<?= url('/leitura.php?book=' . ($m['slug'] ?? '') . '&chapter=' . (int) $m['chapter_number']) ?>">
              <?= e($m['book_name'] ?? '') ?> <?= (int) $m['chapter_number'] ?>
            </a>
          <?php endif; ?>
        </p>
      <?php endif; ?>

      <div class="min-body"><?= nl2br(e($m['body'])) ?></div>

      <div class="card-actions card-actions-spaced">
        <?php if ($m['share_token']): ?>
          <button class="btn btn-sm" type="button" onclick='bwShareMin(<?= json_encode(["t" => $m["title"], "b" => excerpt($m["body"], 140), "u" => ministry_share_url($m['share_token'])], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="fas fa-share-nodes"></i> Partilhar</button>
        <?php else: ?>
          <form method="post" class="inline-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="share">
            <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
            <button class="btn btn-sm" type="submit"><i class="fas fa-share-nodes"></i> Publicar por link</button>
          </form>
        <?php endif; ?>
        <form method="post" class="inline-form">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="edit">
          <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
          <button class="btn btn-sm" type="submit"><i class="fas fa-pen"></i> Editar</button>
        </form>
        <form method="post" class="inline-form" onsubmit="return confirm('Eliminar esta ministração?')">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
          <button class="btn btn-sm btn-danger" type="submit"><i class="fas fa-trash"></i> Eliminar</button>
        </form>
      </div>
      <?php if ($m['share_token']): ?>
        <div class="min-share">
          <input type="text" readonly value="<?= e(ministry_share_url($m['share_token'])) ?>" onclick="this.select()" aria-label="Link público">
          <button class="btn btn-sm" type="button" onclick="copyText(this.parentElement.querySelector('input').value)"><i class="fas fa-copy"></i> Copiar</button>
          <form method="post" class="inline-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="unshare">
            <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
            <button class="btn btn-sm btn-danger" type="submit" title="Desactivar o link público"><i class="fas fa-link-slash"></i> Despublicar</button>
          </form>
        </div>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
<?php endif; ?>

<?php
ob_start();
?>

<!-- Selector visual dedicado das ministrações: passos → livros → capítulos → versículos -->
<div class="vp-overlay" id="vp-modal">
  <div class="vp-modal" role="dialog" aria-modal="true" aria-label="Escolher textos bíblicos">
    <header class="vp-head">
      <div class="vp-head-main">
        <span class="vp-head-badge"><?= brand_svg('', 18) ?></span>
        <div class="vp-head-text">
          <h3>Escolher textos bíblicos</h3>
          <div class="vp-steps">
            <button type="button" class="vp-step" data-step="book"><b>1</b><span>Livro</span></button>
            <i class="fas fa-chevron-right vp-step-sep"></i>
            <button type="button" class="vp-step" data-step="chapter"><b>2</b><span>Capítulo</span></button>
            <i class="fas fa-chevron-right vp-step-sep"></i>
            <button type="button" class="vp-step" data-step="verse"><b>3</b><span>Versículos</span></button>
          </div>
        </div>
      </div>
      <button class="vp-close" type="button" aria-label="Fechar"><i class="fas fa-xmark"></i></button>
    </header>

    <div class="vp-body">
      <div class="vp-sec vp-sec-trans">
        <i class="fas fa-language"></i>
        <select id="vp-trans" class="vp-trans" aria-label="Tradução"></select>
        <div class="vp-seg" id="vp-testament" role="group" aria-label="Testamento">
          <button type="button" class="vp-seg-btn" data-tst="AT">Antigo</button>
          <button type="button" class="vp-seg-btn" data-tst="NT">Novo</button>
        </div>
      </div>

      <div class="vp-view" id="vp-view"></div>
    </div>

    <footer class="vp-foot">
      <span class="vp-count" id="vp-count">Nada escolhido</span>
      <div class="modal-actions">
        <button class="btn btn-ghost" type="button" id="vp-cancel">Cancelar</button>
        <button class="btn btn-primary" type="button" id="vp-add" disabled><i class="fas fa-plus"></i> Adicionar</button>
      </div>
    </footer>
  </div>
</div>

<script>
/* ================================================================
   VersePicker — escolher textos bíblicos visualmente:
   tradução → livro → capítulo → versículos (com o texto à vista)
   Modal dedicado, com passos 1·2·3 clicáveis e barra fixa.
   ================================================================ */
const VersePicker = {
  trans: <?= json_encode(array_map(fn($t) => ['a' => $t['abbreviation'], 'n' => $t['name']], $transList), JSON_UNESCAPED_UNICODE) ?>,
  abbr: <?= json_encode($defAbbr) ?>,
  books: null,          // grupos {testament, group, books[]}
  testament: 'AT',
  view: 'books',        // books | chapters | verses
  insertMode: null,     // 'ref' = só a escritura | 'full' = escritura + texto
  curBook: null, curChapter: 0,
  selected: [],         // [{b,c,v}]
  _q: '',

  el(id) { return document.getElementById(id); },

  async ensureBooks() {
    if (!this.books) {
      const res = await apiGet('/books');
      this.books = res.data.groups;
    }
  },

  open() {
    // Modo de inserção memorizado no browser (1.ª vez: escritura + texto)
    if (!this.insertMode) {
      let saved = null;
      try { saved = localStorage.getItem('vp_insert_mode'); } catch (e) { /* privado */ }
      this.insertMode = (saved === 'ref' || saved === 'full') ? saved : 'full';
    }
    this.el('vp-modal').classList.add('open');
    document.body.classList.add('vp-lock');
    // tradução: opções só existem aqui (o select é único, fora das vistas)
    const sel = this.el('vp-trans');
    if (!sel.options.length) {
      this.trans.forEach((t) => {
        const o = document.createElement('option');
        o.value = t.a; o.textContent = t.a + (t.n !== t.a ? ' — ' + t.n : '');
        if (t.a === this.abbr) o.selected = true;
        sel.appendChild(o);
      });
      sel.onchange = () => { this.abbr = sel.value; };
    }
    this.el('vp-testament').querySelectorAll('.vp-seg-btn').forEach((b) => {
      b.classList.toggle('on', b.dataset.tst === this.testament);
      b.onclick = () => { this.testament = b.dataset.tst; this.renderBooks(); };
    });
    this.el('vp-cancel').onclick = () => this.close();
    this.el('vp-add').onclick = () => {
      if (!this.curBook) return;
      const picks = this.selected.map((s) => ({ b: s.b, c: s.c, v: s.v }));
      if (picks.length) this.add(picks);
    };
    this.el('vp-modal').querySelector('.vp-close').onclick = () => this.close();
    this.el('vp-modal').addEventListener('click', (ev) => {
      if (ev.target === this.el('vp-modal')) this.close();
    });
    this.renderBooks();
    this.ensureBooks()
      .then(() => {
        // Sem livro "actual" nesta sessão? Usa a última referência já no formulário.
        if (!this.curBook) {
          const refs = window.MinRefs ? window.MinRefs.refs : [];
          const last = refs.length ? refs[refs.length - 1] : null;
          if (last) {
            const hit = this.books.flatMap((g) => g.books).find((b) => b.slug === last.b);
            if (hit) {
              this.curBook = hit;
              this.curChapter = last.c || 0;
              this.testament = hit.testament;
              document.querySelectorAll('#vp-testament .vp-seg-btn').forEach((btn) =>
                btn.classList.toggle('on', btn.dataset.tst === this.testament));
            }
          }
        }
        if (this.view === 'books') this.renderBooks();
      })
      .catch((e) => toast(e.message, 'error'));
  },

  close() {
    this.el('vp-modal').classList.remove('open');
    document.body.classList.remove('vp-lock');
    this.selected = [];
  },

  setStep(step) {
    const order = { book: 1, chapter: 2, verse: 3 };
    const n = order[step] || 1;
    this.el('vp-modal').querySelectorAll('.vp-step').forEach((s) => {
      const sn = order[s.dataset.step] || 1;
      s.classList.toggle('done', sn < n);
      s.classList.toggle('on', sn === n);
      s.disabled = sn >= n; // só se volta a passos anteriores
    });
  },

  esc(s) { return escapeHtml(s); },
  norm(s) { return s.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, ''); },

  renderBooks() {
    this.view = 'books';
    this.setStep('book');
    const view = this.el('vp-view');
    view.innerHTML = '';

    const search = document.createElement('input');
    search.type = 'search';
    search.className = 'vp-search';
    search.placeholder = 'Filtrar livros…';
    search.value = this._q;
    search.oninput = debounce(() => { this._q = search.value; this.listBooks(); }, 150);
    view.appendChild(search);

    const wrap = document.createElement('div');
    wrap.className = 'vp-bookwrap';
    view.appendChild(wrap);
    this.listBooks(wrap);
    this.refreshFoot();
  },

  listBooks(wrap) {
    wrap.innerHTML = '';
    if (!this.books) {
      wrap.innerHTML = '<div class="vp-loading"><i class="fas fa-spinner fa-spin"></i> A carregar os livros…</div>';
      return;
    }
    const q = this.norm(this._q.trim());
    const groups = this.books.filter((g) => g.testament === this.testament);
    let shown = 0;
    for (const g of groups) {
      const books = g.books.filter((b) => !q || this.norm(b.name).includes(q) || this.norm(b.slug).includes(q));
      if (!books.length) continue;
      const label = document.createElement('div');
      label.className = 'vp-group-label';
      label.innerHTML = '<span class="test-mark ' + (g.testament === 'AT' ? 'at' : 'nt') + '">' + BRAND_SVG(10) + '</span>' + this.esc(g.group);
      wrap.appendChild(label);
      const grid = document.createElement('div');
      grid.className = 'vp-books';
      for (const b of books) {
        const isCur = !!(this.curBook && b.slug === this.curBook.slug);
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'vp-book' + (isCur ? ' current' : '');
        btn.innerHTML =
          '<span class="bk-badge">' + BRAND_SVG(13) + '</span>' +
          '<span class="vp-book-name">' + this.esc(b.name) + (isCur ? ' <i class="fas fa-circle-check" title="Último usado"></i>' : '') + '</span>' +
          '<small class="vp-book-ch">' + b.chapters_count + ' cap.</small>';
        btn.onclick = () => { this.curBook = b; this.curChapter = 0; this.renderChapters(); };
        grid.appendChild(btn);
        shown++;
      }
      wrap.appendChild(grid);
    }
    if (!shown) {
      wrap.innerHTML = '<div class="vp-empty"><i class="fas fa-book"></i>Nenhum livro encontrado para “' + this.esc(this._q) + '”.</div>';
    }
  },

  renderChapters() {
    this.view = 'chapters';
    this.setStep('chapter');
    const view = this.el('vp-view');
    view.innerHTML = '';
    this.renderCrumb();

    const cg = document.createElement('div');
    cg.className = 'vp-chapters';
    for (let c = 1; c <= this.curBook.chapters_count; c++) {
      const isCur = c === this.curChapter;
      const t = document.createElement('button');
      t.type = 'button';
      t.className = 'vp-chap' + (isCur ? ' current' : '');
      if (isCur) t.title = 'Capítulo actual';
      t.innerHTML = '<em>cap.</em>' + c;
      t.onclick = () => { this.curChapter = c; this.renderVerses(); };
      cg.appendChild(t);
    }
    view.appendChild(cg);
    this.refreshFoot();
  },

  renderCrumb() {
    const view = this.el('vp-view');
    const old = view.querySelector('.vp-crumb');
    if (old) old.remove();
    const crumb = document.createElement('button');
    crumb.type = 'button';
    crumb.className = 'vp-crumb';
    crumb.innerHTML = '<i class="fas fa-arrow-left"></i> ' + this.esc(this.curBook.name) +
      '<small>' + this.curBook.chapters_count + ' capítulos</small>';
    crumb.onclick = () => { this.curBook = null; this.curChapter = 0; this.renderBooks(); };
    view.prepend(crumb);
  },

  async renderVerses() {
    this.view = 'verses';
    this.setStep('verse');
    const view = this.el('vp-view');
    view.innerHTML = '';
    this.renderCrumb();

    const hint = document.createElement('div');
    hint.className = 'vp-hint';
    hint.innerHTML = '<span>Toque nos versículos — pode escolher vários.</span>' +
      ' <button type="button" class="vp-whole"><i class="fas fa-layer-group"></i> Usar o capítulo inteiro</button>';
    hint.querySelector('.vp-whole').onclick = () => { this.selected = [{ b: this.curBook.slug, c: this.curChapter, v: 0 }]; this.add([{ b: this.curBook.slug, c: this.curChapter, v: 0 }]); };
    view.appendChild(hint);

    // O que inserir no corpo: só a referência (escritura) ou referência + texto
    const MODES = [
      ['ref', 'Só a escritura', 'Insere apenas a referência, ex.: “João 3:16” — escreve você a mensagem.'],
      ['full', 'Escritura + texto', 'Insere a referência e a citação por baixo, ex.: “João 3:16 (ARC)” com o versículo.'],
    ];
    const modeRow = document.createElement('div');
    modeRow.className = 'vp-mode-row';
    modeRow.innerHTML = '<span>Inserir no corpo:</span>';
    const modeHint = document.createElement('div');
    modeHint.className = 'vp-mode-hint';
    const syncHint = () => {
      const m = MODES.find((x) => x[0] === this.insertMode);
      modeHint.textContent = m ? m[2] : '';
    };
    for (const [val, label, desc] of MODES) {
      const lab = document.createElement('label');
      lab.className = 'vp-radio' + (this.insertMode === val ? ' on' : '');
      lab.innerHTML = '<input type="radio" name="vp-mode" value="' + val + '"' + (this.insertMode === val ? ' checked' : '') + '> ' + label;
      const input = lab.querySelector('input');
      input.onchange = () => {
        this.insertMode = val;
        try { localStorage.setItem('vp_insert_mode', val); } catch (e2) { /* privado */ }
        modeRow.querySelectorAll('.vp-radio').forEach((r) => r.classList.toggle('on', r.querySelector('input').value === val));
        syncHint();
      };
      modeRow.appendChild(lab);
    }
    modeRow.appendChild(modeHint);
    syncHint();
    view.appendChild(modeRow);

    const list = document.createElement('div');
    list.className = 'vp-verses';
    list.innerHTML = '<div class="vp-loading"><i class="fas fa-spinner fa-spin"></i> A carregar o capítulo…</div>';
    view.appendChild(list);

    try {
      const res = await apiGet('/bible/' + encodeURIComponent(this.abbr) + '/' + encodeURIComponent(this.curBook.slug) + '/' + this.curChapter);
      list.innerHTML = '';
      // Guarda os textos para inserir no corpo ao adicionar
      this._verseTexts = {};
      res.data.verses.forEach((v) => { this._verseTexts[v.verse] = v.text; });
      this._verseBookName = this.curBook.name;
      this._verseAbbr = this.abbr;
      const has = (v) => this.selected.some((s) => s.b === this.curBook.slug && s.c === this.curChapter && s.v === v);
      const refresh = () => {
        this.selected = this.selected.filter((s) => s.b === this.curBook.slug && s.c === this.curChapter);
        res.data.verses.forEach((v) => {
          const row = list.querySelector('[data-v="' + v.verse + '"]');
          if (row) row.classList.toggle('sel', has(v.verse));
        });
        this.refreshFoot();
      };
      res.data.verses.forEach((v) => {
        const row = document.createElement('button');
        row.type = 'button';
        row.className = 'vp-verse';
        row.dataset.v = v.verse;
        row.innerHTML = '<sup>' + v.verse + '</sup> ' + escapeHtml(v.text);
        row.onclick = () => {
          const key = { b: this.curBook.slug, c: this.curChapter, v: v.verse };
          const i = this.selected.findIndex((s) => s.b === key.b && s.c === key.c && s.v === key.v);
          if (i >= 0) this.selected.splice(i, 1);
          else this.selected.push(key);
          refresh();
        };
        list.appendChild(row);
      });
      refresh();
    } catch (e) {
      list.innerHTML = '';
      const err = document.createElement('div');
      err.className = 'alert alert-error';
      err.textContent = 'Sem texto nesta tradução para este capítulo. Escolha outra tradução ou capítulo.';
      list.appendChild(err);
    }
  },

  refreshFoot() {
    const n = this.selected.length;
    const add = this.el('vp-add');
    const count = this.el('vp-count');
    add.disabled = n === 0;
    if (!n) {
      count.textContent = 'Nada escolhido';
    } else if (n === 1 && this.selected[0].v === 0) {
      count.innerHTML = '<i class="fas fa-check"></i> ' + this.esc(this.curBook ? this.curBook.name : '') + ' ' + this.selected[0].c + ' — capítulo inteiro';
    } else {
      count.innerHTML = '<i class="fas fa-check"></i> ' + n + ' versículo' + (n > 1 ? 's' : '') + ' escolhido' + (n > 1 ? 's' : '') +
        (this.curBook ? ' em ' + this.esc(this.curBook.name) + ' ' + this.curChapter : '');
    }
  },

  add(picks) {
    // Insere no corpo conforme o modo escolhido:
    //   ref  → só a escritura:      João 3:16
    //   full → escritura + texto:   João 3:16 (ARC)
    //                               "Porque Deus amou o mundo…"
    const ta = document.getElementById('min-body');
    let block = '';
    for (const p of picks) {
      const bookName = this._verseBookName || p.b;
      const abbr = this._verseAbbr ? ' (' + this._verseAbbr + ')' : '';
      const refLine = bookName + ' ' + p.c + (p.v ? ':' + p.v : '') + (this.insertMode === 'full' ? abbr : '');
      if (ta && ta.value.includes(refLine)) continue; // já inserido antes
      block += '\n\n' + refLine;
      if (this.insertMode === 'full' && p.v && this._verseTexts && this._verseTexts[p.v]) {
        block += '\n"' + this._verseTexts[p.v] + '"';
      }
    }
    if (block && ta) {
      const pos = (typeof ta.selectionStart === 'number') ? ta.selectionStart : ta.value.length;
      ta.value = ta.value.slice(0, pos) + block.replace(/^\n+/, '\n\n') + ta.value.slice(pos).replace(/^\n+/, '');
    }
    this.selected = [];       // limpa a selecção
    const labels = picks.map((p) => ({
      b: p.b, name: this._verseBookName || p.b,
      c: p.c, v: p.v || 0,
      text: (p.v && this._verseTexts) ? (this._verseTexts[p.v] || '') : ''
    }));
    window.MinRefs.add(picks, labels);
    this.close();
  },
};

/* Gestão dos "chips" de referências do formulário */
window.MinRefs = {
  refs: <?= json_encode(array_map(fn($r) => ['b' => $r['b'], 'c' => (int) $r['c'], 'v' => $r['v']], $editRefs), JSON_UNESCAPED_UNICODE) ?>,
  labels: <?= json_encode(array_map(fn($r) => ['b' => $r['b'], 'name' => $r['name'], 'text' => $r['text']], $editRefs), JSON_UNESCAPED_UNICODE) ?>,

  labelOf(ref) {
    const l = this.labels.find((x) => x.b === ref.b);
    return (l ? l.name : ref.b) + ' ' + ref.c + (ref.v ? ':' + ref.v : '');
  },
  render() {
    const box = document.getElementById('min-refs');
    const input = document.getElementById('refs-input');
    input.value = JSON.stringify(this.refs);
    box.innerHTML = '';
    if (!this.refs.length) {
      box.innerHTML = '<span class="min-refs-empty">Nenhuma referência — clique em “Escolher versículos”.</span>';
      return;
    }
    this.refs.forEach((r, i) => {
      const chip = document.createElement('span');
      chip.className = 'min-chip';
      chip.title = (this.labels.find((x) => x.b === r.b && x.c === r.c && x.v === r.v)?.text) || '';
      chip.innerHTML = '<span class="bk-badge">' + BRAND_SVG(11) + '</span>' + escapeHtml(this.labelOf(r));
      const x = document.createElement('button');
      x.type = 'button';
      x.className = 'min-chip-x';
      x.innerHTML = '<i class="fas fa-xmark"></i>';
      x.setAttribute('aria-label', 'Remover');
      x.onclick = () => { this.refs.splice(i, 1); this.render(); };
      chip.appendChild(x);
      box.appendChild(chip);
    });
  },
  add(picks, labels = []) {
    for (const l of labels) {
      if (!this.labels.some((x) => x.b === l.b && x.c === l.c && x.v === l.v)) this.labels.push(l);
    }
    for (const p of picks) {
      if (!this.refs.some((r) => r.b === p.b && r.c === p.c && r.v === p.v)) {
        this.refs.push({ b: p.b, c: p.c, v: p.v });
      }
    }
    this.render();
  },
};

document.getElementById('btn-add-verse').addEventListener('click', () => VersePicker.open());
MinRefs.render();

/* Botão de partilha da ministração (link público) */
function bwShareMin(d) {
  shareText(d.t + ' — <?= e(APP_NAME) ?>', d.b, d.u);
}
</script>
<?php
$pageScripts = ob_get_clean();
require_once __DIR__ . '/includes/footer.php';
