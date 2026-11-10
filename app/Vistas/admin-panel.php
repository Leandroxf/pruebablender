<?php

// Panel de administración: inicio, agregar libros y administrar el catálogo.

$section = $section ?? 'home';
$message = $message ?? null;
$success = $success ?? false;
$books = $books ?? array();
$nombreAdmin = htmlspecialchars((string) $sesion->usuarioNombre(), ENT_QUOTES, 'UTF-8');

$titulos = array('add' => 'Agregar un libro', 'manage' => 'Administrar libros');
$titulo = $titulos[$section] ?? 'Administración';

$claseMensaje = 'admin-message--error';
if ($success) {
    $claseMensaje = 'admin-message--success';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administrar audiolibrería</title>
    <link rel="stylesheet" href="style.css?v=shop-sections-1">
</head>
<body class="admin-page">
    <main class="admin-card admin-card--wide">
        <header class="admin-header">
            <div>
                <p class="catalog__eyebrow">Modo Editor · Panel de administrador</p>
                <h1><?= $titulo ?></h1>
                <p class="admin-account-label">Sesi?n de <strong><?= $nombreAdmin ?></strong> ? Administrador</p>
            </div>
            <?php if ($section === 'home') { ?>
                <a class="admin-logout-button" href="./">Volver al inicio</a>
            <?php } ?>
            <?php if ($section !== 'home') { ?>
                <a class="intro__cta admin-back-button" href="admin.php">Volver</a>
            <?php } ?>
        </header>
        <?php if ($message !== null) { ?>
            <p class="admin-message <?= $claseMensaje ?>" role="status"><?= htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8') ?></p>
        <?php } ?>
        <?php if ($section === 'home') { ?>
            <section class="admin-dashboard" aria-label="Opciones de administración">
                <a class="admin-action-card" href="admin.php?section=add">
                    <span class="admin-action-icon" aria-hidden="true">＋</span>
                    <span>
                        <strong>Agregar libros</strong>
                        <small>Publicar un libro con precio y stock</small>
                    </span>
                    <span class="admin-action-arrow" aria-hidden="true">→</span>
                </a>
                <a class="admin-action-card" href="admin.php?section=manage">
                    <span class="admin-action-icon admin-action-icon--manage" aria-hidden="true">≡</span>
                    <span>
                        <strong>Administrar libros</strong>
                        <small>Actualizar existencias y administrar libros</small>
                    </span>
                    <span class="admin-action-arrow" aria-hidden="true">→</span>
                </a>
            </section>
        <?php } ?>
        <?php if ($section === 'manage') { ?>
            <section class="admin-catalog" aria-labelledby="admin-catalog-title">
                <h2 id="admin-catalog-title">Libros en el catálogo (<?= count($books) ?>)</h2>
                <?php if (count($books) === 0) { ?>
                    <p class="admin-help">Todavía no hay libros en el catálogo.</p>
                <?php } else { ?>
                    <ul class="admin-book-list">
                        <?php foreach ($books as $book) { ?>
                            <?php
                            $nombreIdioma = 'Inglés';
                            if ($book['idioma'] === 'es') {
                                $nombreIdioma = 'Español';
                            }
                            ?>
                            <li class="admin-book-item">
                                <div class="admin-book-details">
                                    <strong><?= htmlspecialchars((string) $book['titulo'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <span><?= htmlspecialchars((string) $book['autor'], ENT_QUOTES, 'UTF-8') ?> · <?= $nombreIdioma ?> · ID <?= (int) $book['id'] ?></span>
                                    <?php if ($book['precio'] !== null) { ?>
                                        <span><?= htmlspecialchars((string) $book['precio'], ENT_QUOTES, 'UTF-8') ?> · Stock: <?= $book['stock'] === null ? 'sin control' : (int) $book['stock'] ?></span>
                                    <?php } ?>
                                </div>
                                <div class="admin-book-actions">
                                    <?php if ($book['precio'] !== null) { ?>
                                        <form action="admin.php?section=manage" method="post" class="admin-stock-form">
                                            <input type="hidden" name="action" value="update-stock">
                                            <input type="hidden" name="id" value="<?= (int) $book['id'] ?>">
                                            <input type="hidden" name="language" value="<?= htmlspecialchars((string) $book['idioma'], ENT_QUOTES, 'UTF-8') ?>">
                                            <label for="stock-<?= (int) $book['id'] ?>">Stock</label>
                                            <button class="admin-stock-step" type="button" data-stock-step="1" aria-label="Aumentar stock en uno">+</button>
                                            <input id="stock-<?= (int) $book['id'] ?>" name="stock" type="number" min="0" max="4294967295" step="1" value="<?= $book['stock'] === null ? '' : (int) $book['stock'] ?>" placeholder="Cantidad" required>
                                            <button class="admin-stock-step" type="button" data-stock-step="-1" aria-label="Disminuir stock en uno">−</button>
                                            <button class="admin-stock-save" type="submit">Guardar</button>
                                        </form>
                                    <?php } ?>
                                    <form action="admin.php?section=manage" method="post" onsubmit="return confirm('¿Eliminar este libro del catálogo? Esta acción no se puede deshacer.');">
                                        <input type="hidden" name="action" value="delete-book">
                                        <input type="hidden" name="id" value="<?= (int) $book['id'] ?>">
                                        <input type="hidden" name="language" value="<?= htmlspecialchars((string) $book['idioma'], ENT_QUOTES, 'UTF-8') ?>">
                                        <button class="admin-delete" type="submit" aria-label="Eliminar <?= htmlspecialchars((string) $book['titulo'], ENT_QUOTES, 'UTF-8') ?>">Eliminar</button>
                                    </form>
                                </div>
                            </li>
                        <?php } ?>
                    </ul>
                <?php } ?>
            </section>
        <?php } ?>
        <?php if ($section === 'add') { ?>
            <section class="admin-product-editor" aria-labelledby="product-editor-title">
                <header class="admin-product-editor__heading">
                    <div class="admin-action-icon" aria-hidden="true">＋</div>
                    <div>
                        <p class="catalog__eyebrow">Nuevo producto</p>
                        <h2 id="product-editor-title">Publica un libro en la tienda</h2>
                        <p>Completa estos cinco datos. La carátula debe ser JPG, PNG o WebP y pesar hasta 2 MB.</p>
                    </div>
                </header>
                <form action="admin.php" method="post" class="admin-product-form" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add-product">
                    <div class="admin-product-form__layout">
                        <div class="admin-product-form__fields">
                            <label for="book-title">Título del libro
                                <input id="book-title" name="title" type="text" maxlength="255" placeholder="Ej.: El nombre del viento" required>
                            </label>
                            <label for="book-author">Autor
                                <input id="book-author" name="author" type="text" maxlength="255" placeholder="Nombre del autor" required>
                            </label>
                            <label class="admin-cover-field" for="book-cover">Foto de carátula
                                <input id="book-cover" name="cover" type="file" accept="image/jpeg,image/png,image/webp" required>
                                <span>Elige una imagen desde tu dispositivo.</span>
                            </label>
                            <label for="book-price">Precio
                                <div class="admin-input-affix">
                                    <span aria-hidden="true">$</span>
                                    <input id="book-price" name="price" type="number" min="0.01" max="99999999.99" step="0.01" placeholder="0,00" required>
                                </div>
                            </label>
                            <div class="admin-product-stock">
                                <label for="book-stock">Stock inicial</label>
                                <div class="admin-product-stock__control">
                                    <button class="admin-stock-step" type="button" data-stock-step="1" data-stock-target="book-stock" aria-label="Aumentar stock inicial en uno">+</button>
                                    <input id="book-stock" name="stock" type="number" min="0" max="4294967295" step="1" value="0" required>
                                    <button class="admin-stock-step" type="button" data-stock-step="-1" data-stock-target="book-stock" aria-label="Disminuir stock inicial en uno">−</button>
                                </div>
                                <small>Unidades disponibles para la venta.</small>
                            </div>
                        </div>
                        <aside class="admin-product-preview" aria-live="polite">
                            <p class="admin-product-preview__eyebrow">Vista previa</p>
                            <div class="admin-product-preview__cover">
                                <img id="product-preview-image" alt="Vista previa de la carátula" hidden>
                                <span id="product-preview-placeholder">La carátula aparecerá aquí</span>
                            </div>
                            <div class="admin-product-preview__details">
                                <h3 id="product-preview-title">Título del libro</h3>
                                <p id="product-preview-author">Autor</p>
                                <strong id="product-preview-price">$0,00</strong>
                                <span id="product-preview-stock">Stock inicial: 0</span>
                            </div>
                        </aside>
                    </div>
                    <div class="admin-product-form__footer">
                        <p>Podrás modificar el stock desde «Administrar libros» después de publicar.</p>
                        <button class="intro__cta admin-submit" type="submit">Publicar libro</button>
                    </div>
                </form>
            </section>
        <?php } ?>
    </main>
    <script>
        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-stock-step]');
            if (!button) return;
            var input = button.dataset.stockTarget
                ? document.getElementById(button.dataset.stockTarget)
                : button.closest('.admin-stock-form').querySelector('input[name="stock"]');
            var current = Number.parseInt(input.value, 10);
            var stock = Number.isInteger(current) ? current : 0;
            var minimum = Number.parseInt(input.min, 10) || 0;
            var maximum = Number.parseInt(input.max, 10) || 4294967295;
            input.value = String(Math.max(minimum, Math.min(maximum, stock + Number(button.dataset.stockStep))));
            input.dispatchEvent(new Event('input', { bubbles: true }));
        });

        var productTitle = document.getElementById('book-title');
        var productAuthor = document.getElementById('book-author');
        var productCover = document.getElementById('book-cover');
        var productPrice = document.getElementById('book-price');
        var productStock = document.getElementById('book-stock');
        var previewImage = document.getElementById('product-preview-image');
        var previewPlaceholder = document.getElementById('product-preview-placeholder');
        var previewTitle = document.getElementById('product-preview-title');
        var previewAuthor = document.getElementById('product-preview-author');
        var previewPrice = document.getElementById('product-preview-price');
        var previewStock = document.getElementById('product-preview-stock');
        var previewCoverUrl = null;

        if (productTitle && productAuthor && productCover && productPrice && productStock) {
            function updateProductPreview() {
                previewTitle.textContent = productTitle.value.trim() || 'Título del libro';
                previewAuthor.textContent = productAuthor.value.trim() || 'Autor';
                var price = Number(productPrice.value);
                previewPrice.textContent = '$' + (Number.isFinite(price) ? price : 0).toLocaleString('es-UY', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
                previewStock.textContent = 'Stock inicial: ' + (productStock.value || '0');
            }

            [productTitle, productAuthor, productPrice, productStock].forEach(function (input) {
                input.addEventListener('input', updateProductPreview);
            });
            productCover.addEventListener('change', function () {
                if (previewCoverUrl) URL.revokeObjectURL(previewCoverUrl);
                var file = productCover.files[0];
                if (!file) {
                    previewImage.hidden = true;
                    previewPlaceholder.hidden = false;
                    return;
                }
                previewCoverUrl = URL.createObjectURL(file);
                previewImage.src = previewCoverUrl;
                previewImage.hidden = false;
                previewPlaceholder.hidden = true;
            });
        }
    </script>
</body>
</html>
