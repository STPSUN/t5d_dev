<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace addons\fomo\user\controller;

/**
 * Description of KeyRecord
 * 购买记录 p3d f3d
 * @author shilinqing
 */
class KeyRecord extends \web\user\controller\AddonUserBase{
    
    public function index(){
        $type = $this->_get('type');
        if($type == '' || $type==0){
            $type = 0;
            $m = new \addons\fomo\model\Game();
            $games = $m->getDataList();
            $this->assign('games',$games);
            $t = new \addons\fomo\model\Team();
            $teams = $t->getDataList();
            $this->assign('teams',$teams);
        }
        $this->assign('type',$type);
        return $this->fetch();
    }
    
    public function loadList() {
        $keyword = $this->_get('keyword');
        $type = $this->_get('type');
        $game_id = $this->_get('game_id');
        $team_id = $this->_get('team_id');
        $filter = '1=1';
        if($type == 0){
            $m = new \addons\fomo\model\KeyRecord();
            if($game_id != ''){
                $filter .= ' and game_id='.$game_id;
            }
            if($team_id != ''){
                $filter .= ' and team_id='.$team_id;
            }
        }else{
            $m = new \addons\fomo\model\TokenRecord();
        }
        if ($keyword != null) {
            $filter .= ' and username like \'%' . $keyword . '%\'';
        }
        $total = $m->getTotal($filter);
        $rows = $m->getList($this->getPageIndex(), $this->getPageSize(), $filter);
        $count_total = $m->getCountTotal($filter);
        return $this->toTotalDataGrid($total, $rows,$count_total);
    }
    
    
    public function set_winner(){
        $id = $this->_get('id');
        try{
            $m = new \addons\fomo\model\KeyRecord();
            $data = $m->getDetail($id);
            if(empty($data)){
                return $this->failData('数据不存在');
            }
            $g = new \addons\fomo\model\Game();
            $game = $g->getDetail($data['game_id']);
            if(empty($game) || $game['status'] != 1){
                return $this->failData('游戏不存在或者不是进行中');
            }
            $data['update_time'] = NOW_DATETIME;
            $data['is_winner'] = 1;
            $m->save($data);
            return $this->successData();
        } catch (\Exception $ex) {
            return $this->failData($ex->getMessage());
        }
    }
    
    
}
