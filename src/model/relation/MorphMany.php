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

use tp51\Db;
use tp51\db\Query;
use tp51\Exception;
use tp51\Model;
use tp51\model\Relation;

class MorphMany extends Relation
{
    // 多态字段
    protected $morphKey;
    protected $morphType;
    // 多态类型
    protected $type;

    /**
     * 架构函数
     * @access public
     * @param Model  $parent    上级模型对象
     * @param string $model     模型名
     * @param string $morphKey  关联外键
     * @param string $morphType 多态字段名
     * @param string $type      多态类型
     */
    public function __construct(Model $parent, $model, $morphKey, $morphType, $type)
    {
        $this->parent    = $parent;
        $this->model     = $model;
        $this->type      = $type;
        $this->morphKey  = $morphKey;
        $this->morphType = $morphType;
        $this->query     = (new $model)->db();
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

        $list   = $this->query->relation($subRelation)->select();
        $parent = clone $this->parent;

        foreach ($list as &$model) {
            $model->setParent($parent);
        }

        return $list;
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
        throw new Exception('relation not support: has');
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
     * @param  array    $resultSet   数据集
     * @param  string   $relation    当前关联名
     * @param  string   $subRelation 子关联名
     * @param  \Closure $closure     闭包
     * @return void
     */
    public function eagerlyResultSet(&$resultSet, $relation, $subRelation, $closure)
    {
        $morphType = $this->morphType;
        $morphKey  = $this->morphKey;
        $type      = $this->type;
        $range     = [];

        foreach ($resultSet as $result) {
            $pk = $result->getPk();
            // 获取关联外键列表
            if (isset($result->$pk)) {
                $range[] = $result->$pk;
            }
        }

        if (!empty($range)) {
            $where = [
                [$morphKey, 'in', $range],
                [$morphType, '=', $type],
            ];
            $data = $this->eagerlyMorphToMany($where, $relation, $subRelation, $closure);

            // 关联属性名
            $attr = Db::parseName($relation);

            // 关联数据封装
            foreach ($resultSet as $result) {
                if (!isset($data[$result->$pk])) {
                    $data[$result->$pk] = [];
                }

                foreach ($data[$result->$pk] as &$relationModel) {
                    $relationModel->setParent(clone $result);
                    $relationModel->isUpdate(true);
                }

                $result->setRelation($attr, $this->resultSetBuild($data[$result->$pk]));
            }
        }
    }

    /**
     * 预载入关联查询
     * @access public
     * @param  Model    $result      数据对象
     * @param  string   $relation    当前关联名
     * @param  string   $subRelation 子关联名
     * @param  \Closure $closure     闭包
     * @return void
     */
    public function eagerlyResult(&$result, $relation, $subRelation, $closure)
    {
        $pk = $result->getPk();

        if (isset($result->$pk)) {
            $key   = $result->$pk;
            $where = [
                [$this->morphKey, '=', $key],
                [$this->morphType, '=', $this->type],
            ];
            $data = $this->eagerlyMorphToMany($where, $relation, $subRelation, $closure);

            if (!isset($data[$key])) {
                $data[$key] = [];
            }

            foreach ($data[$key] as &$relationModel) {
                $relationModel->setParent(clone $result);
                $relationModel->isUpdate(true);
            }

            $result->setRelation(Db::parseName($relation), $this->resultSetBuild($data[$key]));
        }
    }

    /**
     * 关联统计
     * @access public
     * @param Model    $result  数据对象
     * @param \Closure $closure 闭包
     * @return integer
     */
    public function relationCount($result, $closure)
    {
        $pk    = $result->getPk();
        $count = 0;

        if (isset($result->$pk)) {
            if ($closure) {
                $closure($this->query);
            }

            $count = $this->query
                ->where([
                    [$this->morphKey, '=', $result->$pk],
                    [$this->morphType, '=', $this->type],
                ])
                ->count();
        }

        return $count;
    }

    /**
     * 获取关联统计子查询
     * @access public
     * @param \Closure $closure 闭包
     * @return string
     */
    public function getRelationCountQuery($closure)
    {
        if ($closure) {
            $closure($this->query);
        }

        return $this->query
            ->where([
                [$this->morphKey, 'exp', Db::raw('=' . $this->parent->getTable() . '.' . $this->parent->getPk())],
                [$this->morphType, '=', $this->type],
            ])
            ->fetchSql()
            ->count();
    }

    /**
     * 多态一对多 关联模型预查询
     * @access public
     * @param  array         $where       关联预查询条件
     * @param  string        $relation    关联名
     * @param  string        $subRelation 子关联
     * @param  \Closure      $closure     闭包
     * @return array
     */
    protected function eagerlyMorphToMany($where, $relation, $subRelation = '', $closure = null)
    {
        // 预载入关联查询 支持嵌套预载入
        $this->query->removeOptions('where');

        if ($closure) {
            $closure($this->query);
        }

        $list     = $this->query->where($where)->with($subRelation)->select();
        $morphKey = $this->morphKey;

        // 组装模型数据
        $data = [];
        foreach ($list as $set) {
            $data[$set->$morphKey][] = $set;
        }

        return $data;
    }

    /**
     * 保存（新增）当前关联数据对象
     * @access public
     * @param mixed $data 数据 可以使用数组 关联模型对象 和 关联对象的主键
     * @return Model|false
     */
    public function save($data)
    {
        if ($data instanceof Model) {
            $data = $data->getData();
        }

        // 保存关联表数据
        $pk = $this->parent->getPk();

        $model = new $this->model;

        $data[$this->morphKey]  = $this->parent->$pk;
        $data[$this->morphType] = $this->type;

        return $model->save($data) ? $model : false;
    }

    /**
     * 批量保存当前关联数据对象
     * @access public
     * @param array $dataSet 数据集
     * @return array|false
     */
    public function saveAll(array $dataSet)
    {
        $result = [];

        foreach ($dataSet as $key => $data) {
            $result[] = $this->save($data);
        }

        return empty($result) ? false : $result;
    }

    /**
     * 执行基础查询（仅执行一次）
     * @access protected
     * @return void
     */
    protected function baseQuery()
    {
        if (empty($this->baseQuery) && $this->parent->getData()) {
            $pk = $this->parent->getPk();

            $this->query->where([
                [$this->morphKey, '=', $this->parent->$pk],
                [$this->morphType, '=', $this->type],
            ]);

            $this->baseQuery = true;
        }
    }

}
