<?php

// v1.0 — 2026-05-22 | Seed reader accounts matching SR_UPLOAD_READERS in sr-upload-system.php

namespace Database\Seeders;

use App\Models\ReaderProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ReadersSeeder extends Seeder
{
    /**
     * Idempotent — safe to run multiple times. Matches by email.
     * Passwords are randomised; readers must use Forgot Password to log in.
     *
     * Names inferred from email addresses where possible.
     * ⚠  DL and FF: first/last names are placeholders — update via the Readers admin page.
     */
    public function run(): void
    {
        $readers = [
            // initials, email,                         first_name,   last_name,  display_name
            ['DL', 'salazar62@gmail.com',         'D.',         'Salazar',  'D. Salazar'],   // ⚠ first name unknown
            ['FF', 'flashfilms@flashfilms.us',    'F.',         'F.',       'FF'],            // ⚠ full name unknown
            ['JF', 'joelfishbane@gmail.com',      'Joel',       'Fishbane', 'Joel Fishbane'],
            ['KD', 'krishnadevine@gmail.com',     'Krishna',    'Devine',   'Krishna Devine'],
            ['SG', 'sam.gurney@hotmail.com',      'Sam',        'Gurney',   'Sam Gurney'],
            ['TZ', 'terrizinner@gmail.com',       'Terri',      'Zinner',   'Terri Zinner'],
        ];

        foreach ($readers as [$initials, $email, $firstName, $lastName, $displayName]) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name'              => $displayName,
                    'role'              => 'reader',
                    'password'          => Hash::make(Str::random(24)),
                    'email_verified_at' => now(),
                ]
            );

            ReaderProfile::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'initials'                   => $initials,
                    'first_name'                 => $firstName,
                    'last_name'                  => $lastName,
                    'max_concurrent_assignments' => 3,
                ]
            );
        }
    }
}
