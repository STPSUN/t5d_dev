<?php
/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/11/9
 * Time: 10:06
 */

namespace addons\member\user\controller;


class Recharge extends \web\user\controller\AddonUserBase
{
    public function index()
    {
        $status = $this->_get('status');
        if($status == ''){
            $status = 1; //未确认
        }
        $this->assign('status',$status);
        return $this->fetch();
    }

    public function loadList(){
//        $keyword = $this->_get('keyword');
        $status = $this->_get('status');
        $type = $this->_get('type');
        $filter = 'status='.$status;
        if($type != ''){
            $filter .= ' and type='.$type;
        }
//        if ($keyword != null) {
//            $filter .= ' and b.username like \'%' . $keyword . '%\'';
//        }
//        $m = new \addons\member\model\Agency();
        $m = new \addons\member\model\Recharge();
        $total = $m->getTotal($filter);
        $rows = $m->getList($this->getPageIndex(), $this->getPageSize(), $filter);
        return $this->toDataGrid($total, $rows);
    }

    public function appr()
    {
        if(IS_POST)
        {
            $id = $this->_post('id');
            $coin_id = $this->_post('coin_id');
            $m = new \addons\member\model\Recharge();
            $data = $m->getDetail($id);
            if(empty($data) || empty($coin_id)){
                return $this->failData("数据异常");
            }

            $balanceM = new \addons\member\model\Balance();
            $balance = $balanceM->getBalanceByCoinID($data['user_id'],$coin_id);
            $m->startTrans();
            try
            {
                //更新充值表状态：已打款
                $m->save([
                    'status' => 2,
                    'update_time' => NOW_DATETIME,
                ],[
                    'id' => $id,
                ]);

                //更新用户余额
                $balanceM->updateBalance($data['user_id'],$data['amount'],$coin_id,true);
                //添加交易记录
                $recordM = new \addons\member\model\TradingRecord();
                $after = $balance['amount'] + $data['amount'];
                $recordM->addRecord($data['user_id'],$coin_id,$data['amount'],$balance['amount'],$after,22,1,$data['user_id'],'','','充值');

                $m->commit();
                return $this->successData('id:'.$id.' 成功。');
            }catch (\Exception $e)
            {
                $m->rollback();
                return $this->failData("操作失败");
            }
        }
    }


    /**
     * 反审核-不通过
     */
    public function cancel_appr(){
        if(IS_POST){
            $id = $this->_post('id');

            $m = new \addons\member\model\Recharge();
            $data = $m->getDetail($id);
            if(empty($data))
                $this->failData('数据异常');

            try{
                //更新充值状态：不通过
                $m->save([
                    'status' => 3,
                    'update_time' => NOW_DATETIME,
                ],[
                    'id' => $id,
                ]);
                return $this->successData('id:'.$id.' 成功。');
            } catch (\Exception $ex) {
                return $this->failData($ex->getMessage());
            }
        }
    }

}