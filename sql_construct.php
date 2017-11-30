<?php
/**
 * Created by PhpStorm.
 * User: 李青川
 * Date: 2016/9/18
 * Time: 15:58
 */

/**
 * 条件模型基类
 */
class ConditionModel{
    public $operator;   //比较符
    public $key;        //字段名
    public $value;      //字段值

    function __construct($Key,$Value,$Operator = '=') {
        if (empty($Key) || !isset($Value)) throw new Exception('error parameter.');

        $this->operator=$Operator;
        $this->key=$Key;
        $this->value=$Value;
    }

    /**
     * 获取条件字符串
    */
    public function GetCondition(){
        if (gettype($this->value) == 'string'){
            return $this->key.' '.$this->operator.' \''.$this->value.'\'';
        }else{
            return $this->key.' '.$this->operator.' '.$this->value;
        }
    }
}

/**
 * Class EqualCondition =条件
 */
class EqualCondition extends ConditionModel{
    function __construct($Key,$Value) {
        parent::__construct($Key,$Value);
    }
}

/**
 * Class UnequalCondition !=条件
 */
class UnequalCondition extends ConditionModel{
    function __construct($Key,$Value) {
        parent::__construct($Key,$Value,'<>');
    }
}

/**
 * Class LikeCondition Like条件
 */
class LikeCondition extends ConditionModel{
    public $percent;

    function __construct($Key,$Value,$DoublePercent = false) {
        parent::__construct($Key,$Value,'like');
        $this->value = $Value;
        $this->percent = $DoublePercent;
    }

    public function GetCondition(){
        if ($this->percent == 'left'){
            return $this->key.' '.$this->operator.' \'%'.$this->value.'\'';
        } else if ($this->percent == 'right'){
            return $this->key.' '.$this->operator.' \''.$this->value.'%\'';
        } else {
            return $this->key.' '.$this->operator.' \'%'.$this->value.'%\'';
        }

    }
}

/**
 * Class NotLikeCondition Not Like条件
 */
class NotLikeCondition extends LikeCondition{
    function __construct($Key,$Value,$DoublePercent = false) {
        parent::__construct($Key,$Value,$DoublePercent);
        $this->operator = 'not like';
    }
}

/**
 * Class InCondition In条件
 */
class InCondition extends ConditionModel{
    private $isExpression;

    function __construct($Key,$Value,$IsExpression = false) {
        parent::__construct($Key,$Value,'in');
        $this->isExpression = $IsExpression;
    }

    public function GetCondition(){
        if ($this->isExpression){
            $valueStr = '(' . $this->value . ')';
        } else {
            if (is_array($this->value)){
                if (gettype($this->value[0]) == 'string') {
                    $valueStr = implode('\',\'', $this->value);
                    $valueStr = '(\'' . $valueStr . '\')';
                }
                else {
                    $valueStr = implode(',', $this->value);
                    $valueStr = '(' . $valueStr . ')';
                }
            }
            else {
                if (gettype($this->value) == 'string') {
                    $valueStr = '(\'' . $this->value . '\')';
                }
                else {
                    $valueStr = '(' . $this->value . ')';
                }
            }
        }

        return $this->key . ' ' . $this->operator . ' ' . $valueStr . '';
    }
}

/**
 * Class NotInCondition Not In条件
 */
class NotInCondition extends InCondition{
    function __construct($Key,$Value) {
        parent::__construct($Key,$Value);
        $this->operator = 'not in';
    }
}

/**
 * Class ConditionCollection 条件集合基类
 */
class ConditionCollection
{
    public $collection = array();
    public $joinStr;

    function __construct($JoinStr = ' and ')
    {
        $this->joinStr = $JoinStr;
    }

    public function Add($Key,$Value,$Operate = '='){
        switch ($Operate){
            case '=':
                $model = new EqualCondition($Key,$Value);
                break;
            case '!=':
            case '<>':
                $model = new UnequalCondition($Key,$Value);
                break;
            case 'like':
                $model = new LikeCondition($Key,$Value);
                break;
            case 'not like':
                $model = new NotLikeCondition($Key,$Value);
                break;
            case 'in':
                $model = new InCondition($Key,$Value);
                break;
            case 'not in':
                $model = new NotInCondition($Key,$Value);
                break;
            default:
                $model = new ConditionModel($Key,$Value,$Operate);
                break;
        }
        $this->AddModel($model);
    }

    public function AddModel(ConditionModel $Model){
        if (!empty($Model)) array_push($this->collection,$Model);
    }
    public function AddCollection(ConditionCollection $Collection){
        if (!empty($Collection)) array_push($this->collection,$Collection);
    }


}

/**
 * Class AndConditionCollection and条件集合，可嵌套
 */
class AndConditionCollection extends ConditionCollection{
    function __construct() {
        parent::__construct(' and ');
    }
}

/**
 * Class OrConditionCollection or 条件集合，可嵌套
 */
class OrConditionCollection extends ConditionCollection{
    function __construct() {
        parent::__construct(' or ');
    }
}

/**
 * Class WhereClauseBuilder Where子句构造器
 */
class WhereClauseBuilder{

    public static function Instance(ConditionCollection $Collection){
        $conditionStr = array();
        foreach ($Collection->collection as $Item){
            $type = get_class($Item);
            if ($type == 'AndConditionCollection' || $type == 'OrConditionCollection' || $type == 'ConditionCollection'){
                array_push($conditionStr, '('.WhereClauseBuilder::Instance($Item).')');
            }else {
                array_push($conditionStr, $Item->GetCondition());
            }
        }

        return implode($Collection->joinStr, $conditionStr);
    }
