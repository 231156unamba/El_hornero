<?php

use PHPUnit\Framework\TestCase;
use App\Models\Usuario;
use App\Http\Controllers\MenuController;

class ExampleTest extends TestCase
{
    public function test_usuario_model_exists(): void
    {
        $this->assertTrue(class_exists(Usuario::class), 'La clase App\\Models\\Usuario debe existir');
    }

    public function test_menu_controller_method_index_exists(): void
    {
        $this->assertTrue(class_exists(MenuController::class), 'La clase App\\Http\\Controllers\\MenuController debe existir');
        $this->assertTrue(method_exists(MenuController::class, 'index'), 'MenuController debe tener el método index');
    }
}
