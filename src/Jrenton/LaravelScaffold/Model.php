<?php namespace Jrenton\LaravelScaffold;

class Model
{
    private $modelName;
    private $originalName;
    private $namespace;
    private $tableName;

    public function __construct($modelName, $namespace = "")
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

    public function upper()
    {
        return ucfirst($this->modelName);
    }

    public function lower()
    {
        return lcfirst($this->modelName);
    }

    public function tableNameLower()
    {
        return $this->originalName;
    }

    public function plural()
    {
        return str_plural(lcfirst($this->modelName));
    }

    public function upperPlural()
    {
        return str_plural($this->upper());
    }

    public function getTableName()
    {
        return $this->tableName;
    }

    public function nameWithNamespace()
    {
        $namespace = $this->namespace ? $this->namespace . "\\" : "";
        return $namespace . $this->upper();
    }
}
