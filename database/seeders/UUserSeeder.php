<?php

namespace Database\Seeders;

use App\Models\UUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create some sample users
        $users = [
            [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'password' => 'password123',
                'phone' => '1234567890',
                'address' => '123 Main St, New York, NY',
                'status' => true,
            ],
            [
                'name' => 'Jane Smith',
                'email' => 'jane@example.com',
                'password' => 'password123',
                'phone' => '0987654321',
                'address' => '456 Park Ave, Boston, MA',
                'status' => true,
            ],
            [
                'name' => 'Robert Johnson',
                'email' => 'robert@example.com',
                'password' => 'password123',
                'phone' => '5551234567',
                'address' => '789 Oak Dr, San Francisco, CA',
                'status' => true,
            ],
            [
                'name' => 'Sarah Williams',
                'email' => 'sarah@example.com',
                'password' => 'password123',
                'phone' => '7778889999',
                'address' => '321 Pine St, Seattle, WA',
                'status' => false,
            ],
            [
                'name' => 'Michael Brown',
                'email' => 'michael@example.com',
                'password' => 'password123',
                'phone' => '3334445555',
                'address' => '654 Cedar Ln, Chicago, IL',
                'status' => true,
            ],
        ];

        // Insert users into database
        foreach ($users as $userData) {
            UUser::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => Hash::make($userData['password']),
                'phone' => $userData['phone'],
                'address' => $userData['address'],
                'status' => $userData['status'],
            ]);
        }
    }
}