<?php

namespace Binondord\LaravelScaffold\Commands;

use Illuminate\Console\AppNamespaceDetectorTrait;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Composer;
use Binondord\LaravelScaffold\Makes\MakeController;
use Binondord\LaravelScaffold\Makes\MakeLayout;
use Binondord\LaravelScaffold\Makes\MakeMigration;
use Binondord\LaravelScaffold\Makes\MakeModel;
use Binondord\LaravelScaffold\Makes\MakeSeed;
use Binondord\LaravelScaffold\Makes\MakeView;
use Binondord\LaravelScaffold\Migrations\Scaffold;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Binondord\LaravelScaffold\Contracts\ScaffoldCommandInterface;

class ScaffoldMakeCommand extends ScaffoldCommand implements ScaffoldCommandInterface
{
    use AppNamespaceDetectorTrait;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'scaffold:make';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a scaffold with bootstrap 3';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {


        /*
        // Start Scaffold
        $this->prepFire();

        // Generate files
        $this->makeMigration();
        $this->makeSeed();
        $this->makeModel();
        $this->makeController();
        $this->makeViewLayout();
        $this->makeViews();*/


    }




}
