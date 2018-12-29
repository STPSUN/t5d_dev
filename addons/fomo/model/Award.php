<?php
/**
 * Created by PhpStorm.
 * User: stp
 * Date: 2018/12/25
 * Time: 17:14
 */

namespace addons\fomo\model;


class Award  extends \web\common\model\BaseModel
{
    protected function _initialize() {
        $this->tableName = 'award';
    }
}