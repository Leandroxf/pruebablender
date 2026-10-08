<?php

class AdminController
{
    private const USERNAME = 'admin';
    private const PASSWORD = 'admin';
    private const MAX_BOOK_BYTES = 5000000;
    private const ALLOWED_GENRES = [
        'aventura',
        'ciencia-ficcion',
        'cuentos',
        'fantasia',
        'filosofia',
        'historia',
        'misterio',
        'novela',
        'poesia',
        'romance',
        'terror',
    ];

    public function handle(): void
    {
        $action = $_POST['action'] ?? '';
        $section = (string)($_GET['section'] ?? 'home');
        if (!in_array($section, ['home', 'add', 'manage'], true)) {
            $section = 'home';
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'login') {
            $this->login();
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'logout') {
            $this->requireAuthentication();
            if (!$this->validateCsrfToken()) {
                $this->renderPanel('La sesión expiró. Recarga la página e inténtalo de nuevo.', false, $section);
                return;
            }
            $_SESSION = [];
            session_regenerate_id(true);
            $this->redirect();
        }

        if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
            $this->renderLogin();
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add-book') {
            if (!$this->validateCsrfToken()) {
                $this->renderPanel('La sesión expiró. Recarga la página e inténtalo de nuevo.', false, 'add');
                return;
            }
            try {
                $id = $this->addBook();
                $this->redirect('section=add&added=1&id=' . $id);
            } catch (InvalidArgumentException $error) {
                $this->renderPanel($error->getMessage(), false, 'add');
                return;
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete-book') {
            if (!$this->validateCsrfToken()) {
                $this->renderPanel('La sesión expiró. Recarga la página e inténtalo de nuevo.', false, 'manage');
                return;
            }
            try {
                $book = $this->deleteBook();
                $this->redirect('section=manage&deleted=1&id=' . $book['id'] . '&lang=' . rawurlencode($book['language']));
            } catch (InvalidArgumentException $error) {
                http_response_code(400);
                $this->renderPanel($error->getMessage(), false, 'manage');
                return;
            }
        }

        $message = null;
        if (isset($_GET['added'])) {
            $message = 'El libro #' . (int)($_GET['id'] ?? 0) . ' se agregó al catálogo.';
            $section = 'add';
        } elseif (isset($_GET['deleted'])) {
            $message = 'El libro #' . (int)($_GET['id'] ?? 0) . ' se eliminó del catálogo.';
            $section = 'manage';
        }
        $this->renderPanel($message, isset($_GET['added']), $section);
    }

    private function login(): void
    {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if (!hash_equals(self::USERNAME, $username) || !hash_equals(self::PASSWORD, $password)) {
            http_response_code(401);
            $this->renderLogin('Usuario o contraseña incorrectos.');
            return;
        }

        session_regenerate_id(true);
        $_SESSION['is_admin'] = true;
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
        $this->redirect();
    }

    private function addBook(): int
    {
        $idText = trim((string)($_POST['id'] ?? ''));
        $title = trim((string)($_POST['title'] ?? ''));
        $author = trim((string)($_POST['author'] ?? ''));
        $genre = (string)($_POST['genre'] ?? '');
        $language = (string)($_POST['language'] ?? '');
        $cover = trim((string)($_POST['cover'] ?? ''));
        $source = trim((string)($_POST['source'] ?? ''));
        $text = str_replace(["\r\n", "\r"], "\n", trim((string)($_POST['text'] ?? '')));

        if ($idText !== '' && (!ctype_digit($idText) || (int)$idText < 1)) {
            throw new InvalidArgumentException('El ID debe ser un número entero positivo o quedar vacío para asignarlo automáticamente.');
        }
        if ($title === '' || $author === '') {
            throw new InvalidArgumentException('El título y el autor son obligatorios.');
        }
        if (!in_array($genre, self::ALLOWED_GENRES, true)) {
            throw new InvalidArgumentException('Selecciona un género válido.');
        }
        if (!in_array($language, ['en', 'es'], true)) {
            throw new InvalidArgumentException('Selecciona un idioma válido.');
        }
        if ($text === '' || strlen($text) > self::MAX_BOOK_BYTES) {
            throw new InvalidArgumentException('El texto completo es obligatorio y no puede superar 5 MB.');
        }
        foreach (['Título' => $title, 'Autor' => $author] as $label => $value) {
            if (strlen($value) > 255) {
                throw new InvalidArgumentException($label . ' no puede superar los 255 caracteres.');
            }
        }
        foreach (['Portada' => $cover, 'Fuente' => $source] as $label => $value) {
            if ($value !== '' && filter_var($value, FILTER_VALIDATE_URL) === false) {
                throw new InvalidArgumentException($label . ' debe ser una URL válida.');
            }
        }

        $paragraphs = preg_split('/\n[ \t]*\n+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $blocks = [];
        foreach ($paragraphs ?: [] as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph !== '') {
                $blocks[] = ['heading' => false, 'text' => $paragraph];
            }
        }
        if ($blocks === []) {
            throw new InvalidArgumentException('Escribe el texto del libro, separado en párrafos por líneas en blanco.');
        }

        $filename = $language === 'es' ? 'libros_es.json' : 'libros.json';
        $path = PROJECT_ROOT . '/api/' . $filename;
        $lockPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'proyectodetodo-' . $filename . '.lock';
        $lock = fopen($lockPath, 'c');
        if ($lock === false) {
            throw new RuntimeException('No se pudo abrir el archivo del catálogo para agregar el libro.');
        }

        try {
            if (!flock($lock, LOCK_EX)) {
                throw new RuntimeException('No se pudo bloquear el archivo del catálogo.');
            }
            $content = file_get_contents($path);
            if ($content === false) {
                throw new RuntimeException('No se pudo leer el archivo del catálogo.');
            }
            try {
                $catalog = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $error) {
                throw new RuntimeException('El archivo del catálogo contiene JSON inválido.', 0, $error);
            }
            if (!is_array($catalog)) {
                throw new RuntimeException('El archivo del catálogo tiene un formato inválido.');
            }
            $id = $idText === ''
                ? (max(array_map('intval', array_keys($catalog)) ?: [0]) + 1)
                : (int)$idText;
            $idText = (string)$id;
            if (isset($catalog[$idText])) {
                throw new InvalidArgumentException('Ya existe un libro con ese ID en el catálogo seleccionado.');
            }

            $book = [
                'id' => $id,
                'titulo' => $title,
                'autor' => $author,
                'genero' => $genre,
                'cover' => $cover ?: 'https://placehold.co/420x600?text=' . rawurlencode($title),
                'fuente' => $source,
                'idioma' => $language,
                'introducciones' => new stdClass(),
                'secciones' => [[
                    'title' => 'Texto completo',
                    'blocks' => $blocks,
                ]],
            ];
            $catalog[$idText] = $book;
            $json = json_encode(
                $catalog,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
            $this->writeCatalogFile($path, $json);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        return $id;
    }

    private function deleteBook(): array
    {
        $idText = trim((string)($_POST['id'] ?? ''));
        $language = (string)($_POST['language'] ?? '');
        if ($idText === '' || !ctype_digit($idText) || (int)$idText < 1) {
            throw new InvalidArgumentException('Selecciona un libro válido para eliminar.');
        }
        if (!in_array($language, ['en', 'es'], true)) {
            throw new InvalidArgumentException('El idioma del libro no es válido.');
        }

        $id = (int)$idText;
        $filename = $language === 'es' ? 'libros_es.json' : 'libros.json';
        $path = PROJECT_ROOT . '/api/' . $filename;
        $lockPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'proyectodetodo-' . $filename . '.lock';
        $lock = fopen($lockPath, 'c');
        if ($lock === false) {
            throw new RuntimeException('No se pudo abrir el archivo del catálogo para eliminar el libro.');
        }

        try {
            if (!flock($lock, LOCK_EX)) {
                throw new RuntimeException('No se pudo bloquear el archivo del catálogo.');
            }
            $content = file_get_contents($path);
            if ($content === false) {
                throw new RuntimeException('No se pudo leer el archivo del catálogo.');
            }
            try {
                $catalog = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $error) {
                throw new RuntimeException('El archivo del catálogo contiene JSON inválido.', 0, $error);
            }
            if (!is_array($catalog)) {
                throw new RuntimeException('El archivo del catálogo tiene un formato inválido.');
            }

            $idKey = (string)$id;
            if (!isset($catalog[$idKey]) || !is_array($catalog[$idKey])) {
                throw new InvalidArgumentException('El libro seleccionado ya no existe en ese catálogo.');
            }
            if ((int)($catalog[$idKey]['id'] ?? 0) !== $id || ($catalog[$idKey]['idioma'] ?? null) !== $language) {
                throw new RuntimeException('El libro del catálogo tiene un formato inválido.');
            }

            unset($catalog[$idKey]);
            $json = json_encode(
                $catalog,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
            $this->writeCatalogFile($path, $json);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        return ['id' => $id, 'language' => $language];
    }

    private function writeCatalogFile(string $path, string $json): void
    {
        $temporary = tempnam(dirname($path), '.catalog-');
        if ($temporary === false) {
            throw new RuntimeException('No se pudo preparar el archivo actualizado del catálogo.');
        }
        try {
            if (file_put_contents($temporary, $json, LOCK_EX) !== strlen($json)) {
                throw new RuntimeException('No se pudo guardar el archivo actualizado del catálogo.');
            }
            if (!rename($temporary, $path)) {
                throw new RuntimeException('No se pudo reemplazar el archivo del catálogo.');
            }
        } finally {
            if (file_exists($temporary)) {
                unlink($temporary);
            }
        }
    }

    private function validateCsrfToken(): bool
    {
        $token = (string)($_POST['csrf_token'] ?? '');
        $sessionToken = (string)($_SESSION['admin_csrf'] ?? '');
        if ($sessionToken === '' || !hash_equals($sessionToken, $token)) {
            http_response_code(403);
            return false;
        }

        return true;
    }

    private function requireAuthentication(): void
    {
        if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
            http_response_code(403);
            throw new RuntimeException('Debes iniciar sesión como administrador.');
        }
    }

    private function renderLogin(?string $error = null): void
    {
        require PROJECT_ROOT . '/app/views/admin-login.php';
    }

    private function renderPanel(?string $message = null, bool $success = false, string $section = 'home'): void
    {
        $books = $section === 'manage' ? (new Book())->getCatalogSummary() : [];
        require PROJECT_ROOT . '/app/views/admin-panel.php';
    }

    private function redirect(string $query = ''): never
    {
        header('Location: admin.php' . ($query === '' ? '' : '?' . $query), true, 303);
        exit;
    }
}
