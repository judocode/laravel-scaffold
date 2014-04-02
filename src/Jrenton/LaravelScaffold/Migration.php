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
    private $columnAdded = false;

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
     * @param string $path
     * @param Model $model
     * @param FileCreator $fileCreator
     * @internal param $lastTimeStamp
     */
    public function __construct($path, Model $model, FileCreator $fileCreator)
    {
        $this->migrationsPath = $path;
        $this->model = $model;
        $this->fileCreator = $fileCreator;
    }

    public function createMigrations(&$lastTimestamp)
    {
        $tableName = $this->model->getTableName();

        $tableCreated = $this->isTableCreated($this->model->getTableName());

        if($tableCreated) {
            $migrationName = "edit_" . $tableName . "_table";
        } else {
            $migrationName = "create_" . $tableName . "_table";
        }

        if ($handle = opendir($this->migrationsPath)) {
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
            if(!$lastTimestamp) {
                $lastTimestamp['day'] = date("Y_m_d");
                $lastTimestamp['second'] = date("His");
            } else {
                $newTime = $lastTimestamp['second'] + 1;
                $lastTimestamp['second'] = sprintf('%06d', $newTime);
            }

            $migrationFile = $this->migrationsPath
                . $lastTimestamp['day'] . "_" . $lastTimestamp['second']
                . "_" . $migrationName . ".php";

            $classNameArr = explode("_", $migrationName);
            $className = "";
            foreach($classNameArr as $class) {
                $className .= ucfirst($class);
            }
            $removedProperties = $this->model->getPropertiesToRemove();

            $fileContents = $this->fileCreator->createFunction("up", $functionContents);
            if(!$tableCreated) {
                $functionContents = "\t\tSchema::dropIfExists('".$tableName."');\n";
            } else {
                $functionContents = "\t\tSchema::table('".$tableName."', function(Blueprint \$table) {\n";

                if(!empty($this->columnsAdded)) {
                    $functionContents .="\t\t\t\$table->dropColumn(";
                    foreach ($this->columnsAdded as $column) {
                        $functionContents .= "'$column',";
                    }
                    $functionContents = rtrim($functionContents, ",");
                    $functionContents .= ");\n\t\t});\n";
                } else if (!empty($removedProperties)) {
                    foreach ($removedProperties as $property => $type) {
                        $functionContents .= "\t\t\t\$table->$type('$property');\n\t\t});\n";
                    }
                }

            }

            $fileContents .= $this->fileCreator->createFunction("down", $functionContents);

            $this->fileCreator->createMigrationClass($migrationFile, $fileContents, $className);
        }
    }

    public function getForeignKeys()
    {
        return $this->fillForeignKeys;
    }

    /**
     * @return string
     */
    protected function addSoftDeletes()
    {
        $content = "";

        if ($this->model->hasSoftDeletes()) {
            if (!$this->tableHasColumn($this->model->getTableName(), "deleted_at")) {
                $this->columnAdded = true;
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

        $content .= $this->addColumns($this->model->getTableName());

        $content .= $this->addTimestamps();

        $content .= $this->addSoftDeletes();

        $content .= $this->addForeignKeys();

        $content .= $this->dropColumns();

        $content .= "\t\t});\n";

        foreach($this->model->getRelationships() as $relation) {
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

    /**
     * @param $tableName
     * @param $columnName
     * @return bool
     */
    private function tableHasColumn($tableName, $columnName)
    {
        if(\Schema::hasColumn($tableName, $columnName)) {
            return true;
        }

        $search = "/Schema::(table|create).*'$tableName',.*\(.*\).*{.*'$columnName'.*}\);/s";

        return $this->searchInMigrationFiles($search);
    }

    /**
     * @param $table
     * @param $column
     * @param $type
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
     * @param $table
     * @param $column
     * @param $type
     * @return bool
     * @throws \Exception
     */
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

    /**
     * @param $table
     * @param $column
     * @param $type
     * @return bool
     */
    private function isColumnOfTypeInMigrationFiles($table, $column, $type)
    {
        $search = "/Schema::(table|create).*'$table',.*\(.*\).*{.*$type\('$column'.*\).*}\);/s";
        $anotherColumn = "/Schema::(table|create).*'$table',.*\(.*\).*{.*\('$column'.*\).*}\);/s";

        $found = false;

        if ($handle = opendir($this->migrationsPath)) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry[0] != ".") {
                    $fileName = $this->migrationsPath.$entry;

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

    /**
     * @param $search
     * @return bool
     */
    private function searchInMigrationFiles($search)
    {
        $found = false;

        if ($handle = opendir($this->migrationsPath)) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry[0] != ".") {
                    $fileName = $this->migrationsPath.$entry;

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

    /**
     * @param $tableOne
     * @param $tableTwo
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
     * @param $tableName
     * @return bool
     */
    private function isTableCreated($tableName)
    {
        $found = false;
        if(\Schema::hasTable($tableName)) {
            return true;
        }

        if ($handle = opendir($this->migrationsPath)) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry != "." && $entry != "..") {
                    $fileName = $this->migrationsPath.$entry;

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

    /**
     * @return string
     */
    private function addForeignKeys()
    {
        $fields = "";
        foreach($this->model->getRelationships() as $relation) {
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

    /**
     * @return string
     */
    protected function increment()
    {
        return "\$table->increments('id')";
    }

    /**
     * @param $type
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
     * @param $option
     * @return string
     */
    protected function addColumnOption($option)
    {
        return "->{$option}()";
    }

    /**
     * @param string $tableName
     * @return string
     */
    protected function addColumns($tableName = "")
    {
        $content = '';

        foreach( $this->model->getProperties() as $field => $type ) {

            if(!empty($tableName) && !$this->tableHasColumn($tableName, $field)) {
                $this->columnAdded = true;
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
        if ($this->model->hasTimestamps()) {
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

    private function dropColumns()
    {
        $content = "";
        foreach ($this->model->getPropertiesToRemove() as $property => $type) {
            $this->columnAdded = true;
            $content .= "\t\t\t\$table->dropColumn('".$property."');\n";
        }
        return $content;
    }
}
