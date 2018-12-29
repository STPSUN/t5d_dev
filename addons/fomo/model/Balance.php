<?php
/**
 * Created by PhpStorm.
 * User: stp
 * Date: 2018/12/25
 * Time: 17:32
 */

namespace addons\fomo\model;


class Balance extends \web\common\model\BaseModel
{
    protected function _initialize() {
        $this->tableName = 'balance';
    }

}