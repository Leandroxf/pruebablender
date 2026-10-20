<?php

class CatalogController extends Controller
{
    public function __construct(private Book $books = new Book())
    {
    }

    public function index(): void
    {
        try {
            $this->json($this->books->getCatalogSummary());
        } catch (Throwable $error) {
            error_log($error->getMessage());
            $this->json(['error' => 'No se pudo cargar el catálogo.'], 500);
        }
    }
}
