<?php

namespace Tests\Feature;

use App\Models\Menu;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MenuControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        Schema::dropIfExists('menu');
        Schema::create('menu', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->decimal('precio', 10, 2);
            $table->text('descripcion');
            $table->string('imagen')->nullable();
            $table->string('categoria')->default('comida');
            $table->decimal('discount_percentage', 5, 2)->nullable();
            $table->timestamp('discount_expires_at')->nullable();
        });
    }

    public function test_menu_endpoint_can_filter_by_category(): void
    {
        Menu::create([
            'nombre' => 'Pollo especial',
            'precio' => 18.50,
            'descripcion' => 'Plato principal',
            'imagen' => 'pollo.jpg',
            'categoria' => 'comida',
        ]);

        Menu::create([
            'nombre' => 'Promo del día',
            'precio' => 12.00,
            'descripcion' => 'Promoción del día',
            'imagen' => 'promo.jpg',
            'categoria' => 'promociones',
        ]);

        $response = $this->getJson('/api/menu?categoria=promociones');

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.categoria', 'promociones');
        $response->assertJsonMissing(['categoria' => 'comida']);
    }
}
