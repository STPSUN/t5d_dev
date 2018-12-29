<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace addons\fomo\user\controller;

/**
 * Description of Parameter
 * fomo参数配置
 * @author shilinqing
 */
class AirdropRule extends \web\user\controller\AddonUserBase{
    
    public function index() {
        if (IS_POST) {
            $json = $_POST['json'];
            $data = json_decode($json, true);
            $m = new \addons\fomo\model\AirdropConf();
            foreach ($data as $key => $val) {
                $id = $m->getID($key);
                if ($id > 0) {
                    $model['id'] = $id;
                    $model['parameter_val'] = $val;
                }
                $ret = $m->save($model);
            }
            return $this->successData();
        } else {
            $this->assign('id', '1');
            $m = new \addons\fomo\model\AirdropConf();
            $list = $m->getDataList(-1,-1,'','','order_index asc');

            $u = new \addons\member\model\MemberAccountModel();
            $this->assign('param_list', $list);

            $list = $u->field('id,username,phone')->where('logic_delete=0')->order('id asc')->select();
            $this->assign('user_list', json_encode($list, 256));

            $Ids = array_column($list, 'id');
            $user_ids_list = array_combine($Ids, $list);
//            $airdrop_user_id = $m->where("field", "airdrop_user_id")->find();

//            $airdrop_user = $airdrop_user_id ? $user_ids_list[$airdrop_user_id] : ['name'=>'', 'id'=> ''];
//            $this->assign('user_ids_data', json_encode($user_ids_list, 256));


            $this->setLoadDataAction('loadData');
            return $this->fetch();
        }
        
    }
    
    public function loadData() {
        $m = new \addons\fomo\model\AirdropConf();
        $data = $m->getDataList(-1,-1,'','','id asc');
        return $data;
    }
    
}
