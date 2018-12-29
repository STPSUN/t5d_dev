<?php
/**
 * Created by PhpStorm.
 * User: stp
 * Date: 2018/12/27
 * Time: 10:07
 */

namespace addons\member\user\controller;


class Pool extends \web\user\controller\AddonUserBase
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
//        $m = new \addons\eth\model\EthTradingOrder();
//        $m = new \addons\member\model\Agency();
        $m = new \addons\member\model\MemberOrePool();
        $total = $m->getTotal($filter);
        $rows = $m->getList($this->getPageIndex(), $this->getPageSize(), $filter);
        foreach ($rows as &$v)
        {
            $v['amount'] += $v['tax'];
        }
        $count_total = $m->getCountTotal($filter);
        return $this->toTotalDataGrid($total, $rows,$count_total);
    }

    public function appr() {
        if(IS_POST) {
            $m = new \addons\member\model\MemberOrePool();
            $data = $_POST;
            $id = $data['id'];

            $res = $m->save([
                    'status' => 2,
                    'update_time'   => NOW_DATETIME,
                ],[
                    'id' => $id,
                ]);
            if($res)
                return $this->successData();
            else
                return $this->failData();
        }
    }

    /**
     * 反审核-不通过
     */
    public function cancel_appr(){
        if(IS_POST){
            $id = $this->_post('id');
            $poolM = new \addons\member\model\MemberOrePool();
            $balanceM = new \addons\member\model\Balance();

            $pool = $poolM->getDetail($id);
            if(empty($pool))
            {
                return $this->failData('未查询到该记录');
            }

            $amount = $pool['amount'] + $pool['tax'];
            $balanceM->startTrans();
            try
            {
                $balanceM->updateBalance($pool['user_id'],$amount,$pool['coin_id'],true);
                $poolM->save([
                    'status' => 3,
                    'update_time'   => 3
                ],[
                    'id'    => $id
                ]);

                $balanceM->commit();
                return $this->successData();
            }catch (\Exception $e)
            {
                $balanceM->rollback();
                return $this->failData($e->getMessage());
            }

        }
    }
}


















