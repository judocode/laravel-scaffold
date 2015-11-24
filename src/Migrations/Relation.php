<?php namespace Binondord\LaravelScaffold\Migrations;

/**
 * From Jrenton\LaravelScaffold\Relation
 * Class Relation
 * @package Binondord\LaravelScaffold\Migrations
 */

class Relation
{
    /**
     * @var string
     */
    private $relationType;

    /**
     * @var BaseModel
     */
    public $model;

    public function __construct($relationType, BaseModel $model)
    {
        $this->relationType = strtolower($relationType);
        $this->model = $model;
    }

    /**
     *  Is the current relation a "belongsTo"
     *
     * @return bool
     */
    public function isBelongsTo()
    {
        return $this->relationType == "belongsto";
    }

    /**
     *  Is the current relation a "belongsToMany"
     *
     * @return bool
     */
    public function isBelongsToMany()
    {
        return $this->relationType == "belongstomany";
    }

    /**
     *  Return the related models' table name
     *
     * @return string
     */
    public function getRelatedModelTableName()
    {
        return $this->model->getTableName();
    }

    /**
     *  Return the foreign key name
     *
     * @return string
     */
    public function getForeignKeyName()
    {
        return $this->model->tableNameLower() . "_id";
    }

    /**
     *  Get the name of the pivot table of current relation
     *   and specified model
     *
     * @param Model $model
     * @return string
     */
    public function getPivotTableName(Model $model)
    {
        $tableOne = $this->model->lower();
        $tableTwo = $model->lower();

        if(strcmp($tableOne, $tableTwo) > 1)
            $tableName = $tableTwo ."_".$tableOne;
        else
            $tableName = $tableOne ."_".$tableTwo;

        return $tableName;
    }

    /**
     *  Return the "reverse" of relationship
     *
     * @return array
     */
    public function reverseRelations()
    {
        $reverseRelations = array();
        switch($this->relationType) {
            case "belongsto":
                $reverseRelations = array('hasOne', 'hasMany');
                break;
            case "hasone":
                $reverseRelations = array('belongsTo');
                break;
            case "belongstomany":
                $reverseRelations = array('belongsToMany');
                break;
            case "hasmany":
                $reverseRelations = array('belongsTo');
                break;
        }

        return $reverseRelations;
    }

    /**
     *  Return the table name of the relation
     *
     * @return string
     */
    public function getTableName()
    {
        return $this->model->plural();
    }

    /**
     *  Return the type of the relation
     *
     * @return string
     */
    public function getType()
    {
        switch($this->relationType) {
            case "belongsto":
                return "belongsTo";
                break;
            case "hasone":
                return "hasOne";
                break;
            case "belongstomany":
                return "belongsToMany";
                break;
            case "hasmany":
                return "hasMany";
                break;
        }

        return "";
    }

    /**
     *  Get the name of the related or specified model
     *
     * @param Model $model
     * @param string $type
     * @return string
     */
    public function getName(Model $model = null, $type = "")
    {
        $relationName = "";

        if(!$type)
            $type = $this->relationType;

        if(!$model)
            $model = $this->model;

        switch($type) {
            case "belongsto":
            case "hasone":
                $relationName = $model->lower();
                break;
            case "belongstomany":
            case "hasmany":
                $relationName = $model->plural();
                break;
        }

        return $relationName;
    }

    /**
     *  Return the name of the relation of the specified model
     *
     * @param Model $model
     * @param $type
     * @return string
     */
    public function getReverseName(Model $model, $type)
    {
        return $this->getName($model, strtolower($type));
    }
}
