<?php

namespace addons\fomo\model;

/**
 * @author shilinqing
 */
class KeyPrice extends \web\common\model\BaseModel{
    
    protected function _initialize() {
        $this->tableName = 'fomo_key_price';
    }
    
    public function getGameCurrentPrice($game_id){
        $where['game_id'] = $game_id;
        $data = $this->where($where)->field('id,key_amount')->find();

        return $data;
    }

    public function getGameNowPrice($game_id){
        $where['game_id'] = $game_id;
        $data = $this->where($where)->field('id,key_amount')->value('key_amount');

        return $data;
    }
    
    public function getDropByGameID($game_id, $fields='id,key_amount,drop_process,total_drop_count'){
        $where['game_id'] = $game_id;
        return $this->where($where)->field($fields)->find();
    }
    
    
}

