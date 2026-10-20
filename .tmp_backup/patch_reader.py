import io

p = 'main.js'
s = io.open(p, encoding='utf-8', newline='').read()

old = '''\t\tconst catalog = await loadBookCatalog(bookLang);
\t\tconst data = catalog[bookId];
\t\tif (!data) throw new Error(`El libro ${bookId} no está en el catálogo ${bookLang}`);'''

new = '''\t\tconst data = await loadBookText(bookId, bookLang);
\t\tif (!data) throw new Error(`El libro ${bookId} no está en el catálogo ${bookLang}`);'''

assert s.count(old) == 1, ('reader', s.count(old))
s = s.replace(old, new)

io.open(p, 'w', encoding='utf-8', newline='').write(s)
print('reader ok')