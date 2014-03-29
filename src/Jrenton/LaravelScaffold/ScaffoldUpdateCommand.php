<?php namespace Jrenton\LaravelScaffold;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;

class ScaffoldUpdateCommand extends Command
{
    protected $name = 'scaffold:update';

    protected $description = "Makes layout, js/css, table, controller, model, views, seeds, and repository";

    public function __construct()
    {
        parent::__construct();
    }

    public function fire()
    {
        $scaffold = new Scaffold($this);

        $scaffold->update();

        $this->info('Please wait a few moments...');

        $this->call('clear-compiled');

        $this->call('optimize');

        $this->info('Done!');
    }

    protected function getArguments()
    {
        return array(
            array('name', InputArgument::OPTIONAL, 'Name of the model/controller.'),
        );
    }
}
