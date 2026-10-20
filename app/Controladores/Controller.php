<?php

abstract class Controller
{
    protected function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    protected function redirect(string $url = './'): never
    {
        header('Location: ' . $url, true, 303);
        exit;
    }
}
