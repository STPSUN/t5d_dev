<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace addons\fomo\model;

/**
 * Description of UncountRecord
 * fomo3d未计算失效分红记录表
 * @author shilinqing
 */
class UncountRecord extends \web\common\model\BaseModel{
    
    protected function _initialize() {
        $this->tableName = 'fomo_uncount_record';
    }
    
    /**
     * 获取用户累计未计算分红金额
     * @param type $user_id
     * @param type $game_id
     */
    public function getUserUnCount($user_id,$game_id){
        $where['user_id'] = $user_id;
        $where['game_id'] = $game_id;
        $data = $this->where($where)->find();
        if(empty($data)){
            $where['num'] = 0;
            $where['total_num'] = 0;
            $where['update_time'] = NOW_DATETIME;
            $where['id'] = $this->add($where);
            $data = $where;
        }
        return $data;
    }
    
    /**
     * 累计分红金额
     * @param type $user_id
     * @param type $game_id
     * @param type $num 当前发放分红金额
     * @return type
     */
    public function updateCount($user_id,$game_id,$num){
        $sql = 'update '.$this->getTableName().' set num=num+'.$num.' '
                . 'and total_num=total_num+'.$num.' wher user_id='.$user_id.' and game_id='.$game_id;
        return $this->execute($sql);
    }

}
