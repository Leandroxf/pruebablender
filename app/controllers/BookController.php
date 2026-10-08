<?php

class BookController
{
    private Book $books;

    public function __construct()
    {
        $this->books = new Book();
    }

    public function show(): void
    {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id || $id < 1) {
            $this->respond(['error' => 'ID inválido'], 400);
            return;
        }

        $language = $_GET['lang'] ?? 'en';
        if (!in_array($language, ['en', 'es'], true)) {
            $this->respond(['error' => 'Idioma inválido'], 400);
            return;
        }

        $book = $this->books->findById($id, $language);
        if (!$book) {
            $this->respond(['error' => 'Libro no encontrado'], 404);
            return;
        }

        $this->respond($book);
    }

    private function respond(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
