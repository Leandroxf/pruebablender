<?php

class HomeController
{
    public function index(): void
    {
        require PROJECT_ROOT . '/app/Vistas/home.php';
    }
}
