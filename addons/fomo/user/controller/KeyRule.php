<?php

namespace addons\fomo\user\controller;

/**
 * Description of KeyConf
 *
 * @author shilinqing
 */
class KeyRule extends \web\user\controller\AddonUserBase{
    
    public function index(){
        return $this->fetch();
    }
    
    public function loadList() {
        $m = new \addons\fomo\model\KeyConf();
        $total = $m->getTotal();
        $rows = $m->getDataList($this->getPageIndex(), $this->getPageSize());
        return $this->toDataGrid($total, $rows);
    }
    
    public function edit() {
        if (IS_POST) {
            $m = new \addons\fomo\model\KeyConf();
            $data = $_POST;
            if (!preg_match('/^[\d,]*$/i', $data['invite_rate'])) {
                return $this->failData('请输入层级费率，用逗号隔开');
            }
            $id = $data['id'];
            if(empty($data['limit']) || $data['limit'] <= 0){
                $data['limit'] = 0;
            }
            $data['update_time'] = NOW_DATETIME;
            try {
                if (empty($id)){
                    $m->add($data);
                } 
                else
                    $m->save($data);
                return $this->successData();
            } catch (\Exception $ex) {
                return $this->failData($ex->getMessage());
            }
        } else {
            $this->assign('id',$this->_get('id'));
            $this->setLoadDataAction('loadData');
            return $this->fetch();
        }
    }
    
    public function loadData() {
        $id = $this->_get('id');
        $m = new \addons\fomo\model\KeyConf();
        $data = $m->getDetail($id);
        return $data;
    }
    
    public function del() {
        $id = $this->_post('id');
        $m = new \addons\fomo\model\KeyConf();
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
}
