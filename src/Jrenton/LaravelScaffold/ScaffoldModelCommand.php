<?php namespace Jrenton\LaravelScaffold;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;

class ScaffoldModelCommand extends Command
{
    protected $name = 'scaffold:model';

    protected $description = "Makes table, controller, model, views, seeds, and repository for model";

    public function __construct()
    {
        parent::__construct();
    }

    public function fire()
    {
        $scaffold = new Scaffold($this);

        $scaffold->createModels();

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
