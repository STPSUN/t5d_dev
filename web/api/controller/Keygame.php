<?php

namespace web\api\controller;

use addons\fomo\model\Game;
use addons\fomo\model\TokenRecord;
use addons\member\model\MemberAccountModel;
use think\Exception;
use web\common\model\sys\SysParameterModel;

class Keygame extends \web\api\controller\ApiBase {

    private $rate;
    
    public function test(){
        if(1.3 % 1 != 0){
            return 1.1%1;
        }else{
            return 1;
        }
    }

    public function getGame() {
        $m = new \addons\fomo\model\Game();
        $game = $m->getRunGame();
        if (empty($game)) {
            //如果有已结束的 查询已结束的游戏与 开奖结果
            $end_game = $m->getLastEndGame();
            if (!empty($end_game)) {
                $game = $end_game[0];
                $recordM = new \addons\fomo\model\RewardRecord();
                $last_winner = $recordM->getGameWinner($game['id']);
                $game['last_winner'] = $last_winner;
            } else {
                return $this->failJSON('等待开启新一轮');
            }
        } else {
            $game = $game[0];
        }
        $end_game_time = $game['end_game_time'];
        if ($end_game_time <= time()) {
            try {
                $m->startTrans();
                //更新游戏状态为结束 status = 2 
                $game['status'] = 2;
                $is_over = $m->save($game);
                if ($is_over > 0) {
                    $game_id = $game['id'];
                    $coin_id = $game['coin_id'];
                    $pool_total_amount = $game['pool_total_amount']; //奖池总数
                    //获取最后投注者
                    $keyRecordM = new \addons\fomo\model\KeyRecord();
                    $last_winner = $keyRecordM->getLastWinner($game_id);
                    $winner_user_id = $last_winner['user_id'];
                    //本轮游戏胜利者比率
                    $winner_amount = $this->countRate($pool_total_amount, $game['winner_rate']); //胜利者所得
                    //更新用户余额 , 添加分红记录 type = 2
                    $balanceM = new \addons\member\model\Balance();
                    $balance = $balanceM->updateBalance($winner_user_id, $winner_amount, $coin_id, true,BALANCE_TYPE_3);
                    $recordM = new \addons\fomo\model\RewardRecord();
                    $before_amount = $balance['before_amount'];
                    $after_amount = $balance['amount'];
                    $type = 2;
                    $remark = '胜利者分红';
                    $recordM->addRecord($winner_user_id, $coin_id, $before_amount, $winner_amount, $after_amount, $type, $game_id, $remark);

                    $winner_team_id = $last_winner['team_id']; //胜利者队伍id
                    if (empty($winner_team_id)) {
                        $m->rollback();
                        return $this->failJSON('胜利战队异常');
                    }
                    //获取战队游戏结束分红配置
                    $teamM = new \addons\fomo\model\Team();
                    $end_game_config = $teamM->getConfigByFields($winner_team_id, 'to_next_rate,end_f3d_rate,end_p3d_rate');
                    $to_next_amount = 0;
                    if ($end_game_config['to_next_rate'] > 0) {
                        $to_next_amount = $this->countRate($pool_total_amount, $end_game_config['to_next_rate']);
                        //更新进入下一局金额
                        $game['to_next_amount'] = $to_next_amount;
                        $m->save($game);
                    }
                    $queueM = new \addons\fomo\model\BonusSequeue();
                    $end_f3d_amount = 0;
                    if ($end_game_config['end_f3d_rate'] > 0) {
                        $end_f3d_amount = $this->countRate($pool_total_amount, $end_game_config['end_f3d_rate']);
                        //添加队列 scene = 2 ,type=1
                        $type = 1;
                        $scene = 2;
                        $queueM->addSequeue($winner_user_id, $coin_id, $end_f3d_amount, $type, $scene, $game_id, $winner_team_id);
                    }
                    $end_p3d_amount = 0;
                    if ($end_game_config['end_p3d_rate'] > 0) {
                        $end_p3d_amount = $this->countRate($pool_total_amount, $end_game_config['end_p3d_rate']);
                        //添加队列 scene = 2 ,type=0
                        $type = 0;
                        $scene = 2;
                        $queueM->addSequeue($winner_user_id, $coin_id, $end_p3d_amount, $type, $scene, $game_id);
                    }
                    $m->commit();
                    return $this->failJSON('游戏已结束');
                }
            } catch (\Exception $ex) {
                $m->rollback();
                return $this->failJSON($ex->getMessage());
            }
        }
        $KeyRecordM = new \addons\fomo\model\KeyRecord();
        $filter = "game_id = {$game['id']}";
        $total_key = $KeyRecordM->getSum($filter, 'key_num');
        $game['total_key'] = $total_key;

        //获取战队与战队的总额
        $tM = new \addons\fomo\model\Team();
        $game['team'] = $tM->getTeamWithTotal($game['id']);
        //获取当前key价,递增参数,空投进度
        $KeyPriceM = new \addons\fomo\model\KeyPrice();
        $game['drop'] = $KeyPriceM->getDropByGameID($game['id']);

        //换算汇率
//        $maketM = new \web\api\model\MarketModel();
//        $rate = $maketM->getCnyRateByCoinId($game['coin_id']);
        $sysM = new SysParameterModel();
        $rate = $sysM->getValByName('token_rate');
        $game['cny_rate'] = $rate;

        return $this->successJSON($game);
    }

    public function getDropRule() {
        $m = new \addons\fomo\model\Airdrop();
        $data = $m->getRuleOrderBy();
        return $this->successJSON($data);
    }

    public function buy() {
        if (IS_POST) {
            //投注 需要验证
            $game_id = $this->_post('game_id');
            $team_id = $this->_post('team_id');
            $key_num = $this->_post('key_num/d', 1); //数量
            //是否有上级,余额是否足够,是否空投
            $gameM = new \addons\fomo\model\Game();
            $game = $gameM->getDetail($game_id);
            $end_game_time = $game['end_game_time'];
            if ($end_game_time <= time()) {
                return $this->failJSON('游戏已经结束');
            }
            $teamM = new \addons\fomo\model\Team();
            $team_config = $teamM->getConfigByFields($team_id);
            if (empty($team_config)) {
                return $this->failJSON('所选团队不存在');
            }
            $coin_id = $game['coin_id']; //币种
            $user_id = $this->user_id;
            $balanceM = new \addons\member\model\Balance();
//            $balance = $balanceM->getBalanceByCoinID($this->user_id, $coin_id);
//            if (empty($balance)) {
//                return $this->failJSON('余额不足');
//            }
            $priceM = new \addons\fomo\model\KeyPrice();
            $current_price_data = $priceM->getGameCurrentPrice($game_id); //游戏当前价格
            $current_price = $current_price_data['key_amount'];
//            $key_total_price = $key_num * $current_price; //总价
//            if ($key_total_price > $balance['amount']) {
//                return $this->failJSON('余额不足');
//            }

            $balanceS = new \addons\fomo\service\Balance();
            $key_total_price = $balanceS->isEnoughBalance($user_id,$key_num,$coin_id,$current_price);
//            echo $key_total_price;exit();
            if(!$key_total_price)
                return $this->failJSON('余额不足');

            $keyRecordM = new \addons\fomo\model\KeyRecord(); //用户key记录
            $ruleM = new \addons\fomo\model\KeyConf();
            $key_rule = $ruleM->getDetail($game['rule_id']); //key规则数组
            if ($key_rule['limit'] > 0) {
                if ($key_rule['unfreeze'] > 0 && $game['total_amount'] < $key_rule['unfreeze']) {
                    //判断总投资额是否达到解除限制额度
                    //如果未达到,判断用户所购买额度是否已超过限制额度(超出额度不予购买,只添加到未达到额度的钥匙)
                    $user_before_eth = $keyRecordM->getUserTotalEthByGameID($this->user_id, $game_id);
                    if (($user_before_eth + $key_total_price) > $key_rule['limit']) {
                        return $this->failJSON('超出限购金额,限购金额为:' . $key_rule['limit'] . '个ETH,当前已购:' . $user_before_eth . '个ETH');
                    }
                }
            }
            $gameM->startTrans();
            try {
                //更新用户购买金额
                $this->updateTokenNum($this->user_id, $key_total_price);
                //扣除用户余额
//                $balance['before_amount'] = $balance['amount'];
//                $balance['amount'] = $balance['amount'] - $key_total_price;
//                $balance['update_time'] = NOW_DATETIME;
//                $balanceM->save($balance);

                $balanceS->updateBalance($user_id,$key_total_price,$coin_id);

                //添加交易记录
                $recordM = new \addons\member\model\TradingRecord();
                $type = 10;
                $before_amount = 0;
                $after_amount = 0;
                $change_type = 0; //减少
                $remark = '购买key';
                $r_id = $recordM->addRecord($this->user_id, $coin_id, $key_total_price, $before_amount, $after_amount, $type, $change_type, '', '', '', $remark, $game_id, $team_id,$key_num);
                if (!$r_id) {
                    $gameM->rollback();
                    return $this->failJSON('购买失败');
                }
                $userM = new \addons\member\model\MemberAccountModel();
                $pid = $userM->getPID($this->user_id);
                if (!empty($pid)) {
                    //多级分红
                    $invite_rate = explode(",", $key_rule['invite_rate']);
                    foreach ($invite_rate as $val) {
                        if (!$val) {
                            continue;
                        }
                        $invite_amount = $this->countRate($key_total_price, $val); //邀请奖励
                        $pidBalance = $balanceM->updateBalance($pid, $invite_amount, $coin_id, true,BALANCE_TYPE_3);
                        if ($pidBalance) {
                            $rewardM = new \addons\fomo\model\RewardRecord();
                            $before_amount = $pidBalance['before_amount'];
                            $after_amount = $pidBalance['amount'];
                            $type = 3; //奖励类型 0=投注分红，1=胜利战队分红，2=胜利者分红，3=邀请分红
                            $remark = '邀请推荐分红';
                            $rewardM->addRecord($pid, $coin_id, $before_amount, $invite_amount, $after_amount, $type, $game_id, $remark);
                            $pid = $userM->getPID($pid);
                            if (empty($pid))
                                break;
                        }
                    }
                }
                $is_drop = $this->getAirDrop($key_total_price, $game_id, $coin_id, $game['drop_total_amount']);
                if ($is_drop == false) {
                    $gameM->rollback();
                    return $this->failJSON('空投失败');
                }
                unset($game['drop_total_amount']);
                unset($game['already_drop_amount']);
                //战队:投注p3d,f3d奖励队列,奖池+,用户key+,时间+
                $pool_amount = $this->countRate($key_total_price, $team_config['pool_rate']); //进入奖池金额
                $release_amount = $key_total_price - $pool_amount; //已发金额
                $buy_inc_second = $key_rule['time_multi'];
                $inc_time = $key_num * $buy_inc_second; //游戏增加时间

                $time = time();
                $end_game_time = $game['end_game_time'] + $inc_time;
                if (($end_game_time - $time ) > 3600 * $game['hour'])
                    $end_game_time = $time + 3600 * $game['hour'];
                //更新数据
                //奖池+ ,时间+
                $game['end_game_time'] = $end_game_time;
                $game['total_buy_seconds'] = $game['total_buy_seconds'] + $inc_time;
                $game['total_amount'] = $game['total_amount'] + $key_total_price;
                $game['pool_total_amount'] = $game['pool_total_amount'] + $pool_amount;
                $game['release_total_amount'] = $game['release_total_amount'] + $release_amount;
                $game['update_time'] = NOW_DATETIME;
                $gameM->save($game);
                //战队总额+
                $teamTotalM = new \addons\fomo\model\TeamTotal();
                $team_total = $teamTotalM->getDataByWhere($team_id, $game_id, $coin_id);
                $team_total['before_total_amount'] = $team_total['total_amount'];
                $team_total['total_amount'] = $team_total['total_amount'] + $key_total_price;
                $team_total['update_time'] = NOW_DATETIME;
                $teamTotalM->save($team_total);

                //战队:投注p3d,f3d奖励队列
                $sequeueM = new \addons\fomo\model\BonusSequeue();

                if ($team_config['p3d_rate'] > 0) {
                    $tokenRecordM = new \addons\fomo\model\TokenRecord();
                    $token_total = $tokenRecordM->getTotalToken();
                    if ($token_total > 0) {
                        $p3d_amount = $this->countRate($key_total_price, $team_config['p3d_rate']); //发放给p3d用户金额
                        $sequeueM->addSequeue($this->user_id, $coin_id, $p3d_amount, 0, 1, $game_id);
                    }
                }
                //用户key+
                $save_key = $keyRecordM->saveUserKey($this->user_id, $team_id, $game_id, $key_num, $key_total_price);
                $key_total = $keyRecordM->getCrontabTotalByGameID($game_id);
                if ($key_total > 1) {//判断是否非第一笔
                    $f3d_amount = $this->countRate($key_total_price, $team_config['f3d_rate']); //发放给f3d用户金额
                    //用户购买分配自己 : 然后f3d_amount - 分配给自己的 = 队列要处理的金额
                    $f3d_amount = $this->_sendToSelf($this->user_id, $game_id, $coin_id, $f3d_amount);
                    if($f3d_amount > 0){
                        $sequeueM->addSequeue($this->user_id, $coin_id, $f3d_amount, 1, 1, $game_id);
                    }
                }
                
                //key 价格+
                $current_price_data['key_amount'] = $current_price + $key_rule['multi'];
                $current_price_data['update_time'] = NOW_DATETIME;
                $priceM->save($current_price_data);

                //代理奖励
                $this->agencyAward($this->user_id, $key_total_price, $game_id, $coin_id);
                $gameM->commit();
                return $this->successJSON();
            } catch (\Exception $ex) {
                $gameM->rollback();
                return $this->failJSON($ex->getMessage());
            }
        }
    }

    private function agencyAward($user_id,$amount,$game_id,$coin_id)
    {
        $use_rate = 0;
        $agency_level = 0;
        $memberM = new \addons\member\model\MemberAccountModel();
        $user = $memberM->getDetail($user_id);
        if(empty($user['pid']))
            return;

        $puers = $this->getParentUser($user['pid']);

        foreach ($puers as $k => $v)
        {
            switch ($v['agency_level'])
            {
                case 1:
                {
                    if($agency_level >= 1)
                        break;

                    $agency_level = 1;
                    $use_rate = 0.02;
                    $rate = 0.02;
                    $this->agencySeueqe($v['user_id'], 10000, $amount, $rate, $game_id, $coin_id,$user_id);
                    break;
                }
                case 2:
                {
                    if($agency_level >= 2)
                        break;

                    $agency_level = 2;
                    $rate = 0.04 - $use_rate;
                    $use_rate = 0.04;
                    $this->agencySeueqe($v['user_id'], 50000, $amount, $rate, $game_id, $coin_id, $user_id);
                    break;
                }
                case 3:
                {
                    if($agency_level >= 3)
                        break;

                    $agency_level = 3;
                    $rate = 0.06 - $use_rate;
                    $use_rate = 0.06;
                    $this->agencySeueqe($v['user_id'], 500000, $amount, $rate, $game_id, $coin_id,$user_id);
                    break;
                }

                case 4:
                {
                    if($agency_level >= 4)
                        break;

                    $agency_level = 4;
                    $rate = 0.08 - $use_rate;
                    $use_rate = 0.08;
                    $this->agencySeueqe($v['user_id'], 1000000, $amount,$rate, $game_id, $coin_id,$user_id);
                    break;
                }
                case 5:
                {
                    if($agency_level >= 5)
                        break;

                    $agency_level = 5;
                    $rate = 0.1 - $use_rate;
                    $use_rate = 0.1;
                    $this->agencySeueqe($v['user_id'], 3000000, $amount,$rate, $game_id, $coin_id,$user_id);
                    break;
                }
            }
        }
    }

    /**
     * 获取上级会员
     */
    private function getParentUser($pid,&$pUsers=array())
    {
        $userM = new \addons\member\model\MemberAccountModel();
        $puser = $userM->getDetail($pid);

        $temp = array(
            'user_id' => $puser['id'],
            'agency_level' => $puser['agency_level']
        );
        $pUsers[] = $temp;

        $user = $userM->getDetail($puser['pid']);
        if(!empty($user))
        {
            $this->getParentUser($user['id'],$pUsers);
        }

        return $pUsers;
    }

    private function getBuyAmount($user_id,&$amount=0)
    {
        $userM = new \addons\member\model\MemberAccountModel();
        $users = $userM->where('pid','in',$user_id)->column('id');
        $user_ids = '';
        foreach ($users as $u)
        {
            $user_ids .= $u . ',';
        }

        $user_ids = rtrim($user_ids,',');
        $keyRecordM = new \addons\fomo\model\KeyRecord();
        $amount += $keyRecordM->where('user_id','in',$user_ids)->sum('eth');
        $users = $userM->where('pid','in',$user_ids)->column('id');
        if(!empty($users))
        {
            $this->getBuyAmount($user_ids,$amount);
        }

        return $amount;
    }

    /**
     * 代理奖励
     */
    private function agencyAward2($user_id, $amount, $game_id, $coin_id) {
        $memberM = new \addons\member\model\MemberAccountModel();
        $user = $memberM->getDetail($user_id);

        if (empty($user['pid']))
            return;

        $pOne = $memberM->getDetail($user['pid']);
        $one_level = $pOne['agency_level'];

        $this->rate = 0;
        if ($one_level > 0) {
            switch ($one_level) {
                case 1:
                    $this->agencySeueqe($user['pid'], 100, $amount, 0.01, $game_id, $coin_id, 1, $user_id);
                    break;
                case 2:
                    $this->agencySeueqe($user['pid'], 300, $amount, 0.03, $game_id, $coin_id, 1, $user_id);
                    break;
                case 3:
                    $this->agencySeueqe($user['pid'], 1000, $amount, 0.05, $game_id, $coin_id, 1, $user_id);
                    break;
                case 4:
                    $this->agencySeueqe($user['pid'], 1000, $amount, 0.05, $game_id, $coin_id, 1, $user_id);
                    break;
                case 5:
                    $this->agencySeueqe($user['pid'], 1000, $amount, 0.05, $game_id, $coin_id, 1, $user_id);
                    break;
            }
        }

        $pTwo = $memberM->getDetail($pOne['pid']);
        if (empty($pTwo))
            return;
        $two_level = $pTwo['agency_level'];
        if ($two_level <= $one_level)
            return;

        switch ($two_level) {
            case 1:
                $this->agencySeueqe($pOne['pid'], 100, $amount, 0.01, $game_id, $coin_id, 2, $user_id);
                break;
            case 2:
                $this->agencySeueqe($pOne['pid'], 300, $amount, 0.03, $game_id, $coin_id, 2, $user_id);
                break;
            case 3:
                $this->agencySeueqe($pOne['pid'], 1000, $amount, 0.05, $game_id, $coin_id, 2, $user_id);
                break;
        }
    }

    /**
     * 获取伞下总投注数量
     */
    public function getBuyAmount2($user_id) {
        $keyRecordM = new \addons\fomo\model\KeyRecord();
        $memberM = new \addons\member\model\MemberAccountModel();
        $one_users = $memberM->where('pid', $user_id)->column('id');
        if (empty($one_users))
            return 0;

        $one_ids = '';
        foreach ($one_users as $v) {
            $one_ids .= $v . ',';
        }
        $one_ids = rtrim($one_ids, ',');
        $amount = $keyRecordM->where('user_id', 'in', $one_ids)->sum('eth');

        $two_users = $memberM->where('pid', 'in', $one_ids)->column('id');
        if (empty($two_users))
            return $amount;

        $two_ids = '';
        foreach ($two_users as $v) {
            $two_ids .= $v . ',';
        }
        $two_ids = rtrim($two_ids, ',');
        $two_amount = $keyRecordM->where('user_id', 'in', $two_ids)->sum('eth');
        $amount += $two_amount;

        return $amount;
    }

    /**
     * 代理奖励加入队列
     */
    private function agencySeueqe($user_id, $need_amount, $amount, $user_rate, $game_id, $coin_id,$from_user_id) {
//        echo $user_id . '/';
//        $total_amount = $this->getBuyAmount($user_id);
//        echo $total_amount . '/' . $need_amount . '***';
//        if ($total_amount < $need_amount)
//            return true;

        $amount = bcmul($amount, $user_rate, 4);
        $agencyAwardM = new \addons\fomo\model\AgencyAward();
        $data = array(
            'user_id' => $user_id,
            'from_user_id' => $from_user_id,
            'game_id' => $game_id,
            'coin_id' => $coin_id,
            'amount' => $amount,
            'status' => 1,
            'update_time' => NOW_DATETIME,
        );

        $res = $agencyAwardM->add($data);
        if ($res)
            return true;
        else
            return false;
    }

    /**
     * @param $user_id
     * @param $amount
     */
    private function updateBuyAmount($user_id, $amount) {
        $buyAmountM = new \addons\fomo\model\BuyAmount();
        $buyAmount = $buyAmountM->where('user_id', $user_id)->find();
        if (empty($buyAmount)) {
            $data = array(
                'user_id' => $user_id,
                'amount' => $amount,
                'total_amount' => $amount,
                'update_time' => NOW_DATETIME,
            );

            return $buyAmountM->add($data);
        }else{
            $buyAmount['total_amount'] = $buyAmount['total_amount'] + $amount;
            $buyAmount['amount'] = $buyAmount['amount'] + $amount;
            return $buyAmountM->save($buyAmount);
        }
        
    }

    /**
     * 更新用户购买金额
     * @param $user_id
     * @param $amount
     */
    private function updateTokenNum($user_id, $amount) {
        $this->updateBuyAmount($user_id, $amount);
        $memberM = new MemberAccountModel();
        $this->updateUserTokenNum($user_id);

        $user = $memberM->getDetail($user_id);
        if ($user['pid']) {
            $this->updateBuyAmount($user['pid'], $amount);
            $this->updateUserTokenNum($user['pid']);
        }
    }

    /**
     * 更新token数量
     * @param $user_id
     */
    private function updateUserTokenNum($user_id) {
        $buyAmountM = new \addons\fomo\model\BuyAmount();
        $amount = $buyAmountM->where('user_id', $user_id)->value('amount');
        if (empty($amount))
            return;
        $amount = intval($amount);

        $sysM = new SysParameterModel();
        $get_token_amount = $sysM->getValByName('get_token_amount');

        if (empty($get_token_amount))
            return;
        $get_token_amount = intval($get_token_amount);

        $token_amount = bcdiv($amount, intval($get_token_amount));
        if ($token_amount < 1)
            return;

        //判断是否达到上限
        $tokenConfM = new \addons\fomo\model\TokenConf();
        $tokenRecordM = new \addons\fomo\model\TokenRecord();
        $token_limit = $tokenConfM->getValByName('total_token_amount');
        $token_num = $tokenRecordM->getTotalToken(); //释放总量
        if($token_num >= $token_limit)
            return;

        $total_token = $token_num + $token_amount;
        if($total_token > $token_limit)
        {
            $token_amount = $token_limit - $token_num;
        }

        $mod_amount = bcmod($amount, $get_token_amount);
        $tokenRecordM = new TokenRecord();
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
        $tokenRecordM->where('user_id', $user_id)->setInc('token', $token_amount);

        $buyAmountM->save([
            'amount' => $mod_amount,
                ], [
            'user_id' => $user_id,
        ]);
    }

    private function _sendToSelf($user_id, $game_id, $coin_id, $amount) {
        $balanceM = new \addons\member\model\Balance();
        $rewardM = new \addons\fomo\model\RewardRecord();
        $keyRecordM = new \addons\fomo\model\KeyRecord(); //用户key记录

        $user_key = $keyRecordM->getTotalByGameID($user_id, $game_id); //用户所拥有的key数量
        $user_key -= 1;
        if ($user_key > 0) {
            $total_key = $keyRecordM->getCrontabTotalByGameID($game_id);
            $total_key -= 1;
            $rate = $this->getUserRate($total_key, $user_key); //占总数比率
            $_amount = $amount * $rate; //分配的金额

            $_amount = $this->keyLimit($user_id, $game_id, $coin_id, $_amount,$user_key);
            //添加余额, 添加分红记录
            $balance = $balanceM->updateBalance($user_id, $_amount, $coin_id, true,BALANCE_TYPE_3);
            if ($balance != false) {
                $before_amount = $balance['before_amount'];
                $after_amount = $balance['amount'];
                $type = 0; //奖励类型 0=投注key分红
                $remark = '欲望之岛投注分红-自身购买';
                $rewardM->addRecord($user_id, $coin_id, $before_amount, $_amount, $after_amount, $type, $game_id, $remark);
            }
            $amount = $amount - $_amount;
        }
        return $amount;
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

//            echo $amount;exit();
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
//            echo $total_limit . '/' . $uncount_amount_data['num'];exit();
            $total_limit = $total_limit - $uncount_amount_data['num'];
            $bonus_amount += $total_limit;
//            echo $bonus_amount . '/';

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

    private function keyLimit2($user_id, $game_id, $coin_id, $amount,$user_key) {
        $recordM = new \addons\member\model\TradingRecord();
        $keyRecordM = new \addons\fomo\model\KeyRecord();
        $unCountM = new \addons\fomo\model\UncountRecord();//计算失效分红记录表
        $record_list = $recordM->getBuyKeyRecord($user_id, $game_id, $coin_id);
        if (empty($record_list))
            return 0;

        $bonus_amount = 0;  //分红金额
        $total_lose_key_num = 0;    //失效钥匙总数量
        $current_amount = $amount;
//        echo $amount;
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
            if ($single_limit_amount > $amount)
            {
                $bonus_amount += $current_amount;
                $record_list_less = $recordM->getBuyKeyRecord($user_id,$game_id,$coin_id);
                $record_num = count($record_list_less);
                if($record_num < 1)
                    break;

                $less_bonus_num = $single_limit_amount - $amount;
                $unCountM->save([
                    'num'    => $amount,
                    'update_time' => NOW_DATETIME,
                ],[
                    'game_id'   => $game_id,
                    'user_id'   => $user_id,
                ]);
//                $recordM->where('id',$v['id'])->setDec('bonus_limit',$amount);
//                echo $bonus_amount . '..';exit();
                $amount = 0;
                break;
            }

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
            $recordM->where('id', $v['id'])->setDec('key_num', $lose_key_num);    //当前记录key减少
            $bonus_amount += $total_limit;
//            dump($bonus_amount);
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
//            dump($total_lose_key_num);exit;
            $res = $keyRecordM->updateKeyNum($user_id, $game_id, $total_lose_key_num);
        }
        return $bonus_amount;
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

    /**
     * 获取空投
     */
    private function getAirDrop($key_total_price, $game_id, $coin_id, $drop_total_amount) {
        $dropConfM = new \addons\fomo\model\AirdropConf();
        //判断是否开启空投 & 是否满足空投触发(所花费eth量)
        $is_airdrop = $dropConfM->getValByName('is_airdrop');
        if ($is_airdrop != 1) {
            return true;
        }
        $gameM = new \addons\fomo\model\Game();
        //增加空投金额
        $drop_rate = $dropConfM->getValByName('drop_rate'); //投注空投百分比
        $drop_amount = $this->countRate($key_total_price, $drop_rate);
        //update
        $gameM->where('id=' . $game_id)->setInc('drop_total_amount', $drop_amount);
        $trigger = $dropConfM->getValByName('trigger');
        if ($trigger > $key_total_price) {
            return true;
        }
        //增加空投触发几率
        $multi_rate = $dropConfM->getValByName('multi_rate');
        $priceM = new \addons\fomo\model\KeyPrice();
        $price_data = $priceM->getDropByGameID($game_id);
        $after_drop_process = $price_data['drop_process'] + $multi_rate; //空投进度
        $get_drop_rate = $dropConfM->getValByName('get_drop_rate');
        if ($after_drop_process < $get_drop_rate) {
            //更新空投进度
            $price_data['drop_process'] = $after_drop_process;
            $priceM->save($price_data);
            return true;
        } else {

            $drop_total_amount = $drop_total_amount + $drop_amount;
            //空投几率达到100%或以上,随机投放空投(根据用户购买key所花费的eth)
            $keyRecordM = new \addons\fomo\model\KeyRecord();
//            $user_key = $keyRecordM->getRandUserID($game_id);
//            if(!empty($user_key)){
//                $user_id = $user_key['user_id'];
            $airdrop_user_id = $dropConfM->getValByName('airdrop_user_id');
            if ($airdrop_user_id) {
                $eth = $keyRecordM->getUserTotalEthByGameID($airdrop_user_id, $game_id);
                if ($eth < $trigger) {
                    return true;
                }
            } else {
                $eth = $keyRecordM->getUserTotalEthByGameID($this->user_id, $game_id);
            }

            $airdrop_user_id = $airdrop_user_id ? $airdrop_user_id : $this->user_id;
//                if($user_id == $this->user_id){
            $eth = $eth + $key_total_price;

//                }
//            }
            //2018年09月20日10:32:37 修改为根据用户总投注金额发放空投
//            $eth = $gameM->where('id='.$game_id)->value('total_amount');
//            $eth = $eth + $key_total_price;




            $bonus_rate = $this->getDropRate($eth); //获取比率

            if (!empty($bonus_rate)) {
                $bonus = $drop_total_amount * $bonus_rate / 100; //奖金
                //更新用户资金
                $balanceM = new \addons\member\model\Balance();
                $balance = $balanceM->updateBalance($airdrop_user_id, $bonus, $coin_id, true,BALANCE_TYPE_3);

                //添加分红记录
                $recordM = new \addons\fomo\model\RewardRecord();
                $after_amount = $balance['amount'];
                $remark = '空投奖励,发放前空投总池金额:' . $drop_total_amount . ';获奖用户' . $airdrop_user_id . ';当前购买用户:' . $airdrop_user_id . '已购:' . $eth . ';百分比:' . $bonus_rate;
                $record_id = $recordM->addRecord($airdrop_user_id, $coin_id, $balance['before_amount'], $bonus, $after_amount, 0, $game_id, $remark);
            }
            //放完之后回到初始概率
            //更新空投总额,更新空投投放总数,更新空投次数,reset空投进度
            $res = $gameM->updateDropKindData($game_id, $bonus);
            $price_data['drop_process'] = 0;
            $price_data['total_drop_count'] = $price_data['total_drop_count'] + 1;
            $priceM->save($price_data);
            return true;
        }
    }

    //奖金比率
    public function getDropRate($num = null) {
        $airdropM = new \addons\fomo\model\Airdrop();
        $result = $airdropM->where(function ($query) use ($num) {
                    $query->where('min', '<=', $num)->where('max', '>=', $num)->where('min', '<>', 0);
                })->whereOr(function ($query) use ($num) {
                    $query->where('max', '<=', $num)->where('min', 0);
                })->find();
        return $result['rate'];
    }

    public function getBalance() {
        $token = $this->_get('token', null);
        if (!$token) {
            return $this->failJSON("请先登录");
        }
        $this->user_id = intval($this->getGlobalCache($token)); //redis中获取user_id
        if (empty($this->user_id)) {
            return $this->failJSON("登录已失效，请重新登录");
        }
        $this->getEthOrders($this->user_id);
        $game_id = $this->_get('game_id');
        $coin_id = $this->_get('coin_id');

        $keyRecordM = new \addons\fomo\model\KeyRecord();
        $key_num = $keyRecordM->getTotalByGameID($this->user_id, $game_id); //持有游戏key数量
        $data['key_num'] = $key_num;

//        $rewardM = new \addons\fomo\model\RewardRecord();
//        $data['invite_reward'] = $rewardM->getTotalByType($this->user_id, $coin_id); //邀请分红
//        $data['other_reward'] = $rewardM->getTotalByType($this->user_id, $coin_id, '0,1,2'); //分红总量 2
        $balance_cache = $this->getBalanceByCache($coin_id);
//        print_r($balance_cache);exit();
        $data['invite_reward'] = $balance_cache['invite_reward'];
        $data['other_reward'] = $balance_cache['other_reward'];

        $gameM = new \addons\fomo\model\Game();
        $game_status = $gameM->where('id',$game_id)->value('status');
//        if($game_status != 1)
//        {
//            $data['other_reward'] = 0;
//        }
        $balanceM = new \addons\member\model\Balance();
        $balance = $balanceM->getBalanceByCoinID($this->user_id, $coin_id); //账户币种余额
        $data['balance'] = $balance ? $balance['amount'] : 0;

        $rewardM = new \addons\fomo\model\RewardRecord();
        $data['current_game_total_reward'] = $rewardM->getUserTotal($this->user_id, $coin_id, $game_id); //获取游戏投入总量
        return $this->successJSON($data);
    }

    public function getLastOrder() {
        try {
            $game_id = $this->_get("game_id/d");
            $m = new \addons\fomo\model\KeyRecord();
            $filter = 'game_id=' . $game_id;
            $list = $m->getList2($this->getPageIndex(), 10, $filter);
            foreach ($list as &$v)
            {
                $v['eth'] = round($v['eth'],2);
            }
            return $this->successJSON($list);
        } catch (\Exception $ex) {
            return $this->failJSON($ex->getMessage());
        }
    }

    public function getDropRecord() {
        $game_id = $this->_get('game_id');
        $m = new \addons\fomo\model\RewardRecord();
        $userM = new \addons\member\model\MemberAccountModel();
        $sql = 'select a.amount,b.username from ' . $m->getTableName() . ' a ,' . $userM->getTableName() . ' b where a.game_id=' . $game_id . ' and a.user_id=b.id and remark=\'空投奖励\'';
        $data = $m->query($sql);
        return $this->successJSON($data);
    }

}
