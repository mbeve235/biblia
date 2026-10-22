# 📖 Bíblia Web

Plataforma web completa de **leitura e estudo da Bíblia**, construída com **PHP 8 + MySQL/MariaDB + HTML5/CSS3/JavaScript ES6** para funcionar localmente no **XAMPP** — sem frameworks pesadas.

As traduções são **dados, não código**: qualquer versão da Bíblia pode ser adicionada através do painel administrativo (JSON, CSV ou texto estruturado) sem tocar no código-fonte.

---

## ✨ Funcionalidades

| Leitor | Administração |
|---|---|
| Escolha de tradução dinâmica (via BD) | Dashboard com estatísticas |
| Navegação Livro → Capítulo → Versículo | Gestão de traduções (CRUD) |
| Pesquisa (palavras, frases, referências como `João 3:16`) | **Importação inteligente** com detecção de formato, validação, prévia, progresso e relatório |
| Comparação de 2-4 traduções (colunas ou tabela) | Gestão de livros, capítulos e versículos |
| Favoritos e notas pessoais | Gestão de utilizadores e papéis |
| Planos de leitura, metas diárias e sequência de leitura | Gestão de utilizadores e papéis |
| Histórico + "Continuar a leitura" | Exportação JSON/CSV por tradução |
| Modo claro/escuro, letra ajustável, foco e leitura em voz alta | Backup SQL completo |
| Pesquisa, cópia e partilha rápida de versículos | Logs de actividade e de pesquisa |
| Responsivo: desktop, tablet, telemóvel (menu inferior) | Logs de actividade e de pesquisa |

---

## 🧱 Estrutura do projecto

```
biblia-web/
├── admin/            Painel administrativo (dashboard, traduções, importar…)
├── api/              API REST (router + rotas por recurso)
│   └── routes/
├── assets/
│   ├── css/          Design system (tema claro/escuro)
│   ├── js/           Núcleo JS (API, toasts, tema, modal de livros)
│   ├── icons/        Placeholders de ícones
│   └── vendor/fontawesome/  (ver instalação de fontes abaixo)
├── config/           config.php (ajustes) + database.php (PDO)
├── database/         database.sql (esquema + 66 livros + 1189 capítulos)
├── includes/         bootstrap, header, footer, auth, helpers, response…
├── logs/             logs da aplicação (protegido)
├── uploads/          ficheiros de importação (protegido)
├── utils/            Importer (parser/validador/importador)
├── *.php             Páginas públicas (index, biblia, leitura, pesquisa…)
├── install.php       Instalador assistido (eliminar depois!)
└── .htaccess         URLs amigáveis + segurança
```

---

## 🚀 Instalação no XAMPP (passo a passo)

### 1. Instalar o XAMPP
Descarregue em [apachefriends.org](https://www.apachefriends.org/) e instale. Certifique-se de que inclui **Apache**, **MySQL/MariaDB** e **phpMyAdmin**.

### 2. Iniciar serviços
Abra o **XAMPP Control Panel** e clique em **Start** em:
- **Apache**
- **MySQL**

### 3. Copiar o projecto
Copie a pasta do projecto para:

```
C:\xampp\htdocs\biblia-web
```

### 4. Criar a base de dados

**Opção A — phpMyAdmin (recomendada):**
1. Abra <http://localhost/phpmyadmin>
2. Separador **Importar** → escolha `database/database.sql` → **Executar**.

**Opção B — instalador assistido:**
1. Abra <http://localhost/biblia-web/install.php>
2. Clique em **Instalar agora**.
3. **Elimine o `install.php`** no final.

### 5. Configurar ligação
Edite `config/config.php` se necessário:

```php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'biblia_web');
define('DB_USER', 'root');
define('DB_PASS', '');        // XAMPP por omissão: vazio
```

### 6. Abrir a aplicação
- **Site:** <http://localhost/biblia-web>
- **Painel:** <http://localhost/biblia-web/admin>

**Conta inicial:**

| Papel | E-mail | Palavra-passe |
|---|---|---|
| Administrador | `admin@biblia.local` | `admin123` |
| Editor | `editor@biblia.local` | `editor123` |

> ⚠️ **Altere estas palavras-passe** no primeiro acesso (Perfil / Utilizadores).

### 7. Ícones (Font Awesome)
A aplicação usa Font Awesome 6 Free. Coloque a distribuição **CSS + webfonts** em:

```
assets/vendor/fontawesome/css/all.min.css
assets/vendor/fontawesome/webfonts/…
```

Descarregue a versão *for the web* (free) em fontawesome.com e copie as pastas `css/` e `webfonts/`. Sem isso a aplicação funciona, mas sem ícones.

---

## 📥 Como adicionar uma nova tradução (sem programar)

1. **Administração → Traduções → Nova tradução** — preencha nome, abreviação (ex.: `NVI`), idioma, copyright e estado.
2. **Administração → Importar** — escolha a tradução, carregue o ficheiro (ou cole o texto) e clique **Validar**.
3. O sistema **detecta o formato**, mostra livros/capítulos/versículos encontrados, avisos e uma amostra.
4. Clique **Iniciar importação** — a barra de progresso mostra o andamento (importação em blocos, sem timeout).
5. No final vê o **relatório** e a tradução fica imediatamente disponível no leitor.

### Formatos aceites

**JSON (estrutura completa):**

```json
{
  "translation": { "name": "Minha Tradução", "abbreviation": "MT" },
  "books": [
    {
      "name": "Génesis",
      "abbreviation": "Gn",
      "testament": "AT",
      "chapters": [
        { "chapter": 1, "verses": [
          { "verse": 1, "text": "No princípio criou Deus os céus e a terra." },
          { "verse": 2, "text": "…" }
        ]}
      ]
    }
  ]
}
```

**CSV** (`translation,book,chapter,verse,text` — com ou sem cabeçalho):

```csv
ARC,Génesis,1,1,"No princípio criou Deus os céus e a terra."
ARC,Génesis,1,2,"E a terra era sem forma e vazia…"
```

**Texto estruturado** (`.txt` ou colado no campo):

```
Génesis
Capítulo 1
1 No princípio criou Deus os céus e a terra.
2 E a terra era sem forma e vazia…
```

ou por linha: `João 3:16 Porque Deus amou o mundo…`

> O parser aceita variações (com/sem acentos, `"Gênesis"`, abreviações, JSON plano `{ "Génesis": { "1": { "1": "texto" } } }`).
> Os livros são reconhecidos pela base de dados — **nomes desconhecidos geram erro antes de importar**.

### Notas legais
Importe apenas traduções **em domínio público** ou para as quais tenha **licença**. A tradução de demonstração incluída (`ARC`) contém apenas amostras curtas para testar a aplicação.

### Onde descarregar JSONs de traduções

**Português (verifique sempre a licença de cada tradução):**

| Fonte | Traduções | Formato | Compatível com o importador? |
|---|---|---|---|
| [thiagobodruk/bible](https://github.com/thiagobodruk/bible) | 90 versões / 35 idiomas: ARC, ACF, ARA, NAA, NVI, KJA… | JSON: `[{abbrev, name, chapters:[[verses]]}]` | ✅ directa (link bruto: `json/pt_arc.json`) |
| [MaatheusGois/bible](https://github.com/MaatheusGois/bible) | pt-br: ARC, ACF, NVI, AA, KJA · outros idiomas | JSON: `[{id, name, chapters:[[verses]]}]` | ✅ directa (ex.: `versions/pt-br/arc.json`) |
| [damarals/biblias](https://github.com/damarals/biblias) | 18 Bíblias em português (Zefania XML, SQLite, JSON) | vários | ⚠️ XML/SQLite exigem conversão prévia |
| [develop4God/bible_versions](https://github.com/develop4God/bible_versions) | várias línguas (SQLite + index) | SQLite | ⚠️ exigem conversão |
| [Bible SuperSearch (SourceForge)](https://sourceforge.net/projects/biblesuper/files/All%20Bibles%20-%20JSON/) | várias, "legalmente partilháveis" | JSON por versão | ✅/⚠️ conforme estrutura |
| [Beblia/Holy-Bible-XML-Format](https://github.com/Beblia/Holy-Bible-XML-Format) | 200+ línguas / 1000+ versões | XML | ⚠️ exigem conversão |
| [wldeh/bible-api](https://github.com/wldeh/bible-api) | centenas de versões (via CDN) | JSON por capítulo | ⚠️ um ficheiro por capítulo |
| [seven1m/open-bibles](https://github.com/seven1m/open-bibles) | domínio público / licenças livres | OSIS/USFX/Zefania XML | ⚠️ exigem conversão |

**Links prontos (formato suportado directamente):**

```
https://raw.githubusercontent.com/thiagobodruk/bible/master/json/pt_arc.json   (Almeida ARC)
https://raw.githubusercontent.com/thiagobodruk/bible/master/json/pt_nvi.json  (NVI — veja licença)
https://raw.githubusercontent.com/MaatheusGois/bible/main/versions/pt-br/arc.json (Almeida ARC)
```

Descarregue o ficheiro, guarde como `.json` e siga o fluxo normal: **Administração → Importar → Validar → Importar**. O importador aceita directamente a estrutura `[{"abbrev":"gn","name":"Gênesis","chapters":[["v1","v2",…]]}]` (numeração implícita de capítulos e versículos) e canonicaliza nomes/abreviações ("Gênesis"→"Génesis", "gn"→"Génesis", "jo"→"João").

---

## 🔗 URLs

| Amigável | Equivalente |
|---|---|
| `/biblia/ARC/joao/3` | `/leitura.php?translation=ARC&book=joao&chapter=3` |
| `/biblia/ARC/joao` | `/biblia.php?translation=ARC&book=joao` |
| `/pesquisa/amor` | `/pesquisa.php?q=amor` |
| `/api/translations` | API REST |

A API responde sempre `{ "success": bool, "data": …, "message": "…" }`. Endpoints: `/api/translations`, `/api/books`, `/api/books/{id}/chapters`, `/api/bible/{trad}/{livro}/{cap}`, `/api/search?q=`, `/api/favorites`, `/api/notes`, `/api/history`, `/api/import/validate|run`, `/api/export/translation/{id}`, `/api/export/database`.

---

## 🔒 Segurança incluída

- PDO + **prepared statements** em todas as consultas
- **CSRF** em formulários e chamadas API
- Escape de saída (XSS) com helper `e()` / `escapeHtml()`
- `password_hash` / `password_verify` (bcrypt), re-hash automático
- Sessões com cookies `HttpOnly`/`SameSite`, regeneração de ID
- Papéis: `admin` > `editor` > `user`, validados no servidor
- Uploads: extensão/limite validados, pasta sem execução de PHP
- `.htaccess` bloqueia `config/`, `includes/`, `utils/`, `database/`, `logs/`, `uploads/`

---

## 🧪 Teste rápido pós-instalação

1. Início mostra 1 tradução, 66 livros e os versículos de demonstração.
2. Abra `Bíblia → João → 3` — deve mostrar João 3:16 (demo ARC).
3. Pesquise **amor** ou **João 3:16**.
4. Entre no painel, **Traduções**, crie "Teste (TST)" e importe o JSON de exemplo acima.
5. Exporte a tradução em **Backup** e confirme o JSON/CSV.

---

## 🛠️ Resolução de problemas

| Problema | Solução |
|---|---|
| "Erro de ligação à base de dados" | MySQL activo? Credenciais certas em `config/config.php`? `database.sql` importado? |
| URL amigável não funciona | Active `mod_rewrite` no XAMPP (Apache → Config → httpd.conf → descomentar `LoadModule rewrite_module`); o site funciona igualmente sem ele |
| Importação grande falha | Aumente `max_execution_time`/`memory_limit` no php.ini; a importação já corre em blocos de 500 versículos |
| Ícones não aparecem | Instale Font Awesome em `assets/vendor/fontawesome/` (secção 7) |
| Esqueci a senha de admin | Use "Recuperar" no login (o link aparece no ecrã, pois o XAMPP não envia e-mail) |
