<?php namespace Jrenton\LaravelScaffold;

use Faker\Factory;

class Scaffold
{
    private $laravelClasses = array();
    private $propertiesArr = array();
    private $propertiesStr = "";
    private $model;
    private $relationship = array();
    private $namespace;
    private $isResource;
    private $fieldNames;
    private $controllerType;
    private $fromFile;
    private $fileCreator;
    private $assetDownloader;
    private $timestamps = true;
    private $softDeletes = false;
    private $lastTimeStamp = array();

    protected $configSettings;
    protected $command;

    private $validTypes = array(
        'biginteger'=>'bigInteger',
        'binary'=>'binary',
        'boolean'=>'boolean',
        'date'=>'date',
        'datetime'=>'dateTime',
        'decimal'=>'decimal',
        'double'=>'double',
        'enum'=>'enum',
        'float'=>'float',
        'integer'=>'integer',
        'longtext'=>'longText',
        'mediumtext'=>'mediumText',
        'smallinteger'=>'smallInteger',
        'tinyinteger'=>'tinyInteger',
        'string'=>'string',
        'text'=>'text',
        'time'=>'time',
        'timestamp'=>'timestamp',
        'morphs'=>'morphs',
        'bigincrements'=>'bigIncrements');

    private $templatePathWithControllerType;
    private $namespaceGlobal;
    private $columnAdded = false;
    private $tablesAdded = array();
    private $onlyMigration = false;

    public function __construct($command)
    {
        $this->configSettings = $this->getConfigSettings();
        $this->command = $command;
        $this->fileCreator = new FileCreator($command);
        $this->assetDownloader = new AssetDownloader($command, $this->configSettings, $this->fileCreator);
    }

    private function getConfigSettings()
    {
        $package = "laravel-scaffold";

        $configSettings = array();

        $configSettings['pathTo'] = \Config::get("$package::paths");

        foreach($configSettings['pathTo'] as $pathName => $path) {
            if($path[strlen($path)-1] != "/") {
                if($pathName != "layout")
                    $path .= "/";
                $configSettings['pathTo'][$pathName] = $path;
            }
        }

        $configSettings['names'] = \Config::get("$package::names");

        $configSettings['appName'] = \Config::get("$package::appName");

        $configSettings['downloads'] = \Config::get("$package::downloads");

        $configSettings['views'] = \Config::get("$package::views");

        $configSettings['useRepository'] = \Config::get("$package::repository");

        $configSettings['useBaseRepository'] = \Config::get("$package::baseRepository");

        $configSettings['modelDefinitionsFile'] = \Config::get("$package::modelDefinitionsFile");

        return $configSettings;
    }

    public function createModels()
    {
        $this->fromFile = false;
        $this->fileCreator->fromFile = false;
        $this->assetDownloader->fromFile = false;

        $this->setupLayoutFiles();

        $modelAndProperties = $this->askForModelAndFields();

        $moreTables = trim($modelAndProperties) == "q" ? false : true;

        while( $moreTables ) {

            $this->saveModelAndProperties($modelAndProperties);

            $this->isResource = $this->command->confirm('Do you want resource (y) or restful (n) controllers? ');

            $this->createFiles();

            $this->command->info("Model ".$this->model->upper(). " and all associated files created successfully!");

            $this->addToModelDefinitions($modelAndProperties);

            $modelAndProperties = $this->command->ask('Add model with fields or "q" to quit: ');

            $moreTables = trim($modelAndProperties) == "q" ? false : true;
        }
    }

    public function createLayout()
    {
        $this->assetDownloader->generateLayoutFiles();
    }

    public function createModelsFromFile($fileName)
    {
        $this->fileCreator->fromFile = true;
        $this->fromFile = true;
        $this->assetDownloader->fromFile = true;

        $this->setupLayoutFiles();

        $this->createLayout();

        $inputFile = file($fileName);

        foreach( $inputFile as $line_num => $modelAndProperties ) {
            $modelAndProperties = trim($modelAndProperties);
            if(!empty($modelAndProperties)) {
                if(preg_match("/^resource =/", $modelAndProperties)) {
                    $this->isResource = trim(substr($modelAndProperties, strpos($modelAndProperties, "=")+1));
                    continue;
                }

                if(preg_match("/^namespace =/", $modelAndProperties)) {
                    $this->namespaceGlobal = true;
                    $this->namespace = trim(substr($modelAndProperties, strpos($modelAndProperties, "=")+1));
                    $this->fileCreator->namespace = $this->namespace;
                    continue;
                }

                $this->saveModelAndProperties($modelAndProperties);

                $this->createFiles();
            }
        }
    }

    public function setupLayoutFiles()
    {
        $this->laravelClasses = $this->getLaravelClassNames();

        $this->copyTemplateFiles();
    }

    public function update()
    {
        $this->fileCreator->fromFile = true;
        $this->fromFile = true;
        $this->assetDownloader->fromFile = true;

        $this->setupLayoutFiles();

        $inputFile = file($this->configSettings['modelDefinitionsFile']);

        //die(var_dump($inputFile));

        //$differences = $this->differences($this->configSettings['modelDefinitionsFile'] , $this->getModelCacheFile());

        foreach( $inputFile as $line_num => $modelAndProperties ) {
            $modelAndProperties = trim($modelAndProperties);
            if(!empty($modelAndProperties)) {
                if(preg_match("/^resource =/", $modelAndProperties)) {
                    $this->isResource = trim(substr($modelAndProperties, strpos($modelAndProperties, "=")+1));
                    continue;
                }

                if(preg_match("/^namespace =/", $modelAndProperties)) {
                    $this->namespaceGlobal = true;
                    $this->namespace = trim(substr($modelAndProperties, strpos($modelAndProperties, "=")+1));
                    $this->fileCreator->namespace = $this->namespace;
                    continue;
                }

                $this->saveModelAndProperties($modelAndProperties);

                $this->createFiles();
            }
        }

        copy($this->configSettings['modelDefinitionsFile'], $this->getModelCacheFile());
    }

    private function saveModelAndProperties($modelAndProperties)
    {
        $modelNameCollision = false;

        $this->resetModels();

        do {
            if($modelNameCollision) {
                $modelAndProperties = $this->command->ask($this->model->upper() ." is already in the global namespace. Please namespace your class or provide a different name: ");
                if(!$this->namespaceGlobal) {
                    $this->fileCreator->namespace = "";
                    $this->namespace = "";
                }
            }

            $values = preg_split('/\s+/', $modelAndProperties);

            $modelWithNamespace = array_shift($values);

            if(!$this->namespaceGlobal) {
                $this->namespace = $this->getNamespace($modelWithNamespace);
                $this->fileCreator->namespace = $this->namespace;
            }

            $this->model = $this->getModel($modelWithNamespace);

            $modelNameCollision = in_array($this->model->lower(), $this->laravelClasses);

        } while($modelNameCollision);

        if( !empty($values) ) {
            $this->getModelsWithRelationships($values);

            $this->fieldNames = $values;

            $this->propertiesArr = $this->getPropertiesFromInput($this->fieldNames);
            $this->propertiesStr .= implode(",", array_keys($this->propertiesArr));
        }
    }

    private function addToModelDefinitions($modelAndProperties)
    {
        \File::append($this->getModelCacheFile(), $modelAndProperties."\n");
    }

    private function getModelCacheFile()
    {
        $file = $this->configSettings['modelDefinitionsFile'];
        $modelFilename = substr(strrchr($this->configSettings['modelDefinitionsFile'], "/"), 1);
        $ext = substr($modelFilename, strrpos($modelFilename, "."), strlen($modelFilename)-strrpos($modelFilename, "."));
        $name = substr($modelFilename, 0, strrpos($modelFilename, "."));
        $modelDefinitionsFile = substr($file, 0, strrpos($file, "/")+1) . "." . $name ."-cache". $ext;
        return $modelDefinitionsFile;
    }

    private function createFiles()
    {
        $this->createModel();

        $this->createMigrations();

        $this->runMigrations();

        if(!$this->onlyMigration && $this->columnAdded) {

            $this->controllerType = $this->getControllerType();

            $this->templatePathWithControllerType = $this->configSettings['pathTo']['templates'] . $this->controllerType ."/";

            if(!$this->model->exists) {
                if($this->configSettings['useRepository']) {
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

    private function getControllerType()
    {
        return $this->isResource ? "resource" : "restful";
    }

    private function nameOf($type)
    {
        return $this->replaceModels($this->configSettings['names'][$type]);
    }

    private function askForModelAndFields()
    {
        $modelAndFields = $this->command->ask('Add model with its relations and fields or type "q" to quit (type info for examples) ');

        if($modelAndFields == "info") {
            $this->showInformation();

            $modelAndFields = $this->command->ask('Now your turn: ');
        }

        return $modelAndFields;
    }

    private function copyTemplateFiles()
    {
        if(!\File::isDirectory($this->configSettings['pathTo']['templates'])) {
            $this->fileCreator->copyDirectory("vendor/jrenton/laravel-scaffold/src/Jrenton/LaravelScaffold/templates/", $this->configSettings['pathTo']['templates']);
        }
    }

    private function showInformation()
    {
        $this->command->info('MyNamespace\Book title:string year:integer');
        $this->command->info('With relation: Book belongsTo Author title:string published:integer');
        $this->command->info('Multiple relations: University hasMany Course, Department name:string city:string state:string homepage:string )');
        $this->command->info('Or group like properties: University hasMany Department string( name city state homepage )');
    }

    private function getLaravelClassNames()
    {
        $classNames = array();

        $aliases = \Config::get('app.aliases');
        foreach ($aliases as $alias => $facade) {
            array_push($classNames, strtolower($alias));
        }

        return $classNames;
    }

    private function resetModels()
    {
        $this->relationship = array();
        if(!$this->namespaceGlobal)
            $this->namespace = "";
        $this->propertiesArr = array();
        $this->propertiesStr = "";
        $this->model = null;
        $this->fillForeignKeys = array();
        $this->timestamps = true;
        $this->softDeletes = false;
        $this->columnAdded = false;
        $this->onlyMigration = false;
    }

    private function getModelsWithRelationships(&$values)
    {
        if($this->nextArgumentIsRelation($values[0])) {
            $relationship = $values[0];
            $relatedTable = trim($values[1], ',');

            $namespace = $this->namespace;

            if(strpos($relatedTable, "\\"))
                $model = substr(strrchr($relatedTable, "\\"), 1);
            else
                $model = $relatedTable;

            if(!$this->namespaceGlobal) {
                $namespace = $this->getNamespace($relatedTable);
            }

            $i = 2;

            $this->relationship = array();
            array_push($this->relationship, new Relation($relationship, new Model($model, $namespace)));

            while($i < count($values) && $this->nextArgumentIsRelation($values[$i])) {
                if(strpos($values[$i], ",") === false) {
                    $next = $i + 1;
                    if($this->isLastRelation($values, $next)) {
                        $relationship = $values[$i];
                        $relatedTable = trim($values[$next], ',');
                        $i++;
                        unset($values[$next]);
                    } else {
                        $relatedTable = $values[$i];
                    }
                } else {
                    $relatedTable = trim($values[$i], ',');
                }

                $namespace = $this->namespace;

                if(strpos($relatedTable, "\\"))
                    $model = substr(strrchr($relatedTable, "\\"), 1);
                else
                    $model = $relatedTable;

                if(!$this->namespaceGlobal) {
                    $namespace = $this->getNamespace($relatedTable);
                }

                array_push($this->relationship, new Relation($relationship, new Model($model, $namespace)));
                unset($values[$i]);
                $i++;
            }

            unset($values[0]);
            unset($values[1]);
        }
    }

    private function getPropertiesFromInput($fieldNames)
    {
        $bundled = false;
        $fieldName = "";
        $type = "";
        $properties = array();

        foreach($fieldNames as $field)
        {
            $skip = false;
            $pos = strrpos($field, ":");
            if ($pos !== false && !$bundled)
            {
                $type = substr($field, $pos+1);
                $fieldName = substr($field, 0, $pos);
            } else if(strpos($field, '(') !== false) {
                $type = substr($field, 0, strpos($field, '('));
                $bundled = true;
                $skip = true;
            } else if($bundled) {
                if($pos !== false && strpos($field, ")") === false) {
                    $fieldName = substr($field, $pos+1);
                    $num = substr($field, 0, $pos);
                } else if(strpos($field, ")") !== false){
                    $skip = true;
                    $bundled = false;
                } else {
                    $fieldName = $field;
                }
            } else if (strpos($field, "-") !== false) {
                $option = substr($field, strpos($field, "-")+1, strlen($field) - (strpos($field, "-")+1));
                if($option == "nt") {
                    $this->timestamps = false;
                    $skip = true;
                }
                if($option == "sd") {
                    $this->softDeletes = true;
                    $skip = true;
                }
                if($option == "pivot") {
                    $this->onlyMigration = true;
                    $skip = true;
                }
            }

            $fieldName = trim($fieldName, ",");

            $type = strtolower($type);

            if(!$skip && !empty($fieldName)) {
                if(!array_key_exists($type, $this->validTypes)) {
                    $this->command->error($type. " is not a valid property type! ");
                    $this->resetModels();
                    if($this->fromFile)
                        exit;
                    else
                        $this->createModels();
                }

                $properties[$fieldName] = $type;
            }
        }

        return $properties;
    }

    private function getNamespace($modelWithNamespace)
    {
        return substr($modelWithNamespace, 0, strrpos($modelWithNamespace, "\\"));
    }

    private function getModel($modelWithNamespace)
    {
        if(strpos($modelWithNamespace, "\\"))
            $model = substr(strrchr($modelWithNamespace, "\\"), 1);
        else
            $model = $modelWithNamespace;

        return new Model($model, $this->namespace);
    }

    private function isLastRelation($values, $next)
    {
        return ($next < count($values) && $this->nextArgumentIsRelation($values[$next]));
    }

    private function nextArgumentIsRelation($value)
    {
        return strpos($value, ":") === false && strpos($value, "(") === false;
    }

    private function createMigrations()
    {
        $tableName = $this->model->getTableName();

        $tableCreated = $this->isTableCreated($this->model->getTableName());

        if($tableCreated) {
            $migrationName = "edit_" . $tableName . "_table";
        } else {
            $migrationName = "create_" . $tableName . "_table";
        }

        if ($handle = opendir($this->configSettings['pathTo']['migrations'])) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry[0] != ".") {
                    if(strpos($entry, $migrationName) !== false) {
                        $index = strpos($entry, "_");
                        $index = strpos($entry, "_", $index+1);
                        $index = strpos($entry, "_", $index+1);
                        $index = strpos($entry, "_", $index+1);

                        $migrationFilename = substr($entry, $index+1, strrpos($entry, ".")-($index+1));
                        $halfMigration = substr($migrationFilename, 0, strrpos($migrationFilename, "_"));
                        $lastSegment = substr(strrchr($migrationFilename, "_"), 1);
                        if(is_numeric($lastSegment)) {
                            $migrationName = $halfMigration . "_" . ($lastSegment+1);
                        } else {
                            $migrationName = $migrationFilename . "_2";
                        }
                    }
                }
            }
            closedir($handle);
        }

        $functionContents = $this->migrationUp($tableCreated);

        if($this->columnAdded) {
            if(!$this->lastTimeStamp) {
                $this->lastTimeStamp['day'] = date("Y_m_d");
                $this->lastTimeStamp['second'] = date("His");
            } else {
                $this->lastTimeStamp['second']++;
            }

            $migrationFile = $this->configSettings['pathTo']['migrations']
                . $this->lastTimeStamp['day'] . "_" . $this->lastTimeStamp['second']
                . "_" . $migrationName . ".php";

            $classNameArr = explode("_", $migrationName);
            $className = "";
            foreach($classNameArr as $class) {
                $className .= ucfirst($class);
            }

            $fileContents = $this->fileCreator->createFunction("up", $functionContents);
            if(!$tableCreated) {
                $functionContents = "\t\tSchema::dropIfExists('".$tableName."');\n";
            } else {
                $functionContents = "\t\tSchema::table('".$tableName."', function(\$table) {\n";
                $functionContents .="\t\t\t\$table->dropColumn(";
                foreach ($this->columnsAdded as $column) {
                    $functionContents .= "'$column',";
                }
                $functionContents = rtrim($functionContents, ",");
                $functionContents .= ");\n\t\t});\n";
            }

            $fileContents .= $this->fileCreator->createFunction("down", $functionContents);

            $this->fileCreator->createMigrationClass($migrationFile, $fileContents, $className);
        }
    }

    /**
     * @param $content
     * @return string
     */
    protected function addSoftDeletes()
    {
        $content = "";

        if ($this->softDeletes) {
            if (!$this->tableHasColumn($this->model->getTableName(), "deleted_at")) {
                $this->columnAdded = true;
                array_push($this->columnsAdded, "deleted_at");
                $content = "\t\t\t" . $this->setColumn('softDeletes', null) . ";\n";
            }
        }
        return $content;
    }

    private $columnsAdded = array();

    protected function migrationUp($tableCreated = false)
    {
        $type = $tableCreated ? "table" : "create";

        $content = "\t\tSchema::$type('".$this->model->getTableName()."', function(Blueprint \$table) {\n";

        if(!$tableCreated)
            $content .= "\t\t\t" . $this->setColumn('increments', 'id') . ";\n";

        $content .= $this->addColumns($this->model->getTableName());

        $content .= $this->addTimestamps();

        $content .= $this->addSoftDeletes();

        $content .= $this->addForeignKeys();
        $content .= "\t\t});\n";

        foreach($this->relationship as $relation) {
            if($relation->getType() == "belongsToMany") {

                $tableOne = $this->model->tableNameLower();
                $tableTwo = $relation->model->tableNameLower();

                $tableName = $this->getPivotTableName($tableOne, $tableTwo);

                if(!$this->isTableCreated($tableName)) {
                    $this->columnAdded = true;
                    array_push($this->tablesAdded, $tableName);
                    $content .= "\t\tSchema::create('".$tableName."', function(Blueprint \$table) {\n";
                    $content .= "\t\t\t\$table->integer('".$tableOne."_id')->unsigned();\n";
                    $content .= "\t\t\t\$table->integer('".$tableTwo."_id')->unsigned();\n";
                    $content .= "\t\t});\n";
                }
            } else if($relation->getType() == "hasOne" || $relation->getType() == "hasMany") {
                if($this->tableHasColumn($relation->model->getTableName() ,$this->model->lower()."_id")) {
                    if(!$tableCreated) {
                        $content .= "\t\tSchema::table('".$relation->model->getTableName()."', function(Blueprint \$table) {\n";
                        $content .= "\t\t\t\$table->foreign('". $this->model->tableNameLower()."_id')->references('id')->on('".$this->model->getTableName()."');\n";
                        $content .= "\t\t});\n";
                    }
                } else if($this->isTableCreated($relation->model->getTableName()) && !$this->tableHasColumn($relation->model->getTableName(), $this->model->tableNameLower()."_id")) {
                    $this->columnAdded = true;
                    $column = $this->model->tableNameLower()."_id";
                    array_push($this->columnsAdded, $column);
                    $content .= "\t\tSchema::table('".$relation->model->getTableName()."', function(Blueprint \$table) {\n";
                    $content .= "\t\t\t\$table->integer('". $column."')->unsigned();\n";
                    $content .= "\t\t\t\$table->foreign('". $column."')->references('id')->on('".$this->model->getTableName()."');\n";
                    $content .= "\t\t});\n";
                }
            }
        }
        return $content;
    }

    private function tableHasColumn($tableName, $columnName)
    {
        if(\Schema::hasColumn($tableName, $columnName)) {
            return true;
        }

        $search = "/Schema::(table|create).*'$tableName',.*\(.*\).*{.*'$columnName'.*}\);/s";

        return $this->searchInMigrationFiles($search);
    }

    public function isColumnOfType($table, $column, $type)
    {
        $isColumnOfType = $this->isColumnOfTypeInMigrationFiles($table, $column, $type);;

        if($isColumnOfType)
            return true;

        $isColumnOfType = $this->isColumnOfTypeInDb($table, $column, $type);

        return $isColumnOfType;
    }

    public function isColumnOfTypeInDb($table, $column, $type)
    {
        switch (\DB::connection()->getConfig('driver')) {
            case 'pgsql':
                $query = "SELECT column_name FROM information_schema.columns WHERE table_name = '".$table."'";
                break;

            case 'mysql':
                $query = "SELECT data_type FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = N'".$table."' AND COLUMN_NAME = N'".$column."' AND TABLE_SCHEMA = N'".\DB::connection()->getConfig('database')."'";
                break;

            case 'sqlsrv':
                $parts = explode('.', $table);
                $num = (count($parts) - 1);
                $table = $parts[$num];
                $query = "SELECT column_name FROM ".\DB::connection()->getConfig('database').".INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = N'".$table."'";
                break;

            default:
                $error = 'Database driver not supported: '.\DB::connection()->getConfig('driver');
                throw new \Exception($error);
                break;
        }

        $column_type = \DB::select($query);

        if(!empty($column_type))
            $column_type = $column_type[0]->data_type;

        $newType = $type;

        if(array_key_exists($newType, $this->dataTypes))
            $newType = $this->dataTypes[$newType];

        if($column_type == $newType)
            return true;

        return false;
    }

    private function isColumnOfTypeInMigrationFiles($table, $column, $type)
    {
        $search = "/Schema::(table|create).*'$table',.*\(.*\).*{.*$type\('$column'.*\).*}\);/s";
        $anotherColumn = "/Schema::(table|create).*'$table',.*\(.*\).*{.*\('$column'.*\).*}\);/s";

        $found = false;

        if ($handle = opendir($this->configSettings['pathTo']['migrations'])) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry[0] != ".") {
                    $fileName = $this->configSettings['pathTo']['migrations'].$entry;

                    $contents = \File::get($fileName);

                    if(!$found) {
                        $matched = preg_match($search, $contents);

                        if($matched !== false && $matched != 0)
                            $found = true;
                    }

                    if($found) {
                        $matched = preg_match($anotherColumn, $contents);

                        if($matched !== false && $matched != 0)
                            $found = false;
                    }
                }
            }
            closedir($handle);
        }

        return $found;
    }

    private function searchInMigrationFiles($search)
    {
        $found = false;

        if ($handle = opendir($this->configSettings['pathTo']['migrations'])) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry[0] != ".") {
                    $fileName = $this->configSettings['pathTo']['migrations'].$entry;

                    $contents = \File::get($fileName);
                    $matched = preg_match($search, $contents);
                    if($matched !== false && $matched != 0) {
                        $found = true;
                        break;
                    }
                }
            }
            closedir($handle);
        }

        return $found;
    }

    private $dataTypes = array(
        'string' =>'varchar',
        'morphs' => 'integer',
        'binary' => 'blob',
        'biginteger'=> 'bigint',
        'smallinteger'=> 'smallint',
        'tinyinteger'=> 'tinyint'
    );

    private function getPivotTableName($tableOne, $tableTwo)
    {
        if($tableOne[0] > $tableTwo[0])
            $tableName = $tableTwo ."_".$tableOne;
        else
            $tableName = $tableOne ."_".$tableTwo;

        return $tableName;
    }

    private function isTableCreated($tableName)
    {
        $found = false;
        if(\Schema::hasTable($tableName)) {
            return true;
        }

        if ($handle = opendir($this->configSettings['pathTo']['migrations'])) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry != "." && $entry != "..") {
                    $fileName = $this->configSettings['pathTo']['migrations'].$entry;

                    $contents = \File::get($fileName);
                    if(strpos($contents, "Schema::create('$tableName'") !== false) {
                        $found = true;
                        break;
                    }
                }
            }
            closedir($handle);
        }

        return $found;
    }

    private $fillForeignKeys = array();

    private function addForeignKeys()
    {
        $fields = "";
        foreach($this->relationship as $relation) {
            if($relation->getType() == "belongsTo") {
                $foreignKey = $relation->model->tableNameLower() . "_id";
                if(!$this->tableHasColumn($this->model->getTableName(), $foreignKey)) {
                    $this->columnAdded = true;
                    array_push($this->columnsAdded, $foreignKey);
                    $fields .= "\t\t\t" .$this->setColumn('integer', $foreignKey);
                    $fields .= $this->addColumnOption('unsigned') . ";\n";
                    if($this->isTableCreated($relation->model->getTableName())) {
                        $fields .= "\t\t\t\$table->foreign('". $foreignKey."')->references('id')->on('".$relation->model->getTableName()."');\n";
                        array_push($this->fillForeignKeys, $foreignKey);
                    }
                }
            }
        }
        return $fields;
    }

    protected function increment()
    {
        return "\$table->increments('id')";
    }

    protected function setColumn($type, $field = '')
    {
        return empty($field)
            ? "\$table->$type()"
            : "\$table->$type('$field')";
    }

    protected function addColumnOption($option)
    {
        return "->{$option}()";
    }

    protected function addColumns($tableName = "")
    {
        $content = '';

        foreach( $this->propertiesArr as $field => $type ) {

            if(!empty($tableName) && !$this->tableHasColumn($tableName, $field)) {
                $this->columnAdded = true;
                $rule = "\t\t\t";

                // Primary key check
                if ( $field === 'id' and $type === 'integer' )
                    $rule .= $this->increment();
                else {
                    $rule .= $this->setColumn($this->validTypes[$type], $field);

                    if ( !empty($setting) )
                        $rule .= $this->addColumnOption($setting);
                }

                array_push($this->columnsAdded, $field);

                $content .= $rule . ";\n";
            }
        }

        return $content;
    }

    private function runMigrations()
    {
        if(!$this->fromFile) {
            $editMigrations = $this->command->confirm('Would you like to edit your migrations file before running it [y/n]? ', true);

            if ($editMigrations) {
                $this->command->info('Remember to run "php artisan migrate" after editing your migration file');
                $this->command->info('And "php artisan db:seed" after editing your seed file');
            } else {
                while (true) {
                    try {
                        $this->command->call('migrate');
                        $this->command->call('db:seed');
                        break;
                    } catch (\Exception $e) {
                        $this->command->info('Error: ' . $e->getMessage());
                        $this->command->error('This table already exists and/or you have duplicate migration files.');
                        $this->command->confirm('Fix the error and enter "yes" ', true);
                    }
                }
            }
        }
    }

    private function createModel()
    {
        $fileName = $this->configSettings['pathTo']['models'] . $this->nameOf("modelName") . ".php";

        if(\File::exists($fileName)) {
            $this->updateModel($fileName);
            $this->model->exists = true;
            return;
        }

        $fileContents = "protected \$table = '". $this->model->getTableName() ."';\n";

        if(!$this->timestamps)
            $fileContents .= "\tpublic \$timestamps = false;\n";

        if($this->softDeletes)
            $fileContents .= "\tprotected \$softDelete = true;\n";

        $properties = "";
        foreach ($this->propertiesArr as $property => $type) {
            $properties .= "'$property',";
        }

        $properties = rtrim($properties, ",");

        $fileContents .= "\tprotected \$fillable = array(".$properties.");\n";

        $fileContents = $this->addRelationships($fileContents);

        $template = $this->configSettings['useRepository'] ? "model.txt" : "model-no-repo.txt";

        $this->makeFileFromTemplate($fileName, $this->configSettings['pathTo']['templates'].$template, $fileContents);

        $this->updateLayoutFile();
    }

    private function updateModel($fileName)
    {
        $fileContents = \File::get($fileName);

        $fileContents = $this->addRelationships($fileContents, false) . "\n}";

        \File::put($fileName, $fileContents);
    }

    private function updateLayoutFile()
    {
        $layoutFile = $this->configSettings['pathTo']['layout'];
        if(\File::exists($layoutFile)) {
            $layout = \File::get($layoutFile);

            $layout = str_replace("<!--[linkToModels]-->", "<a href=\"{{ url('".$this->nameOf("viewFolder")."') }}\" class=\"list-group-item\">".$this->model->upper()."</a>\n<!--[linkToModels]-->", $layout);

            \File::put($layoutFile, $layout);
        }
    }

    private function createSeeds()
    {
        $faker = Factory::create();

        $databaseSeeder = $this->configSettings['pathTo']['seeds'] . 'DatabaseSeeder.php';
        $databaseSeederContents = \File::get($databaseSeeder);
        if(preg_match("/faker/", $databaseSeederContents) !== 1) {
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

        foreach($this->propertiesArr as $property => $type) {

            if($property == "password") {
                $functionContent .= "\t\t\t\t'$property' => \\Hash::make('password'),\n";
            } else {
                $fakerProperty = "";
                try {

                    $fakerProperty2 = $faker->getFormatter($property);
                    $fakerProperty = $property;
                } catch (\InvalidArgumentException $e) { }

                if(empty($fakerProperty)) {
                    try {
                        $fakerProperty2 = $faker->getFormatter($type);
                        $fakerProperty = $type;
                    } catch (\InvalidArgumentException $e) { }
                }

                if(empty($fakerProperty)) {
                    $fakerType = "";
                    switch($type) {
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
                } else {
                    $fakerType = "\$faker->".$fakerProperty;
                }

                $functionContent .= "\t\t\t\t'$property' => $fakerType,\n";

            }
        }

        foreach($this->fillForeignKeys as $key) {
            $functionContent .= "\t\t\t\t'$key' => \$i,\n";
        }

        $functionContent .= "\t\t\t);\n";

        $namespace = $this->namespace ? "\\" . $this->namespace . "\\" : "";

        $functionContent .= "\t\t\t". $namespace . $this->model->upper()."::create(\$".$this->model->lower().");\n";
        $functionContent .= "\t\t}\n";

        $fileContents = $this->fileCreator->createFunction("run", $functionContent);

        $fileName = $this->configSettings['pathTo']['seeds'] . $this->model->upperPlural() . "TableSeeder.php";

        $this->fileCreator->createClass($fileName, $fileContents, array('name' => 'DatabaseSeeder'), array(), array(), "class", false, false);

        $tableSeederClassName = $this->model->upperPlural() . 'TableSeeder';

        $content = \File::get($databaseSeeder);
        if(preg_match("/$tableSeederClassName/", $content) !== 1) {
            $content = preg_replace("/(run\(\).+?)}/us", "$1\t\$this->call('{$tableSeederClassName}');\n\t}", $content);
            \File::put($databaseSeeder, $content);
        }
    }
    /**
     * @return array
     */
    private function createRepositoryInterface()
    {
        $this->fileCreator->createDirectory($this->configSettings['pathTo']['repositoryInterfaces']);

        $baseRepository = $this->configSettings['pathTo']['repositoryInterfaces'] . $this->nameOf("baseRepositoryInterface") . ".php";

        $useBaseRepository = $this->configSettings['useBaseRepository'];

        $repoTemplate = $this->configSettings['pathTo']['templates']."repository-interface";

        if($useBaseRepository) {
            if(!file_exists($baseRepository))
                $this->makeFileFromTemplate($baseRepository, $this->configSettings['pathTo']['templates']."base-repository-interface.txt");
            $repoTemplate .= "-with-base";
        }

        $repoTemplate .= ".txt";

        $fileName = $this->configSettings['pathTo']['repositoryInterfaces'] . $this->nameOf("repositoryInterface") . ".php";

        $this->makeFileFromTemplate($fileName, $repoTemplate);
    }

    /**
     * @return array
     */
    private function createRepository()
    {
        $this->fileCreator->createDirectory($this->configSettings['pathTo']['repositories']);

        $fileName = $this->configSettings['pathTo']['repositories'] . $this->nameOf("repository") . '.php';

        $this->makeFileFromTemplate($fileName, $this->configSettings['pathTo']['templates']."eloquent-repository.txt");
    }

    /**
     * @return mixed
     */
    private function putRepositoryFolderInStartFiles()
    {
        $repositories = substr($this->configSettings['pathTo']['repositories'], 0, strlen($this->configSettings['pathTo']['repositories'])-1);

        $startRepo = $repositories;

        if(strpos($repositories, "app") !== false)
            $startRepo = "app_path().'".substr($repositories, strpos($repositories, "/"), strlen($repositories) - strpos($repositories, "/"))."'";

        $content = \File::get('app/start/global.php');
        if (preg_match("/repositories/", $content) !== 1)
            $content = preg_replace("/app_path\(\).'\/controllers',/", "app_path().'/controllers',\n\t$startRepo,", $content);

        \File::put('app/start/global.php', $content);

        $content = \File::get('composer.json');
        if (preg_match("/repositories/", $content) !== 1)
            $content = preg_replace("/\"app\/controllers\",/", "\"app/controllers\",\n\t\t\t\"$repositories\",", $content);

        \File::put('composer.json', $content);
    }

    /**
     * @return array
     */
    private function createController()
    {
        $fileName = $this->configSettings['pathTo']['controllers'] . $this->nameOf("controller"). ".php";

        $this->makeFileFromTemplate($fileName, $this->templatePathWithControllerType."controller.txt");
    }

    /**
     * @return array
     */
    private function createTests()
    {
        $this->fileCreator->createDirectory($this->configSettings['pathTo']['tests']. 'controller');

        $fileName = $this->configSettings['pathTo']['tests']."controller/" . $this->nameOf("test") .".php";

        $this->makeFileFromTemplate($fileName, $this->templatePathWithControllerType."test.txt");
    }

    /**
     * @return string
     */
    private function updateRoutes()
    {
        $routeFile = $this->configSettings['pathTo']['routes']."routes.php";

        $namespace = $this->namespace ? $this->namespace . "\\" : "";

        $fileContents = "";

        if($this->configSettings['useRepository'])
            $fileContents = "\nApp::bind('" . $namespace . $this->nameOf("repositoryInterface")."','" . $namespace . $this->nameOf("repository") ."');\n";

        $routeType = $this->isResource ? "resource" : "controller";

        $fileContents .= "Route::" . $routeType . "('" . $this->nameOf("viewFolder") . "', '" . $namespace. $this->nameOf("controller") ."');\n";

        $content = \File::get($routeFile);
        if (preg_match("/" . $this->model->lower() . "/", $content) !== 1) {
            \File::append($routeFile, $fileContents);
        }
    }

    private function createViews()
    {
        $dir = $this->configSettings['pathTo']['views'] . $this->nameOf('viewFolder') . "/";
        if (!\File::isDirectory($dir))
            \File::makeDirectory($dir);

        $pathToViews = $this->configSettings['pathTo']['templates'].$this->controllerType."/";

        foreach($this->configSettings['views'] as $view) {
            $fileName = $dir . "$view.blade.php";

            try{
                $this->makeFileFromTemplate($fileName, $pathToViews."$view.txt");
            } catch(\Illuminate\Filesystem\FileNotFoundException $e) {
                $this->command->error("Template file ".$pathToViews . $view.".txt does not exist! You need to create it to generate that file!");
            }
        }
    }

    public function makeFileFromTemplate($fileName, $template, $content = "")
    {
        try {
            $fileContents = \File::get($template);
        } catch(\Illuminate\Filesystem\FileNotFoundException $e) {
            $shortTemplate = substr($template, strpos($template, $this->configSettings["pathTo"]["templates"]) + strlen($this->configSettings["pathTo"]["templates"]),strlen($template)-strlen($this->configSettings["pathTo"]["templates"]));
            $this->fileCreator->copyFile("vendor/jrenton/laravel-scaffold/src/Jrenton/LaravelScaffold/templates/".$shortTemplate, $template);
            $fileContents = \File::get($template);
        }
        $fileContents = $this->replaceNames($fileContents);
        $fileContents = $this->replaceModels($fileContents);
        $fileContents = $this->replaceProperties($fileContents);
        if($content) {
            $fileContents = str_replace("[content]", $content, $fileContents);
        }

        $namespace = $this->namespace ? "namespace ".$this->namespace. ";" : "";
        $fileContents = str_replace("[namespace]", $namespace, $fileContents);

        if(!$this->configSettings['useRepository']) {
            $fileContents = str_replace($this->nameOf("repositoryInterface"), $this->nameOf("modelName"), $fileContents);
        }

        $this->fileCreator->createFile($fileName, $fileContents);
    }

    private function replaceModels($fileContents)
    {
        $modelReplaces = array('[model]'=>$this->model->lower(), '[Model]'=>$this->model->upper(), '[models]'=>$this->model->plural(), '[Models]'=>$this->model->upperPlural());
        foreach($modelReplaces as $model => $name) {
            $fileContents = str_replace($model, $name, $fileContents);
        }

        return $fileContents;
    }

    public function replaceNames($fileContents)
    {
        foreach($this->configSettings['names'] as $name => $text) {
            $fileContents = str_replace("[$name]", $text, $fileContents);
        }

        return $fileContents;
    }

    private function replaceProperties($fileContents)
    {
        $lastPos = 0;
        $needle = "[repeat]";
        $endRepeat = "[/repeat]";

        while (($lastPos = strpos($fileContents, $needle, $lastPos))!== false) {
            $beginning = $lastPos;
            $lastPos = $lastPos + strlen($needle);
            $endProp = strpos($fileContents, $endRepeat, $lastPos);
            $end = $endProp + strlen($endRepeat);
            $replaceThis = substr($fileContents, $beginning, $end-$beginning);
            $propertyTemplate = substr($fileContents, $lastPos, $endProp - $lastPos);
            $properties = "";
            foreach($this->propertiesArr as $property => $type) {
                $temp = str_replace("[property]", $property, $propertyTemplate);
                $temp = str_replace("[Property]", ucfirst($property), $temp);
                $properties .= $temp;
            }
            $properties = trim($properties, ",");
            $fileContents = str_replace($replaceThis, $properties, $fileContents);
        }

        return $fileContents;
    }

    /**
     * @param $fileContents
     * @return string
     */
    private function addRelationships($fileContents, $newModel = true)
    {
        if(!$newModel) {
            $fileContents = substr($fileContents, 0, strrpos($fileContents, "}"));
        }

        foreach ($this->relationship as $relation) {
            $relatedModel = $relation->model;

            if(strpos($fileContents, $relation->getName()) !== false && !$newModel)
                continue;

            $functionContent = "\t\treturn \$this->" . $relation->getType() . "('" . $relatedModel->nameWithNamespace() . "');\n";
            $fileContents .= $this->fileCreator->createFunction($relation->getName(), $functionContent);

            $relatedModelFile = $this->configSettings['pathTo']['models'] . $relatedModel->upper() . '.php';

            if (!\File::exists($relatedModelFile)) {
                if ($this->fromFile)
                    continue;
                else {
                    $editRelatedModel = $this->command->confirm("Model " . $relatedModel->upper() . " doesn't exist yet. Would you like to create it now [y/n]? ", true);
                    if ($editRelatedModel)
                        $this->fileCreator->createClass($relatedModelFile, "", array('name' => "\\Eloquent"));
                    else
                        continue;
                }
            }

            $content = \File::get($relatedModelFile);
            if (preg_match("/function " . $this->model->lower() . "/", $content) !== 1 && preg_match("/function " . $this->model->plural() . "/", $content) !== 1) {
                $index = 0;
                $reverseRelations = $relation->reverseRelations();

                if (count($reverseRelations) > 1) {
                    $index = $this->command->ask($relatedModel->upper() . " (0=" . $reverseRelations[0] . " OR 1=" . $reverseRelations[1] . ") " . $this->model->upper() . "? ");
                }

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
     * @param $content
     * @return string
     */
    private function addTimestamps()
    {
        $content = "";
        if ($this->timestamps) {
            if (!$this->tableHasColumn($this->model->getTableName(), "created_at")) {
                $this->columnAdded = true;
                $content .= "\t\t\t" . $this->setColumn("timestamp", "created_at") . ";\n";
            }
            if (!$this->tableHasColumn($this->model->getTableName(), "updated_at")) {
                $this->columnAdded = true;
                $content .= "\t\t\t" . $this->setColumn("timestamp", "updated_at") . ";\n";
            }
        }
        return $content;
    }

    private function differences($fileOne, $fileTwo)
    {
        $inputFile = file($fileOne);

        $fileOneContents = array();

        foreach( $inputFile as $line_num => $modelAndProperties ) {
            $lineContents = preg_split('/\s+/', $modelAndProperties, -1, PREG_SPLIT_NO_EMPTY);
            array_push($fileOneContents, $lineContents);
        }

        $inputFile = file($fileTwo);

        $fileTwoContents = array();

        foreach( $inputFile as $line_num => $modelAndProperties ) {
            $lineContents = preg_split('/\s+/', $modelAndProperties, -1, PREG_SPLIT_NO_EMPTY);
            array_push($fileTwoContents, $lineContents);
        }

        $max = count($fileOneContents);
        if(count($fileTwoContents) > $max)
            $max = count($fileTwoContents);

        $changes = array();

        for($i = 0; $i < $max; $i++) {
            if(array_key_exists($i, $fileOneContents)) {
                if(array_key_exists($i, $fileTwoContents)) {
                    $diff = array_diff($fileOneContents[$i], $fileTwoContents[$i]);
                    //$diff2 = array_diff($fileOneContents[$i], $fileTwoContents[$i]);
                    //$totalDiff = array_merge($diff, $diff2);

                    if(count($diff) > 0) {
                        array_unshift($diff, $fileTwoContents[$i][0]);
                        $modelStr = implode(" ", $diff);
                        array_push($changes, $modelStr);
                    }
                } else {
                    array_push($changes, implode(" ", $fileOneContents[$i]));
                }
            } else {
                array_push($changes, implode(" ", $fileTwoContents[$i]));
            }
        }

        return $changes;
    }
}
