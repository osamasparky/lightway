<?php

namespace App\Console\Commands;

use App\Models\Webinar;
use Illuminate\Console\Command;

class SetWebinarsPriceToZero extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'webinars:set-price-zero';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set all webinars prices to zero';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $count = Webinar::query()->update([
            'price' => 0,
        ]);

        $this->info("Updated {$count} webinars successfully.");

        return Command::SUCCESS;
    }
}
