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

namespace tp51\model\concern;

use DateTime;
/**
 * 自动时间戳
 */
trait TimeStamp
{
    // 是否需要自动写入时间戳 如果设置为字符串 则表示时间字段的类型
    protected $autoWriteTimestamp;
    // 创建时间字段
    protected $createTime = 'create_time';
    // 更新时间字段
    protected $updateTime = 'update_time';
    // 时间字段取出后的默认时间格式
    protected $dateFormat;

    /**
     * 时间日期字段格式化处理
     * @access protected
     * @param  mixed $format    日期格式
     * @param  mixed $time      时间日期表达式
     * @param  bool  $timestamp 是否进行时间戳转换
     * @return mixed
     */
    protected function formatDateTime($format, $time = 'now', $timestamp = false)
    {
        if (empty($time)) {
            return;
        }

        if (false === $format) {
            return $time;
        } elseif (false !== strpos($format, '\\')) {
            return new $format($time);
        }

        if ($timestamp) {
            $dateTime = new DateTime();
            $dateTime->setTimestamp($time);
        } else {
            $dateTime = new DateTime($time);
        }

        return $dateTime->format($format);
    }

    protected function checkTimeStampWrite()
    {
        // 自动写入创建时间和更新时间
        if ($this->autoWriteTimestamp) {
            if ($this->createTime && !isset($this->data[$this->createTime])) {
                $this->data[$this->createTime] = $this->autoWriteTimestamp($this->createTime);
            }
            if ($this->updateTime && !isset($this->data[$this->updateTime])) {
                $this->data[$this->updateTime] = $this->autoWriteTimestamp($this->updateTime);
            }
        }
    }
}
