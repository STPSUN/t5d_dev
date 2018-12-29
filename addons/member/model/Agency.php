<?php
/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/11/6
 * Time: 15:08
 */

namespace addons\member\model;


class Agency extends \web\common\model\BaseModel
{
    protected function _initialize()
    {
        $this->tableName = 'member_agency';
    }

    public function getList($pageIndex = -1, $pageSize = -1, $filter = '', $order = 'id asc') {
        $sql = " SELECT a.id,a.level,a.update_time,a.status,m.username,m.phone,m.agency_level "
            . " FROM tp_member_agency AS a "
            . " LEFT JOIN tp_member_account AS m ON m.id = a.user_id "
            . " WHERE 1=1 ";

        if($filter)
            $sql .= ' and ' .$filter;
        return $this->getDataListBySQL($sql, $pageIndex, $pageSize, $order);
    }

    public function getCountTotal($filter = '') {
        $sql = " SELECT a.*,m.username,m.phone "
            . " FROM tp_member_agency AS a "
            . " LEFT JOIN tp_member_account AS m ON m.id = a.user_id "
            . " WHERE 1=1 ";

        if($filter)
            $sql .= ' and ' .$filter;
        $count = $this->query($sql);
        return count($count);
    }

}