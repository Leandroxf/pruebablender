<?php

class BookController extends Controller
{
    public function __construct(
        private Book $books = new Book(),
        private PaidBook $paidBooks = new PaidBook(),
        private Purchase $purchases = new Purchase()
    ) {
    }

    public function show(): void
    {
        header('Access-Control-Allow-Origin: *');

        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        $language = $_GET['lang'] ?? Lang::EN;
        if (!$id || $id < 1) {
            $this->json(['error' => 'ID inválido'], 400);
            return;
        }
        if (!Lang::isValid($language)) {
            $this->json(['error' => 'Idioma inválido'], 400);
            return;
        }

        // Un libro de pago solo se entrega completo a quien lo compro.
        $paid = $this->paidBooks->findMetadata($id, $language);
        if ($paid !== null) {
            $this->showPaid($id, $language, $paid);
            return;
        }

        $book = $this->books->findById($id, $language);
        if (!$book) {
            $this->json(['error' => 'Libro no encontrado'], 404);
            return;
        }
        $this->json($book);
    }

    /**
     * Libro de pago: el texto se entrega solo a quien tiene la compra registrada.
     */
    private function showPaid(int $id, string $language, array $metadata): void
    {
        $userId = Auth::userId();
        if ($userId === null) {
            $this->json([
                'error' => 'Necesitás iniciar sesión para leer este libro.',
                'requiere_compra' => true,
                'libro' => $metadata,
            ], 401);
            return;
        }

        if (!$this->purchases->owns($userId, $id, $language)) {
            $this->json([
                'error' => 'Este libro es de pago. Compralo para poder leerlo.',
                'requiere_compra' => true,
                'libro' => $metadata,
            ], 403);
            return;
        }

        $book = $this->paidBooks->findFull($id, $language);
        if ($book === null) {
            $this->json(['error' => 'Libro no encontrado'], 404);
            return;
        }

        $this->json($book);
    }
}