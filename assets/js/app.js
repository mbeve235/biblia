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

async function shareText(title, text) {
  if (navigator.share) {
    try { await navigator.share({ title, text }); } catch (e) { /* cancelado */ }
  } else {
    copyText(title + ' — ' + text);
  }
}

/* ---------------------------------------------------------------------
   Modal de selecção de livro/capítulo (usado no leitor)
--------------------------------------------------------------------- */
const BookPicker = {
  books: null,
  onPickChapter: null, // callback(slug, bookName, chapter, hasVerses)

  async ensureLoaded() {
    if (!this.books) {
      const res = await apiGet('/books');
      this.books = res.data.groups;
    }
  },

  open(onPickChapter) {
    this.onPickChapter = onPickChapter;
    this.ensureLoaded()
      .then(() => this.renderBooks('AT'))
      .catch((e) => toast(e.message, 'error'));
    document.getElementById('modal-books').classList.add('open');
  },

  close() {
    document.getElementById('modal-books').classList.remove('open');
  },

  renderBooks(testament) {
    const grid = document.getElementById('book-grid');
    document.querySelectorAll('.testament-tabs button').forEach((b) =>
      b.classList.toggle('active', b.dataset.testament === testament));

    const groups = this.books.filter((g) => g.testament === testament);
    grid.innerHTML = '';
    for (const g of groups) {
      const label = document.createElement('div');
      label.className = 'nav-label';
      label.style.margin = '.6rem .1rem .3rem';
      label.textContent = g.group;
      grid.appendChild(label);

      for (const b of g.books) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.innerHTML = '<span>' + escapeHtml(b.name) + '</span> <small>' + b.chapters_count + '</small>';
        btn.onclick = () => this.renderChapters(b);
        grid.appendChild(btn);
      }
    }
  },

  renderChapters(book) {
    const grid = document.getElementById('book-grid');
    grid.innerHTML = '<div class="nav-label" style="margin:.2rem .1rem .4rem">' +
      escapeHtml(book.name) + ' — escolha o capítulo</div>' +
      '<div class="chapter-grid" id="chapter-grid"></div>';
    const cg = document.getElementById('chapter-grid');
    for (let c = 1; c <= book.chapters_count; c++) {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.textContent = c;
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
    const back = document.createElement('button');
    back.className = 'btn btn-sm btn-ghost';
    back.style.marginTop = '.8rem';
    back.innerHTML = '<i class="fas fa-arrow-left"></i> Voltar aos livros';
    back.onclick = () => this.renderBooks(book.testament);
    grid.appendChild(back);
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
    el.addEventListener('click', (ev) => { ev.preventDefault(); BookPicker.open(window.onPickChapter || null); }));
  document.querySelector('#modal-books .modal-close')?.addEventListener('click', () => BookPicker.close());
  document.getElementById('modal-books')?.addEventListener('click', (e) => {
    if (e.target.id === 'modal-books') BookPicker.close();
  });
  document.querySelectorAll('.testament-tabs button').forEach((b) =>
    b.addEventListener('click', () => BookPicker.renderBooks(b.dataset.testament)));

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') { BookPicker.close(); closeSidebar(); }
  });

  // Favoritos carregados se existir leitor nesta página
  if (document.getElementById('verses')) {
    VerseActions.loadFavorites();
  }
});
