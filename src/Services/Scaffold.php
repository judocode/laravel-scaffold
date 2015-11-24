<?php namespace Binondord\LaravelScaffold\Services;

use Faker\Factory;

use Illuminate\Console\Command;
use Illuminate\Filesystem\FileNotFoundException;

use Binondord\LaravelScaffold\Migrations\AssetDownloader;
use Binondord\LaravelScaffold\Migrations\FileCreator;
use Binondord\LaravelScaffold\Migrations\Migration;
use Binondord\LaravelScaffold\Migrations\Model;
use Binondord\LaravelScaffold\Migrations\NameParser;
use Binondord\LaravelScaffold\Migrations\Relation;
use Binondord\LaravelScaffold\Migrations\SchemaParser;
use Binondord\LaravelScaffold\Migrations\SyntaxBuilder;

use Binondord\LaravelScaffold\Contracts\Migrations\AssetDownloaderInterface;
use Binondord\LaravelScaffold\Contracts\Migrations\FileCreatorInterface;
use Binondord\LaravelScaffold\Contracts\Commands\ScaffoldCommandInterface;
use Binondord\LaravelScaffold\Contracts\Services\ScaffoldInterface;

/**
 * From Jrenton
 * Class Scaffold
 * @package Binondord\LaravelScaffold\Migrations
 */

class Scaffold implements ScaffoldInterface
{
    /**
     * @var array
     */
    private $laravelClasses = array();

    /**
     * @var array (md5hash => filename)
     */
    private $createdFilesCache = array();

    /**
     * @var array
     */
    private $createDirCache = array();

    /**
     * @var array
     */
    private $createdItemsCache = array();

    /**
     * @var array
     */
    private $createMigrationFilesCache = array();

    /**
     * @var Model
     */
    private $model;

    /**
     * @var Migration
     */
    private $migration;

    /**
     * @var bool
     */
    private $isResource;

    /**
     * @var string
     */
    private $controllerType;

    /**
     * @var bool
     */
    private $fromFile;

    /**
     * @var FileCreator
     */
    private $fileCreator;

    /**
     * @var AssetDownloader
     */
    private $assetDownloader;

    /**
     * @var array
     */
    protected $configSettings;

    /**
     * @var \Binondord\LaravelScaffold\Contracts\Commands\ScaffoldCommandInterface;
     */
    protected $command;

    /**
     * @var string
     */
    private $templatePathWithControllerType;

    /**
     * @var bool
     */
    private $columnAdded = false;

    /**
     * @var bool
     */
    private $onlyMigration = false;

    /**
     * @var bool
     */
    private $namespaceGlobal;

    /**
     * @var string
     */
    private $namespace;

    /**
     * @var array
     */
    private $lastTimeStamp = array();

    /**
     *  Stores the current collection of models
     *
     * @var array
     */
    private $models = array();

    private $configFields = array(
        'names',
        'appName',
        'downloads',
        'views',
        'repository',
        'baseRepository',
        'modelDefinitionsFile',
        'useRepository',
        'useBaseRepository',
        'transfers'
    );

    public function __construct(ScaffoldCommandInterface $command)
    {
        $this->configSettings = $this->getConfigSettings();
        $this->command = $command;
        $this->fileCreator = app(FileCreatorInterface::class,[$command]);
        $this->assetDownloader = app(AssetDownloaderInterface::class,[$command, $this->fileCreator]);
        $this->assetDownloader->setConfigSettings($this->configSettings);

    }

    /**
     * Load user's config settings
     *
     * @return array
     */
    private function getConfigSettings()
    {
        $package = "l5scaffold";

        $configSettings = array();

        $configSettings['pathTo'] = config("$package.paths");

        foreach($configSettings['pathTo'] as $pathName => $path)
        {
            if($path[strlen($path)-1] != "/")
            {
                if($pathName != "layout")
                    $path .= "/";

                $configSettings['pathTo'][$pathName] = $path;
            }
        }

        foreach($this->configFields as $configField)
        {
            $configSettings[$configField] = config("$package.$configField");
        }

        return $configSettings;
    }

    /**
     *  Prompt for and save models from the command line
     */
    public function createModels()
    {
        $this->fromFile = false;
        $this->fileCreator->fromFile = false;
        $this->assetDownloader->fromFile = false;

        $this->setupLayoutFiles();

        $modelAndProperties = $this->askForModelAndFields();

        $moreTables = trim($modelAndProperties) == "q" ? false : true;

        while( $moreTables )
        {
            $this->saveModelAndProperties($modelAndProperties, array());

            $this->isResource = $this->confirm('Do you want resource (y) or restful (n) controllers? ');

            $this->createFiles();

            $this->info("Model ".$this->model->upper(). " and all associated files created successfully!");

            $this->addToModelDefinitions($modelAndProperties);

            $modelAndProperties = $this->command->ask('Add model with fields or "q" to quit: ');

            $moreTables = trim($modelAndProperties) == "q" ? false : true;
        }
    }

    /**
     *  Generate the layout and download js/css files
     */
    public function createLayout()
    {
        $this->assetDownloader->generateLayoutFiles();
    }

    /**
     *  Generate models from a file
     *
     * @param $fileName
     */
    public function createModelsFromFile($fileName)
    {
        $this->fileCreator->fromFile = true;
        $this->fromFile = true;
        $this->assetDownloader->fromFile = true;

        $this->setupLayoutFiles();

        $this->createLayout();

        $inputFile = file($fileName);

        $this->addAllModelsFromFile($inputFile);
    }

    /**
     *
     */
    public function setupLayoutFiles()
    {
        $this->laravelClasses = $this->getLaravelClassNames();

        $this->copyTemplateFiles();
    }

    /**
     *  Update any changes made to model definitions file
     */
    public function update()
    {
        $this->fileCreator->fromFile = true;
        $this->fromFile = true;
        $this->assetDownloader->fromFile = true;

        $this->setupLayoutFiles();

        $inputFile = file($this->configSettings['modelDefinitionsFile']);

        $this->addAllModelsFromFile($inputFile);
    }

    /**
     *  Add and save all models from specified file
     *
     * @param $inputFile
     */
    public function addAllModelsFromFile($inputFile)
    {
        $oldModelFile = array();

        // Get the cached model definitions file to compare against
        if(\File::exists($this->getModelCacheFile()))
        {
            $cachedFile = file($this->getModelCacheFile());
            $oldModelFile = $this->getCachedModels($cachedFile, false);
        }

        // Loop through the file and create all associated files
        foreach( $inputFile as $line_num => $modelAndProperties )
        {
            $modelAndProperties = trim($modelAndProperties);
            if(!empty($modelAndProperties))
            {
                if(preg_match("/^resource =/", $modelAndProperties))
                {
                    $this->isResource = trim(substr($modelAndProperties, strpos($modelAndProperties, "=")+1));
                    continue;
                }

                if(preg_match("/^namespace =/", $modelAndProperties))
                {
                    $this->namespaceGlobal = true;
                    $this->namespace = trim(substr($modelAndProperties, strpos($modelAndProperties, "=")+1));
                    $this->fileCreator->namespace = $this->namespace;
                    continue;
                }

                $this->saveModelAndProperties($modelAndProperties, $oldModelFile);

                $this->createFiles();
            }
        }

        $contentFilesCache =  serialize([
            'createdFilesCache' => $this->createdFilesCache,
            'createdDirCache' => $this->createDirCache,
            'createdMigrationCache' => $this->createMigrationFilesCache
        ]);

        $this->fileCreator->createFile($this->getCreatedFilesCache(), $contentFilesCache);

        // If any models existed in the cached file,
        // and not in the current file, drop that table
        foreach ($oldModelFile as $tableName => $modelData)
        {
            if(!array_key_exists($tableName, $this->models))
            {
                $migration = new Migration($this->configSettings['pathTo']['migrations'], $modelData['model'], $this->fileCreator);
                $migration->dropTable($this->lastTimeStamp);
            }
        }

        copy($this->configSettings['modelDefinitionsFile'], $this->getModelCacheFile());
    }

    /**
     *  Get all of the cached models from the specified file
     *
     * @param $inputFile
     * @param bool $createFiles
     * @return array
     */
    public function getCachedModels($inputFile, $createFiles = true)
    {
        $oldModelFile = array();

        foreach( $inputFile as $line_num => $modelAndProperties )
        {
            $modelAndProperties = trim($modelAndProperties);
            if(!empty($modelAndProperties))
            {
                if(preg_match("/^resource =/", $modelAndProperties))
                {
                    $this->isResource = trim(substr($modelAndProperties, strpos($modelAndProperties, "=")+1));
                    continue;
                }

                if(preg_match("/^namespace =/", $modelAndProperties))
                {
                    $this->namespaceGlobal = true;
                    $this->namespace = trim(substr($modelAndProperties, strpos($modelAndProperties, "=")+1));
                    $this->fileCreator->namespace = $this->namespace;
                    continue;
                }

                $this->saveModelAndProperties($modelAndProperties, array(), false);

                $oldModelFile[$this->model->getTableName()] = array();

                $oldModelFile[$this->model->getTableName()]['relationships'] = $this->model->getRelationships();
                $oldModelFile[$this->model->getTableName()]['properties'] = $this->model->getProperties();
                $oldModelFile[$this->model->getTableName()]['model'] = $this->model;


                if($createFiles)
                    $this->createFiles();
            }
        }

        return $oldModelFile;
    }

    /**
     *  Get the laravel class names to check for collisions
     *
     * @return array
     */
    private function getLaravelClassNames()
    {
        $classNames = array();

        $aliases = config('app.aliases');

        foreach ($aliases as $alias => $facade)
        {
            array_push($classNames, strtolower($alias));
        }

        return $classNames;
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
        do {
            if(!$this->namespaceGlobal)
                $this->namespace = "";

            $this->namespace = "App\\Models";

            $this->model = new Model($this->command, $oldModelFile, $this->namespace);

            $this->model->generateModel($modelAndProperties);

            if($storeInArray)
                $this->models[$this->model->getTableName()] = $this->model;

            if(!$this->namespaceGlobal)
            {
                $this->fileCreator->namespace = $this->model->getNamespace();
                $this->namespace = $this->model->getNamespace();
            }

            $modelNameCollision = in_array($this->model->lower(), $this->laravelClasses);

        } while($modelNameCollision);

        $propertiesGenerated = $this->model->generateProperties();

        if(!$propertiesGenerated)
        {
            if($this->fromFile)
                exit;
            else
                $this->createModels();
        }
    }

    /**
     *  Add the current model to the model definitions file
     *
     * @param $modelAndProperties
     */
    private function addToModelDefinitions($modelAndProperties)
    {
        \File::append($this->getModelCacheFile(), "\n" . $modelAndProperties);
    }

    /**
     *  Gets the model cache file as it relates to the model definitions file
     *
     * @return string
     */
    private function getModelCacheFile()
    {
        $file = $this->configSettings['modelDefinitionsFile'];
        $modelFilename = substr(strrchr($file, "/"), 1);
        $ext = substr($modelFilename, strrpos($modelFilename, "."), strlen($modelFilename)-strrpos($modelFilename, "."));
        $name = substr($modelFilename, 0, strrpos($modelFilename, "."));
        $modelDefinitionsFile = substr($file, 0, strrpos($file, "/")+1) . "." . $name ."-cache". $ext;
        return $modelDefinitionsFile;
    }

    private function getCreatedFilesCache()
    {
        $cacheModel = $this->getModelCacheFile();
        return str_replace('models','created-files',$cacheModel);
    }

    private function removeCreatedFiles()
    {
        $remainingFiles = array();

        if(\File::exists($this->getCreatedFilesCache()))
        {
            $createdFilesCache = \File::get($this->getCreatedFilesCache());
            $createdFilesCacheArray = unserialize($createdFilesCache);
            $createdFilesArray = $createdFilesCacheArray['createdFilesCache'];
            $createdMigrationArray = $createdFilesCacheArray['createdMigrationCache'];
            $createdFilesArray = array_merge($createdFilesArray, $createdMigrationArray);

            foreach($createdFilesArray as $key=>$createdFile)
            {
                if(\File::exists($createdFile))
                {
                    $content = \File::get($createdFile);
                    if(md5($content) == $key)
                    {
                        \File::delete($createdFile);
                    }else{
                        $remainingFiles[$key] = $createdFile;
                    }
                }
            }

            $createdDirCacheArray = $createdFilesCacheArray['createdDirCache'];

            foreach($createdDirCacheArray as $createdDir)
            {
                $listFiles = \File::files($createdDir);
                if(empty($listFiles))
                {
                    \File::deleteDirectory($createdDir);
                }
            }

            if(!empty($remainingFiles))
            {
                $this->info('List of modified files preserved. (You may want to retain or remove them manually.)');
                $i=0;
                foreach($remainingFiles as $remainingFile)
                {
                    $this->info(++$i.'.) '.$remainingFile);
                }
            }

            return true;
        }else{
            $this->info('Nothing to remove.');

            return false;
        }
    }

    public function reset()
    {
        if($this->removeCreatedFiles())
        {
            $this->info('Finishing...');

            $this->command->call('clear-compiled');

            $this->command->call('optimize');
        };

        $this->info('Done!');
    }

    /**
     *  Creates all of the files
     */
    private function createFiles()
    {
        $this->createModel();

        $this->migration = new Migration($this->configSettings['pathTo']['migrations'], $this->model, $this->fileCreator);

        $tableCreated = $this->migration->createMigrations($this->lastTimeStamp);

        $this->createMigrationFilesCache = array_merge($this->createMigrationFilesCache, $this->migration->getCreatedMigrationFiles());

        $this->runMigrations();

        if(!$this->onlyMigration && $tableCreated)
        {
            $this->controllerType = $this->getControllerType();

            $this->templatePathWithControllerType = $this->configSettings['pathTo']['templates'] . $this->controllerType ."/";

            if(!$this->model->exists)
            {
                if($this->configSettings['useRepository'])
                {
                    $this->createRepository();
                    $this->createRepositoryInterface();
                    $this->putRepositoryFolderInStartFiles();
                }


                $this->createController();

                $this->createViews();

                $this->updateRoutes();

                $this->createTests();

                $this->createSeeds();
            }
        }
    }

    /**
     * Creates the model file
     */
    private function createModel()
    {
        $fileName = $this->configSettings['pathTo']['models'] . $this->nameOf("modelName") . ".php";

        if(\File::exists($fileName))
        {
            $this->updateModel($fileName);
            $this->model->exists = true;
            return;
        }

        $fileContents = "protected \$table = '". $this->model->getTableName() ."';\n";

        if(!$this->model->hasTimestamps())
            $fileContents .= "\tpublic \$timestamps = false;\n";

        if($this->model->hasSoftdeletes())
            $fileContents .= "\tprotected \$softDelete = true;\n";

        $properties = "";
        foreach ($this->model->getProperties() as $property => $type) {
            $properties .= "'$property',";
        }

        $properties = rtrim($properties, ",");

        $fileContents .= "\tprotected \$fillable = array(".$properties.");\n";

        $fileContents = $this->addRelationships($fileContents);

        $template = $this->configSettings['useRepository'] ? "model.php" : "model-no-repo.php";

        $this->makeFileFromTemplate($fileName, $this->configSettings['pathTo']['templates'].$template, $fileContents);

        $this->addModelLinksToLayoutFile();
    }

    /**
     *  Updates an existing model file
     *
     * @param $fileName
     */
    private function updateModel($fileName)
    {
        $fileContents = \File::get($fileName);

        $fileContents = trim($this->addRelationships($fileContents, false));

        $fileContents = trim($this->removeRelationships($fileContents)) . "\n}\n";

        \File::put($fileName, $fileContents);
    }

    /**
     *  Adds model links to the layout file
     */
    private function addModelLinksToLayoutFile()
    {
        $layoutFile = $this->configSettings['pathTo']['layout'];
        if(\File::exists($layoutFile))
        {
            $layout = \File::get($layoutFile);

            $layout = str_replace("<!--[linkToModels]-->", "<a href=\"{{ url('".$this->nameOf("viewFolder")."') }}\" class=\"list-group-item\">".$this->model->upper()."</a>\n<!--[linkToModels]-->", $layout);

            \File::put($layoutFile, $layout);
        }
    }

    /**
     *  Add relationships to the model
     *
     * @param $fileContents
     * @param $newModel
     * @return string
     */
    private function addRelationships($fileContents, $newModel = true)
    {
        if(!$newModel)
            $fileContents = substr($fileContents, 0, strrpos($fileContents, "}"));

        foreach ($this->model->getRelationships() as $relation)
        {
            $relatedModel = $relation->model;

            if(strpos($fileContents, $relation->getName()) !== false && !$newModel)
                continue;

            $functionContent = "\t\treturn \$this->" . $relation->getType() . "(\\" . $relatedModel->nameWithNamespace() . "::class);\n";
            $fileContents .= $this->fileCreator->createFunction($relation->getName(), $functionContent);

            $relatedModelFile = $this->configSettings['pathTo']['models'] . $relatedModel->upper() . '.php';

            if (!\File::exists($relatedModelFile))
            {
                if ($this->fromFile)
                    continue;
                else
                {
                    $editRelatedModel = $this->confirm("Model " . $relatedModel->upper() . " doesn't exist yet. Would you like to create it now [y/n]? ", true);

                    if ($editRelatedModel)
                        $this->fileCreator->createClass($relatedModelFile, "", array('name' => "\\Eloquent"));
                    else
                        continue;
                }
            }

            $content = \File::get($relatedModelFile);
            if (preg_match("/function " . $this->model->lower() . "/", $content) !== 1 && preg_match("/function " . $this->model->plural() . "/", $content) !== 1)
            {
                $index = 0;
                $reverseRelations = $relation->reverseRelations();

                if (count($reverseRelations) > 1)
                    $index = $this->command->ask($relatedModel->upper() . " (0=" . $reverseRelations[0] . " OR 1=" . $reverseRelations[1] . ") " . $this->model->upper() . "? ");

                $reverseRelationType = $reverseRelations[$index];
                $reverseRelationName = $relation->getReverseName($this->model, $reverseRelationType);

                $content = substr($content, 0, strrpos($content, "}"));
                $functionContent = "\t\treturn \$this->" . $reverseRelationType . "('" . $this->model->nameWithNamespace() . "');\n";
                $content .= $this->fileCreator->createFunction($reverseRelationName, $functionContent) . "}\n";

                \File::put($relatedModelFile, $content);
            }
        }
        return $fileContents;
    }

    /**
     *  Remove relationships from the model
     *
     * @param $fileContents
     * @return string
     */
    private function removeRelationships($fileContents)
    {
        foreach ($this->model->getRelationshipsToRemove() as $relation)
        {
            $name = $relation->getName();

            if(strpos($fileContents, $name) !== false)
            {
                $fileContents = preg_replace("/public\s+function\s+$name\s*\(.*?\).*?\{.*?\}/s", "", $fileContents);
            }
        }
        return $fileContents;
    }

    /**
     *  Get controller type, either resource or restful
     *
     * @return string
     */
    private function getControllerType()
    {
        return $this->isResource ? "resource" : "restful";
    }

    /**
     *  Gets the name from the configuration file
     *
     * @param string $type
     * @return string
     */
    private function nameOf($type)
    {
        return $this->replaceModels($this->configSettings['names'][$type]);
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
     *  Copy template files from package folder to specified user folder
     */
    private function copyTemplateFiles()
    {
        if(!\File::isDirectory($this->configSettings['pathTo']['templates'])) {
            $this->fileCreator->copyDirectory("vendor/binondord/laravel-scaffold/src/templates/", $this->configSettings['pathTo']['templates']);
        }
    }

    /**
     *  Show the examples of the syntax to be used to add models
     */
    private function showInformation()
    {
        $this->info('MyNamespace\Book title:string year:integer');
        $this->info('With relation: Book belongsTo Author title:string published:integer');
        $this->info('Multiple relations: University hasMany Course, Department name:string city:string state:string homepage:string )');
        $this->info('Or group like properties: University hasMany Department string( name city state homepage )');
    }

    /**
     *  Prompt user to run the migrations
     */
    private function runMigrations()
    {
        if(!$this->fromFile)
        {
            $editMigrations = $this->confirm('Would you like to edit your migrations file before running it [y/n]? ', true);

            if ($editMigrations)
            {
                $this->info('Remember to run "php artisan migrate" after editing your migration file');
                $this->info('And "php artisan db:seed" after editing your seed file');
            }
            else
            {
                while (true)
                {
                    try
                    {
                        $this->command->call('migrate');
                        $this->command->call('db:seed');
                        break;
                    }
                    catch (\Exception $e)
                    {
                        $this->info('Error: ' . $e->getMessage());
                        $this->command->error('This table already exists and/or you have duplicate migration files.');
                        $this->confirm('Fix the error and enter "yes" ', true);
                    }
                }
            }
        }
    }

    /**
     *  Generate the seeds file
     */
    private function createSeeds()
    {
        $faker = Factory::create();

        $databaseSeeder = $this->configSettings['pathTo']['seeds'] . 'DatabaseSeeder.php';
        $databaseSeederContents = \File::get($databaseSeeder);
        if(preg_match("/faker/", $databaseSeederContents) !== 1)
        {
            $contentBefore = substr($databaseSeederContents, 0, strpos($databaseSeederContents, "{"));
            $contentAfter = substr($databaseSeederContents, strpos($databaseSeederContents, "{")+1);

            $databaseSeederContents = $contentBefore;
            $databaseSeederContents .= "{\n\tprotected \$faker;\n\n";
            $functionContents = "\t\tif(empty(\$this->faker)) {\n";
            $functionContents .= "\t\t\t\$this->faker = Faker\\Factory::create();\n\t\t}\n\n";
            $functionContents .= "\t\treturn \$this->faker;\n";

            $databaseSeederContents .= $this->fileCreator->createFunction("getFaker", $functionContents);

            $databaseSeederContents .= $contentAfter;

            \File::put($databaseSeeder, $databaseSeederContents);
        }

        $functionContent = "\t\t\$faker = \$this->getFaker();\n\n";
        $functionContent .= "\t\tfor(\$i = 1; \$i <= 10; \$i++) {\n";

        $functionContent .= "\t\t\t\$".$this->model->lower()." = array(\n";

        foreach($this->model->getProperties() as $property => $type)
        {

            if($property == "password")
                $functionContent .= "\t\t\t\t'$property' => \\Hash::make('password'),\n";
            else
            {
                $fakerProperty = "";
                try
                {
                    $fakerProperty2 = $faker->getFormatter($property);
                    $fakerProperty = $property;
                }
                catch (\InvalidArgumentException $e) { }

                if(empty($fakerProperty))
                {
                    try
                    {
                        $fakerProperty2 = $faker->getFormatter($type);
                        $fakerProperty = $type;
                    }
                    catch (\InvalidArgumentException $e) { }
                }

                if(empty($fakerProperty))
                {
                    $fakerType = "";
                    switch($type)
                    {
                        case "integer":
                        case "biginteger":
                        case "smallinteger":
                        case "tinyinteger":
                            $fakerType = "randomDigitNotNull";
                            break;
                        case "string":
                            $fakerType = "word";
                            break;
                        case "float":
                        case "double":
                            $fakerType = "randomFloat";
                            break;
                        case "mediumtext":
                        case "longtext":
                        case "binary":
                            $fakerType = "text";
                            break;
                    }

                    $fakerType = $fakerType ? "\$faker->".$fakerType : "0";
                }
                else
                    $fakerType = "\$faker->".$fakerProperty;

                $functionContent .= "\t\t\t\t'$property' => $fakerType,\n";

            }
        }

        foreach($this->migration->getForeignKeys() as $key)
            $functionContent .= "\t\t\t\t'$key' => \$i,\n";

        $functionContent .= "\t\t\t);\n";

        $namespace = $this->namespace ? "\\" . $this->namespace . "\\" : "";

        $functionContent .= "\t\t\t". $namespace . $this->model->upper()."::create(\$".$this->model->lower().");\n";
        $functionContent .= "\t\t}\n";

        $fileContents = $this->fileCreator->createFunction("run", $functionContent);

        $fileName = $this->configSettings['pathTo']['seeds'] . $this->model->upperPlural() . "TableSeeder.php";

        $this->fileCreator->createClass($fileName, $fileContents, array('name' => 'DatabaseSeeder'), array(), array(), "class", false, false);

        $actualSeederContent = \File::get($fileName);
        $this->createdFilesCache[md5($actualSeederContent)] = $fileName;

        $tableSeederClassName = $this->model->upperPlural() . 'TableSeeder';

        $content = \File::get($databaseSeeder);

        if(preg_match("/$tableSeederClassName/", $content) !== 1)
        {
            $content = preg_replace("/(run\(\).+?)}/us", "$1\t\$this->call('{$tableSeederClassName}');\n\t}", $content);
            \File::put($databaseSeeder, $content);
        }
    }

    /**
     *  Create the repository interface
     *
     * @return array
     */
    private function createRepositoryInterface()
    {
        $dir = $this->configSettings['pathTo']['repositoryInterfaces'];
        $this->fileCreator->createDirectory($dir);
        $this->createDirCache[] = $dir;

        $baseRepository = $this->configSettings['pathTo']['repositoryInterfaces'] . $this->nameOf("baseRepositoryInterface") . ".php";

        $useBaseRepository = $this->configSettings['useBaseRepository'];

        $repoTemplate = $this->configSettings['pathTo']['templates']."repository-interface";

        if($useBaseRepository)
        {
            if(!file_exists($baseRepository))
                $this->makeFileFromTemplate($baseRepository, $this->configSettings['pathTo']['templates']."base-repository-interface.php");
            $repoTemplate .= "-with-base";
        }

        $repoTemplate .= ".php";

        $fileName = $this->configSettings['pathTo']['repositoryInterfaces'] . $this->nameOf("repositoryInterface") . ".php";

        $this->makeFileFromTemplate($fileName, $repoTemplate);
    }

    /**
     *  Create the repository
     *
     * @return array
     */
    private function createRepository()
    {
        $dir = $this->configSettings['pathTo']['repositories'];
        $this->fileCreator->createDirectory($dir);
        $this->createDirCache[] = $dir;

        $fileName = $this->configSettings['pathTo']['repositories'] . $this->nameOf("repository") . '.php';

        $this->makeFileFromTemplate($fileName, $this->configSettings['pathTo']['templates']."eloquent-repository.php");
    }

    /**
     *  Add repository folder so that it autoloads
     *
     * @return mixed
     */
    private function putRepositoryFolderInStartFiles()
    {
        $repositories = substr($this->configSettings['pathTo']['repositories'], 0, strlen($this->configSettings['pathTo']['repositories'])-1);

        $startRepo = $repositories;

        if(strpos($repositories, "app") !== false)
            $startRepo = "app_path().'".substr($repositories, strpos($repositories, "/"), strlen($repositories) - strpos($repositories, "/"))."'";

        $content = \File::get('composer.json');

        if (preg_match("/repositories/", $content) !== 1)
            $content = preg_replace("/\"app\/controllers\",/", "\"app/controllers\",\n\t\t\t\"$repositories\",", $content);

        \File::put('composer.json', $content);
    }

    /**
     *  Create controller
     *
     * @return array
     */
    private function createController()
    {
        $fileName = $this->configSettings['pathTo']['controllers'] . $this->nameOf("controller"). ".php";

        $this->makeFileFromTemplate($fileName, $this->templatePathWithControllerType."controller.php");
    }

    /**
     *  Create tests
     *
     * @return array
     */
    private function createTests()
    {
        $this->fileCreator->createDirectory($this->configSettings['pathTo']['tests']. 'controller');

        $fileName = $this->configSettings['pathTo']['tests']."controller/" . $this->nameOf("test") .".php";

        $this->makeFileFromTemplate($fileName, $this->templatePathWithControllerType."test.php");
    }

    /**
     *  Update routes file with new controller
     *
     * @return string
     */
    private function updateRoutes()
    {
        $routeFile = $this->configSettings['pathTo']['routes']."routes.php";

        $namespace = $this->namespace ? $this->namespace . "\\" : "";

        $contractsNamespace = "App\\Contracts\\Repositories";

        $fileContents = "";

        if($this->configSettings['useRepository'])
            $fileContents = "\nApp::bind('" . $contractsNamespace . $this->nameOf("repositoryInterface")."','" . $namespace . $this->nameOf("repository") ."');\n";

        $routeType = $this->isResource ? "resource" : "controller";

        $fileContents .= "Route::" . $routeType . "('" . $this->nameOf("viewFolder") . "', '" . $namespace. $this->nameOf("controller") ."');\n";

        $content = \File::get($routeFile);
        if (preg_match("/" . $this->model->lower() . "/", $content) !== 1)
            \File::append($routeFile, $fileContents);
    }

    /**
     *  Create views as specified in the configuration file
     */
    private function createViews()
    {
        $dir = $this->configSettings['pathTo']['views'] . $this->nameOf('viewFolder') . "/";
        if (!\File::isDirectory($dir)) {
            \File::makeDirectory($dir);
            $this->createDirCache[] = $dir;
        }

        $pathToViews = $this->configSettings['pathTo']['templates'].$this->controllerType."/";

        foreach($this->configSettings['views'] as $view)
        {
            $fileName = $dir . "$view.blade.php";

            $success = false;

            try
            {
                $this->makeFileFromTemplate($fileName, $pathToViews."$view.blade.php");
                $success = true;
            }
            catch(FileNotFoundException $e)
            {
                $this->command->error("Template file ".$pathToViews . $view.".blade.php does not exist! You need to create it to generate that file!");
            }

            if($success)
            {
                $transferMap = $this->configSettings['transfers']['views'];
                if(in_array($view, array_keys($transferMap)))
                {
                    $this->fileCreator->createDirectory($transferMap[$view]);
                    $ngCtrlscript = $transferMap[$view].'/'.$this->nameOf('viewFolder').'Ctrl.js';
                    $this->fileCreator->copyFile($fileName, $ngCtrlscript);
                    $key = array_search($fileName, $this->createdFilesCache);
                    if($key !== false && array_key_exists($key, $this->createdFilesCache)) {
                        \File::delete($fileName);
                        $this->createdFilesCache[$key] = $ngCtrlscript;
                    }
                }
            }
        }
    }

    /**
     *  Generate a file based off of a template
     *
     * @param $fileName
     * @param $template
     * @param string $content
     */
    public function makeFileFromTemplate($fileName, $template, $content = "")
    {
        try
        {
            $fileContents = \File::get($template);
        }
        catch(FileNotFoundException $e)
        {
            $shortTemplate = substr($template, strpos($template, $this->configSettings["pathTo"]["templates"]) + strlen($this->configSettings["pathTo"]["templates"]),strlen($template)-strlen($this->configSettings["pathTo"]["templates"]));
            $this->fileCreator->copyFile("vendor/binondord/laravel-scaffold/src/templates/".$shortTemplate, $template);
            $fileContents = \File::get($template);
        }

        $fileContents = $this->replaceNames($fileContents);
        $fileContents = $this->replaceModels($fileContents);
        $fileContents = $this->replaceProperties($fileContents);

        if($content)
            $fileContents = str_replace("[content]", $content, $fileContents);

        $namespace = $this->namespace ? "namespace ".$this->namespace. ";" : "";
        $fileContents = str_replace("[namespace]", $namespace, $fileContents);

        if(!$this->configSettings['useRepository'])
            $fileContents = str_replace($this->nameOf("repositoryInterface"), $this->nameOf("modelName"), $fileContents);

        $this->createdFilesCache[md5($fileContents)] = $fileName;
        $this->fileCreator->createFile($fileName, $fileContents);
    }

    private function finalizeView()
    {

    }

    /**
     *  Replace [model] tags in template with the model name
     *
     * @param $fileContents
     * @return mixed
     */
    private function replaceModels($fileContents)
    {
        $modelReplaces = array(
            '[model]'   =>$this->model->lower(),
            '[Model]'   =>$this->model->upper(),
            '[models]'  =>$this->model->plural(),
            '[Models]'  =>$this->model->upperPlural()
        );

        foreach($modelReplaces as $model => $name)
            $fileContents = str_replace($model, $name, $fileContents);

        return $fileContents;
    }

    /**
     *  Replace 'names' from the config file with their names
     *
     * @param $fileContents
     * @return mixed
     */
    public function replaceNames($fileContents)
    {
        foreach($this->configSettings['names'] as $name => $text)
            $fileContents = str_replace("[$name]", $text, $fileContents);

        return $fileContents;
    }

    /**
     *  Replace [property] with model's properties
     *
     * @param $fileContents
     * @return mixed
     */
    private function replaceProperties($fileContents)
    {
        $lastPos = 0;
        $needle = "[repeat]";
        $endRepeat = "[/repeat]";

        while (($lastPos = strpos($fileContents, $needle, $lastPos))!== false)
        {
            $beginning = $lastPos;
            $lastPos = $lastPos + strlen($needle);
            $endProp = strpos($fileContents, $endRepeat, $lastPos);
            $end = $endProp + strlen($endRepeat);
            $replaceThis = substr($fileContents, $beginning, $end-$beginning);
            $propertyTemplate = substr($fileContents, $lastPos, $endProp - $lastPos);
            $properties = "";

            foreach($this->model->getProperties() as $property => $type)
            {
                $temp = str_replace("[property]", $property, $propertyTemplate);
                $temp = str_replace("[Property]", ucfirst($property), $temp);
                $properties .= $temp;
            }

            $properties = trim($properties, ",");
            $fileContents = str_replace($replaceThis, $properties, $fileContents);
        }

        return $fileContents;
    }

    /*************** Start command wrappers *******************/
    /**
     *
     * @param $message
     */

    public function info($message)
    {
        $this->command->info($message);
    }

    public function confirm($message)
    {
        $this->command->confirm($message);
    }
}
