<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;

class HashPasswords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:hash-passwords';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Hash plain text passwords for all users idempotently.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $users = Usuario::all();
        $count = 0;

        foreach ($users as $user) {
            // Check if password is already a valid hash (e.g., bcrypt starts with $2y$)
            $info = Hash::info($user->clave);
            
            // If the algoName is 'unknown', it means it's not a valid Laravel hash
            if ($info['algoName'] === 'unknown') {
                // Also double check it's not empty and doesn't start with $2y$ manually just in case
                if (!empty($user->clave) && !str_starts_with($user->clave, '$2y$')) {
                    $user->clave = Hash::make($user->clave);
                    $user->save();
                    $count++;
                    $this->info("Hashed password for user: {$user->usuario}");
                }
            }
        }

        $this->info("Finished hashing passwords. Total updated: {$count}");
    }
}
