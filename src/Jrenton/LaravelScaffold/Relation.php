<?php namespace Jrenton\LaravelScaffold;

class Relation
{
    private $relationType;
    public $model;

    public function __construct($relationType, Model $model)
    {
        $this->relationType = strtolower($relationType);
        $this->model = $model;
    }

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

    public function getTableName()
    {
        return $this->model->upper();
    }

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
    }

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

    public function getReverseName(Model $model, $type)
    {
        return $this->getName($model, strtolower($type));
    }
}
