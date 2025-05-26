<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        
        User::create([
            'name' => 'James',
            'email' => 'jhenderson@texasintegratedservices.com',
            'password' => Hash::make('texas123'), // Always hash passwords
        ]);

        $this->call(OhlcvTableSeeder::class);
        $this->call(OhlcvImportSeeder::class);
    }
}
