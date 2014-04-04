<?php namespace Jrenton\LaravelScaffold;

use Illuminate\Console\Command;

class Model extends BaseModel
{
    /**
     * @Illuminate\Console\Command
     */
    private $command;

    /**
     * @var string
     */
    private $propertiesStr = "";

    /**
     * @var array
     */
    private $propertiesToRemove = array();

    /**
     * @var array
     */
    private $propertiesToChange = array();

    /**
     * @var string
     */
    private $inputProperties;

    /**
     * @var string
     */
    private $namespaceGlobal;

    /**
     * @var array
     */
    private $oldModelFile;

    /**
     * @var bool
     */
    public $exists = false;

    /**
     * @var array
     */
    private $relationshipsToRemove = array();

    /**
     * @param Command $command
     * @param array $oldModelFile
     */
    public function __construct(Command $command, $oldModelFile)
    {
        $this->command = $command;
        $this->oldModelFile = $oldModelFile;
    }

    /**
     * @param $modelAndProperties
     */
    public function generateModel($modelAndProperties)
    {
        $modelNameCollision = false;

        if($modelNameCollision)
            $modelAndProperties = $this->command->ask($this->upper() ." is already in the global namespace. Please namespace your class or provide a different name: ");

        $this->inputProperties = preg_split('/\s+/', $modelAndProperties);

        $modelWithNamespace = array_shift($this->inputProperties);

        if(!$this->namespaceGlobal)
            $this->namespace = $this->getNamespaceFromInput($modelWithNamespace);

        $this->getModel($modelWithNamespace);
    }

    /**
     * @return bool
     */
    public function generateProperties()
    {
        if( !empty($this->inputProperties) )
        {
            $this->getModelsWithRelationships($this->inputProperties);

            $this->propertiesArr = $this->getPropertiesFromInput($this->inputProperties);

            if(array_key_exists($this->getTableName(), $this->oldModelFile))
            {
                if(array_key_exists("properties", $this->oldModelFile[$this->getTableName()]))
                {
                    foreach ($this->oldModelFile[$this->getTableName()]["properties"] as $property => $type)
                    {
                        if(!array_key_exists($property, $this->propertiesArr))
                        {
                            $this->propertiesToRemove[$property] = $type;
                        }
                        else if($this->oldModelFile[$this->getTableName()]["properties"][$property] != $this->propertiesArr[$property])
                        {
                            $this->propertiesToChange[$property] = $this->propertiesArr[$property];
                        }
                    }
                }
            }

            if($this->propertiesArr === false)
                return false;

            $this->propertiesStr .= implode(",", array_keys($this->propertiesArr));
        }

        return true;
    }

    /**
     * @return array
     */
    public function getPropertiesToRemove()
    {
        return $this->propertiesToRemove;
    }

    /**
     * @return Relation[]
     */
    public function getRelationshipsToRemove()
    {
        return $this->relationshipsToRemove;
    }

    /**
     * @param $values
     */
    private function getModelsWithRelationships(&$values)
    {
        if($this->nextArgumentIsRelation($values[0]))
        {
            $relationship = $values[0];
            $relatedTable = trim($values[1], ',');

            $namespace = $this->namespace;

            if(strpos($relatedTable, "\\"))
                $model = substr(strrchr($relatedTable, "\\"), 1);
            else
                $model = $relatedTable;

            if(!$this->namespaceGlobal)
            {
                $namespace = $this->getNamespace($relatedTable);
            }

            $i = 2;

            $this->relationship = array();

            array_push($this->relationship, new Relation($relationship, new BaseModel($model, $namespace)));

            while($i < count($values) && $this->nextArgumentIsRelation($values[$i]))
            {
                if(strpos($values[$i], ",") === false)
                {
                    $next = $i + 1;
                    if($this->isLastRelation($values, $next))
                    {
                        $relationship = $values[$i];
                        $relatedTable = trim($values[$next], ',');
                        $i++;
                        unset($values[$next]);
                    }
                    else
                    {
                        $relatedTable = $values[$i];
                    }
                }
                else
                {
                    $relatedTable = trim($values[$i], ',');
                }

                $namespace = $this->namespace;

                if(strpos($relatedTable, "\\"))
                    $model = substr(strrchr($relatedTable, "\\"), 1);
                else
                    $model = $relatedTable;

                if(!$this->namespaceGlobal)
                {
                    $namespace = $this->getNamespace($relatedTable);
                }

                array_push($this->relationship, new Relation($relationship, new BaseModel($model, $namespace)));
                unset($values[$i]);
                $i++;
            }

            unset($values[0]);
            unset($values[1]);
        }

        if(array_key_exists($this->getTableName(), $this->oldModelFile))
        {
            if(array_key_exists("relationships", $this->oldModelFile[$this->getTableName()]))
            {
                foreach ($this->oldModelFile[$this->getTableName()]["relationships"] as $oldRelation)
                {
                    $found = false;
                    foreach ($this->relationship as $relation)
                    {
                        if (isset($relation->model->tableName) && $relation->model->tableName == $oldRelation->model->tableName)
                        {
                            $found = true;
                            break;
                        }
                    }

                    if(!$found)
                    {
                        array_push($this->relationshipsToRemove, $oldRelation);
                    }
                }
            }
        }
    }

    /**
     * @param $modelWithNamespace
     * @return string
     */
    private function getNamespaceFromInput($modelWithNamespace)
    {
        return substr($modelWithNamespace, 0, strrpos($modelWithNamespace, "\\"));
    }

    /**
     * @param $modelWithNamespace
     */
    private function getModel($modelWithNamespace)
    {
        if(strpos($modelWithNamespace, "\\"))
            $model = substr(strrchr($modelWithNamespace, "\\"), 1);
        else
            $model = $modelWithNamespace;

        $this->generateModelName($model, $this->namespace);
    }

    /**
     * @param $values
     * @param $next
     * @return bool
     */
    private function isLastRelation($values, $next)
    {
        return ($next < count($values) && $this->nextArgumentIsRelation($values[$next]));
    }

    /**
     * @param $value
     * @return bool
     */
    private function nextArgumentIsRelation($value)
    {
        return strpos($value, ":") === false && strpos($value, "(") === false;
    }

    /**
     * @param $fieldNames
     * @return array|bool
     */
    private function getPropertiesFromInput($fieldNames)
    {
        $bundled = false;
        $fieldName = "";
        $type = "";
        $properties = array();

        foreach($fieldNames as $field)
        {
            $skip = false;
            $colonLocation = strrpos($field, ":");

            if ($colonLocation !== false && !$bundled)
            {
                $type = substr($field, $colonLocation+1);
                $fieldName = substr($field, 0, $colonLocation);
            }
            else if(strpos($field, '(') !== false)
            {
                $type = substr($field, 0, strpos($field, '('));
                $bundled = true;
                $skip = true;
            }
            else if($bundled)
            {
                if($colonLocation !== false && strpos($field, ")") === false)
                {
                    $fieldName = substr($field, $colonLocation+1);
                    $num = substr($field, 0, $colonLocation);
                }
                else if(strpos($field, ")") !== false)
                {
                    $skip = true;
                    $bundled = false;
                }
                else
                {
                    $fieldName = $field;
                }
            }
            else if (strpos($field, "-") !== false)
            {
                $option = substr($field, strpos($field, "-")+1, strlen($field) - (strpos($field, "-")+1));

                if($option == "nt")
                {
                    $this->timestamps = false;
                    $skip = true;
                }
                else if($option == "sd")
                {
                    $this->softDeletes = true;
                    $skip = true;
                }
                else if($option == "pivot")
                {
                    $this->onlyMigration = true;
                    $skip = true;
                }
            }

            $fieldName = trim($fieldName, ",");

            $type = strtolower($type);

            if(!$skip && !empty($fieldName)) {
                if(!array_key_exists($type, $this->validTypes)) {
                    $this->command->error($type. " is not a valid property type! ");
                    return false;
                }

                $properties[$fieldName] = $type;
            }
        }

        return $properties;
    }

    /**
     * @var array
     */
    public $validTypes = array(
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

}
