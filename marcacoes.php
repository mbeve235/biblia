<?php
/** Marcações coloridas do utilizador. */
require_once __DIR__ . '/includes/bootstrap.php';
boot_session();
$user = require_login();
$pageTitle = 'Marcações';
require_once __DIR__ . '/includes/header.php';

try {
    $highlights = db_all(
        'SELECT h.verse_id,h.color,h.updated_at,v.verse_number,v.text,c.chapter_number,b.name AS book_name,b.slug,t.abbreviation AS translation
         FROM verse_highlights h JOIN verses v ON v.id=h.verse_id JOIN chapters c ON c.id=v.chapter_id
         JOIN books b ON b.id=v.book_id JOIN translations t ON t.id=v.translation_id
         WHERE h.user_id=? ORDER BY h.updated_at DESC',
        [$user['id']]
    );
    $highlightSetupMissing = false;
} catch (PDOException $e) {
    $highlights = [];
    $highlightSetupMissing = true;
}
?>
<header class="page-header saved-header"><span class="page-kicker">Pessoal</span><div class="page-title-row"><span class="page-icon"><i class="fas fa-bookmark"></i></span><div><h1>Guardados</h1><p>Reúna aqui os versículos e pensamentos que deseja revisitar.</p></div></div></header>
<nav class="saved-tabs" aria-label="Conteúdo guardado"><a href="<?= url('/favoritos.php') ?>"><i class="fas fa-bookmark"></i> Versículos</a><a class="active" aria-current="page" href="<?= url('/marcacoes.php') ?>"><i class="fas fa-highlighter"></i> Marcações <span><?= count($highlights) ?></span></a><a href="<?= url('/notas.php') ?>"><i class="fas fa-pen-to-square"></i> Notas</a></nav>

<?php if ($highlightSetupMissing): ?><div class="alert alert-warning">Execute a migração <code>20261022_verse_highlights.sql</code> para activar as marcações.</div>
<?php elseif (!$highlights): ?><div class="empty-state"><i class="fas fa-highlighter"></i><p>Ainda não marcou versículos com cores.</p><a class="btn btn-primary" href="<?= url('/biblia.php') ?>">Começar a ler</a></div>
<?php else: ?>
<div class="personal-filter"><i class="fas fa-magnifying-glass"></i><input type="search" id="highlights-filter" placeholder="Filtrar marcações…" aria-label="Filtrar marcações"><span id="highlights-count"><?= count($highlights) ?> visíveis</span></div>
<?php foreach ($highlights as $highlight): ?><section class="card highlight-card highlight-card-<?= e($highlight['color']) ?> personal-filter-item" data-search="<?= e(mb_strtolower($highlight['book_name'].' '.$highlight['chapter_number'].':'.$highlight['verse_number'].' '.$highlight['text'])) ?>"><div class="card-head"><h3 class="entity-title"><span class="highlight-dot"></span><a href="<?= url('/leitura.php?translation='.urlencode($highlight['translation']).'&book='.urlencode($highlight['slug']).'&chapter='.(int)$highlight['chapter_number'].'&verse='.(int)$highlight['verse_number']) ?>"><?= e($highlight['book_name']) ?> <?= (int)$highlight['chapter_number'] ?>:<?= (int)$highlight['verse_number'] ?></a><span class="badge badge-accent"><?= e($highlight['translation']) ?></span></h3><button class="btn btn-sm btn-ghost" type="button" onclick="removeHighlight(<?= (int)$highlight['verse_id'] ?>,this)"><i class="fas fa-eraser"></i> Remover</button></div><p class="scripture-text"><?= e($highlight['text']) ?></p></section><?php endforeach; ?>
<?php endif; ?>
<?php ob_start(); ?><script>
const highlightsFilter=document.getElementById('highlights-filter');highlightsFilter?.addEventListener('input',()=>{const q=highlightsFilter.value.trim().toLocaleLowerCase('pt');let count=0;document.querySelectorAll('.personal-filter-item').forEach(item=>{item.hidden=!!q&&!item.dataset.search.includes(q);if(!item.hidden)count++;});document.getElementById('highlights-count').textContent=count+' visíveis';});
async function removeHighlight(id,button){if(!confirm('Remover esta marcação?'))return;try{await api('DELETE','/highlights/'+id);button.closest('.card').remove();toast('Marcação removida.');}catch(error){toast(error.message,'error');}}
</script><?php $pageScripts=ob_get_clean(); require_once __DIR__ . '/includes/footer.php'; ?>
