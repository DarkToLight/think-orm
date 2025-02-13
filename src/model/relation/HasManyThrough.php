<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2017 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace tp51\model\relation;

use tp51\db\Query;
use tp51\Exception;
use tp51\Db;
use tp51\Model;
use tp51\model\Relation;

class HasManyThrough extends Relation
{
    // 中间关联表外键
    protected $throughKey;
    // 中间表模型
    protected $through;

    /**
     * 架构函数
     * @access   public
     * @param Model  $parent     上级模型对象
     * @param string $model      模型名
     * @param string $through    中间模型名
     * @param string $foreignKey 关联外键
     * @param string $throughKey 关联外键
     * @param string $localKey   当前主键
     */
    public function __construct(Model $parent, $model, $through, $foreignKey, $throughKey, $localKey)
    {
        $this->parent     = $parent;
        $this->model      = $model;
        $this->through    = $through;
        $this->foreignKey = $foreignKey;
        $this->throughKey = $throughKey;
        $this->localKey   = $localKey;
        $this->query      = (new $model)->db();
    }

    /**
     * 延迟获取关联数据
     * @param string   $subRelation 子关联名
     * @param \Closure $closure     闭包查询条件
     * @return \tp51\Collection
     */
    public function getRelation($subRelation = '', $closure = null)
    {
        if ($closure) {
            $closure($this->query);
        }

        $this->baseQuery();

        return $this->query->relation($subRelation)->select();
    }

    /**
     * 根据关联条件查询当前模型
     * @access public
     * @param string  $operator 比较操作符
     * @param integer $count    个数
     * @param string  $id       关联表的统计字段
     * @param string  $joinType JOIN类型
     * @return Query
     */
    public function has($operator = '>=', $count = 1, $id = '*', $joinType = 'INNER')
    {
        return $this->parent;
    }

    /**
     * 根据关联条件查询当前模型
     * @access public
     * @param mixed     $where 查询条件（数组或者闭包）
     * @param mixed     $fields 字段
     * @return Query
     */
    public function hasWhere($where = [], $fields = null)
    {
        throw new Exception('relation not support: hasWhere');
    }

    /**
     * 预载入关联查询
     * @access public
     * @param array    $resultSet   数据集
     * @param string   $relation    当前关联名
     * @param string   $subRelation 子关联名
     * @param \Closure $closure     闭包
     * @return void
     */
    public function eagerlyResultSet(&$resultSet, $relation, $subRelation, $closure)
    {}

    /**
     * 预载入关联查询 返回模型对象
     * @access public
     * @param Model    $result      数据对象
     * @param string   $relation    当前关联名
     * @param string   $subRelation 子关联名
     * @param \Closure $closure     闭包
     * @return void
     */
    public function eagerlyResult(&$result, $relation, $subRelation, $closure)
    {}

    /**
     * 关联统计
     * @access public
     * @param Model    $result  数据对象
     * @param \Closure $closure 闭包
     * @return integer
     */
    public function relationCount($result, $closure)
    {}

    /**
     * 执行基础查询（仅执行一次）
     * @access protected
     * @return void
     */
    protected function baseQuery()
    {
        if (empty($this->baseQuery) && $this->parent->getData()) {
            $through      = $this->through;
            $alias        = Db::parseName(basename(str_replace('\\', '/', $this->model)));
            $throughTable = $through::getTable();
            $pk           = (new $through)->getPk();
            $throughKey   = $this->throughKey;
            $modelTable   = $this->parent->getTable();
            $fields       = $this->getQueryFields($alias);

            $this->query
                ->field($fields)
                ->alias($alias)
                ->join($throughTable, $throughTable . '.' . $pk . '=' . $alias . '.' . $throughKey)
                ->join($modelTable, $modelTable . '.' . $this->localKey . '=' . $throughTable . '.' . $this->foreignKey)
                ->where($throughTable . '.' . $this->foreignKey, $this->parent->{$this->localKey});

            $this->baseQuery = true;
        }
    }

}
