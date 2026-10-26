<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Solo el catálogo (widgets y requisitos). Corre en cada despliegue y no duplica.
     * El caso de ejemplo se carga aparte: php artisan db:seed --class=SankalpaDemoSeeder
     */
    public function run(): void
    {
        $this->call(CatalogSeeder::class);
    }
}
