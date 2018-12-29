<?php

namespace addons\fomo\model;

/**
 * Description of KeyConf
 *
 * @author shilinqing
 */
class KeyConf extends \web\common\model\BaseModel{
    
    protected function _initialize() {
        $this->tableName = 'fomo_key_conf';
    }
    
    public function getDataByID($id ,$fields ='init_amount'){
        $where['id'] = $id;
        $data = $this->where($where)->field($fields)->find();
        if(!empty($data)){
            return $data[$fields];
        }else{
            return $data;
        }
    }
    
    
    
}
