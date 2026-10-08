<?php

class CatalogController
{
    public function __construct(private Book $books = new Book())
    {
    }

    public function index(): void
    {
        try {
            http_response_code(200);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($this->books->getCatalogSummary(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $error) {
            error_log($error->getMessage());
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'No se pudo cargar el catálogo.'], JSON_UNESCAPED_UNICODE);
        }
    }
}
