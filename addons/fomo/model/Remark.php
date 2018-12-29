<?php

namespace addons\fomo\model;

/**
 * Description of Remark
 *
 * @author shilinqing
 */
class Remark extends \web\common\model\BaseModel{
    
    protected function _initialize() {
        $this->tableName = 'fomo_remark';
    }
    
    /**
     * 
     * @param type $id
     * @param type $type
     * @return type
     */
    public function hasSetRemark($id, $type){
        if(!empty($id)){
            $where['id'] = array('<>',$id);
        }
        $where['type'] = $type;
        return $this->where($where)->find();
    }
    
    
}