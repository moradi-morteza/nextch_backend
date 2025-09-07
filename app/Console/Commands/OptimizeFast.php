<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class OptimizeFast extends Command
{
    protected $signature = 'optimize:fast';
    protected $description = 'Clear and rebuild Laravel caches (config, route, view) for better performance';

    public function handle()
    {
        $this->info('🔄 Clearing all Laravel caches...');

        $this->call('config:clear');
        $this->call('cache:clear');
        $this->call('route:clear');
//        $this->call('view:clear');

        $this->info('✅ Rebuilding optimized caches...');

        $this->call('config:cache');
        $this->call('route:cache');
//        $this->call('view:cache');

        $this->info('🚀 Done! Your Laravel app is optimized for speed.');
        return 0;
    }
}
