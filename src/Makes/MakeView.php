<?php
/**
 * Created by PhpStorm.
 * User: fernandobritofl
 * Date: 4/21/15
 * Time: 4:58 PM
 */

namespace Binondord\LaravelScaffold\Makes;


use Illuminate\Filesystem\Filesystem;
use Binondord\LaravelScaffold\Migrations\SchemaParser;
use Binondord\LaravelScaffold\Migrations\SyntaxBuilder;
use Binondord\LaravelScaffold\Traits\MakerTrait;
use Binondord\LaravelScaffold\Contracts\Commands\ScaffoldCommandInterface;

class MakeView extends BaseMake
{
    use MakerTrait;

    protected $viewName;

    function __construct(ScaffoldCommandInterface $command, Filesystem $files)
    {
        parent::__construct($command, $files);
    }

    public function setView($viewName)
    {
        $this->viewName = $viewName;
        $this->start();
    }

    private function start()
    {
        $this->generateView($this->viewName); // index, show, edit and create
    }





    protected function generateView($nameView = 'index'){
        // Get path
        $path = $this->getPath($this->command->getObjName('names'), 'view-'.$nameView);


        // Create directory
        $this->makeDirectory($path);


        if ($this->files->exists($path)) {
            if ($this->command->confirm($path . ' already exists! Do you wish to overwrite? [yes|no]')) {
                // Put file
                $this->files->put($path, $this->compileViewStub($nameView));
            }
        } else {

            // Put file
            $this->files->put($path, $this->compileViewStub($nameView));
        }


    }






    /**
     * Compile the migration stub.
     *
     * @param $nameView
     * @return string
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function compileViewStub($nameView)
    {
        $stub = $this->files->get(__DIR__ . '/../stubs/html_assets/'.$nameView.'.stub');

        if($nameView == 'show'){
            // show.blade.php
            $this->replaceName($stub)
                ->replaceSchemaShow($stub);

        } elseif($nameView == 'edit'){
            // edit.blade.php
            $this->replaceName($stub)
                ->replaceSchemaEdit($stub);

        } elseif($nameView == 'create'){
            // edit.blade.php
            $this->replaceName($stub)
                ->replaceSchemaCreate($stub);

        } else {
            // index.blade.php
            $this->replaceName($stub)
                ->replaceSchemaIndex($stub);
        }



        return $stub;
    }


    /**
     * Replace the class name in the stub.
     *
     * @param  string $stub
     * @return $this
     */
    protected function replaceName(&$stub)
    {
        $stub = str_replace('{{Class}}', $this->command->getObjName('Names'), $stub);
        $stub = str_replace('{{class}}', $this->command->getObjName('names'), $stub);
        $stub = str_replace('{{classSingle}}', $this->command->getObjName('name'), $stub);

        return $this;
    }





    /**
     * Replace the schema for the index.stub.
     *
     * @param  string $stub
     * @return $this
     */
    protected function replaceSchemaIndex(&$stub)
    {

        if ($schema = $this->command->option('schema')) {
            $schemaArray = (new SchemaParser)->parse($schema);
        }


        // Create view index header fields
        $schema = (new SyntaxBuilder)->create($schemaArray, $this->command->getMeta(), 'view-index-header');
        $stub = str_replace('{{header_fields}}', $schema, $stub);


        // Create view index content fields
        $schema = (new SyntaxBuilder)->create($schemaArray, $this->command->getMeta(), 'view-index-content');
        $stub = str_replace('{{content_fields}}', $schema, $stub);


        return $this;
    }





    /**
     * Replace the schema for the show.stub.
     *
     * @param  string $stub
     * @return $this
     */
    protected function replaceSchemaShow(&$stub)
    {

        if ($schema = $this->command->option('schema')) {
            $schemaArray = (new SchemaParser)->parse($schema);
        }


        // Create view index content fields
        $schema = (new SyntaxBuilder)->create($schemaArray, $this->command->getMeta(), 'view-show-content');
        $stub = str_replace('{{content_fields}}', $schema, $stub);


        return $this;
    }


    /**
     * Replace the schema for the edit.stub.
     *
     * @param  string $stub
     * @return $this
     */
    private function replaceSchemaEdit(&$stub)
    {

        if ($schema = $this->command->option('schema')) {
            $schemaArray = (new SchemaParser)->parse($schema);
        }


        // Create view index content fields
        $schema = (new SyntaxBuilder)->create($schemaArray, $this->command->getMeta(), 'view-edit-content', $this->command->option('form'));
        $stub = str_replace('{{content_fields}}', $schema, $stub);


        return $this;

    }


    /**
     * Replace the schema for the edit.stub.
     *
     * @param  string $stub
     * @return $this
     */
    private function replaceSchemaCreate(&$stub)
    {

        if ($schema = $this->command->option('schema')) {
            $schemaArray = (new SchemaParser)->parse($schema);
        }


        // Create view index content fields
        $schema = (new SyntaxBuilder)->create($schemaArray, $this->command->getMeta(), 'view-create-content', $this->command->option('form'));
        $stub = str_replace('{{content_fields}}', $schema, $stub);


        return $this;

    }

}