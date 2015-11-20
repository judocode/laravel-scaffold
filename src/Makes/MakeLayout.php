<?php

namespace Binondord\LaravelScaffold\Makes;

use Illuminate\Filesystem\Filesystem;

use Binondord\LaravelScaffold\Traits\MakerTrait;
use Binondord\LaravelScaffold\Contracts\ScaffoldCommandInterface;
use Binondord\LaravelScaffold\Contracts\Makes\MakeLayoutInterface;

class MakeLayout extends BaseMake implements MakeLayoutInterface
{
    use MakerTrait;

    function __construct(ScaffoldCommandInterface $command, Filesystem $files)
    {
        parent::__construct($command, $files);

        $this->start();
    }


    protected function start()
    {
        $this->putViewLayout('Layout', 'stubs/html_assets/layout.stub', 'layout.blade.php');
        $this->putViewLayout('Error', 'stubs/html_assets/error.stub', 'error.blade.php');
    }


    /**
     * @param $path_resource
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function putViewLayout($name, $stub, $file)
    {
        $path_file = $this->getPathResource().$file;
        $path_stub = __DIR__ .'/../'.$stub;

        if (!\File::exists($path_file)){
            $html = \File::get($path_stub);
            \File::put($path_file, $html);

            $this->command->info("$name created successfully.");
        }else{
            $this->command->comment("Skip $name, because already exists.");
        }
    }



    /**
     * Get the path to where we should store the view.
     *
     * @return string
     */
    protected function getPathResource()
    {
        return './resources/views/';
    }
}