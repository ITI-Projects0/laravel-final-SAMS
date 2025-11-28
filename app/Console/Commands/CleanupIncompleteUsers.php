<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CleanupIncompleteUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cleanup-incomplete-users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove users who have not completed their profile within 3 days';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = \App\Models\User::incomplete()
            ->where('created_at', '<', now()->subDays(3))
            ->delete();

        $this->info("Deleted {$count} incomplete users.");
    }
}
