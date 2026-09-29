<?php
/**
 * Assistente de importação inteligente.
 * Fluxo: escolher tradução → ficheiro/texto → validar → pré-visualizar → importar → relatório.
 */
require_once __DIR__ . '/_admin_header.php';
require_editor();

$translations = db_all('SELECT id, name, abbreviation FROM translations ORDER BY name');
$preselect = (int) ($_GET['translation_id'] ?? 0);
?>

<div class="reader-header">
  <div class="crumb">Administração</div>
  <h1><i class="fas fa-file-import" style="color:var(--accent)"></i> Importar tradução</h1>
  <p style="color:var(--text-soft)">O sistema detecta o formato automaticamente, valida e importa em blocos com barra de progresso.</p>
</div>

<div class="wizard-steps">
  <div class="step active" id="step-1">1 · Ficheiro</div>
  <div class="step" id="step-2">2 · Validação</div>
  <div class="step" id="step-3">3 · Importação</div>
  <div class="step" id="step-4">4 · Relatório</div>
</div>

<!-- PASSO 1: origem dos dados -->
<section class="card" id="panel-source">
  <div class="card-head"><h3>Origem dos dados</h3></div>

  <label class="field"><span>Tradução de destino *</span>
    <select id="imp-translation">
      <option value="">— seleccionar —</option>
      <?php foreach ($translations as $t): ?>
        <option value="<?= (int) $t['id'] ?>" <?= $preselect === (int) $t['id'] ? 'selected' : '' ?>>
          <?= e($t['abbreviation']) ?> — <?= e($t['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <small style="color:var(--text-faint)">Não encontra? <a href="<?= url('/admin/traducoes.php') ?>">Crie a tradução primeiro</a>.</small>
  </label>

  <label class="field"><span>Formato (opcional — detectado automaticamente)</span>
    <select id="imp-format">
      <option value="">Detectar automaticamente</option>
      <option value="json">JSON</option>
      <option value="csv">CSV</option>
      <option value="text">Texto estruturado</option>
    </select>
  </label>

  <div class="dropzone" id="dropzone">
    <i class="fas fa-cloud-arrow-up"></i>
    <p><strong>Clique para escolher o ficheiro</strong> ou arraste-o para aqui</p>
    <p style="font-size:.78rem">Formatos: .json · .csv · .txt — máx. <?= (int) (UPLOAD_MAX_BYTES / 1048576) ?> MB</p>
    <input type="file" id="imp-file" accept=".json,.csv,.txt" hidden>
  </div>

  <div style="text-align:center;color:var(--text-faint);margin:.8rem 0">— ou —</div>

  <label class="field"><span>Colar conteúdo estruturado</span>
    <textarea id="imp-text" rows="7" placeholder='{"translation":{"name":"Minha Tradução","abbreviation":"MT"},"books":[{"name":"Génesis","chapters":[{"chapter":1,"verses":[{"verse":1,"text":"No princípio…"}]}]}]}'></textarea>
  </label>

  <button class="btn btn-primary" id="btn-validate" type="button"><i class="fas fa-check-double"></i> Validar</button>
</section>

<!-- PASSO 2: pré-visualização -->
<section class="card" id="panel-preview" style="display:none">
  <div class="card-head">
    <h3>Pré-visualização</h3>
    <span class="badge badge-accent" id="pv-format"></span>
  </div>

  <div class="grid grid-4" id="pv-stats"></div>

  <div id="pv-warnings" style="margin-top:1rem"></div>

  <h4 style="margin:1rem 0 .4rem">Amostra</h4>
  <div class="table-wrap"><table class="table" id="pv-sample"><thead><tr><th>Referência</th><th>Texto</th></tr></thead><tbody></tbody></table></div>

  <div style="display:flex;gap:.6rem;margin-top:1rem">
    <button class="btn" type="button" onclick="location.reload()"><i class="fas fa-arrow-left"></i> Voltar</button>
    <button class="btn btn-primary" id="btn-start" type="button"><i class="fas fa-play"></i> Iniciar importação</button>
  </div>
</section>

<!-- PASSO 3: progresso -->
<section class="card" id="panel-progress" style="display:none">
  <div class="card-head"><h3>Importação em curso…</h3></div>
  <div class="progressbar"><div id="pg-bar"></div></div>
  <p style="margin-top:.7rem" id="pg-label">A preparar…</p>
</section>

<!-- PASSO 4: relatório -->
<section class="card" id="panel-report" style="display:none">
  <div class="card-head">
    <h3 style="color:var(--success)"><i class="fas fa-circle-check"></i> <span id="rp-title">Importação concluída</span></h3>
    <button class="btn btn-sm" id="btn-report-dl" type="button"><i class="fas fa-download"></i> Exportar relatório</button>
  </div>
  <div class="grid grid-4" id="rp-stats"></div>
  <div id="rp-details" style="margin-top:1rem"></div>
  <div style="display:flex;gap:.6rem;margin-top:1rem">
    <a class="btn btn-primary" href="<?= url('/admin/traducoes.php') ?>"><i class="fas fa-language"></i> Ver traduções</a>
    <a class="btn" href="<?= url('/admin/importacoes.php') ?>"><i class="fas fa-clipboard-list"></i> Histórico de importações</a>
  </div>
</section>

<?php
ob_start();
?>
<script>
(function () {
  const $ = (id) => document.getElementById(id);
  let lastReport = null;

  const dropzone = $('dropzone'), fileInput = $('imp-file');
  dropzone.addEventListener('click', () => fileInput.click());
  dropzone.addEventListener('dragover', (e) => { e.preventDefault(); dropzone.classList.add('drag'); });
  dropzone.addEventListener('dragleave', () => dropzone.classList.remove('drag'));
  dropzone.addEventListener('drop', (e) => {
    e.preventDefault(); dropzone.classList.remove('drag');
    if (e.dataTransfer.files.length) {
      fileInput.files = e.dataTransfer.files;
      dropzone.querySelector('p strong').textContent = fileInput.files[0].name;
    }
  });
  fileInput.addEventListener('change', () => {
    if (fileInput.files.length) dropzone.querySelector('p strong').textContent = fileInput.files[0].name;
  });

  function setStep(n) {
    for (let i = 1; i <= 4; i++) {
      const el = $('step-' + i);
      el.classList.toggle('active', i === n);
      el.classList.toggle('done', i < n);
    }
  }

  // -----------------------------------------------------------
  // VALIDAR
  // -----------------------------------------------------------
  $('btn-validate').addEventListener('click', async () => {
    const transId = $('imp-translation').value;
    if (!transId) { toast('Escolha a tradução de destino.', 'error'); return; }

    const fd = new FormData();
    fd.append('translation_id', transId);
    fd.append('format', $('imp-format').value);
    if (fileInput.files.length) fd.append('file', fileInput.files[0]);
    else if ($('imp-text').value.trim()) fd.append('text', $('imp-text').value);
    else { toast('Escolha um ficheiro ou cole o conteúdo.', 'error'); return; }

    $('btn-validate').disabled = true;
    $('btn-validate').innerHTML = '<i class="fas fa-spinner fa-spin"></i> A validar…';
    try {
      const res = await api('POST', '/import/validate', fd);
      showPreview(res.data);
    } catch (e) {
      toast(e.message, 'error', 6000);
    } finally {
      $('btn-validate').disabled = false;
      $('btn-validate').innerHTML = '<i class="fas fa-check-double"></i> Validar';
    }
  });

  function showPreview(data) {
    if (!data.valid) {
      let html = '<div class="alert alert-error"><strong>O ficheiro não pode ser importado.</strong><ul style="margin:.4rem 0 0 1.2rem">';
      (data.errors || []).forEach((er) => { html += '<li><strong>' + escapeHtml(er.location) + '</strong>: ' + escapeHtml(er.problem) + '</li>'; });
      html += '</ul></div>';
      $('pv-warnings').innerHTML = html;
      $('panel-preview').style.display = 'block';
      $('panel-preview').scrollIntoView({ behavior: 'smooth' });
      $('btn-start').style.display = 'none';
      $('pv-format').textContent = 'Formato: ' + (data.detected_format || '?').toUpperCase();
      $('pv-stats').innerHTML = '';
      $('pv-sample').querySelector('tbody').innerHTML = '';
      setStep(2);
      return;
    }

    const s = data.stats;
    $('pv-format').textContent = 'Formato detectado: ' + (data.detected_format || '?').toUpperCase();
    $('pv-stats').innerHTML = [
      ['book', 'Livros', s.books], ['layer-group', 'Capítulos', s.chapters],
      ['scroll', 'Versículos', s.verses],
      ['triangle-exclamation', 'Avisos', data.warnings_total || 0],
    ].map(([ic, lb, v]) =>
      '<div class="stat-card"><div class="icon"><i class="fas fa-' + ic + '"></i></div>' +
      '<div><div class="value">' + fmtNum(v) + '</div><div class="label">' + lb + '</div></div></div>').join('');

    let warnHtml = '';
    if ((data.warnings || []).length) {
      warnHtml = '<div class="alert alert-warning"><strong>' + data.warnings_total + ' aviso(s) — estes registos serão ignorados/ajustados:</strong><div class="log-list" style="margin-top:.5rem">';
      data.warnings.forEach((w) => {
        warnHtml += '<div class="log-item"><i class="fas fa-triangle-exclamation" style="color:var(--warning);margin-top:.25rem"></i><span><strong>' + escapeHtml(w.location) + '</strong>: ' + escapeHtml(w.problem) + '</span></div>';
      });
      warnHtml += '</div></div>';
    } else {
      warnHtml = '<div class="alert alert-success">Nenhum problema detectado. Compatibilidade: válida.</div>';
    }
    $('pv-warnings').innerHTML = warnHtml;

    const tb = $('pv-sample').querySelector('tbody');
    tb.innerHTML = (data.preview || []).map((p) =>
      '<tr><td><strong>' + escapeHtml(p.reference) + '</strong></td><td>' + escapeHtml(p.text) + '</td></tr>').join('');

    $('btn-start').style.display = 'inline-flex';
    $('panel-preview').style.display = 'block';
    $('panel-preview').scrollIntoView({ behavior: 'smooth' });
    setStep(2);
  }

  const fmtNum = (n) => Number(n || 0).toLocaleString('pt-PT');

  // -----------------------------------------------------------
  // IMPORTAR (blocos AJAX)
  // -----------------------------------------------------------
  $('btn-start').addEventListener('click', async () => {
    $('panel-progress').style.display = 'block';
    $('panel-progress').scrollIntoView({ behavior: 'smooth' });
    setStep(3);
    let offset = 0;
    try {
      for (;;) {
        const res = await api('POST', '/import/run', { offset });
        const d = res.data;
        if (d.done) { showReport(d.report); break; }
        const pct = d.total ? Math.round((d.processed / d.total) * 100) : 0;
        $('pg-bar').style.width = pct + '%';
        $('pg-label').textContent = pct + '% — ' + fmtNum(d.processed) + ' / ' + fmtNum(d.total) + ' versículos processados';
        offset++;
      }
    } catch (e) {
      toast('Importação interrompida: ' + e.message, 'error', 8000);
      $('pg-label').textContent = 'Erro: ' + e.message;
    }
  });

  function showReport(r) {
    $('pg-bar').style.width = '100%';
    setStep(4);
    $('panel-report').style.display = 'block';
    $('panel-report').scrollIntoView({ behavior: 'smooth' });
    lastReport = r;
    $('rp-stats').innerHTML = [
      ['book', 'Livros com texto', r.books], ['scroll', 'Versículos na tradução', r.verses_total],
      ['plus', 'Versículos novos', r.verses_new], ['clone', 'Duplicados actualizados', r.duplicates],
    ].map(([ic, lb, v]) =>
      '<div class="stat-card"><div class="icon"><i class="fas fa-' + ic + '"></i></div>' +
      '<div><div class="value">' + fmtNum(v) + '</div><div class="label">' + lb + '</div></div></div>').join('');
    $('rp-details').innerHTML =
      '<div class="alert alert-success">Tradução <strong>' + escapeHtml(r.translation) +
      '</strong> concluída com ' + fmtNum(r.errors) + ' erro(s). Já está disponível na aplicação.</div>';
    toast('Importação concluída com sucesso.', 'success', 6000);
  }

  $('btn-report-dl').addEventListener('click', () => {
    if (!lastReport) return;
    const blob = new Blob([JSON.stringify(lastReport, null, 2)], { type: 'application/json' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'relatorio-importacao.json';
    a.click();
  });
})();
</script>
<?php
$pageScripts = ob_get_clean();
require_once __DIR__ . '/_admin_footer.php';
