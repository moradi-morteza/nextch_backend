<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AppInit extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:init';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset and re-run all migrations and seed the database';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {

        # CREATE DATABASE budget CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
        # DROP DATABASE budget;

        if (getenv('DEPLOY_AREA')!='liara'){
            # in liara not need to generate key because it defined in panel setting env
            $this->call('key:generate');
        }

        $this->info('Resetting and re-running migrations...');
        $this->call('migrate:reset');
        $this->call('migrate');

        $this->info('Setting up Laravel Passport...');
        $this->call('passport:install');

        $this->call('db:seed'); // This will call the DatabaseSeeder, which in turn calls the UserSeeder

        $this->info('Database has been reset, seeded, and Passport has been set up successfully.');

        $this->call('optimize:fast');

        return 0;
    }
}
