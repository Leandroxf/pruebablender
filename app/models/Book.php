<?php

class Book
{
    private static array $booksByLanguage = [];

    public function findById(int $id, string $language = 'en'): ?array
    {
        if (!in_array($language, ['en', 'es'], true)) {
            return null;
        }

        $books = $this->loadLanguage($language);
        $book = $books[$id] ?? $books[(string)$id] ?? null;
        if (!is_array($book)) {
            return null;
        }

        foreach ($book['secciones'] as &$section) {
            if (!is_array($section)) {
                continue;
            }
            if (isset($section['title']) && is_string($section['title'])) {
                $section['title'] = $this->repairLegacyEncoding($section['title']);
            }
            if (!isset($section['blocks']) || !is_array($section['blocks'])) {
                continue;
            }
            foreach ($section['blocks'] as &$block) {
                if (isset($block['text']) && is_string($block['text'])) {
                    $block['text'] = $this->repairLegacyEncoding($block['text']);
                }
            }
            unset($block);
        }
        unset($section);
        if ($language === 'en' && isset($book['secciones'][0]) && is_array($book['secciones'][0])) {
            $book['secciones'][0]['title'] = 'Introduction';
        }

        return $book;
    }

    private function loadLanguage(string $language): array
    {
        if (isset(self::$booksByLanguage[$language])) {
            return self::$booksByLanguage[$language];
        }

        $filename = $language === 'es' ? 'libros_es.json' : 'libros.json';
        $path = PROJECT_ROOT . '/api/' . $filename;
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('No se pudo leer el archivo de libros: ' . $filename);
        }

        try {
            $books = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException('El archivo de libros no contiene JSON válido: ' . $filename, 0, $error);
        }

        if (!is_array($books)) {
            throw new RuntimeException('El archivo de libros tiene un formato inválido: ' . $filename);
        }

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
        foreach (['en', 'es'] as $language) {
            foreach ($this->loadLanguage($language) as $book) {
                $books[] = [
                    'id' => (string)$book['id'],
                    'titulo' => $book['titulo'],
                    'autor' => $book['autor'],
                    'genero' => $book['genero'],
                    'cover' => $book['cover'],
                    'idioma' => $book['idioma'],
                    'bytes' => strlen(json_encode($book['secciones'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
                    'num_secciones' => count($book['secciones']),
                ];
            }
        }

        usort($books, static fn(array $left, array $right): int => (int)$left['id'] <=> (int)$right['id']);
        return $books;
    }
}
