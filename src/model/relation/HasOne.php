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
use tp51\Model;

class HasOne extends OneToOne
{
    /**
     * 架构函数
     * @access public
     * @param Model  $parent     上级模型对象
     * @param string $model      模型名
     * @param string $foreignKey 关联外键
     * @param string $localKey   当前模型主键
     */
    public function __construct(Model $parent, $model, $foreignKey, $localKey)
    {
        $this->parent     = $parent;
        $this->model      = $model;
        $this->foreignKey = $foreignKey;
        $this->localKey   = $localKey;
        $this->joinType   = 'INNER';
        $this->query      = (new $model)->db();

        if (get_class($parent) == $model) {
            $this->selfRelation = true;
        }
    }

    /**
     * 延迟获取关联数据
     * @param string   $subRelation 子关联名
     * @param \Closure $closure     闭包查询条件
     * @return Model
     */
    public function getRelation($subRelation = '', $closure = null)
    {
        $localKey = $this->localKey;

        if ($closure) {
            $closure($this->query);
        }

        // 判断关联类型执行查询
        $relationModel = $this->query
            ->removeWhereField($this->foreignKey)
            ->where($this->foreignKey, $this->parent->$localKey)
            ->relation($subRelation)
            ->find();

        if ($relationModel) {
            $relationModel->setParent(clone $this->parent);
        }

        return $relationModel;
    }

    /**
     * 创建关联统计子查询
     * @access public
     * @param  \Closure $closure 闭包
     * @param  string   $aggregate 聚合查询方法
     * @param  string   $field 字段
     * @return string
     */
    public function getRelationCountQuery($closure, $aggregate = 'count', $field = '*')
    {
        if ($closure) {
            $closure($this->query);
        }

        return $this->query
            ->whereExp($this->foreignKey, '=' . $this->parent->getTable() . '.' . $this->parent->getPk())
            ->fetchSql()
            ->$aggregate($field);
    }

    /**
     * 根据关联条件查询当前模型
     * @access public
     * @return Query
     */
    public function has()
    {
        $table      = $this->query->getTable();
        $model      = basename(str_replace('\\', '/', get_class($this->parent)));
        $relation   = basename(str_replace('\\', '/', $this->model));
        $localKey   = $this->localKey;
        $foreignKey = $this->foreignKey;

        return $this->parent->db()
            ->alias($model)
            ->whereExists(function ($query) use ($table, $model, $relation, $localKey, $foreignKey) {
                $query->table([$table => $relation])
                    ->field($relation . '.' . $foreignKey)
                    ->whereExp($model . '.' . $localKey, '=' . $relation . '.' . $foreignKey);
            });
    }

    /**
     * 根据关联条件查询当前模型
     * @access public
     * @param mixed     $where 查询条件（数组或者闭包）
     * @param mixed     $fields   字段
     * @return Query
     */
    public function hasWhere($where = [], $fields = null)
    {
        $table    = $this->query->getTable();
        $model    = basename(str_replace('\\', '/', get_class($this->parent)));
        $relation = basename(str_replace('\\', '/', $this->model));

        if (is_array($where)) {
            $this->getQueryWhere($where, $relation);
        }

        $fields = $this->getRelationQueryFields($fields, $model);

        return $this->parent->db()
            ->alias($model)
            ->field($fields)
            ->join($table . ' ' . $relation, $model . '.' . $this->localKey . '=' . $relation . '.' . $this->foreignKey, $this->joinType)
            ->where($where);
    }

    /**
     * 预载入关联查询（数据集）
     * @access public
     * @param  array    $resultSet   数据集
     * @param  string   $relation    当前关联名
     * @param  string   $subRelation 子关联名
     * @param  \Closure $closure     闭包
     * @return void
     */
    protected function eagerlySet(&$resultSet, $relation, $subRelation, $closure)
    {
        $localKey   = $this->localKey;
        $foreignKey = $this->foreignKey;

        $range = [];
        foreach ($resultSet as $result) {
            // 获取关联外键列表
            if (isset($result->$localKey)) {
                $range[] = $result->$localKey;
            }
        }

        if (!empty($range)) {
            $this->query->removeWhereField($foreignKey);

            $data = $this->eagerlyWhere([
                [$foreignKey, 'in', $range],
            ], $foreignKey, $relation, $subRelation, $closure);

            // 关联属性名
            $attr = Db::parseName($relation);

            // 关联数据封装
            foreach ($resultSet as $result) {
                // 关联模型
                if (!isset($data[$result->$localKey])) {
                    $relationModel = null;
                } else {
                    $relationModel = $data[$result->$localKey];
                    $relationModel->setParent(clone $result);
                    $relationModel->isUpdate(true);
                }

                if (!empty($this->bindAttr)) {
                    // 绑定关联属性
                    $this->bindAttr($relationModel, $result, $this->bindAttr);
                } else {
                    // 设置关联属性
                    $result->setRelation($attr, $relationModel);
                }
            }
        }
    }

    /**
     * 预载入关联查询（数据）
     * @access public
     * @param  Model    $result      数据对象
     * @param  string   $relation    当前关联名
     * @param  string   $subRelation 子关联名
     * @param  \Closure $closure     闭包
     * @return void
     */
    protected function eagerlyOne(&$result, $relation, $subRelation, $closure)
    {
        $localKey   = $this->localKey;
        $foreignKey = $this->foreignKey;
        $data       = $this->eagerlyWhere([
            [$foreignKey, '=', $result->$localKey],
        ], $foreignKey, $relation, $subRelation, $closure);

        // 关联模型
        if (!isset($data[$result->$localKey])) {
            $relationModel = null;
        } else {
            $relationModel = $data[$result->$localKey];
            $relationModel->setParent(clone $result);
            $relationModel->isUpdate(true);
        }

        if (!empty($this->bindAttr)) {
            // 绑定关联属性
            $this->bindAttr($relationModel, $result, $this->bindAttr);
        } else {
            $result->setRelation(Db::parseName($relation), $relationModel);
        }
    }

    /**
     * 执行基础查询（仅执行一次）
     * @access protected
     * @return void
     */
    protected function baseQuery()
    {
        if (empty($this->baseQuery)) {
            if (isset($this->parent->{$this->localKey})) {
                // 关联查询带入关联条件
                $this->query->where($this->foreignKey, '=', $this->parent->{$this->localKey});
            }

            $this->baseQuery = true;
        }
    }
}
