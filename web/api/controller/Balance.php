<?php
/**
 * Created by PhpStorm.
 * User: stp
 * Date: 2018/12/26
 * Time: 14:49
 */

namespace web\api\controller;


use think\Request;
use think\Validate;

class Balance extends ApiBase
{
    public function drawPool()
    {
        $param = Request::instance()->post();
        $validate = new Validate([
            'amount'    => 'require',
            'pool_account' => 'require',
            'pay_password' => 'require'
        ]);
        if(!$validate->check($param))
            return $this->failJSON($validate->getError());

        $coin_id = 2;
        $amount = $param['amount'];
        $pool_account = $param['pool_account'];
        $pay_pass = $param['pay_password'];
        $user_id = 1;

        $poolM = new \addons\member\model\MemberOrePool();
        $pool = $poolM->where(['user_id' => $user_id, 'coin_id' => $coin_id, 'status' => 1])->find();
        if(!empty($pool))
            return $this->failJSON('审核通过后，才能再次申请，请耐心等待');

        $sysM = new \web\common\model\sys\SysParameterModel();
        $is_pool = $sysM->getValByName('is_pool_tax');
        $tax = 0;
        $total_amount = $amount;
        if($is_pool == 1)
        {
            $pool_rate = $sysM->getValByName('pool_tax');
            if(!empty($pool_rate))
                $tax = $amount * $pool_rate / 100;
            $total_amount += $tax;
        }

        $balanceM = new \addons\member\model\Balance();
        $balance = $balanceM->getBalanceByCoinID($user_id,$coin_id);
        if($balance['amount'] < $total_amount)
            return $this->failJSON('余额不足');

        $userM = new \addons\member\model\MemberAccountModel();
        $user = $userM->getDetail($user_id);
        if($user['pay_password'] !== md5($pay_pass)){
            return $this->failJSON('支付密码错误');
        }

        $recordM = new \addons\member\model\TradingRecord();
        $balanceM->startTrans();
        try
        {
            $userAsset = $balanceM->updateBalance($user_id,$total_amount,$coin_id,false);
            if(!$userAsset)
            {
                $balanceM->rollback();
                return $this->failJSON('系统繁忙，请稍后再试，#1');
            }

            $before_amount = $userAsset['before_amount'];
            $after_amount = $userAsset['amount'];
            $recordM->addRecord($user_id,$coin_id,$total_amount,$before_amount,$after_amount,23,0,$user_id,'','','提币到矿池');
            $pool_data = [
                'user_id'   => $user_id,
                'pool_account'  => $pool_account,
                'amount'    => $amount,
                'tax'   => $tax,
                'coin_id'   => $coin_id,
                'update_time'   => NOW_DATETIME,
            ];
            $poolM->add($pool_data);
        }catch (\Exception $e)
        {
            $balanceM->rollback();
            return $this->failJSON($e->getMessage());
        }

        $balanceM->commit();
        return $this->successJSON();
    }
}


































