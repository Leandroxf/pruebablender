import io

p = 'main.js'
s = io.open(p, encoding='utf-8', newline='').read()

# --- 1) refreshPaidCard: un solo boton de accion (comprar o leer) ---
old = '''/**
 * Actualiza la tarjeta de un libro de pago segun si el usuario lo compro.
 */
function refreshPaidCard(card, bookId, language) {
\tconst owned = ownsBook(bookId, language);
\tcard.classList.toggle("book-card--owned", owned);

\tconst buyButton = card.querySelector('[data-purchase-action="buy"]');
\tconst readButton = card.querySelector('[data-reader-url]');
\tconst badge = card.querySelector(".book-card__owned-badge");

\tif (buyButton) {
\t\tbuyButton.hidden = owned;
\t\tbuyButton.disabled = false;
\t}
\tif (badge) {
\t\tbadge.hidden = !owned;
\t}
\tif (readButton) {
\t\treadButton.textContent = owned ? "Leer libro" : "Leer con compra";
\t\treadButton.dataset.requiresPurchase = owned ? "0" : "1";
\t}
}'''

new = '''/**
 * Actualiza la tarjeta de un libro de pago segun si el usuario lo compro.
 *
 * La tarjeta muestra una sola accion: "Comprar libro" mientras no se compro
 * y "Leer libro" una vez que el libro esta en la biblioteca del usuario.
 */
function refreshPaidCard(card, bookId, language) {
\tconst owned = ownsBook(bookId, language);
\tcard.classList.toggle("book-card--owned", owned);

\tconst buyButton = card.querySelector('[data-purchase-action="buy"]');
\tconst readButton = card.querySelector('[data-reader-url]');
\tconst badge = card.querySelector(".book-card__owned-badge");

\tif (buyButton) {
\t\tbuyButton.hidden = owned;
\t\tbuyButton.disabled = false;
\t}
\tif (badge) {
\t\tbadge.hidden = !owned;
\t}
\tif (readButton && card.dataset.requiresPurchase === "1") {
\t\treadButton.hidden = !owned;
\t\treadButton.textContent = "Leer libro";
\t}
}'''
assert s.count(old) == 1, ('refreshPaidCard', s.count(old))
s = s.replace(old, new)

# --- 2) Creacion de la tarjeta: marcar el libro de pago y usar "Comprar libro" ---
old = '''\t\tif (isPaid) {
\t\t\tconst ownedBadge = document.createElement("span");
\t\t\townedBadge.className = "book-card__owned-badge";
\t\t\townedBadge.textContent = "Comprado";
\t\t\townedBadge.hidden = true;
\t\t\tinfo.append(ownedBadge);

\t\t\tconst buyButton = document.createElement("button");
\t\t\tbuyButton.className = "book-buy";
\t\t\tbuyButton.type = "button";
\t\t\tbuyButton.dataset.purchaseAction = "buy";
\t\t\tbuyButton.dataset.bookId = String(book.id);
\t\t\tbuyButton.dataset.bookLanguage = language;
\t\t\tbuyButton.dataset.bookPrice = String(book.precio);
\t\t\tbuyButton.textContent = `Comprar por ${formatPrice(book.precio)}`;
\t\t\tinfo.append(buyButton);

\t\t\trefreshPaidCard(card, book.id, language);
\t\t}

\t\tinfo.append(readButton);
\t\tcard.append(cover, info);'''

new = '''\t\tif (isPaid) {
\t\t\tcard.dataset.requiresPurchase = "1";

\t\t\tconst ownedBadge = document.createElement("span");
\t\t\townedBadge.className = "book-card__owned-badge";
\t\t\townedBadge.textContent = "Comprado";
\t\t\townedBadge.hidden = true;
\t\t\tinfo.append(ownedBadge);

\t\t\tconst buyButton = document.createElement("button");
\t\t\tbuyButton.className = "book-buy";
\t\t\tbuyButton.type = "button";
\t\t\tbuyButton.dataset.purchaseAction = "buy";
\t\t\tbuyButton.dataset.bookId = String(book.id);
\t\t\tbuyButton.dataset.bookLanguage = language;
\t\t\tbuyButton.dataset.bookPrice = String(book.precio);
\t\t\tbuyButton.textContent = "Comprar libro";
\t\t\tinfo.append(buyButton);
\t\t}

\t\tinfo.append(readButton);
\t\tcard.append(cover, info);

\t\tif (isPaid) {
\t\t\trefreshPaidCard(card, book.id, language);
\t\t}'''
assert s.count(old) == 1, ('card build', s.count(old))
s = s.replace(old, new)

# --- 3) El boton del lector tambien se llama "Comprar libro" ---
old = '''\tconst canBuy = Boolean(meta.id) && Boolean(meta.precio);
\treaderBuyButton.hidden = !canBuy;
\tif (canBuy) {
\t\treaderBuyButton.textContent = `Comprar por ${formatPrice(meta.precio)}`;'''
new = '''\tconst canBuy = Boolean(meta.id) && Boolean(meta.precio);
\treaderBuyButton.hidden = !canBuy;
\tif (canBuy) {
\t\treaderBuyButton.textContent = "Comprar libro";'''
assert s.count(old) == 1, ('reader buy label', s.count(old))
s = s.replace(old, new)

# --- 4) El aviso del lector menciona el precio ---
old = '''\treaderLockedText.textContent = error?.message || "Necesitás comprar este libro para leerlo.";'''
new = '''\tconst reason = error?.message || "Necesitás comprar este libro para leerlo.";
\treaderLockedText.textContent = meta.precio
\t\t? `${reason} Cuesta ${formatPrice(meta.precio)}.`
\t\t: reason;'''
assert s.count(old) == 1, ('locked text', s.count(old))
s = s.replace(old, new)

io.open(p, 'w', encoding='utf-8', newline='').write(s)
print('js ok')