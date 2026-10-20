import io

# --- 1) PaidBook: marcar los libros de pago en el catalogo ---
p = 'app/Modelos/PaidBook.php'
s = io.open(p, encoding='utf-8', newline='').read()

old = """                'es_texto_protegido' => true,
            ];
        }
        $statement->close();

        return $books;"""
new = """                'es_texto_protegido' => true,
                'requiere_compra' => true,
            ];
        }
        $statement->close();

        return $books;"""
assert s.count(old) == 1, ('paidbook', s.count(old))
io.open(p, 'w', encoding='utf-8', newline='').write(s.replace(old, new))

# --- 2) main.js: cargar las compras antes de armar las tarjetas ---
p = 'main.js'
s = io.open(p, encoding='utf-8', newline='').read()

old = """async function loadAdditionalCatalogBooks() {
\tconst catalogUrl = new URL("api/lista.php", document.baseURI);"""
new = """async function loadAdditionalCatalogBooks() {
\t// Las compras se cargan primero: asi las tarjetas ya muestran el estado real.
\tawait loadUserPurchases();

\tconst catalogUrl = new URL("api/lista.php", document.baseURI);"""
assert s.count(old) == 1, ('loaduserpurchases', s.count(old))
s = s.replace(old, new)

io.open(p, 'w', encoding='utf-8', newline='').write(s)
print('ok')