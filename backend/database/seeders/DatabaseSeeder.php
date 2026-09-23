<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * No default seed data -- the super admin account is created via
 * `php artisan admin:create` (interactive, or --email/--password flags),
 * not auto-seeded, since a hardcoded default admin credential would be a
 * real security risk if this ever ran against production by accident.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        //
    }
}
