<?php

class HomeController
{
    public function index(): void
    {
        require PROJECT_ROOT . '/app/views/home.php';
    }
}
