<?php

class RatingController extends Controller
{
    public function __construct(
        private BookRating $ratings = new BookRating(),
        private Book $books = new Book()
    ) {
    }

    public function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
                header('Allow: GET, POST');
                $this->json(['error' => 'Método no permitido.'], 405);
                return;
            }
            match ($_SERVER['REQUEST_METHOD']) {
                'GET' => $this->json(['valoraciones' => $this->ratings->getAll($this->getVoterHash())]),
                default => $this->saveRating(),
            };
        } catch (InvalidArgumentException $error) {
            $this->json(['error' => $error->getMessage()], 400);
        } catch (Throwable $error) {
            error_log($error->getMessage());
            $this->json(['error' => 'No se pudieron procesar las valoraciones.'], 500);
        }
    }

    private function saveRating(): void
    {
        if (!Auth::isLoggedIn()) {
            $this->json(['error' => 'Debes iniciar sesión para valorar los libros.'], 401);
            return;
        }
        if (!Auth::checkCsrf('ratings_csrf', (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) {
            $this->json(['error' => 'La sesión expiró. Recarga la página e inténtalo de nuevo.'], 403);
            return;
        }

        try {
            $data = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new InvalidArgumentException('La solicitud de valoración no contiene JSON válido.', 0, $error);
        }
        if (!is_array($data)) {
            throw new InvalidArgumentException('La solicitud de valoración tiene un formato inválido.');
        }

        $bookId = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        $rating = filter_var($data['valoracion'] ?? null, FILTER_VALIDATE_INT);
        if (!$bookId || $bookId < 1 || !Lang::isValid($data['idioma'] ?? null) || !$rating || $rating < 1 || $rating > 5) {
            throw new InvalidArgumentException('Selecciona un libro y una valoración de 1 a 5 estrellas.');
        }

        if ($this->books->findById($bookId, $data['idioma']) === null) {
            $this->json(['error' => 'El libro seleccionado no existe.'], 404);
            return;
        }

        $this->json(['valoracion' => $this->ratings->save($bookId, $data['idioma'], $this->getVoterHash(), $rating)]);
    }

    private function getVoterHash(): string
    {
        return hash('sha256', session_id());
    }
}
