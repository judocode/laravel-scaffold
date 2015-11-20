<?php namespace Binondord\LaravelScaffold\Migrations;

/**
 * From Jrenton
 * Class Model
 * @package Binondord\LaravelScaffold\Migrations
 */

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
     * @var string
     */
    private $inputProperties;

    /**
     * @var bool
     */
    private $namespaceGlobal = false;

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
     * @var bool
     */
    private $isPivotTable;

    /**
     * @param Command $command
     * @param array $oldModelFile
     */
    public function __construct(Command $command, $oldModelFile, $globalNamespace)
    {
        $this->command = $command;
        $this->oldModelFile = $oldModelFile;

        if(!empty($globalNamespace))
        {
            $this->namespace = $globalNamespace;
            $this->namespaceGlobal = true;
        }
    }

    /**
     *  Generate a model from the specified input
     *
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
     *  Generate properties
     *
     * @return bool
     */
    public function generateProperties()
    {
        if( !empty($this->inputProperties) )
        {
            $this->getModelsWithRelationships($this->inputProperties);

            $this->propertiesArr = $this->getPropertiesFromInput($this->inputProperties);

            $this->propertiesToRemove = $this->generatePropertiesToRemove();

            if($this->propertiesArr === false)
                return false;

            $this->propertiesStr .= implode(",", array_keys($this->propertiesArr));
        }

        return true;
    }

    /**
     *  Return the properties that have been removed
     *
     * @return array
     */
    public function getPropertiesToRemove()
    {
        return $this->propertiesToRemove;
    }

    /**
     *  Return the relationships that have been removed
     *
     * @return Relation[]
     */
    public function getRelationshipsToRemove()
    {
        return $this->relationshipsToRemove;
    }

    /**
     *  Get relationships for the model from input
     *
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

        $this->relationshipsToRemove = $this->generateRelationshipsToRemove();
    }

    /**
     *  Get the namespace of the model
     *
     * @param $modelWithNamespace
     * @return string
     */
    private function getNamespaceFromInput($modelWithNamespace)
    {
        return substr($modelWithNamespace, 0, strrpos($modelWithNamespace, "\\"));
    }

    /**
     *  Get the model name
     *
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
     *  Check to see if input is the last relation
     *
     * @param $values
     * @param $next
     * @return bool
     */
    private function isLastRelation($values, $next)
    {
        return ($next < count($values) && $this->nextArgumentIsRelation($values[$next]));
    }

    /**
     *  Check to see if the argument is a relation
     *
     * @param $value
     * @return bool
     */
    private function nextArgumentIsRelation($value)
    {
        return strpos($value, ":") === false && strpos($value, "(") === false;
    }

    /**
     *  Get properties from the specified input
     *
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
                    $this->isPivotTable = true;
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
     *  Compare new models definition file with old file to return
     *  a list of the relationships that have been removed
     *
     * @return array
     */
    private function generateRelationshipsToRemove()
    {
        $relationships = array();
        if (array_key_exists($this->getTableName(), $this->oldModelFile)) {
            if (array_key_exists("relationships", $this->oldModelFile[$this->getTableName()])) {
                foreach ($this->oldModelFile[$this->getTableName()]["relationships"] as $oldRelation) {
                    $found = false;
                    foreach ($this->relationship as $relation) {
                        if (isset($relation->model->tableName) && $relation->model->tableName == $oldRelation->model->tableName) {
                            $found = true;
                            break;
                        }
                    }

                    if (!$found) {
                        array_push($relationships, $oldRelation);
                    }
                }
            }
        }

        return $relationships;
    }

    /**
     *  Generates properties to remove based on properties that
     *  have been removed since the last model definition file
     *
     * @return array
     */
    private function generatePropertiesToRemove()
    {
        $properties = array();
        if (array_key_exists($this->getTableName(), $this->oldModelFile)) {
            if (array_key_exists("properties", $this->oldModelFile[$this->getTableName()])) {
                foreach ($this->oldModelFile[$this->getTableName()]["properties"] as $property => $type) {
                    if (!array_key_exists($property, $this->propertiesArr)) {
                        $properties[$property] = $type;
                    } else if ($this->oldModelFile[$this->getTableName()]["properties"][$property] != $this->propertiesArr[$property]) {
                        $properties[$property] = $this->propertiesArr[$property];
                    }
                }
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
