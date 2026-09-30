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
   Modal de selecção de livro/capítulo (usado no leitor)
--------------------------------------------------------------------- */
const BookPicker = {
  books: null,
  onPickChapter: null, // callback(slug, bookName, chapter, hasVerses)
  currentSlug: null,   // livro/capítulo actualmente a ler (para destacar no modal)
  currentChapter: 0,
  _query: '',
  _lastTestament: 'AT',

  async ensureLoaded() {
    if (!this.books) {
      const res = await apiGet('/books');
      this.books = res.data.groups;
    }
  },

  open(onPickChapter, currentSlug = null, currentChapter = 0) {
    this.onPickChapter = onPickChapter;
    this.currentSlug = currentSlug;
    this.currentChapter = currentChapter;
    this._query = '';
    this.ensureLoaded()
      .then(() => this.renderBooks('AT'))
      .catch((e) => toast(e.message, 'error'));
    document.getElementById('modal-books').classList.add('open');
  },

  close() {
    document.getElementById('modal-books').classList.remove('open');
  },

  // Normaliza acentos para a pesquisa ("genesis" encontra "Génesis")
  norm(s) {
    return String(s || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  },

  buildSearch() {
    const wrap = document.createElement('div');
    wrap.className = 'book-search';
    wrap.innerHTML = '<i class="fas fa-magnifying-glass"></i>';
    const input = document.createElement('input');
    input.type = 'search';
    input.placeholder = 'Pesquisar livro… (ex.: joao, sal)';
    input.value = this._query;
    input.setAttribute('aria-label', 'Pesquisar livro');
    input.addEventListener('input', debounce(() => {
      this._query = input.value;
      if (this._query.trim()) this.renderSearch();
      else this.renderBooks(this._lastTestament);
    }, 120));
    wrap.appendChild(input);
    return wrap;
  },

  appendBookButtons(grid, books) {
    for (const b of books) {
      const btn = document.createElement('button');
      btn.type = 'button';
      if (this.currentSlug && b.slug === this.currentSlug) btn.classList.add('current');
      btn.innerHTML = '<span>' + escapeHtml(b.name) + '</span> ' +
        '<span class="bk-meta"><small>' + b.chapters_count + '</small><i class="fas fa-chevron-right"></i></span>';
      btn.onclick = () => this.renderChapters(b);
      grid.appendChild(btn);
    }
  },

  renderSearch() {
    const grid = document.getElementById('book-grid');
    const q = this.norm(this._query.trim());
    grid.innerHTML = '';
    grid.appendChild(this.buildSearch());
    const all = this.books.flatMap((g) => g.books);
    const hits = all.filter((b) =>
      this.norm(b.name).includes(q) || this.norm(b.abbreviation).includes(q) || this.norm(b.slug).includes(q));
    if (!hits.length) {
      const empty = document.createElement('div');
      empty.className = 'nav-label';
      empty.style.margin = '1rem .1rem';
      empty.textContent = 'Nenhum livro encontrado para “' + this._query.trim() + '”';
      grid.appendChild(empty);
      return;
    }
    this.appendBookButtons(grid, hits);
  },

  renderBooks(testament) {
    this._lastTestament = testament;
    const grid = document.getElementById('book-grid');
    document.querySelectorAll('.testament-tabs button').forEach((b) =>
      b.classList.toggle('active', b.dataset.testament === testament));

    const groups = this.books.filter((g) => g.testament === testament);
    grid.innerHTML = '';
    grid.appendChild(this.buildSearch());

    for (const g of groups) {
      const label = document.createElement('div');
      label.className = 'nav-label';
      label.style.margin = '.6rem .1rem .3rem';
      label.textContent = g.group;
      grid.appendChild(label);

      this.appendBookButtons(grid, g.books);
    }
  },

  renderChapters(book) {
    const grid = document.getElementById('book-grid');
    grid.innerHTML = '';

    // Voltar no TOPO: com listas de 150 capítulos, ficava escondido no fundo
    const back = document.createElement('button');
    back.className = 'btn btn-sm btn-ghost book-back';
    back.innerHTML = '<i class="fas fa-arrow-left"></i> Todos os livros';
    back.onclick = () => this.renderBooks(book.testament);
    grid.appendChild(back);

    const label = document.createElement('div');
    label.className = 'nav-label';
    label.style.margin = '.2rem .1rem .4rem';
    label.textContent = book.name + ' — ' + book.chapters_count + ' capítulo(s): escolha o capítulo';
    grid.appendChild(label);

    const cg = document.createElement('div');
    cg.className = 'chapter-grid';
    for (let c = 1; c <= book.chapters_count; c++) {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.textContent = c;
      if (this.currentSlug && book.slug === this.currentSlug && c === this.currentChapter) {
        btn.classList.add('current');
        btn.title = 'Capítulo actual';
      }
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
    grid.appendChild(cg);
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
      toast('Inicie sessão para guardar favoritos.', 'error');
      return;
    }
    try {
      if (this.isFav(verseId)) {
        await api('DELETE', '/favorites/' + verseId);
        this.favorites.delete(verseId);
        toast('Removido dos favoritos.');
      } else {
        await api('POST', '/favorites', { verse_id: verseId });
        this.favorites.add(verseId);
        toast('Adicionado aos favoritos.', 'success');
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

  document.querySelectorAll('[data-theme-toggle]').forEach((el) =>
    el.addEventListener('click', (ev) => { ev.preventDefault(); toggleTheme(); }));

  document.querySelectorAll('[data-font-inc]').forEach((el) => el.addEventListener('click', () => setFontSize(getFontSize() + 1)));
  document.querySelectorAll('[data-font-dec]').forEach((el) => el.addEventListener('click', () => setFontSize(getFontSize() - 1)));

  // Sidebar em tablet/mobile
  const toggles = document.querySelectorAll('.menu-toggle');
  const closeSidebar = () => {
    document.body.classList.remove('sidebar-open');
    toggles.forEach((t) => t.setAttribute('aria-expanded', 'false'));
  };
  toggles.forEach((t) => t.addEventListener('click', () => {
    const open = document.body.classList.toggle('sidebar-open');
    t.setAttribute('aria-expanded', open ? 'true' : 'false');
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
  document.querySelector('#modal-books .modal-close')?.addEventListener('click', () => BookPicker.close());
  document.getElementById('modal-books')?.addEventListener('click', (e) => {
    if (e.target.id === 'modal-books') BookPicker.close();
  });
  document.querySelectorAll('.testament-tabs button').forEach((b) =>
    b.addEventListener('click', () => BookPicker.renderBooks(b.dataset.testament)));

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
