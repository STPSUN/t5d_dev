<?php
/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/11/8
 * Time: 15:56
 */

namespace addons\member\model;


class Recharge  extends \web\common\model\BaseModel
{
    public function _initialize()
    {
        $this->tableName = 'member_recharge';
    }

    public function getList($pageIndex = -1, $pageSize = -1, $filter = '', $order = 'id asc') {
        $sql = " SELECT a.id,a.amount,a.update_time,a.status,a.coin_id,a.create_time,a.update_time,a.remit_img,m.username,m.phone,c.coin_name "
            . " FROM tp_member_recharge AS a "
            . " LEFT JOIN tp_member_account AS m ON m.id = a.user_id "
            . " LEFT JOIN tp_coins AS c ON c.id = a.coin_id"
            . " WHERE 1=1 ";

        if($filter)
            $sql .= ' and ' .$filter;
        return $this->getDataListBySQL($sql, $pageIndex, $pageSize, $order);
    }

    public function getCountTotal($filter = '') {
        $sql = " SELECT a.id,a.amount,a.update_time,a.status,a.remit_img,m.username,m.phone "
            . " FROM tp_member_recharge AS a "
            . " LEFT JOIN tp_member_account AS m ON m.id = a.user_id "
            . " LEFT JOIN tp_coins AS c ON c.id = a.coin_id"
            . " WHERE 1=1 ";

        if($filter)
            $sql .= ' and ' .$filter;
        $count = $this->query($sql);
        return count($count);
    }
}