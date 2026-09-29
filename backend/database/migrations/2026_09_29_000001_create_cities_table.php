<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bolivian cities used to segment courts. Coordinates are approximate city centers.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: float, 4: float}>
     */
    private const CITIES = [
        // key, name, department, latitude, longitude
        ['santa_cruz_de_la_sierra', 'Santa Cruz de la Sierra', 'Santa Cruz', -17.7833, -63.1821],
        ['montero', 'Montero', 'Santa Cruz', -17.3422, -63.2558],
        ['warnes', 'Warnes', 'Santa Cruz', -17.5103, -63.1647],
        ['cotoca', 'Cotoca', 'Santa Cruz', -17.7539, -62.9969],
        ['la_guardia', 'La Guardia', 'Santa Cruz', -17.8928, -63.3294],
        ['yapacani', 'Yapacaní', 'Santa Cruz', -17.4028, -63.8850],
        ['camiri', 'Camiri', 'Santa Cruz', -20.0386, -63.5183],
        ['san_ignacio_de_velasco', 'San Ignacio de Velasco', 'Santa Cruz', -16.3667, -60.9500],
        ['puerto_suarez', 'Puerto Suárez', 'Santa Cruz', -18.9633, -57.7978],
        ['la_paz', 'La Paz', 'La Paz', -16.4955, -68.1336],
        ['el_alto', 'El Alto', 'La Paz', -16.5047, -68.1633],
        ['viacha', 'Viacha', 'La Paz', -16.6533, -68.3017],
        ['cochabamba', 'Cochabamba', 'Cochabamba', -17.3895, -66.1568],
        ['quillacollo', 'Quillacollo', 'Cochabamba', -17.3925, -66.2786],
        ['sacaba', 'Sacaba', 'Cochabamba', -17.4042, -66.0408],
        ['sucre', 'Sucre', 'Chuquisaca', -19.0196, -65.2619],
        ['oruro', 'Oruro', 'Oruro', -17.9667, -67.1167],
        ['potosi', 'Potosí', 'Potosí', -19.5836, -65.7531],
        ['llallagua', 'Llallagua', 'Potosí', -18.4242, -66.5842],
        ['villazon', 'Villazón', 'Potosí', -22.0866, -65.5942],
        ['tarija', 'Tarija', 'Tarija', -21.5355, -64.7296],
        ['yacuiba', 'Yacuiba', 'Tarija', -22.0153, -63.6775],
        ['bermejo', 'Bermejo', 'Tarija', -22.7322, -64.3378],
        ['trinidad', 'Trinidad', 'Beni', -14.8333, -64.9000],
        ['riberalta', 'Riberalta', 'Beni', -11.0000, -66.0667],
        ['guayaramerin', 'Guayaramerín', 'Beni', -10.8258, -65.3567],
        ['cobija', 'Cobija', 'Pando', -11.0267, -68.7692],
    ];

    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('department');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['department', 'name']);
        });

        $now = now();
        DB::table('cities')->insert(array_map(fn (array $city) => [
            'key' => $city[0],
            'name' => $city[1],
            'department' => $city[2],
            'latitude' => $city[3],
            'longitude' => $city[4],
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], self::CITIES));

        Schema::table('courts', function (Blueprint $table) {
            $table->foreignId('city_id')
                ->nullable()
                ->after('owner_id')
                ->constrained('cities')
                ->nullOnDelete();
            $table->index('city_id');
        });

        // Every court registered so far is in Santa Cruz de la Sierra.
        $santaCruzId = DB::table('cities')->where('key', 'santa_cruz_de_la_sierra')->value('id');
        DB::table('courts')->whereNull('city_id')->update(['city_id' => $santaCruzId]);
    }

    public function down(): void
    {
        Schema::table('courts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('city_id');
        });

        Schema::dropIfExists('cities');
    }
};
