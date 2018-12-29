<?php

namespace addons\fomo\user\controller;

/**
 * Description of Game
 * 游戏设置
 * @author shilinqing
 */
class Game extends \web\user\controller\AddonUserBase{
    
    public function index() {
        return $this->fetch();
    }

    public function edit() {
        if (IS_POST) {
            $m = new \addons\fomo\model\Game();
            $data = $_POST;
            $id = $data['id'];
            $data['update_time'] = NOW_DATETIME;
            try {
                if (empty($id)){
                    $data['status'] = 0;
                    $m->add($data);
                } 
                else
                    $m->save($data);
                return $this->successData();
            } catch (\Exception $ex) {
                return $this->failData($ex->getMessage());
            }
        } else {
            $m = new \addons\config\model\Coins();
            $data = $m->getDataList(-1,-1,'','','id asc');
            $this->assign('id',$this->_get('id'));
            $this->setLoadDataAction('loadData');
            $this->assign('coin_list',$data);
            $r = new \addons\fomo\model\KeyConf();
            $this->assign('rules',$r->getDataList());
            $t = new \addons\fomo\model\Team();
            $this->assign('teams',$t->getDataList());
            $u = new \addons\member\model\MemberAccountModel();
            $list = $u->field('id,username,phone')->where('logic_delete=0')->order('id asc')->select();
            $this->assign('user_list', json_encode($list, 256));
            return $this->fetch();
        }
    }
    
    public function edit_total(){
        if(IS_POST){
            $data['id'] = $this->_post('id');
            $data['pool_total_amount'] = $this->_post('pool_total_amount');
            $m = new \addons\fomo\model\Game();
            try {
                $ret = $m->save($data);
                if ($ret > 0) {
                    return $this->successData();
                } else {
                    $message = '操作失败';
                    return $this->failData($message);
                }
            } catch (\Exception $ex) {
                return $this->failData($ex->getMessage());
            }
        }else{
            $this->assign('id',$this->_get('id'));
            $this->setLoadDataAction('loadData');
            return $this->fetch();
        }
        
    }
    
    
    public function start(){
        $id = $this->_post('id');
        $m = new \addons\fomo\model\Game();
        $game = $m->getDetail($id);
        if(empty($game)){
            return $this->failData('游戏不存在');
        }
        $hour = $game['hour'];
        try {
            $m->startTrans();
            $ret = $m->startGame($id,$hour);
            if ($ret > 0) {
                $conf = new \addons\fomo\model\KeyConf();
                $init_amount = $conf->getDataByID($game['rule_id']);
                $priceM = new \addons\fomo\model\KeyPrice();
                $price['game_id'] = $id;
                $price['key_amount'] = $init_amount;
                $price['update_time'] = NOW_DATETIME;
                $priceM->add($price);
                
                $teamM  = new \addons\fomo\model\Team();
                $teams = $teamM->getDataList(-1,-1,'status=1','id','id asc');
                if(!empty($teams)){
                    $teamTotalM = new \addons\fomo\model\TeamTotal();
                    $coin_id = $game['coin_id'];
                    foreach($teams as $k => $team){
                        $data['game_id'] = $id;
                        $data['coin_id'] = $coin_id;
                        $data['team_id'] = $team['id'];
                        $data['update_time'] = NOW_DATETIME;
                        $teamTotalM->add($data);
                    }
                }
                $m->commit();
                return $this->successData();
            } else {
                $message = '操作失败';
                $m->rollback();
                return $this->failData($message);
            }
        } catch (\Exception $ex) {
            $m->rollback();
            return $this->failData($ex->getMessage());
        }
    }
    

    /**
     * 逻辑删除
     * @return type
     */
    public function del() {
        $id = $this->_post('id');
        $m = new \addons\fomo\model\Game();
        try {
            $ret = $m->deleteData($id);
            if ($ret > 0) {
                return $this->successData();
            } else {
                $message = '删除失败';
                return $this->failData($message);
            }
        } catch (\Exception $ex) {
            return $this->failData($ex->getMessage());
        }
    }

    public function loadData() {
        $id = $this->_get('id');
        $m = new \addons\fomo\model\Game();
        $data = $m->getDetail($id);
        return $data;
    }

    public function loadList() {
        $keyword = $this->_get('keyword');
        $filter = '';
        if ($keyword != null) {
            $filter .= ' name like \'%' . $keyword . '%\'';
        }
        $m = new \addons\fomo\model\Game();
        $total = $m->getTotal($filter);
        $rows = $m->getList($this->getPageIndex(), $this->getPageSize(), $filter);
        return $this->toDataGrid($total, $rows);
    }
    
    
}
