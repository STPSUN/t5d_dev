<?php

namespace addons\fomo\service;

/**
 * Created by PhpStorm.
 * User: stp
 * Date: 2018/12/20
 * Time: 10:35
 */
class Balance extends \web\index\controller\AddonIndexBase
{
    /**
     * 分红发放到账户，%到可用，%到复投
     * @param $user_id
     * @param $amount
     * @param $coin_id
     * @return bool
     */
    public function updateBalanceByBonus($user_id,$coin_id)
    {
        $balanceM = new \addons\member\model\Balance();
        $balance = $balanceM->getBalanceByCoinID($user_id,$coin_id,BALANCE_TYPE_3);
        $amount = $balance['amount'];
        $sysM = new \web\common\model\sys\SysParameterModel();
        $bonus_redo_rate = $sysM->getValByName('bonus_redo_rate');
        $bonus_use_rate  = $sysM->getValByName('bonus_use_rate');

        $balanceM->startTrans();
        try
        {
            $redo_amount = bcmul($amount,$bonus_redo_rate,8);
            $res = $balanceM->updateBalance($user_id,$redo_amount,$coin_id,true,BALANCE_TYPE_2);
            if(!$res)
            {
                $balanceM->rollback();
                return false;
            }

            $use_amount = bcmul($amount,$bonus_use_rate,8);
            $res = $balanceM->updateBalance($user_id,$use_amount,$coin_id,true,BALANCE_TYPE_1);
            if(!$res)
            {
                $balanceM->rollback();
                return false;
            }

        }catch (\Exception $e)
        {
            $balanceM->rollback();
            return false;
        }

        $balanceM->commit();
        return true;
    }


    /**
     * 余额是否不足
     * @param $user_id
     * @param $key_num
     * @param $coin_id
     * @param $current_price
     * @return bool|float|mixed
     */
    public function isEnoughBalance($user_id,$key_num,$coin_id,$current_price)
    {
//        $priceM = new \addons\fomo\model\KeyPrice();
//        $current_price_data = $priceM->getGameCurrentPrice($game_id);
//        $current_price = $current_price_data['key_amount'];

        //计算总金额：key价格递增
        $key_total_price = $key_num * $current_price; //总价
        $key_total_price = round($key_total_price,8);

        $balanceM = new \addons\member\model\Balance();
        $redo_balance = $balanceM->getBalanceByCoinID($user_id,$coin_id,BALANCE_TYPE_2);
        $use_balance = $balanceM->getBalanceByCoinID($user_id,$coin_id,BALANCE_TYPE_1);
        $total_balance = $redo_balance['amount'] + $use_balance['amount'];

        if($key_total_price > $total_balance)
            return false;

        return $key_total_price;
    }

    /**
     * 扣除余额
     * 先扣复投，再扣可用
     * @param $user_id
     * @param $amount
     * @param $coin_id
     * @return bool
     */
    public function updateBalance($user_id,$amount,$coin_id)
    {
        $balanceM = new \addons\member\model\Balance();
        $redo_balance = $balanceM->getBalanceByCoinID($user_id,$coin_id,BALANCE_TYPE_2);

        if($redo_balance['amount'] >= $amount)
        {
            $is_save = $balanceM->updateBalance($user_id,$amount,$coin_id,false,BALANCE_TYPE_2);
            if(!$is_save)
                return false;
        }else
        {
            $is_save = $balanceM->updateBalance($user_id,$redo_balance['amount'],$coin_id,false,BALANCE_TYPE_2);
            if(!$is_save)
                return false;

            $use_amount = $amount - $redo_balance['amount'];
            $is_save = $balanceM->updateBalance($user_id,$use_amount,$coin_id,false,BALANCE_TYPE_1);
            if(!$is_save)
                return false;
        }

        return true;
    }

}









































