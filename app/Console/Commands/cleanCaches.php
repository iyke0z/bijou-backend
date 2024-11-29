<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class cleanCaches extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clean';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear Caches';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('------------------------------------------------------------');
        $this->info("Clearing all caches...");
        $this->info('------------------------------------------------------------');

        $this->call('config:cache');
        $this->call('config:clear');
        $this->call('cache:clear');
        $this->call('optimize:clear');
        $this->call('optimize');

        $this->info('------------------------------------------------------------');
        $this->info("Cache cleared successfully" . (config('services.devs.name') ? ', well done ' .config('services.devs.name') . '!' : ', well done Ikenna!'));
        $this->info('------------------------------------------------------------');
        return 0;
    }
}


