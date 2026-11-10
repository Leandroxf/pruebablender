<?php

// Pantalla de ingreso del panel de administración.

$error = $error ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingreso de administrador</title>
    <link rel="stylesheet" href="style.css?v=shop-sections-1">
</head>
<body class="admin-page">
    <main class="admin-card">
        <a class="intro__cta admin-back-button" href="./">Volver</a>
        <p class="catalog__eyebrow">Acceso restringido</p>
        <h1>Administración</h1>
        <p>Paso 1 de 2 · Ingresa para gestionar el catálogo de libros.</p>
        <?php if ($error !== null) { ?>
            <p class="admin-message admin-message--error" role="alert"><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></p>
        <?php } ?>
        <form class="admin-login-form" action="admin.php" method="post">
            <input type="hidden" name="action" value="login">
            <label for="admin-username">Usuario</label>
            <input id="admin-username" name="username" type="text" autocomplete="username" autofocus required>
            <label for="admin-password">Contrase?a</label>
            <input id="admin-password" name="password" type="password" autocomplete="current-password" required>
            <label class="admin-remember" for="admin-remember">
                <input id="admin-remember" type="checkbox" name="remember" value="1">
                <span>Recordarme en este dispositivo durante 30 d?as</span>
            </label>
            <button class="intro__cta admin-submit" type="submit">Entrar</button>
        </form>
    </main>
</body>
</html>
