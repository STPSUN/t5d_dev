<?php

namespace addons\fomo\user\controller;

/**
 * Description of Parameter
 * fomo说明设置
 * @author shilinqing
 */
class Remark extends \web\user\controller\AddonUserBase{
    
    public function index(){
        return $this->fetch();
    }
    
    public function loadList() {
        $m = new \addons\fomo\model\Remark();
        $total = $m->getTotal();
        $rows = $m->getDataList($this->getPageIndex(), $this->getPageSize());
        return $this->toDataGrid($total, $rows);
    }
    
    public function edit() {
        if (IS_POST) {
            $m = new \addons\fomo\model\Remark();
            $data = $_POST;
            $id = $data['id'];
            $is_set = $m->hasSetRemark($id, $data['type']);
            if(!empty($is_set)){
                return $this->failData('所设置说明已存在,请编辑原有数据');
            }
            $data['update_time'] = NOW_DATETIME;
            try {
                if (empty($id)) {
                    unset($data['id']);
                    $ret = $m->add($data,false);
                } else {
                    $ret = $m->save($data,'',null,false);
                }
                return $this->successData();
            } catch (\Exception $e) {
                return $this->failData($e->getMessage());
            }
        } else {
            $this->assign('id',$this->_get('id'));
            $this->setLoadDataAction('loadData');
            return $this->fetch();
        }
    }
    
    public function loadData() {
        $id = $this->_get('id');
        $m = new \addons\fomo\model\Remark();
        $data = $m->getDetail($id);
        return $data;
    }
    
    public function del() {
        $id = $this->_post('id');
        $m = new \addons\fomo\model\Remark();
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
