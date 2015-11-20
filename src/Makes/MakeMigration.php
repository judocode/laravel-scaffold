<?php
/**
 * Created by PhpStorm.
 * User: fernandobritofl
 * Date: 4/22/15
 * Time: 10:34 PM
 */

namespace Binondord\LaravelScaffold\Makes;


use Illuminate\Filesystem\Filesystem;
use Binondord\LaravelScaffold\Migrations\SchemaParser;
use Binondord\LaravelScaffold\Migrations\SyntaxBuilder;
use Binondord\LaravelScaffold\Traits\MakerTrait;
use Binondord\LaravelScaffold\Contracts\ScaffoldCommandInterface;

class MakeMigration extends BaseMake
{
    use MakerTrait;

    protected $className;

    function __construct(ScaffoldCommandInterface $command, Filesystem $files)
    {
        parent::__construct($command, $files);

        $this->start();
    }

    public function __construct(ScaffoldCommandInterface $command, Filesystem $files)
    {
        $this->files = $files;
        $this->command = $command;
        $this->className = ucwords(camel_case('Create'.str_plural($this->command->argument('name')).'Table'));

        $this->start();
    }


    protected function start(){
        // Cria o nome do arquivo do migration // create_tweets_table
        $name = 'create_'.str_plural(strtolower( $this->command->argument('name') )).'_table';

        // Verifica se o arquivo existe com o mesmo o nome
        if ($this->files->exists($path = $this->getPath($name)) || class_exists($this->className))
        {
            return $this->command->error($this->className.' already exists!');
        }

        // Cria a pasta caso nao exista
        $this->makeDirectory($path);

        // Grava o arquivo
        $this->files->put($path, $this->compileMigrationStub());

        $this->command->info('Migration created successfully');
    }


    /**
     * Get the path to where we should store the migration.
     *
     * @param  string $name
     * @return string
     */
    protected function getPath($name)
    {
        return './database/migrations/'.date('Y_m_d_His').'_'.$name.'.php';
    }



    /**
     * Compile the migration stub.
     *
     * @return string
     */
    protected function compileMigrationStub()
    {
        $stub = $this->files->get(__DIR__.'/../stubs/migration.stub');

        $this->replaceClassName($stub)
            ->replaceSchema($stub)
            ->replaceTableName($stub);


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
        $stub = str_replace('{{class}}', $this->className, $stub);

        return $this;
    }

    /**
     * Replace the table name in the stub.
     *
     * @param  string $stub
     * @return $this
     */
    protected function replaceTableName(&$stub)
    {
        $table = $this->command->getMeta()['table'];
        $stub = str_replace('{{table}}', $table, $stub);

        return $this;
    }

    /**
     * Replace the schema for the stub.
     *
     * @param  string $stub
     * @param string $type
     * @return $this
     */
    protected function replaceSchema(&$stub, $type='migration')
    {
        if ($schema = $this->command->option('schema')) {
            $schema = (new SchemaParser)->parse($schema);
        }


        if($type == 'migration'){
            // Create migration fields
            $schema = (new SyntaxBuilder)->create($schema, $this->command->getMeta());
            $stub = str_replace(['{{schema_up}}', '{{schema_down}}'], $schema, $stub);


        } else if($type='controller'){
            // Create controllers fields
            $schema = (new SyntaxBuilder)->create($schema, $this->command->getMeta(), 'controller');
            $stub = str_replace('{{model_fields}}', $schema, $stub);


        } else {}

        return $this;
    }

}