<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audiolibrería de clásicos</title>
    <link rel="stylesheet" href="style.css?v=spanish-classics-27">
    <script src="main.js?v=spanish-classics-18" defer></script>
</head>
<body>
    <header class="header">
        <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true): ?>
            <a class="header__admin" href="admin.php">Administración</a>
            <form class="header__logout" action="admin.php" method="post">
                <input type="hidden" name="action" value="logout">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['admin_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                <button class="admin-logout-button" type="submit">Salir del modo administrador</button>
            </form>
        <?php else: ?>
            <button class="button" type="button" data-modal-open="login-modal">Iniciar sesión</button>
            <button class="button" type="button" data-modal-open="register-modal">Crear cuenta</button>
        <?php endif; ?>
    </header>
    <main>
        <section class="intro">
            <div class="intro__content">
                <p class="intro__eyebrow">32 clásicos · Texto íntegro · Inglés y español</p>
                <h1 class="welcome-title">¡Bienvenido a nuestra audiolibrería!</h1>
                <p class="intro__subtitle">Textos completos de clásicos en inglés y español. Leé en el navegador y escuchá con voz natural.</p>
                <div class="intro__actions">
                    <a href="#catalog-title" class="intro__cta">Explorar colección →</a>
                    <span class="intro__meta">32 títulos · 12 libros íntegros en español · lectura en voz alta</span>
                </div>
            </div>
            <div class="intro__art" aria-hidden="true">
                <div class="intro__books">
                    <span></span><span></span><span></span>
                </div>
            </div>
        </section>

        <section class="catalog" aria-labelledby="catalog-title">
            <div class="catalog__heading">
                <p class="catalog__eyebrow">Explora la colección</p>
                <h2 id="catalog-title">Historias que te acompañan</h2>
                <p>Clásicos inolvidables para escuchar, descubrir y volver a disfrutar.</p>
            </div>

            <div class="catalog-layout">
                <div class="catalog-content">
                    <div class="catalog-tools">
                        <label for="book-search">
                            Buscar libros
                            <input id="book-search" type="search" placeholder="Título, autor o género" aria-controls="book-grid">
                        </label>
                    </div>
                    <p class="catalog-results" id="book-results" aria-live="polite">Se muestran 32 libros</p>

                    <div class="book-grid" id="book-grid">
                        <article class="book-card" data-genre="romance">
                            <img class="book-cover" src="https://covers.openlibrary.org/b/isbn/9780141439518-L.jpg" alt="Portada de Pride and Prejudice" loading="lazy">
                            <div class="book-card__info">
                                <p class="book-card__genre">Romance · Clásico</p>
                                <h3>Pride and Prejudice</h3>
                                <p>Jane Austen</p>
                                <button class="book-read" type="button" data-reader-url="books/1342.html">Leer libro</button>
                            </div>
                        </article>
                        <article class="book-card" data-genre="ciencia-ficcion">
                            <img class="book-cover" src="https://covers.openlibrary.org/b/isbn/9780141439471-L.jpg" alt="Portada de Frankenstein" loading="lazy">
                            <div class="book-card__info">
                                <p class="book-card__genre">Ciencia ficción · Clásico</p>
                                <h3>Frankenstein</h3>
                                <p>Mary Shelley</p>
                                <button class="book-read" type="button" data-reader-url="books/84.html">Leer libro</button>
                            </div>
                        </article>
                        <article class="book-card" data-genre="terror">
                            <img class="book-cover" src="https://covers.openlibrary.org/b/isbn/9780141439846-L.jpg" alt="Portada de Dracula" loading="lazy">
                            <div class="book-card__info">
                                <p class="book-card__genre">Terror · Clásico</p>
                                <h3>Dracula</h3>
                                <p>Bram Stoker</p>
                                <button class="book-read" type="button" data-reader-url="books/345.html">Leer libro</button>
                            </div>
                        </article>
                        <article class="book-card" data-genre="fantasia">
                            <img class="book-cover" src="https://covers.openlibrary.org/b/isbn/9780141439761-L.jpg" alt="Portada de Alice's Adventures in Wonderland" loading="lazy">
                            <div class="book-card__info">
                                <p class="book-card__genre">Fantasía · Clásico</p>
                                <h3>Alice's Adventures in Wonderland</h3>
                                <p>Lewis Carroll</p>
                                <button class="book-read" type="button" data-reader-url="books/11.html">Leer libro</button>
                            </div>
                        </article>
                        <article class="book-card" data-genre="aventura">
                            <img class="book-cover" src="https://covers.openlibrary.org/b/isbn/9780142437247-L.jpg" alt="Portada de Moby-Dick" loading="lazy">
                            <div class="book-card__info">
                                <p class="book-card__genre">Aventura · Clásico</p>
                                <h3>Moby-Dick</h3>
                                <p>Herman Melville</p>
                                <button class="book-read" type="button" data-reader-url="books/2701.html">Leer libro</button>
                            </div>
                        </article>
                        <article class="book-card" data-genre="novela">
                            <img class="book-cover" src="https://covers.openlibrary.org/b/isbn/9780743273565-L.jpg" alt="Portada de The Great Gatsby" loading="lazy">
                            <div class="book-card__info">
                                <p class="book-card__genre">Novela · Clásico</p>
                                <h3>The Great Gatsby</h3>
                                <p>F. Scott Fitzgerald</p>
                                <button class="book-read" type="button" data-reader-url="books/64317.html">Leer libro</button>
                            </div>
                        </article>
                        <article class="book-card" data-genre="romance">
                            <img class="book-cover" src="https://covers.openlibrary.org/b/isbn/9780141439587-L.jpg" alt="Portada de Emma" loading="lazy">
                            <div class="book-card__info">
                                <p class="book-card__genre">Romance · Clásico</p>
                                <h3>Emma</h3>
                                <p>Jane Austen</p>
                                <button class="book-read" type="button" data-reader-url="books/158.html">Leer libro</button>
                            </div>
                        </article>
                        <article class="book-card" data-genre="ciencia-ficcion">
                            <img class="book-cover" src="https://covers.openlibrary.org/b/isbn/9780141441030-L.jpg" alt="Portada de The War of the Worlds" loading="lazy">
                            <div class="book-card__info">
                                <p class="book-card__genre">Ciencia ficción · Clásico</p>
                                <h3>The War of the Worlds</h3>
                                <p>H. G. Wells</p>
                                <button class="book-read" type="button" data-reader-url="books/36.html">Leer libro</button>
                            </div>
                        </article>
                        <article class="book-card" data-genre="terror">
                            <img class="book-cover" src="https://covers.openlibrary.org/b/isbn/9780141439730-L.jpg" alt="Portada de The Strange Case of Dr Jekyll and Mr Hyde" loading="lazy">
                            <div class="book-card__info">
                                <p class="book-card__genre">Terror · Clásico</p>
                                <h3>The Strange Case of Dr Jekyll and Mr Hyde</h3>
                                <p>Robert Louis Stevenson</p>
                                <button class="book-read" type="button" data-reader-url="books/43.html">Leer libro</button>
                            </div>
                        </article>
                        <article class="book-card" data-genre="fantasia">
                            <img class="book-cover" src="https://covers.openlibrary.org/b/isbn/9780141321028-L.jpg" alt="Portada de The Wonderful Wizard of Oz" loading="lazy">
                            <div class="book-card__info">
                                <p class="book-card__genre">Fantasía · Clásico</p>
                                <h3>The Wonderful Wizard of Oz</h3>
                                <p>L. Frank Baum</p>
                                <button class="book-read" type="button" data-reader-url="books/55.html">Leer libro</button>
                            </div>
                        </article>
                        <article class="book-card" data-genre="aventura">
                            <img class="book-cover" src="https://covers.openlibrary.org/b/isbn/9780141321059-L.jpg" alt="Portada de The Call of the Wild" loading="lazy">
                            <div class="book-card__info">
                                <p class="book-card__genre">Aventura · Clásico</p>
                                <h3>The Call of the Wild</h3>
                                <p>Jack London</p>
                                <button class="book-read" type="button" data-reader-url="books/215.html">Leer libro</button>
                            </div>
                        </article>
                        <article class="book-card" data-genre="novela">
                            <img class="book-cover" src="https://covers.openlibrary.org/b/isbn/9780141441146-L.jpg" alt="Portada de Jane Eyre" loading="lazy">
                            <div class="book-card__info">
                                <p class="book-card__genre">Novela · Clásico</p>
                                <h3>Jane Eyre</h3>
                                <p>Charlotte Brontë</p>
                                <button class="book-read" type="button" data-reader-url="books/1260.html">Leer libro</button>
                            </div>
                        </article>
                        <article class="book-card" data-genre="misterio">
                            <img class="book-cover" src="https://covers.openlibrary.org/b/id/8063264-L.jpg" alt="Portada de The Hound of the Baskervilles" loading="lazy">
                            <div class="book-card__info">
                                <p class="book-card__genre">Misterio · Clásico</p>
                                <h3>The Hound of the Baskervilles</h3>
                                <p>Arthur Conan Doyle</p>
                                <button class="book-read" type="button" data-reader-url="books/2852.html">Leer libro</button>
                            </div>
                        </article>
                        <article class="book-card" data-genre="misterio">
                            <img class="book-cover" src="https://covers.openlibrary.org/b/id/11100465-L.jpg" alt="Portada de Murder on the Orient Express" loading="lazy">
                            <div class="book-card__info">
                                <p class="book-card__genre">Misterio · Clásico</p>
                                <h3>Murder on the Orient Express</h3>
                                <p>Agatha Christie</p>
                                <a class="book-read" href="https://openlibrary.org/works/OL471576W/Murder_on_the_Orient_Express" target="_blank" rel="noopener noreferrer">Consultar disponibilidad</a>
                            </div>
                        </article>
                        <article class="book-card" data-genre="poesia">
                            <img class="book-cover" src="https://covers.openlibrary.org/b/id/6981869-L.jpg" alt="Portada de The Complete Poems of Emily Dickinson" loading="lazy">
                            <div class="book-card__info">
                                <p class="book-card__genre">Poesía · Clásico</p>
                                <h3>The Complete Poems</h3>
                                <p>Emily Dickinson</p>
                                <button class="book-read" type="button" data-reader-url="books/12242.html">Leer libro</button>
                            </div>
                        </article>
                        <article class="book-card" data-genre="poesia">
                            <img class="book-cover" src="https://covers.openlibrary.org/b/id/9000447-L.jpg" alt="Portada de Leaves of Grass" loading="lazy">
                            <div class="book-card__info">
                                <p class="book-card__genre">Poesía · Clásico</p>
                                <h3>Leaves of Grass</h3>
                                <p>Walt Whitman</p>
                                <button class="book-read" type="button" data-reader-url="books/1322.html">Leer libro</button>
                            </div>
                        </article>
                        <article class="book-card" data-genre="historia">
                            <img class="book-cover" src="https://covers.openlibrary.org/b/id/12621906-L.jpg" alt="Portada de War and Peace" loading="lazy">
                            <div class="book-card__info">
                                <p class="book-card__genre">Historia · Clásico</p>
                                <h3>War and Peace</h3>
                                <p>León Tolstói</p>
                                <button class="book-read" type="button" data-reader-url="books/2600.html">Leer libro</button>
                            </div>
                        </article>
                        <article class="book-card" data-genre="historia">
                            <img class="book-cover" src="https://covers.openlibrary.org/b/id/13301713-L.jpg" alt="Portada de A Tale of Two Cities" loading="lazy">
                            <div class="book-card__info">
                                <p class="book-card__genre">Historia · Clásico</p>
                                <h3>A Tale of Two Cities</h3>
                                <p>Charles Dickens</p>
                                <button class="book-read" type="button" data-reader-url="books/98.html">Leer libro</button>
                            </div>
                        </article>
                        <article class="book-card" data-genre="filosofia">
                            <img class="book-cover book-cover--portrait" src="https://covers.openlibrary.org/b/isbn/9780140449334-L.jpg" alt="Portada de Meditations" loading="lazy">
                            <div class="book-card__info">
                                <p class="book-card__genre">Filosofía · Clásico</p>
                                <h3>Meditations</h3>
                                <p>Marco Aurelio</p>
                                <button class="book-read" type="button" data-reader-url="books/2680.html">Leer libro</button>
                            </div>
                        </article>
                        <article class="book-card" data-genre="filosofia">
                            <img class="book-cover" src="https://covers.openlibrary.org/b/id/14377522-L.jpg" alt="Portada de The Republic" loading="lazy">
                            <div class="book-card__info">
                                <p class="book-card__genre">Filosofía · Clásico</p>
                                <h3>The Republic</h3>
                                <p>Platón</p>
                                <button class="book-read" type="button" data-reader-url="books/1497.html">Leer libro</button>
                            </div>
                        </article>
                        <article class="book-card" data-genres="aventura novela" data-language="es">
                            <img class="book-cover" src="https://www.gutenberg.org/cache/epub/2000/pg2000.cover.medium.jpg" alt="Portada de Don Quijote de la Mancha" loading="lazy">
                            <div class="book-card__info">
                                <p class="book-card__genre">Aventura · Español</p>
                                <h3>Don Quijote de la Mancha</h3>
                                <p>Miguel de Cervantes Saavedra</p>
                                <button class="book-read" type="button" data-reader-url="books/2000.html">Leer libro</button>
                            </div>
                        </article>
                        <article class="book-card" data-genre="novela" data-language="es">
                            <img class="book-cover" src="https://www.gutenberg.org/cache/epub/320/pg320.cover.medium.jpg" alt="Portada de Lazarillo de Tormes" loading="lazy">
                            <div class="book-card__info">
                                <p class="book-card__genre">Novela · Español</p>
                                <h3>Lazarillo de Tormes</h3>
                                <p>Anónimo</p>
                                <button class="book-read" type="button" data-reader-url="books/320.html">Leer libro</button>
                            </div>
                        </article>
                        <article class="book-card" data-genre="novela" data-language="es">
                            <img class="book-cover" src="https://www.gutenberg.org/cache/epub/15725/pg15725.cover.medium.jpg" alt="Portada de Doña Perfecta" loading="lazy">
                            <div class="book-card__info">
                                <p class="book-card__genre">Novela · Español</p>
                                <h3>Doña Perfecta</h3>
                                <p>Benito Pérez Galdós</p>
                                <button class="book-read" type="button" data-reader-url="books/15725.html">Leer libro</button>
                            </div>
                        </article>
                        <article class="book-card" data-genre="novela" data-language="es">
                            <img class="book-cover" src="https://www.gutenberg.org/cache/epub/49836/pg49836.cover.medium.jpg" alt="Portada de Niebla" loading="lazy">
                            <div class="book-card__info">
                                <p class="book-card__genre">Novela · Español</p>
                                <h3>Niebla</h3>
                                <p>Miguel de Unamuno</p>
                                <button class="book-read" type="button" data-reader-url="books/49836.html">Leer libro</button>
                            </div>
                        </article>
                        <article class="book-card" data-genres="cuentos romance" data-language="es">
                            <img class="book-cover" src="https://www.gutenberg.org/cache/epub/55514/pg55514.cover.medium.jpg" alt="Portada de Cuentos de amor" loading="lazy">
                            <div class="book-card__info">
                                <p class="book-card__genre">Cuentos · Español</p>
                                <h3>Cuentos de amor</h3>
                                <p>Emilia Pardo Bazán</p>
                                <button class="book-read" type="button" data-reader-url="books/55514.html">Leer libro</button>
                            </div>
                        </article>
                        <article class="book-card" data-genre="filosofia" data-language="es">
                            <img class="book-cover book-cover--portrait" src="https://www.gutenberg.org/cache/epub/13608/pg13608.cover.medium.jpg" alt="Portada de Filosofía fundamental, tomo I" loading="lazy">
                            <div class="book-card__info">
                                <p class="book-card__genre">Filosofía · Español</p>
                                <h3>Filosofía fundamental, tomo I</h3>
                                <p>Jaime Luciano Balmes</p>
                                <button class="book-read" type="button" data-reader-url="books/13608.html">Leer libro</button>
                            </div>
                        </article>
                        <article class="book-card" data-genres="romance novela" data-language="es">
                            <img class="book-cover" src="https://www.gutenberg.org/cache/epub/52597/pg52597.cover.medium.jpg" alt="Portada de Insolación y Morriña" loading="lazy">
                            <div class="book-card__info">
                                <p class="book-card__genre">Romance · Novela · Español</p>
                                <h3>Insolación y Morriña</h3>
                                <p>Emilia Pardo Bazán</p>
                                <button class="book-read" type="button" data-reader-url="books/52597.html">Leer libro</button>
                            </div>
                        </article>
                        <article class="book-card" data-genres="poesia fantasia" data-language="es">
                            <img class="book-cover" src="https://www.gutenberg.org/cache/epub/57303/pg57303.cover.medium.jpg" alt="Portada de La Divina Comedia" loading="lazy">
                            <div class="book-card__info">
                                <p class="book-card__genre">Poesía · Fantasía · Español</p>
                                <h3>La Divina Comedia</h3>
                                <p>Dante Alighieri</p>
                                <button class="book-read" type="button" data-reader-url="books/57303.html">Leer libro</button>
                            </div>
                        </article>
                        <article class="book-card" data-genres="misterio novela" data-language="es">
                            <img class="book-cover" src="https://www.gutenberg.org/cache/epub/61851/pg61851.cover.medium.jpg" alt="Portada de El crimen y el castigo" loading="lazy">
                            <div class="book-card__info">
                                <p class="book-card__genre">Misterio · Novela · Español</p>
                                <h3>El crimen y el castigo</h3>
                                <p>Fiódor Dostoyevski</p>
                                <button class="book-read" type="button" data-reader-url="books/61851.html">Leer libro</button>
                            </div>
                        </article>
                        <article class="book-card" data-genres="ciencia-ficcion fantasia aventura" data-language="es">
                            <img class="book-cover" src="https://www.gutenberg.org/cache/epub/62359/pg62359.cover.medium.jpg" alt="Portada de El anacronópete y otros relatos" loading="lazy">
                            <div class="book-card__info">
                                <p class="book-card__genre">Ciencia ficción · Fantasía · Aventura · Español</p>
                                <h3>El anacronópete y otros relatos</h3>
                                <p>Enrique Gaspar</p>
                                <button class="book-read" type="button" data-reader-url="books/62359.html">Leer libro</button>
                            </div>
                        </article>
                        <article class="book-card" data-genres="terror cuentos fantasia" data-language="es">
                            <img class="book-cover" src="https://www.gutenberg.org/cache/epub/10814/pg10814.cover.medium.jpg" alt="Portada de Leyendas de Gustavo Adolfo Bécquer" loading="lazy">
                            <div class="book-card__info">
                                <p class="book-card__genre">Terror · Cuentos · Fantasía · Español</p>
                                <h3>Leyendas de Gustavo Adolfo Bécquer</h3>
                                <p>Gustavo Adolfo Bécquer</p>
                                <button class="book-read" type="button" data-reader-url="books/10814.html">Leer libro</button>
                            </div>
                        </article>
                        <article class="book-card" data-genres="historia fantasia aventura" data-language="es">
                            <img class="book-cover" src="https://www.gutenberg.org/cache/epub/49660/pg49660.cover.medium.jpg" alt="Portada de La Alhambra: leyendas árabes" loading="lazy">
                            <div class="book-card__info">
                                <p class="book-card__genre">Historia · Fantasía · Español</p>
                                <h3>La Alhambra: leyendas árabes</h3>
                                <p>Manuel Fernández y González</p>
                                <button class="book-read" type="button" data-reader-url="books/49660.html">Leer libro</button>
                            </div>
                        </article>
                    </div>
                    <p class="catalog-empty" id="book-empty" hidden>No encontramos libros que coincidan.</p>
                </div>

                <aside class="catalog-filters" aria-labelledby="genre-title">
                    <h3 id="genre-title">Género</h3>
                    <div class="genre-filter-buttons" role="group" aria-labelledby="genre-title">
                        <button class="genre-filter-button" type="button" data-genre-filter="all" aria-pressed="true">Todos</button>
                        <button class="genre-filter-button" type="button" data-genre-filter="romance" aria-pressed="false">Romance</button>
                        <button class="genre-filter-button" type="button" data-genre-filter="ciencia-ficcion" aria-pressed="false">Ciencia ficción</button>
                        <button class="genre-filter-button" type="button" data-genre-filter="terror" aria-pressed="false">Terror</button>
                        <button class="genre-filter-button" type="button" data-genre-filter="fantasia" aria-pressed="false">Fantasía</button>
                        <button class="genre-filter-button" type="button" data-genre-filter="aventura" aria-pressed="false">Aventura</button>
                        <button class="genre-filter-button" type="button" data-genre-filter="novela" aria-pressed="false">Novela</button>
                        <button class="genre-filter-button" type="button" data-genre-filter="misterio" aria-pressed="false">Misterio</button>
                        <button class="genre-filter-button" type="button" data-genre-filter="poesia" aria-pressed="false">Poesía</button>
                        <button class="genre-filter-button" type="button" data-genre-filter="historia" aria-pressed="false">Historia</button>
                        <button class="genre-filter-button" type="button" data-genre-filter="filosofia" aria-pressed="false">Filosofía</button>
                        <button class="genre-filter-button" type="button" data-genre-filter="cuentos" aria-pressed="false">Cuentos</button>
                    </div>
                    <div class="catalog-language-filter">
                        <h3 id="language-title">Idioma</h3>
                        <div class="genre-filter-buttons" role="group" aria-labelledby="language-title">
                            <button class="genre-filter-button" type="button" data-language-filter="all" aria-pressed="true">Todos</button>
                            <button class="genre-filter-button" type="button" data-language-filter="es" aria-pressed="false">Español</button>
                        </div>
                    </div>
                </aside>
            </div>
        </section>
    </main>

    <dialog class="reader-modal" id="reader-modal" aria-labelledby="reader-title">
        <div class="reader__header">
            <div>
                <p class="reader__eyebrow">Modo lectura</p>
                <h2 id="reader-title">Elige un libro</h2>
                <p id="reader-author" class="reader__author"></p>
            </div>
            <form method="dialog">
                <button class="modal__close" type="submit" aria-label="Cerrar lector">&times;</button>
            </form>
        </div>
        <div class="reader__toolbar">
            <div class="reader__speech" id="reader-speech-controls" role="group" aria-label="Lectura en voz alta">
                <button class="reader-control reader-control--primary" id="reader-listen" type="button" disabled>Escuchar</button>
                <button class="reader-control" id="reader-pause" type="button" disabled>Pausar</button>
                <button class="reader-control" id="reader-stop" type="button" disabled>Detener</button>
            </div>
            <label class="reader__speed" for="reader-speed">
                Velocidad <output id="reader-speed-value" for="reader-speed">1.0x</output>
                <input id="reader-speed" type="range" min="0.75" max="1.5" step="0.25" value="1">
            </label>
            <div class="reader__display" role="group" aria-label="Ajustes de lectura">
                <button class="reader-control" id="reader-smaller" type="button" aria-label="Reducir tamaño del texto">A−</button>
                <button class="reader-control" id="reader-larger" type="button" aria-label="Aumentar tamaño del texto">A+</button>
                <button class="reader-control" id="reader-theme" type="button">Modo oscuro</button>
            </div>
        </div>
        <p class="reader__status" id="reader-status" aria-live="polite">Cada libro se lee en su idioma original.</p>
        <p class="reader__source" id="reader-source" hidden>
            Fuente: Project Gutenberg · <a id="reader-source-link" target="_blank" rel="noopener noreferrer">ver edición original y licencia</a>
        </p>
        <div class="reader__layout">
            <article class="reader__text" id="reader-content" aria-label="Texto del libro"></article>
            <aside class="reader__chapters" aria-labelledby="reader-chapters-title">
                <h3 id="reader-chapters-title">Capítulos y secciones</h3>
                <nav class="reader__chapter-list" id="reader-chapter-list" aria-label="Índice del libro"></nav>
            </aside>
        </div>
    </dialog>

    <dialog class="modal" id="login-modal" aria-labelledby="login-title">
        <div class="modal__heading">
            <h2 id="login-title">Iniciar sesión</h2>
            <form method="dialog">
                <button class="modal__close" type="submit" aria-label="Cerrar inicio de sesión">&times;</button>
            </form>
        </div>
        <p>Ingresa tus datos para continuar.</p>
        <form action="admin.php" method="post">
            <input type="hidden" name="action" value="login">
            <label for="login-username">Usuario</label>
            <input id="login-username" name="username" type="text" autocomplete="username" required>
            <label for="login-password">Contraseña</label>
            <input id="login-password" name="password" type="password" autocomplete="current-password" required>
            <button class="intro__cta admin-submit" type="submit">Entrar</button>
        </form>
    </dialog>

    <dialog class="modal" id="register-modal" aria-labelledby="register-title">
        <div class="modal__heading">
            <h2 id="register-title">Crear cuenta</h2>
            <form method="dialog">
                <button class="modal__close" type="submit" aria-label="Cerrar registro">&times;</button>
            </form>
        </div>
        <p>Crea una cuenta nueva.</p>
        <form id="register-form">
            <label for="register-name">Nombre</label>
            <input id="register-name" name="name" type="text" autocomplete="name" required>
            <label for="register-email">Correo electrónico</label>
            <input id="register-email" name="email" type="email" autocomplete="email" required>
            <label for="register-password">Contraseña</label>
            <input id="register-password" name="password" type="password" autocomplete="new-password" required>
            <p id="register-status" class="admin-help" aria-live="polite"></p>
            <button class="intro__cta admin-submit" type="submit">Crear cuenta</button>
        </form>
    </dialog>
</body>
</html>