<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace addons\fomo\index\controller;

/**
 * Description of Crontab
 * f3d投注分配限制所选战队, p3d为所有
 * 类型：0=p3d分红,1=f3d分红
 * @author shilinqing
 */
class Crontab extends \web\common\controller\Controller {

    public function _initialize() {
        
    }

    public function excute() {
        $id = $this->_get('id');
        $queueM = new \addons\fomo\model\BonusSequeue();
        $data = $queueM->getUnSendData($id);
        if (!empty($data)) {
            try {
                $queueM->startTrans();
                if ($data['type'] == 1) {
                    //f3d分红,去除用户本身
                    $res = $this->sendF3d($data['user_id'], $data['coin_id'], $data['game_id'], $data['amount'], $data['scene'], $data['team_id']);
                } else {
                    //p3d分红
                    $res = $this->sendP3d($data['user_id'], $data['coin_id'], $data['amount'], $data['game_id'], $data['scene']);
                }
                if ($res) {
                    //更新发放状态
                    $data['status'] = 1;
                    $data['update_time'] = NOW_DATETIME;
                    $queueM->save($data);
                    $queueM->commit();
                    return json($this->successData());
                } else {
                    return json($this->failData('发放失败'));
                }
            } catch (\Exception $ex) {
                $queueM->rollback();
                return json($this->failData($ex->getMessage()));
            }
        } else {
            return $this->successData();
        }
    }

    public function excuteAll() {
        set_time_limit(0);
        try{
            $queueM = new \addons\fomo\model\BonusSequeue();
            $sequeue_list = $queueM->getUnAllSendData(1000);
            if (!empty($sequeue_list)) {
                foreach ($sequeue_list as $k => $data) {
                    try {
                        $queueM->startTrans();
                        if ($data['type'] == 1) {
                            //f3d分红,去除用户本身
                            $res = $this->sendF3d($data['user_id'], $data['coin_id'], $data['game_id'], $data['amount'], $data['scene'], $data['team_id']);
                        } else {
                            //p3d分红
                            $res = $this->sendP3d($data['user_id'], $data['coin_id'], $data['amount'], $data['game_id'], $data['scene']);
                        }
                        if ($res) {
                            echo 1 . '***';
                            //更新发放状态
                            $data['status'] = 1;
                            $data['update_time'] = NOW_DATETIME;
                            $queueM->save($data);
                            $queueM->commit();
                        } else {
                            echo 2 . '***';
                            $queueM->rollback();
                        }
                    } catch (\Exception $ex) {
                        echo '队列处理失败';
                        $queueM->rollback();
                    }
                }
                echo '队列处理成功';
            } else {
                echo '无队列';
            }
        } catch (\Exception $ex) {
            echo '队列处理失败';
            return $this->failData($ex->getMessage());
        }
    }

    /**
     * 代理奖励发放
     */
    public function agencyAward() {
        set_time_limit(0);
        $agencyAwardM = new \addons\fomo\model\AgencyAward();
        $balanceM = new \addons\member\model\Balance();
        $rewardM = new \addons\fomo\model\RewardRecord();
        $data = $agencyAwardM->where('status', 1)->limit(1000)->select();
        foreach ($data as $v) {
            try {
                $agencyAwardM->startTrans();
                $agencyAwardM->save([
                    'status' => 2,
                    'update_time' => NOW_DATETIME,
                        ], [
                    'id' => $v['id'],
                ]);

                $user_id = $v['user_id'];
                $amount = $v['amount'];
                $coin_id = $v['coin_id'];
                $game_id = $v['game_id'];
                $remark = '代理分红';
                $type = 5;
                //添加余额, 添加分红记录
                $balance = $balanceM->updateBalance($user_id, $amount, $coin_id, true,BALANCE_TYPE_3);
                if ($balance != false) {
                    $before_amount = $balance['before_amount'];
                    $after_amount = $balance['amount'];
                    $rewardM->addRecord($user_id, $coin_id, $before_amount, $amount, $after_amount, $type, $game_id, $remark);
                }

                $agencyAwardM->commit();
            } catch (\Exception $e) {
                $agencyAwardM->rollback();
                echo '代理分红处理失败';
            }
        }

        echo '代理分红处理成功';
    }

    private function sendF3d($user_id, $coin_id, $game_id, $amount, $scene, $team_id) {
        $keyRecordM = new \addons\fomo\model\KeyRecord();
        $balanceM = new \addons\member\model\Balance();
        $rewardM = new \addons\fomo\model\RewardRecord();
        $unCountM = new \addons\fomo\model\UncountRecord();//计算失效分红记录表
        if ($scene == 2) {
            //开奖分红 查询战队成员key总数 and record
            $record_list = $keyRecordM->getRecordWithOutUserID($user_id, $game_id, $team_id);
            $total_key = $keyRecordM->getCrontabTotalByGameID($game_id, $team_id, $user_id);
            $type = 1; //1=胜利战队分红
            $remark = '胜利战队分红';
        } else {
            //查询拥有key的所有user
            $record_list = $keyRecordM->getRecordWithOutUserID($user_id, $game_id);
            $total_key = $keyRecordM->getCrontabTotalByGameID($game_id, '',$user_id);
            $type = 0; //奖励类型 0=投注分红
            $remark = '欲望之岛投注分红';
        }
        if (!empty($record_list)) {
            foreach ($record_list as $k => $record) {
                $user_id = $record['user_id'];
                $key_num = $record['key_num'];
                if ($key_num <= 0)
                    continue;
                $rate = $this->getUserRate($total_key, $key_num);
//                echo $amount . '/' . $key_num;exit();
                $_amount = $amount * $rate;
                $_amount = $this->keyLimit($user_id, $game_id, $coin_id, $_amount,$key_num);
                //添加余额, 添加分红记录
                $balance = $balanceM->updateBalance($user_id, $_amount, $coin_id, true,BALANCE_TYPE_3);
                if ($balance != false) {
                    $before_amount = $balance['before_amount'];
                    $after_amount = $balance['amount'];
                    $rewardM->addRecord($user_id, $coin_id, $before_amount, $_amount, $after_amount, $type, $game_id, $remark);
                }

            }
        }
        return true;
    }

    private function keyLimit($user_id, $game_id, $coin_id, $amount,$user_key) {
        $recordM = new \addons\member\model\TradingRecord();
        $keyRecordM = new \addons\fomo\model\KeyRecord();
        $unCountM = new \addons\fomo\model\UncountRecord();//计算失效分红记录表
        $record_list = $recordM->getBuyKeyRecord($user_id, $game_id, $coin_id);
        if (empty($record_list))
            return 0;

        $bonus_amount = 0;  //分红金额
        $total_lose_key_num = 0;    //失效钥匙总数量
        $current_amount = $amount;  //当前分红值
        $temp = 0;
        foreach ($record_list as $v) {
            //每把key的封顶值
            $user_total_limit = $keyRecordM->getTotalLimit($user_id,$game_id);//用户总封顶金额
            $single_limit_amount = $user_total_limit / $user_key; //当前每把钥匙的封顶值
            $single_limit_amount = round($single_limit_amount, 4);//保留4位
//            $key_bonus_limit = bcdiv($v['bonus_limit'], $v['key_num'], 8);
            $uncount_amount_data = $unCountM->getUserUnCount($user_id, $game_id);//当前用户分红累计额

            if(!empty($uncount_amount_data['num']))
            {
                $amount += $uncount_amount_data['num'];
            }
            //判断单个key的封顶值是否大于分红
//            echo $single_limit_amount . '/' . $amount . '*';
            if ($single_limit_amount > $amount)
            {
                if($temp == 0)  //第一次循环
                {
                    $bonus_amount += $current_amount;
                }else
                {
                    $bonus_amount += $amount;
                }
//                echo $bonus_amount;exit();
                $temp++;
                $record_list_less = $recordM->getBuyKeyRecord($user_id,$game_id,$coin_id);
                $record_num = count($record_list_less);
//                echo $record_num;exit();
                if($record_num < 1)
                {
                    break;
                }

//                echo $amount;exit();
//                $less_bonus_num = $single_limit_amount - $amount;
                $unCountM->save([
                    'num'    => $amount,
                    'update_time' => NOW_DATETIME,
                ],[
                    'game_id'   => $game_id,
                    'user_id'   => $user_id,
                ]);
//                $recordM->where('id',$v['id'])->setDec('bonus_limit',$amount);
                $amount = 0;
                break;
            }

            $temp++;

            //失效key = 分红金额/当个key的封顶值 取整
            $lose_key_num = bcdiv($amount,$single_limit_amount);
//            if ($lose_key_num < 1) {
//                //足够扣除,直接return
//                $bonus_amount += $amount;
//                break;
//            }

            //当前记录钥匙数量
            $record_key_num = $recordM->where('id',$v['id'])->value('key_num');
            if($record_key_num < $lose_key_num)
            {
                $lose_key_num = $record_key_num;
            }

            $total_limit = $single_limit_amount * $lose_key_num;    //当前记录减少的封顶金额

            //剩余分红值 = 分红总值（当前分红值+剩余分红值） - 封顶金额
            $amount -= $total_limit;
            $recordM->where('id', $v['id'])->setDec('key_num', $lose_key_num);    //当前记录key减少
            $total_limit = $total_limit - $uncount_amount_data['num'];
            $bonus_amount += $total_limit;

            $record_list_less = $recordM->getBuyKeyRecord($user_id,$game_id,$coin_id);
            $record_num = count($record_list_less);
            if($record_num > 0)
            {
                $unCountM->save([
                    'num'    => $amount,
                    'update_time' => NOW_DATETIME,
                ],[
                    'game_id'   => $game_id,
                    'user_id'   => $user_id,
                ]);
            }else
            {
                $unCountM->save([
                    'num'    => 0,
                    'update_time' => NOW_DATETIME,
                ],[
                    'game_id'   => $game_id,
                    'user_id'   => $user_id,
                ]);
            }

            $amount = 0;
            $total_lose_key_num += $lose_key_num;
        }

        if($amount != 0){
            $where['user_id'] = $user_id;
            $where['game_id'] = $game_id;
            $less_key_num = $recordM->where($where)->sum('key_num');
            if($less_key_num > 0){
                $bonus_amount += $amount;
            }
        }
//        echo $bonus_amount . '/' . $total_lose_key_num . '/' . $user_id;exit();
        if($total_lose_key_num > 0){
            //钥匙失效
//            dump($total_lose_key_num);exit;
            $res = $keyRecordM->updateKeyNum($user_id, $game_id, $total_lose_key_num);
        }
        return $bonus_amount;
    }

    private function keyLimit2($user_id, $game_id, $coin_id, $amount) {
        $recordM = new \addons\member\model\TradingRecord();
        $keyRecordM = new \addons\fomo\model\KeyRecord();
        $record_list = $recordM->getBuyKeyRecord($user_id, $game_id, $coin_id);
        if (empty($record_list))
            return 0;

        $bonus_amount = 0;  //分红金额
        $total_lose_key_num = 0;    //失效钥匙总数量

//        echo $amount;exit();
        foreach ($record_list as $v) {
            //每把key的封顶值
            $key_bonus_limit = bcdiv($v['bonus_limit'], $v['key_num'], 8);
            //判断单个key的封顶值是否大于分红
            if ($key_bonus_limit > $amount)
                break;

            //失效key = 分红金额/当个key的封顶值 取整
            $lose_key_num = bcdiv($amount,$key_bonus_limit);
            if ($lose_key_num < 1) {
                //足够扣除,直接return
                $bonus_amount += $amount;
                break;
            }

            //当前记录钥匙数量
            $record_key_num = $recordM->where('id',$v['id'])->value('key_num');
            //如果记录钥匙数量 小于 应该失效的数量
            if($record_key_num < $lose_key_num)
            {
                $lose_key_num = $record_key_num;
            }

            $total_limit = $key_bonus_limit * $lose_key_num;    //当前记录减少的封顶金额
            $recordM->where('id', $v['id'])->setDec('key_num', $lose_key_num);    //当前记录key减少
            $recordM->where('id', $v['id'])->setDec('bonus_limit', $total_limit); //当天记录封顶金额减少
            $bonus_amount += $total_limit;
            //剩余分红值
            $amount -= $total_limit;
            $total_lose_key_num += $lose_key_num;
        }
        if($amount != 0){
            $where['user_id'] = $user_id;
            $where['game_id'] = $game_id;
            $less_key_num = $recordM->where($where)->sum('key_num');
            if($less_key_num > 0){
                $bonus_amount += $amount;
            }
        }
        if($total_lose_key_num > 0){
            //钥匙失效
            $res = $keyRecordM->updateKeyNum($user_id, $game_id, $total_lose_key_num);
        }
        return $bonus_amount;
    }


    /**
     * 发放P3d分红 
     * @param type $coin_id
     * @param type $amount
     * @param type $game_id
     * @param type $scene 场景id 场景:0=p3d购买，1=f3d投注分配，2=f3d开奖分配'
     * @return boolean
     */
    private function sendP3d($user_id, $coin_id, $amount, $game_id, $scene) {
        $tokenRecordM = new \addons\fomo\model\TokenRecord();
        $balanceM = new \addons\member\model\Balance();
        $rewardM = new \addons\fomo\model\RewardRecord();
        $total_amount = $tokenRecordM->getTotalToken(); //p3d总额
        if ($scene == 0) {
            $user_token = $tokenRecordM->getTotalToken($user_id);
            $total_amount = $total_amount - $user_token;
        }
        $filter = '';
        if ($scene == 0) {
            //场景:0=p3d购买
            $filter = 'user_id !=' . $user_id;
        }
        $user_list = $tokenRecordM->getDataList(-1, -1, $filter, 'id,user_id,token', 'id asc');
        if (!empty($user_list)) {
            foreach ($user_list as $k => $user) {
                if ($user['token'] <= 0) {
                    continue;
                }
                $user_id = $user['user_id'];
                $rate = $this->getUserRate($total_amount, $user['token']);
                $_amount = $amount * $rate; //所得分红
                //添加余额, 添加分红记录
                $balance = $balanceM->updateBalance($user_id, $_amount, $coin_id, true,BALANCE_TYPE_3);
                if ($balance != false) {
                    $before_amount = $balance['before_amount'];
                    $after_amount = $balance['amount'];
                    $type = 6; //奖励类型6
                    $remark = '福利之岛投注分红';
                    $rewardM->addRecord($user_id, $coin_id, $before_amount, $_amount, $after_amount, $type, $game_id, $remark);
                }
            }
        }
        return true;
    }

    /**
     * 计算用户所拥有的key/token 数量占全部的百分比
     * @param type $total_amount
     * @param type $amount
     * @return type
     */
    private function getUserRate($total_amount, $amount) {
        return $amount / $total_amount;
    }

}
