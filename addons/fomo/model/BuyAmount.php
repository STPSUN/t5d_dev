<?php
/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/11/4
 * Time: 15:58
 */

namespace addons\fomo\model;


class BuyAmount extends \web\common\model\BaseModel
{
    protected function _initialize()
    {
        $this->tableName = 'fomo_buy_amount';
    }
}