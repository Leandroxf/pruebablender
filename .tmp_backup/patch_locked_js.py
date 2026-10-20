import io

p = 'main.js'
s = io.open(p, encoding='utf-8', newline='').read()

# --- 1) Referencias del DOM del estado bloqueado ---
old = '''const readerSpeechControls = document.querySelector("#reader-speech-controls");'''
new = '''const readerLocked = document.querySelector("#reader-locked");
const readerLockedText = document.querySelector("#reader-locked-text");
const readerBuyButton = document.querySelector("#reader-buy");
const readerSpeechControls = document.querySelector("#reader-speech-controls");'''
assert s.count(old) == 1, ('dom refs', s.count(old))
s = s.replace(old, new)

# --- 2) Mostrar / ocultar el estado bloqueado ---
old = '''\treaderStatus.textContent = `Cargando el texto completo en ${bookLang === "es" ? "español" : "inglés"}...`;
\tlistenButton.disabled = true;

\ttry {'''
new = '''\treaderStatus.textContent = `Cargando el texto completo en ${bookLang === "es" ? "español" : "inglés"}...`;
\tlistenButton.disabled = true;
\treaderLocked.hidden = true;

\ttry {'''
assert s.count(old) == 1, ('reset locked', s.count(old))
s = s.replace(old, new)

# --- 3) Error: si el libro esta bloqueado, mostrar el CTA de compra ---
old = '''\t} catch (error) {
\t\tif (requestId !== readerRequestId) return;
\t\tconsole.error("openBookReader error:", error);
\t\tconst isFile = window.location.protocol === "file:";
\t\tconst extra = isFile ? " Estás abriendo el archivo con doble clic (file://). Abrí http://localhost/proyectodetodo/ desde XAMPP." : "";
\t\treaderStatus.textContent = `No se pudo cargar el libro (${error.message}). Verifica que el archivo api/libros${bookLang === "es" ? "_es" : ""}.json exista y que abras el sitio desde http://localhost/proyectodetodo/.${extra}`;
\t}'''
new = '''\t} catch (error) {
\t\tif (requestId !== readerRequestId) return;
\t\tconsole.error("openBookReader error:", error);

\t\t// Libro de pago sin compra: mostrar el aviso y el boton de compra.
\t\tif (error?.requiresPurchase) {
\t\t\tshowLockedBook(error);
\t\t\treturn;
\t\t}

\t\tconst isFile = window.location.protocol === "file:";
\t\tconst extra = isFile ? " Estás abriendo el archivo con doble clic (file://). Abrí http://localhost/proyectodetodo/ desde XAMPP." : "";
\t\treaderStatus.textContent = `No se pudo cargar el libro (${error.message}). Verifica que el archivo api/libros${bookLang === "es" ? "_es" : ""}.json exista y que abras el sitio desde http://localhost/proyectodetodo/.${extra}`;
\t}'''
assert s.count(old) == 1, ('catch locked', s.count(old))
s = s.replace(old, new)

# --- 4) Funcion showLockedBook + error con flag requiresPurchase ---
old = '''/**
 * Carga el texto de un libro.'''
new = '''/**
 * Muestra el estado "bloqueado" en el lector, con la llamada a comprar.
 */
function showLockedBook(error) {
\tconst meta = error?.libro || {};
\treaderStatus.textContent = "Este libro es de pago.";
\treaderLocked.hidden = false;
\treaderLockedText.textContent = error?.message || "Necesitás comprar este libro para leerlo.";

\tconst canBuy = Boolean(meta.id) && Boolean(meta.precio);
\treaderBuyButton.hidden = !canBuy;
\tif (canBuy) {
\t\treaderBuyButton.textContent = `Comprar por ${formatPrice(meta.precio)}`;
\t\treaderBuyButton.dataset.bookId = String(meta.id);
\t\treaderBuyButton.dataset.bookLanguage = meta.idioma === "es" ? "es" : "en";
\t\treaderBuyButton.dataset.bookPrice = String(meta.precio);
\t}
}

/**
 * Carga el texto de un libro.'''
assert s.count(old) == 1, ('showLockedBook', s.count(old))
s = s.replace(old, new)

# --- 5) loadBookText: marcar el error como requiresPurchase y adjuntar el libro ---
old = '''\tconst data = await response.json().catch(() => null);

\tif (!response.ok) {
\t\tif (response.status === 401) {
\t\t\topenLoginModal();
\t\t}
\t\tthrow new Error(data?.error || `No se pudo acceder al libro (HTTP ${response.status})`);
\t}'''
new = '''\tconst data = await response.json().catch(() => null);

\tif (!response.ok) {
\t\tif (response.status === 401 && !data?.libro) {
\t\t\topenLoginModal();
\t\t\tthrow new Error(data?.error || "Necesitás iniciar sesión para leer este libro.");
\t\t}

\t\tconst error = new Error(data?.error || `No se pudo acceder al libro (HTTP ${response.status})`);
\t\terror.requiresPurchase = true;
\t\terror.libro = data?.libro || null;
\t\tthrow error;
\t}'''
assert s.count(old) == 1, ('loadBookText error', s.count(old))
s = s.replace(old, new)

io.open(p, 'w', encoding='utf-8', newline='').write(s)
print('locked js ok')