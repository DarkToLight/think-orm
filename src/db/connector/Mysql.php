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

namespace tp51\db\connector;

use PDO;
use tp51\db\Connection;
use tp51\db\Query;

/**
 * mysql数据库驱动
 */
class Mysql extends Connection
{

    protected $builder = '\\tp51\\db\\builder\\Mysql';

    /**
     * 初始化
     * @access protected
     * @return void
     */
    protected function initialize()
    {
        // Point类型支持
        Query::extend('point', function ($query, $field, $value = null, $fun = 'GeomFromText', $type = 'POINT') {
            if (!is_null($value)) {
                $query->data($field, ['point', $value, $fun, $type]);
            } else {
                if (is_string($field)) {
                    $field = explode(',', $field);
                }
                $query->setOption('point', $field);
            }

            return $query;
        });
    }

    /**
     * 解析pdo连接的dsn信息
     * @access protected
     * @param array $config 连接信息
     * @return string
     */
    protected function parseDsn($config)
    {
        if (!empty($config['socket'])) {
            $dsn = 'mysql:unix_socket=' . $config['socket'];
        } elseif (!empty($config['hostport'])) {
            $dsn = 'mysql:host=' . $config['hostname'] . ';port=' . $config['hostport'];
        } else {
            $dsn = 'mysql:host=' . $config['hostname'];
        }
        $dsn .= ';dbname=' . $config['database'];

        if (!empty($config['charset'])) {
            $dsn .= ';charset=' . $config['charset'];
        }

        return $dsn;
    }

    /**
     * 取得数据表的字段信息
     * @access public
     * @param string $tableName
     * @return array
     */
    public function getFields($tableName)
    {
        list($tableName) = explode(' ', $tableName);

        if (false === strpos($tableName, '`')) {
            if (strpos($tableName, '.')) {
                $tableName = str_replace('.', '`.`', $tableName);
            }
            $tableName = '`' . $tableName . '`';
        }

        $sql    = 'SHOW COLUMNS FROM ' . $tableName;
        $pdo    = $this->query($sql, [], false, true);
        $result = $pdo->fetchAll(PDO::FETCH_ASSOC);
        $info   = [];

        if ($result) {
            foreach ($result as $key => $val) {
                $val                 = array_change_key_case($val);
                $info[$val['field']] = [
                    'name'    => $val['field'],
                    'type'    => $val['type'],
                    'notnull' => (bool) ('' === $val['null']), // not null is empty, null is yes
                    'default' => $val['default'],
                    'primary' => (strtolower($val['key']) == 'pri'),
                    'autoinc' => (strtolower($val['extra']) == 'auto_increment'),
                ];
            }
        }

        return $this->fieldCase($info);
    }

    /**
     * 取得数据库的表信息
     * @access public
     * @param string $dbName
     * @return array
     */
    public function getTables($dbName = '')
    {
        $sql    = !empty($dbName) ? 'SHOW TABLES FROM ' . $dbName : 'SHOW TABLES ';
        $pdo    = $this->query($sql, [], false, true);
        $result = $pdo->fetchAll(PDO::FETCH_ASSOC);
        $info   = [];

        foreach ($result as $key => $val) {
            $info[$key] = current($val);
        }

        return $info;
    }

    /**
     * SQL性能分析
     * @access protected
     * @param string $sql
     * @return array
     */
    protected function getExplain($sql)
    {
        $pdo    = $this->linkID->query("EXPLAIN " . $sql);
        $result = $pdo->fetch(PDO::FETCH_ASSOC);
        $result = array_change_key_case($result);

        if (isset($result['extra'])) {
            if (strpos($result['extra'], 'filesort') || strpos($result['extra'], 'temporary')) {
                $this->log('SQL:' . $this->queryStr . '[' . $result['extra'] . ']', 'warn');
            }
        }

        return $result;
    }

    protected function supportSavepoint()
    {
        return true;
    }

    /**
     * 启动XA事务
     * @access public
     * @param  string $xid XA事务id
     * @return void
     */
    public function startTransXa($xid)
    {
        $this->initConnect(true);
        if (!$this->linkID) {
            return false;
        }

        $this->execute("XA START '$xid'");
    }

    /**
     * 预编译XA事务
     * @access public
     * @param  string $xid XA事务id
     * @return void
     */
    public function prepareXa($xid)
    {
        $this->initConnect(true);
        $this->execute("XA END '$xid'");
        $this->execute("XA PREPARE '$xid'");
    }

    /**
     * 提交XA事务
     * @access public
     * @param  string $xid XA事务id
     * @return void
     */
    public function commitXa($xid)
    {
        $this->initConnect(true);
        $this->execute("XA COMMIT '$xid'");
    }

    /**
     * 回滚XA事务
     * @access public
     * @param  string $xid XA事务id
     * @return void
     */
    public function rollbackXa($xid)
    {
        $this->initConnect(true);
        $this->execute("XA ROLLBACK '$xid'");
    }
}
