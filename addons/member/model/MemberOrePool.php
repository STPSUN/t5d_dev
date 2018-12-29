<?php
/**
 * Created by PhpStorm.
 * User: stp
 * Date: 2018/12/26
 * Time: 15:16
 */

namespace addons\member\model;


class MemberOrePool extends \web\common\model\BaseModel
{
    public function _initialize()
    {
        $this->tableName = 'member_ore_pool';
    }

    public function getList($pageIndex = -1, $pageSize = -1, $filter = '', $order = 'id asc') {
        $sql = " SELECT p.id,p.pool_account,p.amount,m.username,m.phone,p.update_time,c.coin_name,p.status,p.tax "
            . " FROM tp_member_ore_pool AS p "
            . " LEFT JOIN tp_member_account AS m ON m.id = p.user_id "
            . " LEFT JOIN tp_coins AS c ON c.id = p.coin_id "
            . " WHERE 1=1 ";

        if($filter)
            $sql .= ' and ' .$filter;
        return $this->getDataListBySQL($sql, $pageIndex, $pageSize, $order);
    }

    public function getCountTotal($filter = '') {
        $sql = " SELECT p.id,p.pool_account,p.amount,m.username,m.phone,p.update_time "
            . " FROM tp_member_ore_pool AS p "
            . " LEFT JOIN tp_member_account AS m ON m.id = p.user_id "
            . " LEFT JOIN tp_coins AS c ON c.id = p.coin_id "
            . " WHERE 1=1 ";

        if($filter)
            $sql .= ' and ' .$filter;
        $count = $this->query($sql);
        return count($count);
    }
}