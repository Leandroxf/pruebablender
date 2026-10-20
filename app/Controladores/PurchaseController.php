<?php

/**
 * API de compras de libros de pago.
 *
 * GET  /api/compra.php            -> lista los libros que el usuario ya compro
 * POST /api/compra.php            -> registra la compra de un libro de pago
 *
 * Requiere sesion iniciada y token CSRF valido.
 */
class PurchaseController extends Controller
{
    public function __construct(
        private Purchase $purchases = new Purchase(),
        private PaidBook $paidBooks = new PaidBook()
    ) {
    }

    public function handle(): void
    {
        try {
            if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
                header('Allow: GET, POST');
                $this->json(['error' => 'Método no permitido.'], 405);
                return;
            }

            $userId = Auth::userId();
            if ($userId === null) {
                $this->json(['error' => 'Debes iniciar sesión para comprar libros.'], 401);
                return;
            }

            match ($_SERVER['REQUEST_METHOD']) {
                'GET' => $this->listPurchases($userId),
                default => $this->buy($userId),
            };
        } catch (InvalidArgumentException $error) {
            $this->json(['error' => $error->getMessage()], 400);
        } catch (Throwable $error) {
            error_log($error->getMessage());
            $this->json(['error' => 'No se pudo procesar la compra.'], 500);
        }
    }

    private function listPurchases(int $userId): void
    {
        $this->json(['compras' => array_values($this->purchases->ownedByUser($userId))]);
    }

    private function buy(int $userId): void
    {
        if (!Auth::checkCsrf('purchases_csrf', (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) {
            $this->json(['error' => 'La sesión expiró. Recarga la página e inténtalo de nuevo.'], 403);
            return;
        }

        try {
            $data = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new InvalidArgumentException('La solicitud de compra no contiene JSON válido.', 0, $error);
        }
        if (!is_array($data)) {
            throw new InvalidArgumentException('La solicitud de compra tiene un formato inválido.');
        }

        $bookId = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        $language = (string)($data['idioma'] ?? '');
        if (!$bookId || $bookId < 1 || !Lang::isValid($language)) {
            throw new InvalidArgumentException('El libro seleccionado no es válido.');
        }

        $book = $this->paidBooks->findMetadata($bookId, $language);
        if ($book === null) {
            $this->json(['error' => 'Ese libro no está disponible para compra.'], 404);
            return;
        }

        if ($this->purchases->owns($userId, $bookId, $language)) {
            $this->json([
                'error' => 'Ya tenés este libro en tu biblioteca.',
                'compras' => array_values($this->purchases->ownedByUser($userId)),
            ], 409);
            return;
        }

        $receipt = $this->purchases->purchase($userId, $bookId, $language, (float)$book['precio']);

        $this->json([
            'mensaje' => '¡Compra realizada! Ya podés leer «' . $book['titulo'] . '».',
            'compra' => $receipt,
            'compras' => array_values($this->purchases->ownedByUser($userId)),
        ], 201);
    }
}