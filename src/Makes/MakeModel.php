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
use Binondord\LaravelScaffold\Contracts\ScaffoldCommandInterface;

class MakeModel extends BaseMake
{
    use MakerTrait;

    function __construct(ScaffoldCommandInterface $command, Filesystem $files)
    {
        parent::__construct($command, $files);

        $this->start();
    }


    protected function start()
    {

        list($name, $modelPath) = $this->command->getModelPath();

        if ($this->files->exists($modelPath)) {
            if ($this->command->confirm($modelPath .' - '. $name.' already exists! Do you wish to overwrite? [yes|no]')) {
                // Put file
                $this->files->put($modelPath, $this->compileModelStub($name));
            }
        }else{

            $this->files->put($modelPath, $this->compileModelStub($name));
        }

    }

    protected function compileModelStub($name)
    {
        $modelAndProperties = $this->askForModelAndFields();

        $moreTables = trim($modelAndProperties) == "q" ? false : true;

        $this->saveModelAndProperties($modelAndProperties, array());

        #return $this->command->processClass($name);
    }

    private function showInformation()
    {
        $this->command->info('MyNamespace\Book title:string year:integer');
        $this->command->info('With relation: Book belongsTo Author title:string published:integer');
        $this->command->info('Multiple relations: University hasMany Course, Department name:string city:string state:string homepage:string )');
        $this->command->info('Or group like properties: University hasMany Department string( name city state homepage )');
    }

    /**
     *  Prompt user for model and properties and return result
     *
     * @return string
     */
    private function askForModelAndFields()
    {
        $modelAndFields = $this->command->ask('Add model with its relations and fields or type "q" to quit (type info for examples) ');

        if($modelAndFields == "info")
        {
            $this->showInformation();

            $modelAndFields = $this->command->ask('Now your turn: ');
        }

        return $modelAndFields;
    }

    /**
     *  Save the model and its properties
     *
     * @param $modelAndProperties
     * @param $oldModelFile
     * @param bool $storeInArray
     */
    private function saveModelAndProperties($modelAndProperties, $oldModelFile, $storeInArray = true)
    {
        

    }
}
