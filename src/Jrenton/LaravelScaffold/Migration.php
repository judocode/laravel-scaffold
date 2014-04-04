<?php namespace Jrenton\LaravelScaffold;

class Migration
{
    /**
     * @var array
     */
    private $fillForeignKeys = array();

    /**
     * @var bool
     */
    private $columnsChanged = false;

    /**
     * @var array
     */
    private $columnsAdded = array();

    /**
     * @var Model
     */
    private $model;

    /**
     * @var string
     */
    private $migrationsPath;

    /**
     * @var array
     */
    private $tablesAdded = array();

    /**
     * @var FileCreator
     */
    private $fileCreator;

    /**
     * @var string
     */
    private $currentFileContents;

    /**
     * @param string $path
     * @param Model $model
     * @param FileCreator $fileCreator
     */
    public function __construct($path, Model $model, FileCreator $fileCreator)
    {
        $this->migrationsPath = $path;
        $this->model = $model;
        $this->fileCreator = $fileCreator;
    }

    /**
     * Create migrations file for the attached model
     */
    public function createMigrations(&$timestamp)
    {
        $tableName = $this->model->getTableName();

        $isTableCreated = $this->isTableCreated($this->model->getTableName());

        $functionContents = $this->migrationUp($isTableCreated);

        if($this->columnsChanged)
        {
            $migrationName = $isTableCreated ? "edit" : "create";
            $migrationName .= "_" . $tableName . "_table";

            $migrationFile = $this->getMigrationFileName($migrationName, $timestamp);

            $fileContents = $this->fileCreator->createFunction("up", $functionContents);

            $functionContents = $this->migrationDownContents($isTableCreated, $tableName);

            $fileContents .= $this->fileCreator->createFunction("down", $functionContents);

            $classNameArr = explode("_", $migrationName);
            $className = "";

            foreach($classNameArr as $class)
            {
                $className .= ucfirst($class);
            }

            $this->fileCreator->createMigrationClass($migrationFile, $fileContents, $className);
        }
    }

    /**
     * @return array
     */
    public function getForeignKeys()
    {
        return $this->fillForeignKeys;
    }

    public function dropTable(&$timestamp)
    {
        $migrationName = "edit_" . $this->model->getTableName() . "_table";

        $migrationFile = $this->getMigrationFileName($migrationName, $timestamp);

        $functionContents = "\t\tSchema::dropIfExists('".$this->model->getTableName()."');\n";

        $this->currentFileContents = $this->fileCreator->createFunction("up", $functionContents);

        $functionContents = $this->migrationUp();

        $this->currentFileContents .= $this->fileCreator->createFunction("down", $functionContents);

        $classNameArr = explode("_", $migrationName);
        $className = "";

        foreach($classNameArr as $class)
        {
            $className .= ucfirst($class);
        }

        $this->fileCreator->createMigrationClass($migrationFile, $this->currentFileContents, $className);
    }

    /**
     * @return string
     */
    protected function addSoftDeletes()
    {
        $content = "";

        if ($this->model->hasSoftDeletes())
        {
            if (!$this->tableHasColumn("deleted_at"))
            {
                $this->columnsChanged = true;
                array_push($this->columnsAdded, "deleted_at");
                $content = "\t\t\t" . $this->setColumn('softDeletes', null) . ";\n";
            }
        }

        return $content;
    }

    /**
     * @param bool $tableCreated
     * @return string
     */
    protected function migrationUp($tableCreated = false)
    {
        $type = $tableCreated ? "table" : "create";

        $content = "\t\tSchema::$type('".$this->model->getTableName()."', function(Blueprint \$table) {\n";

        if(!$tableCreated)
            $content .= "\t\t\t" . $this->setColumn('increments', 'id') . ";\n";

        $content .= $this->addColumns();

        $content .= $this->addTimestamps();

        $content .= $this->addSoftDeletes();

        $content .= $this->addForeignKeys();

        $content .= $this->dropColumns();

        $content .= $this->dropRelationships();

        $content .= "\t\t});\n";

        if($this->columnsChanged)
            $content .= $this->dropPivotTables();
        else
            $content = $this->dropPivotTables();

        foreach($this->model->getRelationships() as $relation) {
            if($relation->getType() == "belongsToMany") {

                $tableOne = $this->model->tableNameLower();
                $tableTwo = $relation->model->tableNameLower();

                $tableName = $this->getPivotTableName($tableOne, $tableTwo);

                if(!$this->isTableCreated($tableName)) {
                    $this->columnsChanged = true;
                    array_push($this->tablesAdded, $tableName);
                    $content .= "\t\tSchema::create('".$tableName."', function(Blueprint \$table) {\n";
                    $content .= "\t\t\t\$table->integer('".$tableOne."_id')->unsigned();\n";
                    $content .= "\t\t\t\$table->integer('".$tableTwo."_id')->unsigned();\n";
                    $content .= "\t\t});\n";
                }
            } else if($relation->getType() == "hasOne" || $relation->getType() == "hasMany") {
                if($this->tableHasColumn($this->model->lower()."_id", $relation->model->getTableName())) {
                    if(!$tableCreated) {
                        $content .= "\t\tSchema::table('".$relation->model->getTableName()."', function(Blueprint \$table) {\n";
                        $content .= "\t\t\t\$table->foreign('". $this->model->tableNameLower()."_id')->references('id')->on('".$this->model->getTableName()."');\n";
                        $content .= "\t\t});\n";
                    }
                } else if($this->isTableCreated($relation->model->getTableName()) && !$this->tableHasColumn($this->model->tableNameLower()."_id", $relation->model->getTableName())) {
                    $this->columnsChanged = true;
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

    /**
     * @param string $tableName
     * @param string $columnName
     * @return bool
     */
    private function tableHasColumn($columnName, $tableName = "")
    {
        if(empty($tableName))
            $tableName = $this->model->getTableName();

        $search = "/public\s+function\s+up.*Schema::(table|create)\s*\(\s*'$tableName'.*\-\>\s*(?!dropColumn).*\(\s*'$columnName'.*public\s+function\s+down/s";
        $notDown = "/public\s+function\s+up.*Schema::(table|create)\s*\(\s*'$tableName'.*\-\>\s*dropColumn\s*\(\s*'$columnName'.*public\s+function\s+down/s";

        $found = $this->searchInMigrationFiles($search, $notDown);

        if($found)
        {
            $matched = preg_match("/public\s+function\s+up.*Schema::drop\w*\s*\(\s*'$tableName'.*/s", $this->currentFileContents);
            if($matched > 0)
                $found = false;
        }

        return $found;
    }

    /**
     * @param string $table
     * @param string $column
     * @param string $type
     * @return bool
     */
    public function isColumnOfType($table, $column, $type)
    {
        $isColumnOfType = $this->isColumnOfTypeInMigrationFiles($table, $column, $type);;

        if($isColumnOfType)
            return true;

        $isColumnOfType = $this->isColumnOfTypeInDb($table, $column, $type);

        return $isColumnOfType;
    }

    /**
     * @param string $table
     * @param string $column
     * @param string $type
     * @return bool
     * @throws \Exception
     */
    public function isColumnOfTypeInDb($table, $column, $type)
    {
        switch (\DB::connection()->getConfig('driver'))
        {
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

    /**
     * @param $table
     * @param $column
     * @param $type
     * @return bool
     */
    private function isColumnOfTypeInMigrationFiles($table, $column, $type)
    {
        $search = "/public\s+function\s+up.*Schema::(table|create).*'$table'.*$type\('$column'.*\).*public\s+function\s+down/s";
        $anotherColumn = "/public\s+function\s+up.*Schema::(table|create).*'$table'.*\('$column'.*\).*public\s+function\s+down/s";

        return $this->searchInMigrationFiles($search, $anotherColumn);
    }

    /**
     * @param $search
     * @param string $fallback
     * @return bool
     */
    private function searchInMigrationFiles($search, $fallback = "")
    {
        $found = false;

        if ($handle = opendir($this->migrationsPath))
        {
            while (false !== ($entry = readdir($handle)))
            {
                if ($entry[0] != ".")
                {
                    $fileName = $this->migrationsPath.$entry;

                    $contents = \File::get($fileName);

                    if(!$found)
                    {
                        $matched = preg_match($search, $contents);

                        if($matched !== false && $matched != 0)
                        {
                            $found = true;
                        }
                    }

                    if(!empty($fallback) && $found)
                    {
                        $matched = preg_match($fallback, $contents);

                        if($matched !== false && $matched != 0)
                        {
                            $found = false;
                        }
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

    /**
     * @param string $tableOne
     * @param string $tableTwo
     * @return string
     */
    private function getPivotTableName($tableOne, $tableTwo)
    {
        if($tableOne[0] > $tableTwo[0])
            $tableName = $tableTwo ."_".$tableOne;
        else
            $tableName = $tableOne ."_".$tableTwo;

        return $tableName;
    }

    /**
     * @param string $tableName
     * @return bool
     */
    private function isTableCreated($tableName)
    {
        //$search = "/public\s+function\s+up.*Schema::(table|create)\s*\(\s*'$tableName'.*\-\>\s*(?!dropColumn).*\(\s*'$columnName'.*public\s+function\s+down/s";
        //$notDown = "/public\s+function\s+up.*Schema::(table|create)\s*\(\s*'$tableName'.*\-\>\s*dropColumn\s*\(\s*'$columnName'.*public\s+function\s+down/s";

        $search = "/public\s+function\s+up.*Schema\s*::\s*create\s*\(\s*'$tableName'.*public\s+function\s+down/s";
        $drop = "/public\s+function\s+up.*Schema\s*::\s*drop\w*\s*\(\s*'$tableName'.*public\s+function\s+down/s";

        $matched = preg_match($drop, $this->currentFileContents);

        if($matched > 0)
            return false;

        return $this->searchInMigrationFiles($search, $drop);

        //if(!$found && \Schema::hasTable($tableName))
        //    return true;
    }

    /**
     * @return string
     */
    private function addForeignKeys()
    {
        $fields = "";

        foreach($this->model->getRelationships() as $relation)
        {
            if($relation->isBelongsTo())
            {
                $foreignKey = $relation->getForeignKeyName();

                if(!$this->tableHasColumn($foreignKey))
                {
                    $this->columnsChanged = true;

                    array_push($this->columnsAdded, $foreignKey);

                    $fields .= "\t\t\t" .$this->setColumn('integer', $foreignKey);

                    $fields .= $this->addColumnOption('unsigned') . ";\n";

                    if($this->isTableCreated($relation->model->getTableName()))
                    {
                        $fields .= "\t\t\t\$table->foreign('". $foreignKey."')->references('id')->on('".$relation->model->getTableName()."');\n";

                        array_push($this->fillForeignKeys, $foreignKey);
                    }
                }
            }
        }
        return $fields;
    }

    /**
     * @return string
     */
    protected function increment()
    {
        return "\$table->increments('id')";
    }

    /**
     * @param string $type
     * @param string $field
     * @return string
     */
    protected function setColumn($type, $field = '')
    {
        return empty($field)
            ? "\$table->$type()"
            : "\$table->$type('$field')";
    }

    /**
     * @param string $option
     * @return string
     */
    protected function addColumnOption($option)
    {
        return "->{$option}()";
    }

    /**
     * @return string
     */
    protected function addColumns()
    {
        $content = '';

        foreach( $this->model->getProperties() as $field => $type ) {

            if(!$this->tableHasColumn($field)) {
                $this->columnsChanged = true;
                $rule = "\t\t\t";

                // Primary key check
                if ( $field === 'id' and $type === 'integer' )
                    $rule .= $this->increment();
                else {
                    $rule .= $this->setColumn($this->model->validTypes[$type], $field);

                    if ( !empty($setting) )
                        $rule .= $this->addColumnOption($setting);
                }

                array_push($this->columnsAdded, $field);

                $content .= $rule . ";\n";
            }
        }

        return $content;
    }

    /**
     * @return string
     */
    private function addTimestamps()
    {
        $content = "";
        if ($this->model->hasTimestamps())
        {
            if (!$this->tableHasColumn("created_at"))
            {
                $this->columnsChanged = true;
                $content .= "\t\t\t" . $this->setColumn("timestamp", "created_at") . ";\n";
            }

            if (!$this->tableHasColumn("updated_at"))
            {
                $this->columnsChanged = true;
                $content .= "\t\t\t" . $this->setColumn("timestamp", "updated_at") . ";\n";
            }
        }
        return $content;
    }

    /**
     * @return string
     */
    private function dropColumns()
    {
        $content = "";
        foreach ($this->model->getPropertiesToRemove() as $property => $type)
        {
            $this->columnsChanged = true;
            $content .= "\t\t\t\$table->dropColumn('".$property."');\n";
        }
        return $content;
    }

    /**
     * @return string
     */
    private function dropPivotTables()
    {
        $content = "";

        foreach ($this->model->getRelationshipsToRemove() as $relation)
        {
            if($relation->getType() == "belongsToMany")
            {
                $this->columnsChanged = true;
                $content .= "\t\tSchema::dropIfExists('".$relation->getPivotTableName($this->model)."');\n";
            }
        }

        return $content;
    }

    /**
     * @return string
     */
    private function dropRelationships()
    {
        $content = "";

        foreach ($this->model->getRelationshipsToRemove() as $relation)
        {
            if($relation->getType() != "belongsToMany")
            {
                $this->columnsChanged = true;
                $content .= "\t\t\t\$table->dropColumn('".$relation->getForeignKeyName()."');\n";
            }
        }
        return $content;
    }

    /**
     * @param string $tableName
     * @param array $properties
     * @return string
     */
    private function addPropertiesToTable($tableName, $properties)
    {
        $functionContents = "\t\tSchema::table('" . $tableName . "', function(Blueprint \$table) {\n";

        foreach ($properties as $property => $type)
        {
            $functionContents .= "\t\t\t\$table->$type('$property');\n";
        }

        $functionContents .= "\t\t});\n";
        return $functionContents;
    }

    /**
     * @param string $tableName
     * @return string
     */
    private function removePropertiesFromTable($tableName)
    {
        $functionContents = "\t\tSchema::table('" . $tableName . "', function(Blueprint \$table) {\n";

        $functionContents .= "\t\t\t\$table->dropColumn(";

        foreach ($this->columnsAdded as $column)
        {
            $functionContents .= "'$column',";
        }

        $functionContents = rtrim($functionContents, ",");
        $functionContents .= ");\n";
        $functionContents .= "\t\t});\n";
        return $functionContents;
    }

    /**
     * @param string $migrationName
     * @param $timestamp
     * @return string $migrationFileName
     */
    private function getMigrationFileName($migrationName, &$timestamp)
    {
        if ($handle = opendir($this->migrationsPath))
        {
            while (false !== ($entry = readdir($handle)))
            {
                if ($entry[0] != ".")
                {
                    if(strpos($entry, $migrationName) !== false)
                    {
                        $index = strpos($entry, "_");
                        $index = strpos($entry, "_", $index+1);
                        $index = strpos($entry, "_", $index+1);
                        $index = strpos($entry, "_", $index+1);

                        $migrationFilename = substr($entry, $index+1, strrpos($entry, ".")-($index+1));
                        $halfMigration = substr($migrationFilename, 0, strrpos($migrationFilename, "_"));
                        $lastSegment = substr(strrchr($migrationFilename, "_"), 1);

                        if(is_numeric($lastSegment))
                        {
                            $migrationName = $halfMigration . "_" . ($lastSegment+1);
                        }
                        else
                        {
                            $migrationName = $migrationFilename . "_2";
                        }
                    }
                }
            }
            closedir($handle);
        }

        if (!$timestamp) {
            $timestamp['day'] = date("Y_m_d");
            $timestamp['second'] = date("His");
        } else {
            $newTime = $timestamp['second'] + 1;
            $timestamp['second'] = sprintf('%06d', $newTime);
        }

        $migrationFileName = $this->migrationsPath
            . $timestamp['day'] . "_" . $timestamp['second']
            . "_" . $migrationName . ".php";

        return $migrationFileName;
    }

    /**
     * @param Relation[] $removedRelationships
     * @param string $tableName
     * @return string
     */
    private function rollbackForRemovedProperties($removedRelationships, $tableName)
    {
        $functionContents = "";
        foreach ($removedRelationships as $relation) {
            if ($relation->getType() == "belongsToMany") {
                $functionContents .= "\t\tSchema::create('" . $relation->getPivotTableName($this->model) . "', function(Blueprint \$table) {\n";
                $functionContents .= "\t\t\t\$table->integer('" . $relation->getForeignKeyName() . "')->unsigned();\n";
                $functionContents .= "\t\t\t\$table->integer('" . $this->model->lower() . "_id')->unsigned();\n";
                $functionContents .= "\t\t\t\$table->foreign('" . $relation->getForeignKeyName() . "')->references('id')->on('" . $relation->getRelatedModelTableName() . "');\n";
                $functionContents .= "\t\t\t\$table->foreign('" . $this->model->lower() . "_id')->references('id')->on('" . $this->model->getTableName() . "');\n";
                $functionContents .= "\t\t});\n";
            } else {
                $functionContents .= "\t\tSchema::table('" . $tableName . "', function(Blueprint \$table) {\n";
                $functionContents .= "\t\t\t\$table->integer('" . $relation->getForeignKeyName() . "')->unsigned();\n";
                $functionContents .= "\t\t\t\$table->foreign('" . $relation->getForeignKeyName() . "')->references('id')->on('" . $relation->getRelatedModelTableName() . "');\n";
                $functionContents .= "\t\t});\n";
            }
        }
        return $functionContents;
    }

    /**
     * @param bool $tableCreated
     * @param string $tableName
     * @return string
     */
    private function migrationDownContents($tableCreated, $tableName)
    {
        $removedProperties = $this->model->getPropertiesToRemove();

        $functionContents = "";

        if (!$tableCreated)
        {
            $functionContents = "\t\tSchema::dropIfExists('" . $tableName . "');\n";
        }
        else
        {
            if (!empty($this->columnsAdded))
            {
                $functionContents = $this->removePropertiesFromTable($tableName);
            }
            else if (!empty($removedProperties))
            {
                $functionContents = $this->addPropertiesToTable($tableName, $removedProperties);
            }

            $removedRelationships = $this->model->getRelationshipsToRemove();

            if (!empty($removedRelationships))
            {
                $functionContents = $this->rollbackForRemovedProperties($removedRelationships, $tableName);
            }
        }

        return $functionContents;
    }
}
