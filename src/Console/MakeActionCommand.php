<?php
/**
 * Created by PhpStorm.
 * User: Sarfraz
 * Date: 7/12/2019
 * Time: 2:47 PM
 */

namespace Sarfraznawaz2005\Actions\Console;

use Illuminate\Console\Command;

class MakeActionCommand extends Command
{
    protected $signature = 'servermonitor:check {checker? : Optional check to run.}';
    protected $description = 'Starts new checks process for server and application.';

    /**
     * Execute the console command.
     *
     * @return mixed
     * @throws \Exception
     */
    public function handle()
    {

    }
}
