<?php namespace Jrenton\LaravelScaffold;

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
     * @return string
     */
    public function getTableName()
    {
        return $this->model->upper();
    }

    /**
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
     * @param Model $model
     * @param $type
     * @return string
     */
    public function getReverseName(Model $model, $type)
    {
        return $this->getName($model, strtolower($type));
    }
}
