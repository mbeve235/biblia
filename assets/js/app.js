/* =====================================================================
   BÍBLIA WEB — Núcleo JavaScript
   Utilitários partilhados: API, toasts, tema, tipografia, modal de livros.
   ===================================================================== */
'use strict';

/** URL base detectada no servidor (definida em footer.php). */
const BASE_URL = window.BASE_URL || '';
const CSRF_TOKEN = window.CSRF_TOKEN || '';
const IS_LOGGED_IN = window.IS_LOGGED_IN || false;

/* ---------------------------------------------------------------------
   API helper
--------------------------------------------------------------------- */
async function api(method, path, body = null) {
  const opts = {
    method,
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-Token': CSRF_TOKEN,
    },
  };
  if (body && !(body instanceof FormData)) {
    opts.headers['Content-Type'] = 'application/json';
    opts.body = JSON.stringify(body);
  } else if (body instanceof FormData) {
    opts.body = body;
  }
  const res = await fetch(BASE_URL + '/api' + path, opts);
  let json;
  try {
    json = await res.json();
  } catch (e) {
    throw new Error('Resposta inválida do servidor (' + res.status + ').');
  }
  if (!json.success) throw new Error(json.message || 'Ocorreu um erro.');
  return json;
}

const apiGet = (path) => api('GET', path);

/* ---------------------------------------------------------------------
   Toasts
--------------------------------------------------------------------- */
function toast(message, type = 'info', ms = 3500) {
  let box = document.querySelector('.toast-container');
  if (!box) {
    box = document.createElement('div');
    box.className = 'toast-container';
    document.body.appendChild(box);
  }
  const el = document.createElement('div');
  el.className = 'toast ' + type;
  el.textContent = message;
  box.appendChild(el);
  setTimeout(() => el.remove(), ms);
}

/* ---------------------------------------------------------------------
   Tema claro/escuro
--------------------------------------------------------------------- */
function applyTheme(theme) {
  document.documentElement.setAttribute('data-theme', theme);
  localStorage.setItem('bw_theme', theme);
  document.querySelectorAll('[data-theme-toggle] i')
    .forEach((i) => (i.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon'));
}
function currentTheme() {
  return localStorage.getItem('bw_theme') ||
    (window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
}
function toggleTheme() {
  applyTheme(currentTheme() === 'dark' ? 'light' : 'dark');
}

/* ---------------------------------------------------------------------
   Tipografia da leitura (tamanho da fonte)
--------------------------------------------------------------------- */
function getFontSize() {
  return parseInt(localStorage.getItem('bw_fontsize') || '17', 10);
}
function setFontSize(px) {
  px = Math.max(14, Math.min(28, px));
  localStorage.setItem('bw_fontsize', String(px));
  document.documentElement.style.setProperty('--read-font-size', px + 'px');
  const label = document.getElementById('font-size-label');
  if (label) label.textContent = px + 'px';
  syncFontSizeButtons();
}
function syncFontSizeButtons() {
  const dec = document.querySelector('[data-font-dec]');
  const inc = document.querySelector('[data-font-inc]');
  if (dec) dec.disabled = getFontSize() <= 14;
  if (inc) inc.disabled = getFontSize() >= 28;
}

/* ---------------------------------------------------------------------
   Utilitários
--------------------------------------------------------------------- */
const escapeHtml = (s) => String(s ?? '')
  .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
  .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

function debounce(fn, wait = 300) {
  let t;
  return (...args) => {
    clearTimeout(t);
    t = setTimeout(() => fn(...args), wait);
  };
}

/* Marca (livro branco) inline — igual ao brand_svg() do PHP */
const BRAND_SVG = (size = 12) =>
  '<svg width="' + size + '" height="' + size + '" viewBox="0 0 64 64" aria-hidden="true">' +
  '<path fill="currentColor" d="M32 20c-4-3.4-9.6-4.6-15-4.6v29c5.4 0 11 1.2 15 4.6 4-3.4 9.6-4.6 15-4.6v-29c-5.4 0-11 1.2-15 4.6z"/>' +
  '<path d="M32 20v29" stroke="#fff" stroke-width="2.4" stroke-linecap="round"/>' +
  '<path d="M22.5 27h7M26 23.5v12" stroke="#fff" stroke-width="2.6" stroke-linecap="round"/>' +
  '<path d="M37 27h7M37 32h7M37 37h4.5" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/>' +
  '</svg>';

async function copyText(text) {
  try {
    await navigator.clipboard.writeText(text);
    toast('Copiado para a área de transferência.', 'success');
  } catch (e) {
    toast('Não foi possível copiar.', 'error');
  }
}

async function shareText(title, text, url = '') {
  // Mensagem completa e consistente: título, texto e link sempre juntos
  const full = (title ? title + '\n\n' : '') + text + (url ? '\n\n' + url : '');
  if (navigator.share) {
    try {
      const payload = url ? { title, text, url } : { title, text };
      await navigator.share(payload);
    } catch (e) { /* cancelado */ }
  } else {
    copyText(full);
  }
}

/* ---------------------------------------------------------------------
   Modal de selecção de livro/capítulo (leitor) — mesmo design vp
   das ministrações: cabeçalho com passos 1·2, livros em cartões,
   capítulos com rótulo "cap." e fio de Ariadne para voltar.
--------------------------------------------------------------------- */
const BookPicker = {
  books: null,
  onPickChapter: null, // callback(slug, bookName, chapter)
  currentSlug: null,   // livro/capítulo actualmente a ler (para destacar no modal)
  currentChapter: 0,
  _query: '',
  _testament: 'AT',

  async ensureLoaded() {
    if (!this.books) {
      const res = await apiGet('/books');
      this.books = res.data.groups;
    }
  },

  el(id) { return document.getElementById(id); },
  esc(s) { return escapeHtml(s); },

  // Normaliza acentos para a pesquisa ("genesis" encontra "Génesis")
  norm(s) {
    return String(s || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  },

  open(onPickChapter, currentSlug = null, currentChapter = 0) {
    this.onPickChapter = onPickChapter;
    this.currentSlug = currentSlug;
    this.currentChapter = currentChapter || 0;
    this._query = '';
    this.el('modal-books').classList.add('open');
    document.body.classList.add('vp-lock');
    this.wireTestament();
    this.renderBooks(); // mostra "A carregar" até a API responder
    this.ensureLoaded()
      .then(() => {
        // Testamento inicial = o do livro actualmente a ler
        if (this.currentSlug) {
          const hit = this.books.flatMap((g) => g.books).find((b) => b.slug === this.currentSlug);
          if (hit) this._testament = hit.testament;
        }
        this.wireTestament();
        this.renderBooks();
      })
      .catch((e) => toast(e.message, 'error'));
  },

  close() {
    this.el('modal-books').classList.remove('open');
    document.body.classList.remove('vp-lock');
  },

  wireTestament() {
    document.querySelectorAll('#bp-testament .vp-seg-btn').forEach((b) => {
      b.classList.toggle('on', b.dataset.tst === this._testament);
      b.onclick = () => { this._testament = b.dataset.tst; this._query = ''; this.renderBooks(); };
    });
  },

  setStep(step) {
    const n = step === 'chapter' ? 2 : 1;
    document.querySelectorAll('#modal-books .vp-step').forEach((s) => {
      const sn = s.dataset.step === 'chapter' ? 2 : 1;
      s.classList.toggle('done', sn < n);
      s.classList.toggle('on', sn === n);
      s.disabled = sn >= n; // só se volta a passos anteriores
    });
  },

  setLoc(book = null, chapter = 0) {
    const loc = this.el('bp-loc');
    if (!loc) return;
    if (!book) { loc.textContent = 'Bíblia Sagrada'; return; }
    loc.innerHTML = '<i class="fas fa-location-dot"></i> ' + this.esc(book.name) + (chapter ? ' ' + chapter : '');
  },

  renderBooks() {
    this.setStep('book');
    const view = this.el('bp-view');
    view.innerHTML = '';

    const search = document.createElement('input');
    search.type = 'search';
    search.className = 'vp-search';
    search.placeholder = 'Pesquisar livro… (ex.: joao, sal)';
    search.value = this._query;
    search.setAttribute('aria-label', 'Pesquisar livro');
    search.oninput = debounce(() => { this._query = search.value; this.listBooks(); }, 120);
    view.appendChild(search);

    const wrap = document.createElement('div');
    wrap.className = 'vp-bookwrap';
    view.appendChild(wrap);
    if (this.books) this.listBooks(wrap);
    else wrap.innerHTML = '<div class="vp-loading"><i class="fas fa-spinner fa-spin"></i> A carregar os livros…</div>';
    this.setLoc();
  },

  listBooks(wrap) {
    wrap.innerHTML = '';
    const q = this.norm(this._query.trim());
    let shown = 0;
    const groups = this.books.filter((g) => g.testament === this._testament);
    for (const g of groups) {
      const books = g.books.filter((b) => !q ||
        this.norm(b.name).includes(q) || this.norm(b.abbreviation).includes(q) || this.norm(b.slug).includes(q));
      if (!books.length) continue;
      const label = document.createElement('div');
      label.className = 'vp-group-label';
      label.innerHTML = '<span class="test-mark ' + (g.testament === 'AT' ? 'at' : 'nt') + '">' + BRAND_SVG(10) + '</span>' + this.esc(g.group);
      wrap.appendChild(label);

      const grid = document.createElement('div');
      grid.className = 'vp-books';
      for (const b of books) {
        const isCurrent = !!(this.currentSlug && b.slug === this.currentSlug);
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'vp-book' + (isCurrent ? ' current' : '');
        btn.innerHTML =
          '<span class="bk-badge">' + BRAND_SVG(13) + '</span>' +
          '<span class="vp-book-name">' + this.esc(b.name) + (isCurrent ? ' <i class="fas fa-circle-check" title="A ler"></i>' : '') + '</span>' +
          '<small class="vp-book-ch">' + b.chapters_count + ' cap.</small>';
        btn.onclick = () => this.renderChapters(b);
        grid.appendChild(btn);
        shown++;
      }
      wrap.appendChild(grid);
    }
    if (!shown) {
      wrap.innerHTML = '<div class="vp-empty"><i class="fas fa-book"></i>Nenhum livro encontrado para “' + this.esc(this._query.trim()) + '”.</div>';
    }
  },

  renderCrumb(book) {
    const view = this.el('bp-view');
    const crumb = document.createElement('button');
    crumb.type = 'button';
    crumb.className = 'vp-crumb';
    crumb.innerHTML = '<i class="fas fa-arrow-left"></i> ' + this.esc(book.name) +
      '<small>' + book.chapters_count + ' capítulo' + (book.chapters_count > 1 ? 's' : '') + '</small>';
    crumb.onclick = () => this.renderBooks();
    view.prepend(crumb);
  },

  renderChapters(book) {
    this.setStep('chapter');
    this._testament = book.testament; // ao voltar, mantém o testamento do livro visto
    document.querySelectorAll('#bp-testament .vp-seg-btn').forEach((b) =>
      b.classList.toggle('on', b.dataset.tst === this._testament));

    const view = this.el('bp-view');
    view.innerHTML = '';
    this.renderCrumb(book);

    const cg = document.createElement('div');
    cg.className = 'vp-chapters';
    for (let c = 1; c <= book.chapters_count; c++) {
      const isCur = book.slug === this.currentSlug && c === this.currentChapter;
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'vp-chap' + (isCur ? ' current' : '');
      if (isCur) btn.title = 'Capítulo actual';
      btn.innerHTML = '<em>cap.</em>' + c;
      btn.onclick = () => {
        this.close();
        if (this.onPickChapter) {
          this.onPickChapter(book.slug, book.name, c);
          return;
        }
        // Fallback: navegar para o leitor mantendo a tradução actual
        const url = new URL(BASE_URL + '/leitura.php', location.href);
        const sel = document.getElementById('header-translation');
        const t = sel?.value || new URLSearchParams(location.search).get('translation') || '';
        if (t) url.searchParams.set('translation', t);
        url.searchParams.set('book', book.slug);
        url.searchParams.set('chapter', String(c));
        location.href = url.toString();
      };
      cg.appendChild(btn);
    }
    view.appendChild(cg);
    this.setLoc(book, 0);
  },
};

/* ---------------------------------------------------------------------
   Favoritos / notas (usados no leitor)
--------------------------------------------------------------------- */
const VerseActions = {
  favorites: new Set(),

  async loadFavorites() {
    if (!IS_LOGGED_IN) return;
    try {
      const res = await apiGet('/favorites');
      res.data.favorites.forEach((f) => {
        if (f.verse_id) this.favorites.add(parseInt(f.verse_id, 10));
      });
    } catch (e) { /* silencioso */ }
  },

  isFav(verseId) { return this.favorites.has(verseId); },

  async toggleFavorite(verseId, btn) {
    if (!IS_LOGGED_IN) {
      toast('Inicie sessão para guardar versículos.', 'error');
      return;
    }
    try {
      if (this.isFav(verseId)) {
        await api('DELETE', '/favorites/' + verseId);
        this.favorites.delete(verseId);
        toast('Versículo removido dos guardados.');
      } else {
        await api('POST', '/favorites', { verse_id: verseId });
        this.favorites.add(verseId);
        toast('Versículo guardado.', 'success');
      }
      if (btn) btn.classList.toggle('on', this.isFav(verseId));
    } catch (e) {
      toast(e.message, 'error');
    }
  },
};

/* ---------------------------------------------------------------------
   Arranque global
--------------------------------------------------------------------- */
document.addEventListener('DOMContentLoaded', () => {
  applyTheme(currentTheme());
  setFontSize(getFontSize());

  // Formulários de autenticação: visibilidade, força e confirmação da senha.
  document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
      const input = document.getElementById(button.dataset.passwordToggle);
      if (!input) return;
      const visible = input.type === 'text';
      input.type = visible ? 'password' : 'text';
      button.setAttribute('aria-label', visible ? 'Mostrar palavra-passe' : 'Ocultar palavra-passe');
      const icon = button.querySelector('i');
      if (icon) icon.className = visible ? 'fas fa-eye' : 'fas fa-eye-slash';
      input.focus();
    });
  });

  document.querySelectorAll('[data-password-strength]').forEach((input) => {
    const meter = input.closest('.field')?.querySelector('.password-strength');
    const updateStrength = () => {
      const value = input.value;
      let score = 0;
      if (value.length >= 6) score++;
      if (value.length >= 10) score++;
      if (/[a-z]/i.test(value) && /\d/.test(value)) score++;
      if (/[^a-z0-9]/i.test(value)) score++;
      const labels = ['', 'Fraca', 'Razoável', 'Boa', 'Forte'];
      if (meter) {
        meter.dataset.score = String(score);
        const label = meter.querySelector('small');
        if (label) label.textContent = value ? labels[score] : 'Use letras, números e símbolos';
      }
    };
    input.addEventListener('input', updateStrength);
  });

  document.querySelectorAll('[data-password-confirm]').forEach((input) => {
    const original = document.getElementById(input.dataset.passwordConfirm);
    const hint = input.closest('.field')?.querySelector('[data-confirm-hint]');
    const checkMatch = () => {
      const mismatch = !!input.value && input.value !== original?.value;
      input.setCustomValidity(mismatch ? 'As palavras-passe não coincidem.' : '');
      if (hint) {
        hint.textContent = input.value ? (mismatch ? 'As palavras-passe não coincidem.' : 'As palavras-passe coincidem.') : '';
        hint.className = 'field-hint ' + (mismatch ? 'error' : 'success');
      }
    };
    input.addEventListener('input', checkMatch);
    original?.addEventListener('input', checkMatch);
  });

  document.querySelectorAll('[data-auth-form]').forEach((form) => {
    form.addEventListener('submit', () => {
      const button = form.querySelector('[data-loading-text]');
      const label = button?.querySelector('span');
      if (!button || !label) return;
      label.textContent = button.dataset.loadingText;
      button.classList.add('loading');
      button.disabled = true;
    });
  });

  document.querySelectorAll('[data-theme-toggle]').forEach((el) =>
    el.addEventListener('click', (ev) => { ev.preventDefault(); toggleTheme(); }));

  document.querySelectorAll('[data-font-inc]').forEach((el) => el.addEventListener('click', () => setFontSize(getFontSize() + 1)));
  document.querySelectorAll('[data-font-dec]').forEach((el) => el.addEventListener('click', () => setFontSize(getFontSize() - 1)));

  // Sidebar em tablet/mobile
  const toggles = document.querySelectorAll('.menu-toggle, [data-sidebar-toggle]');
  let sidebarTrigger = null;
  const closeSidebar = () => {
    document.body.classList.remove('sidebar-open');
    toggles.forEach((t) => t.setAttribute('aria-expanded', 'false'));
    if (sidebarTrigger && document.activeElement?.closest('#sidebar')) sidebarTrigger.focus();
    sidebarTrigger = null;
  };
  toggles.forEach((t) => t.addEventListener('click', () => {
    const open = document.body.classList.toggle('sidebar-open');
    toggles.forEach((toggle) => toggle.setAttribute('aria-expanded', open ? 'true' : 'false'));
    sidebarTrigger = open ? t : null;
    if (open) document.querySelector('#sidebar .nav-item')?.focus();
  }));
  document.querySelector('.sidebar-backdrop')?.addEventListener('click', closeSidebar);

  // Tocar num link do menu fecha a sidebar (os botões de letra mantêm o menu aberto)
  document.querySelectorAll('.sidebar a.nav-item').forEach((el) =>
    el.addEventListener('click', closeSidebar));

  // Modal de livros
  document.querySelectorAll('[data-open-books]').forEach((el) =>
    el.addEventListener('click', (ev) => {
      ev.preventDefault();
      const ctx = window.bookPickerCurrent || {};
      BookPicker.open(window.onPickChapter || null, ctx.slug || null, ctx.chapter || 0);
    }));
  document.querySelector('#modal-books .vp-close')?.addEventListener('click', () => BookPicker.close());
  document.getElementById('bp-cancel')?.addEventListener('click', () => BookPicker.close());
  document.getElementById('modal-books')?.addEventListener('click', (e) => {
    if (e.target.id === 'modal-books') BookPicker.close();
  });

  // Qualquer modal fecha ao tocar fora (notas, livros, futuros modais do admin)
  document.querySelectorAll('.modal-overlay').forEach((ov) =>
    ov.addEventListener('click', (e) => {
      if (e.target === ov) ov.classList.remove('open');
    }));

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      BookPicker.close();
      closeSidebar();
      document.querySelectorAll('.modal-overlay.open').forEach((ov) => ov.classList.remove('open'));
    }
  });

  // Favoritos carregados se existir leitor nesta página
  if (document.getElementById('verses')) {
    VerseActions.loadFavorites();
  }
});
