<?php

/**
 * Created by PhpStorm.
 * User: mr_z
 * Date: 2018/8/10
 * Time: 上午9:49
 */

namespace addons\eth\user\controller;

class Crontab extends \web\common\controller\BaseController {
    /*
     * 提取子账号eth到主账号
     */
    public function clientEthTosys() {
        $m = new \addons\eth\model\EthTradingOrder();
        $paramM = new \web\common\model\sys\SysParameterModel();
        $sys_address = $paramM->getValByName('out_address');
        $filter = "address is not null";
        $list = $m->getRechargeByCoin(1);

        //初始化参数 eth api
        //初始化参数 eth api
        $msg = '';
        $ethApi = $this->_initArguments($msg);
        if($ethApi == false){
            return json($msg);
        }
        $addr = array();
        foreach ($list as $key => $val) {
            if(!$val['address'] || !$val['eth_pass'])
                continue;
            $addr[$val['address']] = $val["eth_pass"];
            if (count($addr) > 0 && ( $key % 20 == 0 ||  $key == count($list) - 1 ) ) {
                $addrs = join(",", array_keys($addr));
                $ret = $this->_getAddressFigure($addrs);
                if (!$ret['success'] || intval($ret['data']['status']) !== 1){
                    //获取地址余额失败
                    echo "address: {$addrs} get address asset fail";
                    echo "\r\n";
                    continue;
                }
                $result = $ret['data']['result'];
                foreach ($result as $v) {
                    $balance = $v["balance"] / bcpow(10, 18);
                    if ($balance > 0.001) {
                        $account = array(
                            'address' => $v['account'],
                            'password' => $addr[$v['account']]
                        );
                        $amount = $balance - 0.001;
                        $ethApi->client_account = $account;
                        $ret = $ethApi->send($sys_address, $amount, '', 18);
                        if (!$ret['success']) {
                            //转账失败信息
                            echo "address: {$v['account']} transfer error:" . $ret['message'];
                            echo "\r\n";
                        }else{
                            //转账成功显示hash串
                            echo "address: {$v['account']} transfer success:" . $ret['data'];
                            echo "\r\n";
                        }
                    }else{
                        //余额不足转账
                        echo "address: {$v['account']} not sufficient funds";
                        echo "\r\n";
                        continue;
                    }
                }
                $addr = [];
            }
        }
        echo "success";
    }

    private function _getAddressFigure($addrs) {
        $url = "https://api.etherscan.io/api?module=account&action=balancemulti&address={$addrs}&tag=latest";
        $data = http($url, null, 'GET');
        return $data;
    }

    public function reloadOrderStatus(){
        set_time_limit(0);
        $m = new \addons\eth\model\EthTradingOrder();
        $filter = "status = 3";
        $list = $m->getList(0,0, $filter);
        $arr = [];
        foreach($list as $val){
            $ret = $this->_getOrderStatus($val['txhash']);
            $arr[] = $ret;
            $now_time = time();
            $order_time = strtotime($val['update_time']);
            $time = $now_time - $order_time;
            if($time > 86300  && (!$ret['success'] || !$ret['data']['result']) ){
                $ret = $m->updateStatus($val['id'], -2, NOW_DATETIME, '', "订单同步失败");
            }elseif($ret['data']['result'] &&  $ret['data']['result']['status'] !== "0x1"){
                $ret = $m->updateStatus($val['id'], -2, NOW_DATETIME, '', "订单hash同步失败");
            }elseif($ret['data']['result'] && $ret['data']['result']['status'] == "0x1"){
                $ret = $m->updateStatus($val['id'], 1, NOW_DATETIME, '', "订单转出同步成功");
            }
        }
        return json($arr);
//        return "success";

    }

    private function _getOrderStatus($txid){
        $url ="https://api.etherscan.io/api?module=proxy&action=eth_getTransactionReceipt&txhash={$txid}";
        $data = http($url, null, 'GET');
        return $data;
    }

}
