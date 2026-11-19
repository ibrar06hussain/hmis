<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
            HospitalOrganizationSeeder::class,
        ]);

        if (config('hmis.demo_mode') || app()->environment('local')) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
