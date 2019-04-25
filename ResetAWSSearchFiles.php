<?php

namespace App\Console\Commands;

use App\Store;
use Illuminate\Console\Command;

class ResetAWSSearchFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'aws:reset_search_files';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
     * @return mixed
     */
    public function handle()
    {
       $stores = Store::whereIn('store_code', ['2701', '1230', '9515','1006','3501'])->get();
       foreach ($stores as $store){
           $store->aws_search_file = 0;
           $store->save();
       }
    }
}
