<?php

class Book
{
    private static array $booksByLanguage = [];

    private PaidBook $paidBooks;

    public function __construct(?PaidBook $paidBooks = null)
    {
        $this->paidBooks = $paidBooks ?? new PaidBook();
    }

    public function findById(int $id, string $language = Lang::EN): ?array
    {
        if (!Lang::isValid($language)) {
            return null;
        }

        // Los libros de pago no estan en el JSON publico: su texto vive en MySQL.
        $paid = $this->paidBooks->findFull($id, $language);
        if ($paid !== null) {
            return $paid;
        }

        $books = $this->loadLanguage($language);
        $book = $books[$id] ?? $books[(string)$id] ?? null;
        if (!is_array($book)) {
            return null;
        }

        foreach ($book['secciones'] ?? [] as &$section) {
            if (!is_array($section)) {
                continue;
            }
            if (is_string($section['title'] ?? null)) {
                $section['title'] = $this->repairLegacyEncoding($section['title']);
            }
            foreach ($section['blocks'] ?? [] as &$block) {
                if (is_array($block) && is_string($block['text'] ?? null)) {
                    $block['text'] = $this->repairLegacyEncoding($block['text']);
                }
            }
            unset($block);
        }
        unset($section);
        if ($language === 'en' && is_array($book['secciones'][0] ?? null)) {
            $book['secciones'][0]['title'] = 'Introduction';
        }

        return $book;
    }

    private function loadLanguage(string $language): array
    {
        if (isset(self::$booksByLanguage[$language])) {
            return self::$booksByLanguage[$language];
        }

        $filename = Lang::file($language);
        $books = CatalogStore::read($language, "de libros: $filename");

        foreach ($books as $id => $book) {
            if (
                !is_array($book) ||
                (int)($book['id'] ?? 0) !== (int)$id ||
                ($book['idioma'] ?? null) !== $language ||
                !isset($book['titulo'], $book['autor'], $book['secciones']) ||
                !is_array($book['secciones']) ||
                !isset($book['introducciones'])
            ) {
                throw new RuntimeException('El libro ' . $id . ' tiene un formato inválido en ' . $filename);
            }
            if ($book['introducciones'] === []) {
                $book['introducciones'] = new stdClass();
            }
            $books[$id] = $book;
        }

        self::$booksByLanguage[$language] = $books;
        return $books;
    }

    private function repairLegacyEncoding(string $text): string
    {
        if (!preg_match('/\x{00D4}\x{00C7}|\x{251C}/u', $text)) {
            return $text;
        }

        static $reverseCodePage = null;
        if ($reverseCodePage === null) {
            $reverseCodePage = [];
            for ($byte = 0; $byte <= 255; $byte++) {
                $character = iconv('CP850', 'UTF-8', chr($byte));
                if ($character !== false) {
                    $reverseCodePage[$character] = chr($byte);
                }
            }
        }

        $repaired = preg_replace_callback(
            '/[^\x00-\x7F]+/u',
            static function (array $match) use ($reverseCodePage): string {
                $characters = preg_split('//u', $match[0], -1, PREG_SPLIT_NO_EMPTY);
                $bytes = '';
                foreach ($characters as $character) {
                    if (!isset($reverseCodePage[$character])) {
                        return $match[0];
                    }
                    $bytes .= $reverseCodePage[$character];
                }

                $decoded = iconv('UTF-8', 'UTF-8', $bytes);
                return $decoded !== false ? $decoded : $match[0];
            },
            $text
        );

        return $repaired ?? $text;
    }

    public function getCatalogSummary(): array
    {
        $books = [];
        foreach (Lang::all() as $language) {
            // Los libros de pago se listan desde MySQL (su texto no esta en el JSON).
            foreach ($this->paidBooks->listPaidBooks($language) as $book) {
                $books[] = $book;
            }
            foreach ($this->loadLanguage($language) as $book) {
                $books[] = [
                    'id' => (string)$book['id'],
                    'titulo' => $book['titulo'],
                    'autor' => $book['autor'],
                    'genero' => $book['genero'],
                    'cover' => $book['cover'],
                    'idioma' => $book['idioma'],
                    'isbn' => (string)($book['isbn'] ?? ''),
                    'precio' => isset($book['precio']) ? (float)$book['precio'] : null,
                    'bytes' => strlen(json_encode($book['secciones'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
                    'num_secciones' => count($book['secciones']),
                ];
            }
        }

        usort($books, static fn(array $left, array $right): int => (int)$left['id'] <=> (int)$right['id']);
        return $books;
    }
}
