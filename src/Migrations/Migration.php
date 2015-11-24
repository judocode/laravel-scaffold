<?php namespace Binondord\LaravelScaffold\Migrations;

/**
 * From Jrenton/scaffold
 * Class Migration
 * @package Binondord\LaravelScaffold\Migrations
 */

class Migration
{
    /**
     *  Foreign keys for the migration
     *
     * @var array
     */
    private $foreignKeys = array();

    /**
     *  Flag to determine if columns have changed
     *
     * @var bool
     */
    private $columnsChanged = false;

    /**
     *  Columns that have been added to the migration
     *
     * @var array
     */
    private $columnsAdded = array();

    /**
     *  Model associated with migration
     *
     * @var Model
     */
    private $model;

    /**
     *  Path to the migrations
     *
     * @var string
     */
    private $migrationsPath;

    /**
     *  Tables that have been added in the migration
     *
     * @var array
     */
    private $tablesAdded = array();

    /**
     *  File creator
     *
     * @var FileCreator
     */
    private $fileCreator;

    /**
     *  Content of the current file
     *
     * @var string
     */
    private $currentFileContents;

    /**
     * @var array
     */
    private $createdMigrationFiles = array();

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

            $actualContent = \File::get($migrationFile);
            $this->createdMigrationFiles[md5($actualContent)] = $migrationFile;
        }

        return !$isTableCreated;
    }

    /**
     *  Return all migration files created ready for caching and later feed to scaffold:reset
     *
     * @return array
     */
    public function getCreatedMigrationFiles()
    {
        return $this->createdMigrationFiles;
    }

    /**
     *  Return the foreign keys
     *
     * @return array
     */
    public function getForeignKeys()
    {
        return $this->foreignKeys;
    }

    /**
     *  Migration to drop a table
     *
     * @param $timestamp
     */
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
     *  Add soft deletes to the migration
     *
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
     *  The up portion of the migration
     *
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
     *  Check to see if a table has a column
     *
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
     *  Check the type of the column
     *
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
     *  Check the type of a column in the database
     *
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
     *  Check the type of column in the migration files
     *
     * @param string $table
     * @param string $column
     * @param string $type
     * @return bool
     */
    private function isColumnOfTypeInMigrationFiles($table, $column, $type)
    {
        $search = "/public\s+function\s+up.*Schema::(table|create).*'$table'.*$type\('$column'.*\).*public\s+function\s+down/s";
        $anotherColumn = "/public\s+function\s+up.*Schema::(table|create).*'$table'.*\('$column'.*\).*public\s+function\s+down/s";

        return $this->searchInMigrationFiles($search, $anotherColumn);
    }

    /**
     *  Search for specified regex in the migration files.
     *  Reverse is the opposite of the search (equivalent of the down)
     *
     * @param string $search
     * @param string $reverse
     * @return bool
     */
    private function searchInMigrationFiles($search, $reverse = "")
    {
        $found = false;

        // First it checks for $search, and then check if the
        //  $reverse follows it (essentially checking if the
        //  original search is undone)
        if ($handle = opendir($this->migrationsPath))
        {
            while (false !== ($entry = readdir($handle)))
            {
                if ($entry[0] != ".")
                {
                    $fileName = $this->migrationsPath.$entry;

                    $contents = \File::get($fileName);

                    // If $search isn't found, search for it
                    if(!$found)
                    {
                        $matched = preg_match($search, $contents);

                        if($matched !== false && $matched != 0)
                        {
                            $found = true;
                        }
                    }

                    // If $search was found, check to see if it's been undone
                    if(!empty($reverse) && $found)
                    {
                        $matched = preg_match($reverse, $contents);

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
     *  Get the pivot table name for the two specified tables
     *
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
     *  Check to see if a table has been created
     *
     * @param string $tableName
     * @return bool
     */
    private function isTableCreated($tableName)
    {
        $search = "/public\s+function\s+up.*Schema\s*::\s*create\s*\(\s*'$tableName'.*public\s+function\s+down/s";
        $reverse = "/public\s+function\s+up.*Schema\s*::\s*drop\w*\s*\(\s*'$tableName'.*public\s+function\s+down/s";

        $matched = preg_match($reverse, $this->currentFileContents);

        if($matched > 0)
            return false;

        return $this->searchInMigrationFiles($search, $reverse);
    }

    /**
     *  Add the foreign keys to the migration file
     *
     * @return string
     */
    private function addForeignKeys()
    {
        $fields = "";

        // BelongsTo relations need to be added to the current migration
        //  in the format of model_id
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

                        array_push($this->foreignKeys, $foreignKey);
                    }
                }
            }
        }
        return $fields;
    }

    /**
     *  Adds the increments (id) column to the migration
     *
     * @return string
     */
    protected function increment()
    {
        return "\$table->increments('id')";
    }

    /**
     *  Set a column with specified type
     *
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
     *  Add option to column
     *
     * @param string $option
     * @return string
     */
    protected function addColumnOption($option)
    {
        return "->{$option}()";
    }

    /**
     *  Add all of the model properties as columns
     *
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
     *  Add timestamps to the migration
     *
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
     *  Remove columns that were removed from the models definition file
     *
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
     *  Drop the pivot tables for belongsToMany relationships that were removed
     *
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
     *  Drop foreign key columns
     *
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
     *  Add specified properties to the table
     *
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
     *  Remove the added columns from the table
     *
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
     *  Generate the migration filename based on the timestamp
     *
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
     *  For relationships that have been removed, this is the reverse to add them
     *
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
     *  Content to be added for the down migration
     *
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