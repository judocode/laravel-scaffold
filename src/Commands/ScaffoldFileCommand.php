<?php namespace Binondord\LaravelScaffold\Commands;

use Illuminate\Console\AppNamespaceDetectorTrait;
use Illuminate\Foundation\Composer;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

use Binondord\LaravelScaffold\Traits\CommonTrait;
use Binondord\LaravelScaffold\Migrations\Scaffold;
use Binondord\LaravelScaffold\Contracts\Commands\ScaffoldCommandInterface;
use Binondord\LaravelScaffold\Contracts\Services\ScaffoldServiceInterface;

class ScaffoldFileCommand extends ScaffoldCommand implements ScaffoldCommandInterface
{
    use AppNamespaceDetectorTrait, CommonTrait;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'scaffold:file';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Makes table, controller, model, views, seeds, and repository from file";

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        $scaffold = app(ScaffoldServiceInterface::class, [$this]);

        $this->info('Please wait while all your files are generated...');

        $scaffold->createModelsFromFile($this->argument('file'));

        $this->info('Finishing...');

        $this->call('clear-compiled');

        $this->call('optimize');

        $this->info('Done!');

        /*
        $this->prepFileFire();

        $this->dumpAutoload();
        */
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['file', InputArgument::REQUIRED, 'The filename of the model definition.'],
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