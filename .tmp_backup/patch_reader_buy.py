import io

p = 'main.js'
s = io.open(p, encoding='utf-8', newline='').read()

# --- El boton de compra del lector ---
old = '''document.addEventListener("click", (event) => {
\tconst button = event.target.closest(".book-read[data-reader-url]");
\tif (!button) return;
\topenBookReader(button.dataset.readerUrl, button.closest(".book-card"));
});'''
new = '''document.addEventListener("click", (event) => {
\tconst button = event.target.closest(".book-read[data-reader-url]");
\tif (!button) return;
\topenBookReader(button.dataset.readerUrl, button.closest(".book-card"));
});

// Comprar desde el lector: al terminar, se abre el libro ya comprado.
readerBuyButton?.addEventListener("click", async () => {
\tconst bookId = readerBuyButton.dataset.bookId;
\tconst language = readerBuyButton.dataset.bookLanguage;
\tconst price = Number(readerBuyButton.dataset.bookPrice);
\tif (!bookId) return;

\tawait buyBook(bookId, language, price, readerBuyButton);

\tif (ownsBook(bookId, language)) {
\t\treaderLocked.hidden = true;
\t\tconst card = bookCards.find((candidate) => {
\t\t\tconst id = candidate.querySelector("[data-reader-url]")?.dataset.readerUrl.match(/(\\d+)\\.html$/)?.[1];
\t\t\treturn id === String(bookId) && (candidate.dataset.language === "es" ? "es" : "en") === language;
\t\t});
\t\tawait openBookReader(`books/${bookId}.html`, card);
\t}
});'''
assert s.count(old) == 1, ('reader buy handler', s.count(old))
s = s.replace(old, new)

io.open(p, 'w', encoding='utf-8', newline='').write(s)
print('reader buy ok')