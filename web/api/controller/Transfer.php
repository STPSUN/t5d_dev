<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace web\api\controller;

/**
 * Description of Transfer
 *
 * @author shilinqing
 */
class Transfer extends ApiBase{
    //put your code here

    /**
     * 获取交易记录
     */
    public function getRecordList(){
        $user_id = $this->user_id;
        if($user_id <= 0)
            return $this->failData('请登录');
        $coin_id = 2;
        $type = $this->_get('type'); // 12 = 转出,11= 转入
        if(empty($user_id) || empty($coin_id)){
            return $this->failJSON('missing arguments');
        }
        try{
            $filter = 'user_id='.$user_id .' and coin_id='.$coin_id;
            if($type != 0){
                $filter .= ' and type='.$type;
            }else{
                $filter .= ' and (type=13 or type=15)';
            }
            $m = new \addons\member\model\TradingRecord();

            $list = $m->getDataList($this->getPageIndex(),$this->getPageSize(),$filter);
            return $this->successJSON($list);

        } catch (\Exception $ex) {
            return $this->failJSON($ex->getMessage());
        }

    }


    public function doTransfer(){
        if(IS_POST){
            $user_id = $this->user_id;
            if($user_id <= 0)
                return $this->failData('请登录');
            $coin_id = intval($this->_post('coin_id'));
            $amount = floatval($this->_post('amount'));
            $to_address = $this->_post("to_address");
            if(!$amount || $amount <= 0){
                return $this->failJSON('请输入有效转账数量');
            }
            $m = new \addons\member\model\MemberAccountModel();
            $user_addr = $m->getUserAddress($user_id);
            if($to_address == $user_addr){
                return $this->failJSON('请勿输入自身钱包地址');
            }
            $target_id = $m->getUserByAddress($to_address);
            if(empty($target_id)){
                $key_head = strtolower(substr($to_address,0,2));
                if(($key_head!=="0x" || strlen($to_address) !==42)){
                    return $this->failJSON('地址是由0X开头的42位16进制数组成');
                }
                return $this->doTransferOut($user_id,$coin_id,$amount,$to_address,$user_addr);
            }else{
                //转内网
                return $this->doTransferIn($user_id,$coin_id,$amount,$to_address,$user_addr,$target_id);
            }

        }
    }

    /**
     * 转内网
     * @param type $user_id
     * @param type $coin_id
     * @param type $amount
     * @param type $to_address
     * @param type $user_addr
     * @param type $target_id
     * @return type
     */
    public function doTransferIn(){
        $user_id = $this->user_id;
        if($user_id <= 0)
            return $this->failData('请登录');
        $amount = floatval($this->_post('amount'));
        $to_address = $this->_post("to_address");
        $pay_pass = $this->_post("password");
        if(!$amount || $amount <= 0){
            return $this->failJSON('请输入有效转账数量');
        }
        $m = new \addons\member\model\MemberAccountModel();
        $user = $m->getDetail($user_id);
        if($to_address == $user['address']){
            return $this->failJSON('请勿输入自身钱包地址');
        }
  //       if($user['is_auth'] !==1){
//             return $this->failJSON('尚未实名认证，无法转账');
//         }

        $target_id = $m->getUserByAddress($to_address);
        if(!$target_id){
            return $this->failJSON('钱包地址无效，无法转账');
        }
        try{
            $sysM = new \web\common\model\sys\SysParameterModel();
            $is_open = $sysM->getValByName('is_transfer_tax');
            $rate = $is_open == 1 ? $sysM->getValByName('transfer_tax') : 0;

            $server_rate = $rate > 0 ? bcmul($amount, ($rate/100) , 5) : 0;

            $coin_id = 2;
            $AssetModel = new \addons\member\model\Balance();
            $userAsset = $AssetModel->getBalanceByCoinID($user_id,$coin_id);
            $total_amount = $amount + $server_rate;

            $AssetModel->startTrans();
            if($total_amount > $userAsset['amount']){
                $AssetModel->rollback();
                return $this->failJSON('账户余额不足');
            }
            $userAsset = $AssetModel->updateBalance($user_id,$total_amount,$coin_id);
            if(!$userAsset){
                $AssetModel->rollback();
                return $this->failJSON('账号扣款失败');
            }
            $user_addr = $user['address'];
            $type = 13;
            $before_amount = $userAsset['before_amount'];
            $after_amount = $userAsset['amount'];
            $change_type = 0; //减少
            $remark = '内网转账';
            $recordM = new \addons\member\model\TradingRecord();
            $record_id = $recordM->addRecord($user_id, $coin_id, $total_amount, $before_amount, $after_amount, $type, $change_type, $target_id, $to_address, $user_addr, $remark);

            if($record_id > 0){
                //用户转入
                $targetAsset = $AssetModel->updateBalance($target_id, $amount, $coin_id, true);
                if(!$userAsset){
                    $AssetModel->rollback();
                    return $this->failJSON('转账失败');
                }
                $type = 15;
                $before_amount = $targetAsset['before_amount'];
                $after_amount = $targetAsset['amount'];
                $change_type = 1; //增加
                $remark = '内网转账';

                $recordM = new \addons\member\model\TradingRecord();
                $r_id = $recordM->addRecord($target_id, $coin_id, $amount, $before_amount, $after_amount, $type, $change_type, $user_id, $to_address, $user_addr, $remark);
                if(!$r_id ){
                    $AssetModel->rollback();
                    return $this->failJSON('提交申请失败');
                }
                $AssetModel->commit();

                return $this->successJSON();
            }
        } catch (\Exception $ex) {
            return $this->failJSON($ex->getMessage());
        }
    }


    public function doTransferOut(){
        if(IS_POST){
            $user_id = $this->user_id;
            if($user_id <= 0){
                return $this->failJSON("请登录");
            }
            $coin_id = $this->_post("coin_id");
            $amount = floatval($this->_post('amount'));
            $to_address = $this->_post("to_address");
            $pay_pass = $this->_post("password");

            $coinM = new \addons\config\model\Coins();
            $coin = $coinM->getDetail($coin_id);
            if(empty($coin))
                return $this->failJSON('该币种不存在');

            if(!$amount || $amount <= 0){
                return $this->failJSON('请输入有效提现数量');
            }
            $m = new \addons\member\model\MemberAccountModel();
            $user = $m->getDetail($user_id);
            if($to_address == $user['address']){
                return $this->failJSON('请勿输入自身钱包地址');
            }
            $key_head = strtolower(substr($to_address,0,2));
//            if(($key_head!=="0x" || strlen($to_address) !==42)){
//                return $this->failJSON('地址是由0X开头的42位16进制数组成');
//            }
            try{
                $memberM = new \addons\member\model\MemberAccountModel();
                if($user['pay_password'] !== md5($pay_pass)){
                    return $this->failJSON('支付密码有误，无法提现');
                }
           //      if($user['is_auth'] !==1){
//                     return $this->failJSON('尚未实名认证，无法提现');
//                 }
                $sysM = new \web\common\model\sys\SysParameterModel();
                $is_open = $sysM->getValByName('is_withdraw_rate');
                $rate = $is_open == 1 ? $sysM->getValByName('withdraw_rate') : 0;

                $server_rate = $rate > 0 ? bcmul($amount, ($rate/100) , 5) : 0;

                $AssetModel = new \addons\member\model\Balance();
                $userAsset = $AssetModel->getBalanceByCoinID($user_id,$coin_id);
                $AssetModel->startTrans();

                $total_amount = $amount + $server_rate;
                if($total_amount >= $userAsset['amount']){
                    $AssetModel->rollback();
                    return $this->failJSON('账户余额不足');
                }

                $withdraw_limit = $sysM->getValByName('withdraw_limit');
                $tradeM = new \addons\eth\model\EthTradingOrder();

                $keyRecordM = new \addons\fomo\model\KeyRecord();
                $total_eops_num = $keyRecordM->where(['user_id' => $user_id])->sum('eth');
                $eops_limit = $total_eops_num * $withdraw_limit;

                if($total_eops_num >= $eops_limit)
                    return $this->failJSON('已经达到提现上限');

                $total_eops = $amount + $total_eops_num;
                if($eops_limit > $total_eops)
                {
                    $can_withdraw_num = $eops_limit - $total_eops_num;
                    return $this->failJSON('已达到今日提现上限，本次可提现：' . $can_withdraw_num);
                }

                $userAsset = $AssetModel->updateBalance($user_id,$total_amount,$coin_id);
                if(!$userAsset){
                    $AssetModel->rollback();
                    return $this->failJSON('账号扣款失败');
                }
                $trade_res = $tradeM->transactionIn($user_id, '',$to_address, $coin_id, $amount,'', '', $server_rate,0, 0,  "可用WNCT转出外网");
                if(!$trade_res){
                    $AssetModel->rollback();
                    return $this->failJSON('提交申请失败');
                }

                $type = 14;
                $before_amount = $userAsset['before_amount'];
                $after_amount = $userAsset['amount'];
                $change_type = 0; //减少
                $remark = '转出可用余额';

                $recordM = new \addons\member\model\TradingRecord();
                $r_id = $recordM->addRecord($user_id, $coin_id, $total_amount, $before_amount, $after_amount, $type, $change_type, $user_id, $to_address, '', $remark);
                if(!$r_id ){
                    $AssetModel->rollback();
                    return $this->failJSON('提交申请失败');
                }
                $AssetModel->commit();
                return $this->successJSON();
            }catch (\Exception $ex) {
                return $this->failJSON($ex->getMessage());
            }
        }
    }

}
















