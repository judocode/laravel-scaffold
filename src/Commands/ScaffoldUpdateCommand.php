<?php

namespace Binondord\LaravelScaffold\Commands;

use Illuminate\Console\AppNamespaceDetectorTrait;
use Illuminate\Filesystem\Filesystem;
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

class ScaffoldUpdateCommand extends ScaffoldCommand implements ScaffoldCommandInterface
{
    use AppNamespaceDetectorTrait, CommonTrait;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'scaffold:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Update model and database schema based on changes to models file";

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        $this->info('Updating...');

        $scaffold = new Scaffold($this);

        $scaffold->update();

        $this->info('Finishing...');

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
            ['name', InputArgument::OPTIONAL, 'The name of the model. (Ex: Post)'],
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
}