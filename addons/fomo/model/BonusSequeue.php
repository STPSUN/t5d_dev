<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace addons\fomo\model;

/**
 * Description of BonusSequeue
 * fomo游戏发奖mysql队列
 * @author shilinqing
 */
class BonusSequeue extends \web\common\model\BaseModel {

    protected function _initialize() {
        $this->tableName = 'fomo_bonus_sequeue';
    }

    /**
     * 添加分红数据库队列
     * @param type $user_id
     * @param type $coin_id
     * @param type $amount
     * @param type $type   类型：0=p3d分红,1=f3d分红
     * @param type $scene  0=p3d购买，1=f3d投注分配，2=f3d开奖分配
     * @param type $game_id
     * @param type $team_id
     */
    public function addSequeue($user_id, $coin_id, $amount, $type, $scene, $game_id = 0, $team_id = 0) {
        $data['user_id'] = $user_id;
        $data['coin_id'] = $coin_id;
        $data['game_id'] = $game_id;
        $data['team_id'] = $team_id;
        $data['type'] = $type;
        $data['scene'] = $scene;
        $data['amount'] = $amount;
        $data['status'] = 0;
        $data['update_time'] = NOW_DATETIME;
        return $this->add($data);
    }

    public function getUnSendData($id = '') {
        if (!empty($id))
            $where['id'] = $id;
        $where['status'] = 0;
        return $this->where($where)->find();
    }

    public function getUnAllSendData($limit = '') {
        $where['status'] = 0;
        $fields = 'id,user_id,coin_id,game_id,team_id,type,scene,amount';
        if ($limit != '') {
            $this->limit(0, $limit);
        }
        return $this->where($where)->field($fields)->select();
    }

    /**
     * 获取列表数据
     * @param type $pageIndex 当前页
     * @param type $pageSize 每页数量
     * @param type $filter 过滤条件
     * @param type $fields 字段信息
     * @param type $order 排序
     * @return type
     */
    public function getDataList($pageIndex = -1, $pageSize = -1, $filter = '', $fields = '*', $order = '') {
        if (!$order) {
            $order = $this->getPk() . " asc";
        }
        $m = new \addons\member\model\MemberAccountModel();
        $gameM = new \addons\fomo\model\Game;
        $coinM = new \addons\config\model\Coins;
        $sql = "select a.*,b.username,c.name as game_name,d.coin_name from {$this->getTableName()} a  "
        . "left join {$m->getTableName()} b on a.user_id = b.id "
        . "left join {$gameM->getTableName()} c on a.game_id = c.id "
        . "left join {$coinM->getTableName()} d on a.coin_id = d.id ";
        
        $sql = "select {$fields} from ($sql) a  where {$filter}";
//        dump($sql);exit;
        return $this->getDataListBySQL($sql, $pageIndex, $pageSize, $order);
    }

    public function getCountTotal($filter = '') {
        $u = new \addons\member\model\MemberAccountModel();
        $sql = 'select a.*,u.username from ' . $this->getTableName() . ' a ,' . $u->getTableName() . ' u where a.user_id=u.id';
        if ($filter != '') {
            $sql = 'select sum(amount) as count_total from (' . $sql . ') as tab where ' . $filter;
        }
        $count = $this->query($sql);
        return $count ? $count[0]['count_total'] : 0;
    }

}
