<?php
/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/11/7
 * Time: 15:19
 */

namespace addons\fomo\model;


class AgencyAward extends \web\common\model\BaseModel
{
    protected function _initialize()
    {
        $this->tableName = 'fomo_agency_award';
    }
}