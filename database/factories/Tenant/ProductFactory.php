<?php
namespace Database\Factories\Tenant;

use App\Models\Tenant\Product;
use App\Models\Tenant\Category;
use App\Models\Tenant\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(3, true);

        return [
            'name' => Str::title($name),
            'sku' => strtoupper(Str::random(8)),
            'barcode' => $this->faker->optional()->ean13(),
            'description' => $this->faker->sentence(),
            'brand' => $this->faker->optional()->company(),
            // si ya existen, usa uno al azar; si no, crea con su factory
            'category_id' => Category::query()->inRandomOrder()->value('id') ?? Category::factory(),
            'unit_id'     => Unit::query()->inRandomOrder()->value('id') ?? Unit::factory(),
            'default_cost_price' => $this->faker->randomFloat(4, 1, 50),
            'default_sale_price' => $this->faker->randomFloat(4, 2, 100),
            'min_stock' => $this->faker->numberBetween(0, 10),
            'is_active' => true,
        ];
    }
}
