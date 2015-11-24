<?php
/**
 * Created by PhpStorm.
 * User: randy
 * Date: 11/24/2015
 * Time: 12:37 PM
 */

namespace Binondord\LaravelScaffold\Commands;

use Binondord\LaravelScaffold\Contracts\Commands\ScaffoldCommandInterface;
use Binondord\LaravelScaffold\Contracts\Services\ScaffoldInterface;

class ScaffoldResetCommand extends ScaffoldCommand implements ScaffoldCommandInterface
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'scaffold:reset';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Remove all created files by scaffold, only those not yet modified";

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        $this->info('Removing...');

        $scaffold = app(ScaffoldInterface::class, [$this]);

        $scaffold->reset();
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            /*['name', InputArgument::REQUIRED, 'The name of the class'],*/
        ];
    }
}