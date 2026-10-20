<?php

class AdminController extends Controller
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
            $this->logout($section);
        }

        if (!Auth::isAdmin()) {
            $this->renderLogin();
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add-book', 'delete-book'], true)) {
            $this->saveBook($action);
        }

        match (true) {
            isset($_GET['added']) => $this->renderPanel('El libro #' . (int)($_GET['id'] ?? 0) . ' se agregó al catálogo.', true, 'add'),
            isset($_GET['deleted']) => $this->renderPanel('El libro #' . (int)($_GET['id'] ?? 0) . ' se eliminó del catálogo.', false, 'manage'),
            default => $this->renderPanel(null, false, $section),
        };
    }

    private function logout(string $section): void
    {
        $isAdmin = Auth::isAdmin();
        if (!$isAdmin && !Auth::isUser()) {
            http_response_code(403);
            $this->renderLogin('La sesión expiró. Inicia sesión nuevamente.');
            exit;
        }
        if (!Auth::checkCsrf(Auth::csrfKey(), (string)($_POST['csrf_token'] ?? ''))) {
            http_response_code(403);
            if ($isAdmin) {
                $this->renderPanel('La sesión expiró. Recarga la página e inténtalo de nuevo.', false, $section);
            } else {
                $this->renderLogin('La sesión expiró. Inicia sesión nuevamente.');
            }
            exit;
        }
        Auth::logout();
        $this->redirect();
    }

    private function saveBook(string $action): void
    {
        $section = $action === 'add-book' ? 'add' : 'manage';
        if (!Auth::checkCsrf('admin_csrf', (string)($_POST['csrf_token'] ?? ''))) {
            http_response_code(403);
            $this->renderPanel('La sesión expiró. Recarga la página e inténtalo de nuevo.', false, $section);
            return;
        }
        try {
            if ($action === 'add-book') {
                $this->redirectToPanel('section=add&added=1&id=' . $this->addBook());
            }
            $book = $this->deleteBook();
            $this->redirectToPanel('section=manage&deleted=1&id=' . $book['id'] . '&lang=' . rawurlencode($book['language']));
        } catch (InvalidArgumentException $error) {
            if ($action === 'delete-book') {
                http_response_code(400);
            }
            $this->renderPanel($error->getMessage(), false, $section);
        }
    }

    private function post(string $key): string
    {
        return trim((string)($_POST[$key] ?? ''));
    }

    private function login(): void
    {
        $username = $this->post('username');
        $password = (string)($_POST['password'] ?? '');

        $isAdminCredentials = hash_equals(self::USERNAME, $username) && hash_equals(self::PASSWORD, $password);
        if ($isAdminCredentials) {
            Auth::loginAs('admin');
            $this->redirect();
        }

        if ($this->loginRegisteredUser($username, $password)) {
            $this->redirect();
        }

        http_response_code(401);
        $this->renderLogin('Usuario, mail o contraseña incorrectos.');
    }

    private function loginRegisteredUser(string $username, string $password): bool
    {
        if ($username === '' || $password === '') {
            return false;
        }

        try {
            $user = (new Usuario())->authenticate($username, $password);
        } catch (Throwable $error) {
            error_log($error->getMessage());
            return false;
        }
        if ($user === null) {
            return false;
        }

        Auth::loginUser((int)$user['id_usuario'], (string)$user['nombre'], (string)$user['rol']);

        return true;
    }

    private function addBook(): int
    {
        $idText = $this->post('id');
        $title = $this->post('title');
        $author = $this->post('author');
        $genre = (string)($_POST['genre'] ?? '');
        $language = (string)($_POST['language'] ?? '');
        $cover = $this->post('cover');
        $source = $this->post('source');
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
        if (!Lang::isValid($language)) {
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

        $blocks = [];
        foreach (preg_split('/\n[ \t]*\n+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph !== '') {
                $blocks[] = ['heading' => false, 'text' => $paragraph];
            }
        }
        if ($blocks === []) {
            throw new InvalidArgumentException('Escribe el texto del libro, separado en párrafos por líneas en blanco.');
        }

        $id = 0;
        CatalogStore::transaction($language, 'del catálogo', static function (array &$catalog) use ($idText, $title, $author, $genre, $cover, $source, $language, $blocks, &$id): void {
            $id = $idText === '' ? ((max(array_map('intval', array_keys($catalog)) ?: [0])) + 1) : (int)$idText;
            if (isset($catalog[(string)$id])) {
                throw new InvalidArgumentException('Ya existe un libro con ese ID en el catálogo seleccionado.');
            }
            $catalog[(string)$id] = [
                'id' => $id,
                'titulo' => $title,
                'autor' => $author,
                'genero' => $genre,
                'cover' => $cover ?: 'https://placehold.co/420x600?text=' . rawurlencode($title),
                'fuente' => $source,
                'idioma' => $language,
                'introducciones' => new stdClass(),
                'secciones' => [['title' => 'Texto completo', 'blocks' => $blocks]],
            ];
        });

        return $id;
    }

    private function deleteBook(): array
    {
        $idText = $this->post('id');
        $language = (string)($_POST['language'] ?? '');
        if ($idText === '' || !ctype_digit($idText) || (int)$idText < 1) {
            throw new InvalidArgumentException('Selecciona un libro válido para eliminar.');
        }
        if (!Lang::isValid($language)) {
            throw new InvalidArgumentException('Selecciona un idioma válido.');
        }

        $id = (int)$idText;
        CatalogStore::transaction($language, 'del catálogo', static function (array &$catalog) use ($id, $language): void {
            $key = (string)$id;
            if (!isset($catalog[$key]) || !is_array($catalog[$key])) {
                throw new InvalidArgumentException('El libro seleccionado ya no existe en ese catálogo.');
            }
            if ((int)($catalog[$key]['id'] ?? 0) !== $id || ($catalog[$key]['idioma'] ?? null) !== $language) {
                throw new RuntimeException('El libro del catálogo tiene un formato inválido.');
            }
            unset($catalog[$key]);
        });

        return ['id' => $id, 'language' => $language];
    }

    private function renderLogin(?string $error = null): void
    {
        require PROJECT_ROOT . '/app/Vistas/admin-login.php';
    }

    private function renderPanel(?string $message = null, bool $success = false, string $section = 'home'): void
    {
        $books = $section === 'manage' ? (new Book())->getCatalogSummary() : [];
        require PROJECT_ROOT . '/app/Vistas/admin-panel.php';
    }

    private function redirectToPanel(string $query): never
    {
        $this->redirect('admin.php?' . $query);
    }
}
