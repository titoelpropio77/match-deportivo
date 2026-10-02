<?php

namespace Database\Seeders;

use App\Models\Court;
use App\Models\ProductCategory;
use App\Models\StockMovement;
use Illuminate\Database\Seeder;

class StoreSeeder extends Seeder
{
    /**
     * Sample stores with products. Upserts by name so re-seeding keeps orders and stock history.
     */
    public function run(): void
    {
        $stores = [
            [
                'court' => 'Complejo Wally Sur',
                'name' => 'Wally Shop',
                'description' => 'Todo para tu partido: pelotas, paletas, ropa y bebidas frías. Compra en la app y retira en el mostrador.',
                'phone' => '70012345',
                'cover_path' => 'https://picsum.photos/seed/wally-shop/800/400',
                'categories' => ['balls', 'rackets', 'apparel', 'accessories', 'drinks', 'snacks'],
                'products' => [
                    ['category' => 'balls', 'name' => 'Pelota de wally profesional', 'price' => 120, 'discount_percent' => 10, 'stock' => 12, 'min_stock' => 3],
                    ['category' => 'rackets', 'name' => 'Paleta de pádel Carbon Pro', 'price' => 650, 'discount_percent' => 15, 'stock' => 4, 'min_stock' => 2],
                    ['category' => 'rackets', 'name' => 'Paleta de frontón de madera', 'price' => 90, 'stock' => 6],
                    ['category' => 'balls', 'name' => 'Tubo de pelotas de pádel (x3)', 'price' => 70, 'stock' => 20, 'min_stock' => 5],
                    ['category' => 'apparel', 'name' => 'Polera dry-fit Wally Sur', 'price' => 85, 'stock' => 15, 'description' => 'Tallas S a XL, consulta disponibilidad al retirar.'],
                    ['category' => 'accessories', 'name' => 'Muñequera (par)', 'price' => 25, 'stock' => 2, 'min_stock' => 4],
                    ['category' => 'drinks', 'name' => 'Bebida isotónica 500 ml', 'price' => 12, 'stock' => 48, 'min_stock' => 12],
                    ['category' => 'drinks', 'name' => 'Agua mineral 600 ml', 'price' => 6, 'stock' => 60],
                    ['category' => 'snacks', 'name' => 'Barra de cereal', 'price' => 8, 'discount_percent' => 25, 'stock' => 30],
                ],
            ],
            [
                'court' => 'Arena Norte',
                'name' => 'Arena Norte Sports',
                'description' => 'Calzado y protección para fútbol y básquet.',
                'cover_path' => 'https://picsum.photos/seed/arena-norte-sports/800/400',
                'categories' => ['footwear', 'protection', 'balls', 'drinks'],
                'products' => [
                    ['category' => 'footwear', 'name' => 'Botines de fútbol sala', 'price' => 380, 'discount_percent' => 20, 'stock' => 8],
                    ['category' => 'protection', 'name' => 'Canilleras', 'price' => 60, 'stock' => 10],
                    ['category' => 'balls', 'name' => 'Balón de fútbol N°5', 'price' => 150, 'stock' => 7],
                    ['category' => 'drinks', 'name' => 'Bebida energética 473 ml', 'price' => 15, 'stock' => 0],
                ],
            ],
        ];

        $categoryIds = ProductCategory::query()->pluck('id', 'key');

        foreach ($stores as $data) {
            $court = Court::query()->where('name', $data['court'])->first();
            if ($court === null) {
                continue;
            }

            $store = $court->stores()->updateOrCreate(
                ['name' => $data['name']],
                collect($data)->only(['description', 'phone', 'cover_path'])->all(),
            );
            $store->categories()->sync($categoryIds->only($data['categories'])->values());

            foreach ($data['products'] as $index => $productData) {
                $product = $store->products()->firstOrNew(['name' => $productData['name']]);
                $product->fill([
                    ...collect($productData)->except(['category', 'stock'])->all(),
                    'product_category_id' => $categoryIds[$productData['category']],
                ]);
                $isNew = ! $product->exists;
                $product->save();

                if ($isNew) {
                    $product->photos()->create(['path' => 'https://picsum.photos/seed/'.str($productData['name'])->slug().'/600/600', 'order' => 0]);
                    if ($productData['stock'] > 0) {
                        $product->moveStock($productData['stock'], StockMovement::TYPE_INITIAL, reason: 'Stock inicial');
                    }
                }
            }
        }
    }
}
