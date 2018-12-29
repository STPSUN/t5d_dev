<?php
/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/11/6
 * Time: 14:41
 */

namespace addons\member\user\controller;


class Agency extends \web\user\controller\AddonUserBase
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
    $m = new \addons\member\model\Agency();
    $total = $m->getTotal($filter);
    $rows = $m->getList($this->getPageIndex(), $this->getPageSize(), $filter);
    $count_total = $m->getCountTotal($filter);
    return $this->toTotalDataGrid($total, $rows,$count_total);
}

    public function edit() {
        if(IS_POST) {
            $m = new \addons\member\model\Agency();
            $memberM = new \addons\member\model\MemberAccountModel();
            $data = $_POST;
            $id = $data['id'];
            $level = $data['level'];

            $agency = $m->getDetail($id);
            $m->startTrans();
            try
            {
                $m->save([
                    'level' => $level,
                    'status' => 2,
                    'update_time'   => NOW_DATETIME,
                ],[
                    'id' => $id,
                ]);

                $memberM->save([
                    'agency_level' => $level,
                ],[
                    'id' => $agency['user_id'],
                ]);

                $m->commit();
                return $this->successData();
            }catch (\Exception $e)
            {
                $m->rollback();
                return $this->failData();
            }
        }else
        {
            $this->assign('id',$this->_get('id'));
            return $this->fetch();
        }
    }

    /**
     * 反审核-不通过
     */
    public function cancel_appr(){
        if(IS_POST){
            $m = new \addons\member\model\Agency();
            $id = $this->_post('id');
            $res = $m->save([
                'status' => 3,
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
}













