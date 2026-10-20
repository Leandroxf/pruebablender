import io

p = 'main.js'
s = io.open(p, encoding='utf-8', newline='').read()

anchor = '''async function openBookReader(bookUrl, card) {'''

addition = '''/**
 * Carga el texto de un libro.
 *
 * Los libros de pago no viven en el JSON publico: su texto llega desde
 * api/libro.php, que solo lo entrega a quien lo compro.
 */
async function loadBookText(bookId, language) {
\tconst catalog = await loadBookCatalog(language);
\tconst entry = catalog[bookId];

\t// Los libros de pago vienen marcados en el catalogo publico.
\tconst isPaid = Boolean(entry?.requiere_compra) || entry?.secciones?.length === 0;
\tif (!isPaid) {
\t\treturn entry;
\t}

\tconst url = new URL(bookEndpoint.href);
\turl.searchParams.set("id", bookId);
\turl.searchParams.set("lang", language);

\tconst response = await fetch(url, {
\t\theaders: { Accept: "application/json" },
\t\tcredentials: "same-origin",
\t\tcache: "no-store"
\t});
\tconst data = await response.json().catch(() => null);

\tif (!response.ok) {
\t\tif (response.status === 401) {
\t\t\topenLoginModal();
\t\t}
\t\tthrow new Error(data?.error || `No se pudo acceder al libro (HTTP ${response.status})`);
\t}

\tif (data?.comprado === false || data?.requiere_compra) {
\t\tthrow new Error(data?.error || "Este libro es de pago. Compralo para poder leerlo.");
\t}

\treturn data;
}

async function openBookReader(bookUrl, card) {'''

assert s.count(anchor) == 1, ('anchor', s.count(anchor))
s = s.replace(anchor, addition)

io.open(p, 'w', encoding='utf-8', newline='').write(s)
print('loadBookText ok')