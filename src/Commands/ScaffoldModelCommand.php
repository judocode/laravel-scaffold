<?php

namespace Binondord\LaravelScaffold\Commands;

use Illuminate\Console\AppNamespaceDetectorTrait;
use Illuminate\Foundation\Composer;
use Binondord\LaravelScaffold\Makes\MakeController;
use Binondord\LaravelScaffold\Makes\MakeLayout;
use Binondord\LaravelScaffold\Makes\MakeMigration;
use Binondord\LaravelScaffold\Makes\MakeModel;
use Binondord\LaravelScaffold\Traits\CommonTrait;
use Binondord\LaravelScaffold\Makes\MakeSeed;
use Binondord\LaravelScaffold\Makes\MakeView;
use Binondord\LaravelScaffold\Migrations\Scaffold;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Binondord\LaravelScaffold\Contracts\ScaffoldCommandInterface;

class ScaffoldModelCommand extends ScaffoldCommand implements ScaffoldCommandInterface
{
    use AppNamespaceDetectorTrait, CommonTrait;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'scaffold:model';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Makes table, controller, model, views, seeds, and repository for model";

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        $scaffold = new Scaffold($this);

        $scaffold->createModels();

        $this->info('Please wait a few moments...');

        $this->call('clear-compiled');

        $this->call('optimize');

        $this->info('Done!');
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the model. (Ex: Post)'],
        ];
    }


    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['schema', 's', InputOption::VALUE_REQUIRED, 'Schema to generate scaffold files. (Ex: --schema="title:string")', null],
            ['form', 'f', InputOption::VALUE_OPTIONAL, 'Use Illumintate/Html Form facade to generate input fields', false]
        ];
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        $namespace = parent::getDefaultNamespace($rootNamespace);
        return $namespace."\\Models";
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return dirname(__DIR__).'/stubs/model.php';
    }
}