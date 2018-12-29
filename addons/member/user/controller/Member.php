<?php

namespace addons\member\user\controller;

use think\Request;
use think\Validate;

class Member extends \web\user\controller\AddonUserBase{
    
    public function index(){
        $is_auth = $this->_get('is_auth');
        if($is_auth == ''){
            $is_auth = 0; //待认证
        }
        $this->assign('is_auth',$is_auth);
        return $this->fetch();
    }

    public function loadList(){
        $is_auth = $this->_get('is_auth');
        $keyword = $this->_get('keyword');
        $phone = $this->_get('phone');
        $filter = '  1 = 1 ';
        if ($keyword != null) {
            $filter .= ' and username like \'%' . $keyword . '%\'';
        }
        if($phone != null)
        {
            $filter .= ' and phone like \'%' . $phone . '%\'';
        }
        $m = new \addons\member\model\MemberAccountModel();
        $total = $m->getTotal($filter);
        $rows = $m->getUserList($this->getPageIndex(), $this->getPageSize(), $filter, 'register_time desc');
        return $this->toDataGrid($total, $rows);
    }
    
    /**
     * 认证
     */
    public function auth(){
       if(IS_POST){
           $is_auth = $this->_post('is_auth');
           $user_id = $this->_post('id');
           if($is_auth && $user_id){
                $m = new \addons\member\model\MemberAccountModel();
                $data['id'] = $user_id;
                $data['is_auth'] = $is_auth;
                $ret = $m->save($data);
                if($ret > 0){
                    return $this->successData();
                }
           }else{
               return $this->failData('缺少参数');
           }
       }else{
           $this->assign('id',$this->_get('id'));
           $this->setLoadDataAction('loadCard');
           return $this->fetch();
       }
    }
    
    public function edit(){
        $m = new \addons\member\model\MemberAccountModel();
        if(IS_POST){
            $data = $_POST;
            $password = $this->_post("now_password");
            $agency_level = $this->_post("agency_level");
            if(!empty($password)){
                if (!preg_match("/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{6,20}$/", $password)) {
                    return $this->failData('请输入5~20位字母数字密码');
                }
                $data['password'] = md5($password);
            }
            $pay_password = $this->_post('now_pay_password');
            if(!empty($pay_password)){
                if (!preg_match("/^[0-9]{6}$/", $pay_password)) {
                    return $this->failData('请输入6位数字交易密码');
                }
                $data['pay_password'] = md5($pay_password);
            }
            if(!empty($agency_level))
            {
                $data['agency_level'] = $agency_level;
            }
            if($data['id']){
                $m->save($data);
                //添加管理日志
                return $this->successData();
            }else{
                return $this->failData('用户id为空');
            }
        }else{
            $list = $m->field('id,username')->where('logic_delete=0')->order('id asc')->select();
            $this->assign('user_list', json_encode($list, 256));
            $this->assign('id', $this->_get('id'));
            $this->setLoadDataAction('loadData');
            return $this->fetch();
        }
    }
    
    public function view_balance(){
        $id = $this->_get('id');
        $m = new \addons\member\model\Balance();
        $filter = 'user_id='.$id;
        $list = $m->getList(-1,-1,$filter);
        $this->assign('data',$list);
        return $this->fetch();
    }
    
    public function loadData() {
        $id = $this->_get('id');
        $m = new \addons\member\model\MemberAccountModel();
        $data = $m->getDetail($id);
        return $data;
    }
    
    /**
     * 加载认证数据
     * @return type
     */
    public function loadCard(){
        $id = $this->_get('id');
        $m = new \addons\member\model\MemberAccountModel();
        $data = $m->getAuthData($id);
        return $data;
    }
    
    /**
     * 拨币
     * @return type
     */
    public function add_coin_stock(){
        if(IS_POST){
            $user_id = $this->_post('id');
            $coin_id = $this->_post('coin_id');
            $amount = $this->_post('amount');
            $memberM = new \addons\member\model\MemberAccountModel();
            $to_address = $memberM->getSingleField($user_id, 'address');
            if(empty($to_address))
                return $this->failData('用户地址不存在!');

            $m = new \addons\member\model\Balance();
            $m->startTrans();
            $where['user_id'] = $user_id;
            $where['coin_id'] = $coin_id;
            $balance = $m->where($where)->find();
            try{
                $before_amount = 0;
                if(!empty($balance)){
                    $id = $balance['id'];
                    $before_amount = $balance['amount'];
                    $balance['amount'] = $before_amount + $amount;
                    $balance['total_amount'] = $balance['total_amount'] + $amount;
                    $balance['before_amount'] = $before_amount;
                    $balance['update_time'] = NOW_DATETIME;
                    $is_save = $m->save($balance);
                    if(!$is_save)
                    {
                        $m->rollback();
                        return $this->failData('拨币失败');
                    }

                }else{
                    $balance['user_id'] = $user_id;
                    $balance['coin_id'] = $coin_id;
                    $balance['amount'] = $amount;
                    $balance['total_amount'] = $amount;
                    $balance['update_time'] = NOW_DATETIME;
                    $id = $m->add($balance);
                }
                if($id > 0){
                    $rm = new \addons\member\model\TradingRecord();
                    $after_amount = $balance['amount'];
                    $change_type = 1; //增加
                    $type = 6;//后台拨币
                    $remark = '系统后台拨币';
                    if($amount > 0){
                        $change_type = 1; //增加
                    }else{
                        $change_type= 0;//减少
                        $amount = abs($amount);
                    }
                    $r_id = $rm->addRecord($user_id, $coin_id, $amount, $before_amount, $after_amount, $type, $change_type, 0, $to_address, '', $remark);
                    if($r_id > 0){
                        $m->commit();
                        return $this->successData();
                    }
                }else{
                    $m->rollback();
                    return $this->failData('拨币失败');
                }
            } catch (\Exception $ex) {
                $m->rollback();
                return $this->failData($ex->getMessage());
            }
            
        }else{
            $m = new \addons\config\model\Coins();
            $list = $m->getDataList(-1,-1,'','id,coin_name','id asc');
            $this->assign('coins',$list);
            $this->assign('id',$this->_get('id'));
            return $this->fetch();
        }
    }
    
    
    public function change_frozen(){
        $id = $this->_post('id');
        $status = $this->_post('status');
        if($status != 0){
            $status = 1;
        }
        $m = new \addons\member\model\MemberAccountModel();
        try{
            $ret = $m->changeFrozenStatus($id, $status);
            if($ret > 0){
                return $this->successData();
            }else{
                $message = '操作失败';
                return $this->failData($message);
            }
        } catch (\Exception $ex) {
            return $this->failData($ex->getMessage());
        }
    }
    
    /**
     * 逻辑删除
     * @return type
     */
    public function del(){
        $id = $this->_post('id');
        $m = new \addons\member\model\MemberAccountModel();
        try{
            $m->startTrans();
            $balance  = new \addons\member\model\Balance();
            $recordM  = new \addons\member\model\TradingRecord();
            $keyRecordM  = new \addons\fomo\model\KeyRecord();
            $TokenRecordM  = new \addons\fomo\model\TokenRecord();
            $otcM  = new \addons\otc\model\OtcOrder();
            $filter = ['user_id' => $id];
            $ret = $balance->deleteFilter($filter);
            $ret2 = $recordM->deleteFilter($filter);
            $ret3 = $keyRecordM->deleteFilter($filter);
            $ret3 = $TokenRecordM->deleteFilter($filter);
            $ret4 = $otcM->deleteFilter($filter);
            $ret = $m->deleteData($id);
            $where = ['pid' => ['in', $id]];
            $res = $m->where($where)->update(['pid' => 0]);
            if($ret > 0){
                $m->commit();
                return $this->successData();
            }else{
                $m->rollback();
                $message = '删除失败';
                return $this->failData($message);
            }
        } catch (\Exception $ex) {
            return $this->failData($ex->getMessage());
        }
    }

    /**
     * 拨币
     * @return type
     */
    public function add_token(){
        if(IS_POST){
            $user_id = $this->_post('id');
            $amount = $this->_post('amount');

            $param = Request::instance()->post();
            $validate = new Validate([
                'amount'    => ['require','regex' => '^[1-9]*$']
            ],[
                'amount'    => '请输入正确的数量'
            ]);
            if(!$validate->check($param))
                return $this->failData($validate->getError());

            $memberM = new \addons\member\model\MemberAccountModel();
            $user = $memberM->getDetail($user_id);
            if(empty($user))
                return $this->failData('用户不存在!');

            //判断是否达到上限
            $tokenConfM = new \addons\fomo\model\TokenConf();
            $tokenRecordM = new \addons\fomo\model\TokenRecord();
            $token_limit = $tokenConfM->getValByName('total_token_amount');
            $token_num = $tokenRecordM->getTotalToken(); //释放总量

            $total_token = $token_num + $amount;
            if($total_token > $token_limit)
                return $this->failData('已达到token上限');

            $user_token = $tokenRecordM->where('user_id', $user_id)->find();
            if (empty($user_token)) {
                $data = array(
                    'user_id' => $user_id,
                    'token' => 0,
                    'before_token' => 0,
                    'update_time' => NOW_DATETIME,
                );
                $tokenRecordM->add($data);
            }
            $tokenRecordM->where('user_id', $user_id)->setInc('token', $amount);
            return $this->successData();
        }else{
            $m = new \addons\config\model\Coins();
            $list = $m->getDataList(-1,-1,'','id,coin_name','id asc');
            $this->assign('coins',$list);
            $this->assign('id',$this->_get('id'));
            return $this->fetch();
        }
    }

}


