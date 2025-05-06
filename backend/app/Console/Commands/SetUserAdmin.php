<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class SetUserAdmin extends Command
{
    protected $signature = 'ozmose:admin {email} {--remove : Remove admin privileges}';
    protected $description = 'Set or unset a user as admin';

    public function handle()
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User with email {$email} not found.");
            return Command::FAILURE;
        }

        $isRemove = $this->option('remove');
        
        if ($isRemove) {
            $user->is_admin = false;
            $user->save();
            $this->info("Admin privileges removed from {$email}.");
        } else {
            $user->is_admin = true;
            $user->save();
            $this->info("{$email} is now an admin.");
        }

        return Command::SUCCESS;
    }
}