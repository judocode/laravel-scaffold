<?php
/**
 * Created by PhpStorm.
 * User: fernandobritofl
 * Date: 4/22/15
 * Time: 10:34 PM
 */

namespace Binondord\LaravelScaffold\Makes;


use Illuminate\Filesystem\Filesystem;
use Binondord\LaravelScaffold\Commands\ScaffoldMakeCommand;
use Binondord\LaravelScaffold\Traits\MakerTrait;

class MakeSeed extends BaseMake
{
    use MakerTrait;

    function __construct(ScaffoldCommandInterface $command, Filesystem $files)
    {
        parent::__construct($command, $files);

        $this->start();
    }


    protected function start()
    {


        // Get path
        $path = $this->getPath($this->command->getObjName('Name') . 'TableSeeder', 'seed');


        // Create directory
        $this->makeDirectory($path);


        if ($this->files->exists($path)) {
            if ($this->command->confirm($path . ' already exists! Do you wish to overwrite? [yes|no]')) {
                // Put file
                $this->files->put($path, $this->compileSeedStub());
                $this->getSuccessMsg();
            }
        } else {

            // Put file
            $this->files->put($path, $this->compileSeedStub());
            $this->getSuccessMsg();

        }

    }


    protected function getSuccessMsg()
    {
        $this->command->info('Seed created successfully.');
    }


    /**
     * Compile the migration stub.
     *
     * @return string
     */
    protected function compileSeedStub()
    {
        $stub = $this->files->get(__DIR__ . '/../stubs/seed.stub');

        $this->replaceClassName($stub);


        return $stub;
    }


    private function replaceClassName(&$stub)
    {
        $name = $this->command->getObjName('Name');

        $stub = str_replace('{{class}}', $name, $stub);

        return $this;
    }


}