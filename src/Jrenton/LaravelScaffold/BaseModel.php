<?php namespace Jrenton\LaravelScaffold;


class BaseModel
{
    /**
     * @var string
     */
    protected $namespace = "";

    /**
     * @var bool
     */
    protected $timestamps = true;

    /**
     * @var bool
     */
    protected $softDeletes = false;

    /**
     * @var bool
     */
    protected $onlyMigration = false;

    /**
     * @var Relation[]
     */
    protected $relationship = array();

    /**
     * @var array
     */
    protected $propertiesArr = array();

    /**
     * @var string
     */
    protected $modelName;

    /**
     * @var string
     */
    protected $originalName;

    /**
     * @var string
     */
    protected $tableName;

    public function __construct($modelName, $namespace = "")
    {
        $this->generateModelName($modelName, $namespace);
    }

    /**
     * @return string
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * @param $modelName
     * @param string $namespace
     */
    public function generateModelName($modelName, $namespace = "")
    {
        $this->originalName = strtolower($modelName);
        $this->tableName = str_plural(strtolower($modelName));
        $this->modelName = "";

        $modelSplit = explode("_", strtolower($modelName));

        foreach ($modelSplit as $modelSegment) {
            $this->modelName .= ucfirst($modelSegment);
        }

        $this->namespace = $namespace;
    }

    /**
     * @return Relation[]
     */
    public function getRelationships()
    {
        return $this->relationship;
    }

    /**
     * @return array
     */
    public function getProperties()
    {
        return $this->propertiesArr;
    }

    /**
     * @return bool
     */
    public function hasTimestamps()
    {
        return $this->timestamps;
    }

    /**
     * @return bool
     */
    public function hasSoftDeletes()
    {
        return $this->softDeletes;
    }

    /**
     * @return string
     */
    public function upper()
    {
        return ucfirst($this->modelName);
    }

    /**
     * @return string
     */
    public function lower()
    {
        return lcfirst($this->modelName);
    }

    /**
     * @return string
     */
    public function tableNameLower()
    {
        return $this->originalName;
    }

    /**
     * @return string
     */
    public function plural()
    {
        return str_plural(lcfirst($this->modelName));
    }

    /**
     * @return string
     */
    public function upperPlural()
    {
        return str_plural($this->upper());
    }

    /**
     * @return string
     */
    public function getTableName()
    {
        return $this->tableName;
    }

    /**
     * @return string
     */
    public function nameWithNamespace()
    {
        $namespace = $this->namespace ? $this->namespace . "\\" : "";
        return $namespace . $this->upper();
    }
} 