<?php
$section ??= 'home';
$message ??= null;
$success ??= false;
$books ??= [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administrar audiolibrería</title>
    <link rel="stylesheet" href="style.css?v=spanish-classics-21">
</head>
<body class="admin-page">
    <main class="admin-card admin-card--wide">
        <header class="admin-header">
            <div>
                <p class="catalog__eyebrow">Modo Editor · Panel de administrador</p>
                <h1><?= $section === 'add' ? 'Agregar un libro' : ($section === 'manage' ? 'Administrar libros' : 'Administración') ?></h1>
            </div>
            <?php if ($section === 'home'): ?>
                <form action="admin.php" method="post">
                    <input type="hidden" name="action" value="logout">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['admin_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                    <button class="admin-logout-button" type="submit">Salir del modo administrador</button>
                </form>
            <?php else: ?>
                <a class="intro__cta admin-back-button" href="admin.php">Volver</a>
            <?php endif; ?>
        </header>
        <?php if ($message !== null): ?>
            <p class="admin-message <?= $success ? 'admin-message--success' : 'admin-message--error' ?>" role="status"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <?php if ($section === 'home'): ?>
            <section class="admin-dashboard" aria-label="Opciones de administración">
                <a class="admin-action-card" href="admin.php?section=add">
                    <span class="admin-action-icon" aria-hidden="true">＋</span>
                    <span>
                        <strong>Agregar libros</strong>
                        <small>Incorporar un libro nuevo al catálogo</small>
                    </span>
                    <span class="admin-action-arrow" aria-hidden="true">→</span>
                </a>
                <a class="admin-action-card" href="admin.php?section=manage">
                    <span class="admin-action-icon admin-action-icon--manage" aria-hidden="true">≡</span>
                    <span>
                        <strong>Administrar libros</strong>
                        <small>Consultar el catálogo y eliminar libros</small>
                    </span>
                    <span class="admin-action-arrow" aria-hidden="true">→</span>
                </a>
            </section>
        <?php elseif ($section === 'manage'): ?>
            <section class="admin-catalog" aria-labelledby="admin-catalog-title">
                <h2 id="admin-catalog-title">Libros en el catálogo (<?= count($books) ?>)</h2>
                <?php if ($books === []): ?>
                    <p class="admin-help">Todavía no hay libros en el catálogo.</p>
                <?php else: ?>
                    <ul class="admin-book-list">
                        <?php foreach ($books as $book): ?>
                            <li class="admin-book-item">
                                <div class="admin-book-details">
                                    <strong><?= htmlspecialchars($book['titulo'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <span><?= htmlspecialchars($book['autor'], ENT_QUOTES, 'UTF-8') ?> · <?= $book['idioma'] === 'es' ? 'Español' : 'Inglés' ?> · ID <?= (int)$book['id'] ?></span>
                                </div>
                                <form action="admin.php?section=manage" method="post" onsubmit="return confirm('¿Eliminar este libro del catálogo? Esta acción no se puede deshacer.');">
                                    <input type="hidden" name="action" value="delete-book">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['admin_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="id" value="<?= (int)$book['id'] ?>">
                                    <input type="hidden" name="language" value="<?= htmlspecialchars($book['idioma'], ENT_QUOTES, 'UTF-8') ?>">
                                    <button class="admin-delete" type="submit" aria-label="Eliminar <?= htmlspecialchars($book['titulo'], ENT_QUOTES, 'UTF-8') ?>">Eliminar</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        <?php else: ?>
            <form action="admin.php" method="post" class="admin-book-form">
                <input type="hidden" name="action" value="add-book">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['admin_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                <label for="book-text">Texto completo
                    <textarea id="book-text" name="text" rows="16" maxlength="5000000" placeholder="Pega aquí el texto completo. Separa los párrafos con una línea en blanco." required></textarea>
                </label>
                <p class="admin-help">Pega el texto primero; luego completa los datos del libro. Máximo: 5 MB. Puedes dejar el ID vacío para que se asigne automáticamente.</p>
                <div class="admin-fields">
                    <label for="book-id">ID (opcional)
                        <input id="book-id" name="id" type="number" min="1" step="1" placeholder="Se asigna automáticamente si queda vacío">
                    </label>
                    <label for="book-title">Título
                        <input id="book-title" name="title" type="text" maxlength="255" required>
                    </label>
                    <label for="book-author">Autor
                        <input id="book-author" name="author" type="text" maxlength="255" required>
                    </label>
                    <label for="book-language">Idioma
                        <select id="book-language" name="language" required>
                            <option value="es">Español</option>
                            <option value="en">Inglés</option>
                        </select>
                    </label>
                    <label for="book-genre">Género
                        <select id="book-genre" name="genre" required>
                            <option value="aventura">Aventura</option>
                            <option value="ciencia-ficcion">Ciencia ficción</option>
                            <option value="cuentos">Cuentos</option>
                            <option value="fantasia">Fantasía</option>
                            <option value="filosofia">Filosofía</option>
                            <option value="historia">Historia</option>
                            <option value="misterio">Misterio</option>
                            <option value="novela">Novela</option>
                            <option value="poesia">Poesía</option>
                            <option value="romance">Romance</option>
                            <option value="terror">Terror</option>
                        </select>
                    </label>
                    <label for="book-cover">URL de la portada (opcional)
                        <input id="book-cover" name="cover" type="url" placeholder="https://...">
                    </label>
                    <label for="book-source">URL de la fuente (opcional)
                        <input id="book-source" name="source" type="url" placeholder="https://...">
                    </label>
                </div>
                <button class="intro__cta admin-submit" type="submit">Guardar libro</button>
            </form>
        <?php endif; ?>
    </main>
</body>
</html>
