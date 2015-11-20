<?php namespace Binondord\LaravelScaffold\Makes;

use Illuminate\Console\AppNamespaceDetectorTrait;
use Illuminate\Filesystem\Filesystem;
use Binondord\LaravelScaffold\Commands\ScaffoldMakeCommand;
use Binondord\LaravelScaffold\Migrations\SchemaParser;
use Binondord\LaravelScaffold\Migrations\SyntaxBuilder;
use Binondord\LaravelScaffold\Traits\MakerTrait;
use Binondord\LaravelScaffold\Contracts\ScaffoldCommandInterface;

class MakeController extends BaseMake
{
    use AppNamespaceDetectorTrait, MakerTrait;

    function __construct(ScaffoldCommandInterface $command, Filesystem $files)
    {
        parent::__construct($command, $files);

        $this->start();
    }

    private function start()
    {
        $name = $this->command->getObjName('Name') . 'Controller';

        if ($this->files->exists($path = $this->getPath($name))) {
            return $this->command->error($name . ' already exists!');
        }

        $this->makeDirectory($path);

        $this->files->put($path, $this->compileControllerStub());

        $this->command->info('Controller created successfully.');

        //$this->composer->dumpAutoloads();
    }





    /**
     * Compile the migration stub.
     *
     * @return string
     */
    protected function compileControllerStub()
    {
        $stub = $this->files->get(__DIR__ . '/../stubs/controller.stub');

        $this->replaceClassName($stub, "controller")
            ->replaceModelPath($stub)
            ->replaceModelName($stub)
            ->replaceSchema($stub, 'controller');


        return $stub;
    }


    /**
     * Replace the class name in the stub.
     *
     * @param  string $stub
     * @return $this
     */
    protected function replaceClassName(&$stub)
    {

        $className = $this->command->getObjName('Name') . 'Controller';
        $stub = str_replace('{{class}}', $className, $stub);

        return $this;
    }


    /**
     * Renomeia o endereço do Model para o controller
     *
     * @param $stub
     * @return $this
     */
    private function replaceModelPath(&$stub)
    {

        $model_name = $this->getAppNamespace() . $this->command->getObjName('Name');
        $stub = str_replace('{{model_path}}', $model_name, $stub);

        return $this;

    }


    private function replaceModelName(&$stub)
    {
        $model_name_uc = $this->command->getObjName('Name');
        $model_name = $this->command->getObjName('name');
        $model_names = $this->command->getObjName('names');

        $stub = str_replace('{{model_name_class}}', $model_name_uc, $stub);
        $stub = str_replace('{{model_name_var_sgl}}', $model_name, $stub);
        $stub = str_replace('{{model_name_var}}', $model_names, $stub);

        return $this;
    }


    /**
     * Replace the schema for the stub.
     *
     * @param  string $stub
     * @param string $type
     * @return $this
     */
    protected function replaceSchema(&$stub, $type = 'migration')
    {

        if ($schema = $this->command->option('schema')) {
            $schema = (new SchemaParser)->parse($schema);
        }


        // Create controllers fields
        $schema = (new SyntaxBuilder)->create($schema, $this->command->getMeta(), 'controller');
        $stub = str_replace('{{model_fields}}', $schema, $stub);


        return $this;
    }
}