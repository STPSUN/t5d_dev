<?php

namespace web\api\controller;

use addons\config\model\Coins;
use addons\fomo\model\AgencyAward;
use addons\member\model\MemberAccountModel;
use addons\member\model\TradingRecord;
use addons\otc\model\PayConfig;
use think\Exception;
use think\Request;
use think\Validate;
use web\common\model\sys\SysParameterModel;

class Member extends \web\api\controller\ApiBase
{

    /**
     * @var array
     */
    private $_address = array(); //eth接口返回
    /**
     * @var string
     */
    private $ethPass = '';

    /**
     * 获取用户资产
     */
    public function getUserAsset()
    {
        try {
            $user_id = $this->user_id;
            if (!$user_id || $user_id <= 0) {
                return $this->failJSON("请登录");
            }
            $game_id = $this->_get("game_id");
            $balanceM = new \addons\member\model\Balance();
            $eth_balance = $balanceM->getBalanceByCoinID($user_id, 1);
            $data = [];
            $data['eth_num'] = $eth_balance ? $eth_balance['amount'] : 0;

            if(!empty($game_id)){
                $keyRecordM = new \addons\fomo\model\KeyRecord();
                $key = $keyRecordM->getTotalByGameID($user_id, $game_id); //持有游戏key数量
                $lose_Key = $keyRecordM->getTotalLoseByGameID($user_id,$game_id);
                $data['key_num'] = $key ? $key : 0;
                $data['lose_key_num'] = $lose_Key ? $lose_Key : 0;
            }
            $tokenM = new \addons\fomo\model\TokenRecord();
            $token = $tokenM->getDataByUserID($user_id);
            $data['token_num'] = (empty($token['token']))?0:$token['token'];

            $coinM = new \addons\config\model\Coins();
            $btc_balance = $balanceM->getBalanceByCoinID($user_id,3);
            $data['btc_num'] = $btc_balance ? $btc_balance['amount'] : 0;

            $usdt_coin_id = $coinM->where('coin_name','USDT')->value('id');
            $usdt_balance = $balanceM->getBalanceByCoinID($user_id,$usdt_coin_id);
            $data['usdt_num'] = $usdt_balance ? $usdt_balance['amount'] : 0;

            $world_balance = $balanceM->getBalanceByCoinID($user_id,2);
            $data['world_num'] = $world_balance ? $world_balance['amount'] : 0;
            $sysM = new SysParameterModel();
            $token_rate = $sysM->getValByName('token_rate');
            $data['cny_num'] = bcmul($world_balance['amount'],$token_rate,2);

            return $this->successJSON($data);
        } catch (Exception $ex) {
            return $this->failJSON($ex->getMessage());
        }
    }

    /*
     * 获取用户数据
     */
    /**
     * @return \think\response\Json|void
     */
    public function getUserInfo()
    {
        $user_id = intval($this->_get('user_id'));
        if (empty($user_id)) {
            return $this->failJSON('missing arguments');
        }
        return json($this->service->getUserInfo($user_id));

    }

    /**
     * 用户登陆
     */
    public function login()
    {
        if (IS_POST) {
            try {
                $phone = $this->_post('phone');
                $password = $this->_post('password');
                if (empty($password)) {
                    return $this->failJSON('密码不能为空');
                }
                if (empty($phone)) {
                    return $this->failJSON('手机号不能为空');
                }
                $m = new \addons\member\model\MemberAccountModel();
                $res = $m->getLoginData($password, $phone, '', 'id,head_pic,area,phone,address,username',true);
                if ($res) {
                    $memberData['head_pic'] = $res['head_pic'];
                    $memberData['username'] = $res['phone'];
                    $memberData['address'] = $res['address'];
                    $memberData['user_id'] = $res['id'];
                    session('memberData', $memberData);

                    $token = md5($res['id'] . $this->apikey);
                    $this->setGlobalCache($res['id'], $token); //user_id存储到入redis
                    $data['head_pic'] = $res['head_pic'];
                    $data['area'] = $res['area'];
                    $data['phone'] = $res['phone'];
                    $data['username'] = $res['username'];
                    $data['address'] = $res['address'];
                    $data['token'] = $token;
                    return $this->successJSON($data);
                } else {
                    return $this->failJSON('帐号或密码有误');
                }
            } catch (\Exception $ex) {
                return $this->failJSON($ex->getMessage());
            }
        } else {
            return $this->failJSON('请求出错');
        }
    }

    /*
     * 短信登录
     */
    public function smsLogin()
    {
        if (IS_POST) {
            try {
                $phone = $this->_post('phone');
                $verify_code = $this->_post('verify_code');
                $type = $this->_post('type', 3);
                if (empty($phone)) {
                    return $this->failJSON('手机号不能为空');
                }
                if (empty($verify_code)) {
                    return $this->failJSON('验证码不能为空');
                }

                $verifyM = new \addons\member\model\VericodeModel();
                $_verify = $verifyM->VerifyCode($verify_code, $phone, $type);
                if (!empty($_verify)) {
                    $m = new \addons\member\model\MemberAccountModel();
                    $res = $m->getLoginDataBySms($phone, 'id,head_pic,area,phone,address'); //短信登录 根据手机号查找用户信息
                    if ($res) {
                        $memberData['head_pic'] = $res['head_pic'];
                        $memberData['username'] = $res['phone'];
                        $memberData['address'] = $res['address'];
                        $memberData['user_id'] = $res['id'];
                        session('memberData', $memberData);

                        $token = md5($res['id'] . $this->apikey);
                        $this->setGlobalCache($res['id'], $token); //user_id存储到入redis
                        $data['head_pic'] = $res['head_pic'];
                        $data['area'] = $res['area'];
                        $data['username'] = $res['phone'];
                        $data['address'] = $res['address'];
                        $data['token'] = $token;
                        return $this->successJSON($data);
                    } else {
                        $this->failJSON('该手机尚未注册');
                    }
                } else {
                    $this->failJSON('验证码已失效');
                }
            } catch (\Exception $ex) {
                return $this->failJSON($ex->getMessage());
            }
        } else {
            return $this->failJSON('请求出错');
        }
    }

    /**
     * 用户注册
     */
    public function register()
    {
        if (IS_POST) {
//            $data['phone'] = $this->_post('phone');
//            $data['verify_code'] = $this->_post('verify_code');
            $password = $this->_post('password');
            $password1 = $this->_post('password1');
            $pay_password = $this->_post('pay_password');
            $data['username'] = $this->_post('username');
            if ($password !== $password1) {
                return $this->failJSON('两次输入的密码不一致');
            }
            if (!preg_match("/^(?![\d]+$)(?![a-zA-Z]+$)(?![^\da-zA-Z]+$).{6,16}$/", $password)) {
                return $this->failJSON('密码必须含有数字，字母，特殊符号中的两种。');
            }
            if (!preg_match("/^[0-9]{6}$/", $pay_password)) {
                return $this->failJSON('请输入6位数字交易密码');
            }
            if (strlen($password) < 8) {
                return $this->failJSON('密码长度不能小于8');
            }
//            $data['area'] = $this->_post('area');
//            if(!$data['area']){
//                return $this->failJSON('请选择手机区号');
//            }
            $data['password1'] = $password1;
            $data['password'] = md5($password);
            $data['pay_password'] = md5($pay_password);
            $m = new \addons\member\model\MemberAccountModel();
            if (preg_match('/[\x7f-\xff]/', $data['username'])) {
                return $this->failJSON('用户名不支持中文');
            }
            $count = $m->hasRegsterUsername($data['username']);
            if ($count > 0) {
                return $this->failJSON('此用户名已被注册');
            }
//            $count = $m->hasRegsterPhone($data['phone']);
//            if ($count > 0) {
//                return $this->failJSON('此手机号已被注册,请直接登录或尝试找回密码');
//            }
            $m->startTrans();
            try {
//                $verifyM = new \addons\member\model\VericodeModel();
//                $_verify = $verifyM->VerifyCode($data['verify_code'], $data['phone']);
//                if (!empty($_verify)) {
                    $inviter_address = $this->_post('inviter_address');
                    if (!empty($inviter_address)) {
                        //获取邀请者id
                        $invite_user_id = $m->getUserByUsername($inviter_address);
                        if (!empty($inviter_address)) {
                            $data['pid'] = $invite_user_id; //邀请者id
                        } else {
                            return $this->failJSON('邀请人不存在');
                        }
                    }
                    $data['register_time'] = NOW_DATETIME;
                    $res = $this->getEthAddr($data['username']);
                    if ($res) {
                        $data['address'] = $this->_address; //eth地址
                        $data['eth_pass'] = $this->ethPass;
                        $user_id = $m->add($data); //用户id
                        $m->commit();
                        return $this->successJSON('注册成功');
                    }
//                } else {
//                    $m->rollback();
//                    return $this->failJSON('验证码失效,请重新注册');
//                }
            } catch (\Exception $ex) {
                return $this->failJSON($ex->getMessage());
            }
        } else {
            return $this->failJSON('请求出错');
        }
    }

    /**
     * @param $name
     * @return bool
     */
    private function getEthAddr($name)
    {
        $eth_pass = 'token' . $name . rand(0000, 9999);
        $this->ethPass = $eth_pass;
        $res = $this->jsonrpc('personal_newAccount', [$eth_pass]);
        return $res;
    }

    /**
     * @param $method
     * @param $params
     * @return bool
     */
    private function jsonrpc($method, $params)
    {
        $m = new \web\common\model\sys\SysParameterModel();
        $port = $m->getValByName('port');
        $url = "http://127.0.0.1:" . $port;
        $request = array('method' => $method, 'params' => $params, 'id' => 1);
        $request = json_encode($request);
        $opts = array('http' => array(
            'method' => 'POST',
            'header' => 'Content-type: application/json',
            'content' => $request
        ));
        $context = stream_context_create($opts);
        if ($result = file_get_contents($url, false, $context)) {
            $data = json_decode($result, true);
            if (!empty($data) && $data['result']) {
                $this->_address = $data['result'];
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * 获取手机验证码
     */
    public function getPhoneVerify()
    {
        $phone = $this->_post('phone');
        $time = $this->_post('time');
        $type = $this->_post('type');
        $area = $this->_post('area');
        if(!$area){
            return $this->failData('请选择国家区号');
        }
        if (empty($type))
            $type = 1; //注册验证码
        if ($type == 2) {
            //找回密码
            $memberM = new \addons\member\model\MemberAccountModel();
            $ret = $memberM->hasRegsterPhone($phone);
            if ($ret <= 0) {
                return $this->failJSON('手机号未注册 - Account is not exists');
            }
        }
        $m = new \addons\member\model\VericodeModel();
        $unpass_code = $m->hasUnpassCode($phone, $type);
        if (!empty($unpass_code)) {
            return $this->failJSON('验证码未过期,请输入之前收到的验证码');
        }
        try {
            $sendPhone = "{$area} $phone";
            //发送验证码
            $res = \addons\member\utils\Sms::send($sendPhone);
//            $res['success'] = true;
//            $res['message'] = '短信发送成功';
//            $res['code'] = '1111';
            if (!$res['success']) {
                return $this->failJSON($res['message']);
            }
            //保存验证码
            $pass_time = date('Y-m-d H:i:s', strtotime("+" . $time . " seconds"));
            $data['phone'] = $phone;
            $data['code'] = $res['code'];
            $data['type'] = $type;
            $data['pass_time'] = $pass_time; //过期时间
            $result = $m->add($data);
            if (empty($result)) {
                return $this->failJSON('验证码生成失败'); //写入数据库失败
            }
            unset($res['code']);

            return $this->successJSON($res['message']);
        } catch (\Exception $ex) {
            return $this->failJSON($ex->getMessage());
        }
    }

    /**
     *
     */
    public function getUserEthAddr()
    {
        $user_id = intval($this->_get('user_id'));
        if (empty($user_id)) {
            return $this->failJSON('missing arguments');
        }
//        return json($this->service->getUserEthAddr($user_id));
        return;
    }

    /**
     * @return \think\response\Json|void
     */
    public function editUserAddr()
    {
        if (!IS_POST) {
            return $this->failJSON('使用POST提交');
        }
        $user_id = $this->_post("user_id/d");
        return json($this->service->editUserAddr($user_id));
    }

    /**
     * @return \think\response\Json|void
     */
    public function getUserAddrList()
    {
        $user_id = $this->_get('user_id');
        if (empty($user_id)) {
            return $this->failJSON('缺少参数');
        }
        return json($this->service->getUserAddrList($user_id));
    }

    /**
     * @return \think\response\Json|void
     */
    public function getAddrByID()
    {
        $id = $this->_get('id');
        if (empty($id)) {
            return $this->failJSON('缺少参数');
        }
        return json($this->service->getAddrByID($id));
    }

    /**
     * @return \think\response\Json|void
     */
    public function delAddr()
    {
        if (!IS_POST) {
            return $this->failJSON('使用POST提交');
        }
        $id = $this->_post('id');
        $user_id = $this->user_id;
        if ($user_id <= 0) {
            return $this->failJSON("请登录");
        }
        if (!$user_id || !$id) {
            return $this->failJSON('缺少参数');
        }
        return json($this->service->delAddr($user_id, $id));
    }

    /**
     * 用户身份验证
     */
    public function userAuth()
    {
        if (!IS_POST) {
            return $this->failJSON('illegal request');
        }
        $user_id = $this->user_id;
        return json($this->service->userAuth($user_id));
    }

    /*
     * 设定用户资料
     */
    /**
     *
     */
    public function setUserInfo()
    {
        if (!IS_POST) {
            return $this->failJSON('illegal request');
        }
        $user_id = $this->user_id;
        if (!$user_id) {
            return $this->failJSON("illegal request");
        }
        $data['id'] = $this->user_id;
        $data['email'] = $this->_post('email');
        $data['home_address'] = $this->_post('home_address');
        $data['city'] = $this->_post('city');
        $data['country'] = $this->_post('country');
        try {
            $m = new \addons\member\model\MemberAccountModel();
            $m->save($data);
            return $this->successJSON();
        } catch (\Exception $ex) {
            return $this->failJSON($ex->getMessage());
        }
    }

    /**
     * 验证手机是否已经注册
     */
    public function hasReg($phone)
    {
        if (empty($phone)) {
            return $this->failJSON('手机号不能为空');
        }
        $m = new \addons\member\model\MemberAccountModel();
        $count = $m->hasRegsterPhone($phone);
        return $this->successJSON($count);
    }

    /**
     * 用户修改密码
     */
    public function changePass()
    {
        if (!IS_POST) {
            return $this->failJSON('illegal request');
        }
        $username = $this->_post('username');
        $phone = $this->_post('phone');
        $code = $this->_post('code',null);
        $password = $this->_post('pass2');
        $type = $this->_post('type', 2);
        if (!preg_match("/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{6,20}$/", $password)) {
            return $this->failJSON('请输入6~20位字母数字密码');
        }
        if(!$code){
            return $this->failJSON('请输入验证码');
        }
        if ($phone && $code && $password && $type) {
            $m = new \addons\member\model\MemberAccountModel();
            try {
                $account = $m->where('username',$username)->find();
                if (!$account || $account['phone'] !== $phone) {
                    return $this->failJSON('用户名与手机号有误，无法重置密码');
                }
                $verifyM = new \addons\member\model\VericodeModel();
                $_verify = $verifyM->VerifyCode($code, $phone, $type);
                if (!empty($_verify)) {

                    $now_password = md5($password);
//                    $account['password'] = md5(md5($password) . $account['salt']);
//                    $id = $m->updatePassByUserName($username, $password);
                    $id = $m->where('id',$account['id'])->update(['password'=>$now_password]);
                    if ($id <= 0) {
                        return $this->failJSON('密码重置失败,请更换密码后重试 。reset the password is fail, please try again');
                    }
                    return $this->successJSON("修改成功");
                } else {
                    return $this->failJSON('验证码失效,请重新发送');
                }
            } catch (\Exception $ex) {
                return $this->failJSON($ex->getMessage());
            }
        } else {
            return $this->failJSON('missing arguments');
        }
    }

    public function setLoginPass()
    {
        if (!IS_POST) {
            return $this->failJSON('illegal request');
        }
        $user_id = $this->user_id;
        if ($user_id <= 0) {
            return $this->failJSON("请登录");
        }
        $old_password = $this->_post('old_password');
        $password = $this->_post('password');
        $now_password = $this->_post('pass2');
//        $code = $this->_post('code');
//        if (!$user_id || !$code || !$now_password) {
        if (!$user_id|| !$old_password || !$now_password) {
            return $this->failJSON("illegal request");
        }
        if (!preg_match("/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{6,20}$/", $now_password)) {
                return $this->failJSON('请输入5~20位字母数字密码');
            }
        try {
            $m = new \addons\member\model\MemberAccountModel();
            $user = $m->getDetail($user_id, "phone,password");
//            $verifyM = new \addons\member\model\VericodeModel();
//            $_verify = $verifyM->VerifyCode($code, $user['phone'], 5);
//            if (!empty($_verify)) {
                $old_password = md5($old_password);
                if($old_password !== $user['password']){
                    return $this->failJSON("原密码输入有误");
                }
                
//                $now_password = md5(md5($now_password) . $user['salt']);
                $now_password = md5($now_password);

                $data['id'] = $user_id;
                $data['password'] = $now_password;
                $ret = $m->save($data); //用户id
                if (!$ret) {
                    return $this->failJSON('修改失败');
                }
                //添加资产记录
                $m->commit();
                return $this->successJSON();
//            } else {
//                $m->rollback();
//                return $this->failJSON('验证码失效,请重新输入');
//            }
        } catch (\Exception $ex) {
            return $this->failJSON($ex->getMessage());
        }
    }

    /**
     *
     */
    public function setPayPass()
    {
        if (!IS_POST) {
            return $this->failJSON('illegal request');
        }
        $user_id = $this->user_id;
        if ($user_id <= 0) {
            return $this->failJSON("请登录");
        }
        $old_password = $this->_post('old_password');
        $password = $this->_post('pass2');
        $now_password = $this->_post('pass2', 0);
        
//        $code = $this->_post('code');
//        if (!$user_id || !$code || !$now_password) {
        if (!$user_id || !$old_password || !$now_password) {
            return $this->failJSON("illegal request");
        }
        if (!preg_match("/^[0-9]{6}$/", $now_password)) {
            return $this->failJSON('请输入6位数字交易密码');
        }

        try {
            $m = new \addons\member\model\MemberAccountModel();
            $user = $m->getDetail($user_id, "phone,pay_password");
            $old_password = md5($old_password);
            if($old_password !== $user['pay_password']){
                return $this->failJSON("原密码输入有误");
            }
//            $verifyM = new \addons\member\model\VericodeModel();
//            $_verify = $verifyM->VerifyCode($code, $user['phone'], 7);
//            if (!empty($_verify)) {
                $now_password = md5($now_password);
                $data['id'] = $user_id;
                $data['pay_password'] = $now_password;
                $ret = $m->save($data); //用户id
                if (!$ret) {
                    return $this->failJSON('修改失败');
                }
                //添加资产记录
                $m->commit();
                return $this->successJSON();
//            } else {
//                $m->rollback();
//                return $this->failJSON('验证码失效,请重新输入或发送');
//            }
        } catch (\Exception $ex) {
            return $this->failJSON($ex->getMessage());
        }
    }


    /**
     * 获取用户推荐链接
     */
    public function getInviteQRCode()
    {
        $user_id = $this->_get('user_id');
        if (empty($user_id)) {
            return $this->failJSON('missing arguments');
        }
        try {
//            $m = new \addons\member\model\MemberAccountModel();
//            $info = $m->getDetail($user_id, 'username');
            $path = "http://www.wnct.io/login/register.html?code=" . $user_id;
            $ret['count'] = 0;
            $ret['path'] = $path;
            return $this->successJSON($ret);

        } catch (\Exception $ex) {
            return $this->failJSON($ex->getMessage());
        }
    }

    /**
     * 获取用户1、2级推荐列表
     * @param $token  users token
     */
    public function getRecommendList()
    {
        $user_id = $this->user_id;
        if ($user_id <= 0) {
            return $this->failJSON("请登录");
        }
        $m = new \addons\member\model\MemberAccountModel();
        $sonUsers = $m->getUserListByPid($user_id);
        $data['recommend'] = $sonUsers ? $sonUsers : [];
        if ($sonUsers) {
            $idsArr = array_column($sonUsers, 'id');
            $ids = join(",", $idsArr);
            $sonUsers2 = $m->getUserListByPid($ids);
            $data['recommend2'] = $sonUsers2 ? $sonUsers2 : [];
        }
        return $this->successJSON($data);
    }


    /**
     * 获取用户团队列表
     * @param $user_id 用户id
     */
    public function getTeamUser()
    {
        $user_id = $this->_get("user_id");
        $m = new \addons\member\model\MemberAccountModel();
        $team = $m->getTeamById($user_id, 1);
        return $this->successJSON($team);
    }
    
    /**
     * 修改用户头像
     */
    public function setUserHead()
    {
        $user_id = $this->user_id;
        if($user_id <= 0){
            return $this->failJSON("未登录");
        }
        $base64 = $this->_post('file');
        if(!$base64){
            return $this->failJSON("请上传头像");
        }

        $m = new \addons\member\model\MemberAccountModel();
        $savePath = 'head/img/'.$user_id.'/';
        $data = $this->base_img_upload($base64, $user_id, $savePath);
        if($data['success']){
            //保存用户头像地址 return $res['path']
            $res = $m->where("id", $user_id)->update(['head_pic' => $data['path']]);
            if($res > 0){
                return $this->successJSON($data['path']);
            }else{
                return $this->failJSON('上传凭证失败');
            }
        }else{
            return $this->failJSON($data['message']);
        }
    }

    /**
     * 申请代理
     */
    public function applyAgency()
    {
        $user_id = $this->user_id;
        $m = new \addons\member\model\Agency();
        $data = $m->where(['user_id' => $user_id, 'status' => 1])->find();
        if(!empty($data))
            return $this->failJSON('代理审核中，请耐心等待');

        $param = array(
            'user_id' => $user_id,
            'status'  => 1,
            'create_time'   => NOW_DATETIME,
            'update_time'   => NOW_DATETIME,
        );
        $res = $m->add($param);
        if($res)
            return $this->successJSON();
        else
            return $this->failData();
    }

    /**
     * 代币兑换其他币种
     */
    public function tokenConvert()
    {
        $coin_id = $this->_post('coin_id');
        $amount = $this->_post('amount');
        $user_id = $this->user_id;

        if (!$user_id || !$coin_id|| !$amount) {
            return $this->failJSON("illegal request");
        }

        $sysM = new \web\common\model\sys\SysParameterModel();
        $balanceM = new \addons\member\model\Balance();
        $coinM = new Coins();

        $coin = $coinM->getDetail($coin_id);
        if(empty($coin))
            return $this->failJSON('该币种不存在');

        $coin_name = $coin['coin_name'] . '_rate';
        $rate = $sysM->getValByName($coin_name);
        $amount_rate = bcdiv($amount,$rate,8);
        $type = 23;
        $remark = '代币兑换其他币种';

        $token_id = $coinM->where('is_token',1)->value('id');
        if(empty($token_id))
            return $this->failJSON('代币不存在');

        $coin_balance = $balanceM->getBalanceByCoinID($user_id,$token_id);
        if($coin_balance['amount'] < $amount)
            return $this->failJSON('余额不足');

        $recordM = new TradingRecord();
        $balanceM->startTrans();
        try
        {
            $balanceM->updateBalance($user_id,$amount,$token_id,false);
            $coin_after = $coin_balance['amount'] - $amount;
            $recordM->addRecord($user_id,$token_id,$amount,$coin_balance['amount'],$coin_after,$type,0,0,'','',$remark);

            $agency_balance = $balanceM->getBalanceByCoinID($user_id,$coin_id);
            $balanceM->updateBalance($user_id,$amount_rate,$coin_id,true);
            $agency_after = $agency_balance['amount'] + $amount_rate;
            $recordM->addRecord($user_id,$coin_id,$amount_rate,$agency_balance['amount'],$agency_after,$type,1,0,'','',$remark);

            $balanceM->commit();
        }catch (\Exception $e)
        {
            $balanceM->rollback();
            return $this->failJSON($e->getMessage());
        }

        return $this->successJSON();
    }

    /**
     * 兑换代币
     */
    public function convertCoin()
    {
        $coin_id = $this->_post('coin_id');
        $amount = $this->_post('amount');
        $user_id = $this->user_id;

        $param = Request::instance()->post();
        $validate = new Validate([
            'coin_id'   => 'require',
            'amount'    => ['require','regex' => '^[1-9]*$']
        ],[
            'amount'    => '请输入正确的数量'
        ]);
        if(!$validate->check($param))
            return $this->failJSON($validate->getError());

        $sysM = new \web\common\model\sys\SysParameterModel();
        $balanceM = new \addons\member\model\Balance();
        $coinM = new Coins();

        $coin = $coinM->getDetail($coin_id);
        if(empty($coin))
            return $this->failJSON('该币种不存在');

        $coin_name = $coin['coin_name'] . '_rate';
        $rate = $sysM->getValByName($coin_name);
        $amount_rate = $amount * $rate;
        $type = 21;
        $remark = '兑换代币';

        $coin_balance = $balanceM->getBalanceByCoinID($user_id,$coin_id);
        if($coin_balance['amount'] < $amount)
            return $this->failJSON('余额不足');

        $agency_coin_id = $coinM->where('is_token',1)->value('id');
        $recordM = new TradingRecord();
        $balanceM->startTrans();
        try
        {
            $balanceM->updateBalance($user_id,$amount,$coin_id,false);
            $coin_after = $coin_balance['amount'] - $amount;
            $recordM->addRecord($user_id,$coin_id,$amount,$coin_balance['amount'],$coin_after,$type,0,0,'','',$remark);

            $agency_balance = $balanceM->getBalanceByCoinID($user_id,$agency_coin_id);
            $balanceM->updateBalance($user_id,$amount_rate,$agency_coin_id,true);
            $agency_after = $agency_balance['amount'] + $amount_rate;
            $recordM->addRecord($user_id,$agency_coin_id,$amount_rate,$agency_balance['amount'],$agency_after,$type,1,0,'','',$remark);

            $balanceM->commit();
        }catch (\Exception $e)
        {
            $balanceM->rollback();
            return $this->failJSON($e->getMessage());
        }

        return $this->successJSON();
    }

    /**
     * 获取币种类型
     */
    public function getCoinType()
    {
        $sysM = new SysParameterModel();
        $coinM = new Coins();
        $data = $coinM->field('id coin_id,coin_name')->where('is_token',0)->select();
        foreach ($data as &$v)
        {
            $coin_name = $v['coin_name'];
            if($coin_name == 'ETH')
                $v['address'] = $sysM->getValByName('out_address');
            else if($coin_name == 'USDT')
                $v['address'] = $sysM->getValByName('usdt_address');
            else if($coin_name == 'BTC')
                $v['address'] = $sysM->getValByName('btc_address');
        }

        return $this->successJSON($data);
    }

    /**
     * 俱乐部
     */
    public function club()
    {
        $user_id = $this->user_id;
        $memberM = new MemberAccountModel();
        $level = $memberM->where('id',$user_id)->value('agency_level');
        $users = $memberM->getTeamByIdBreak($user_id,1,2);

        $agencyAwardM = new AgencyAward();
        $total_amount = $agencyAwardM->where(['user_id' => $user_id, 'status' => 2])->sum('amount');
        $data = [
            'level' => $level,
            'user_num'  => count($users),
            'total_amount' => empty($total_amount) ? 0 : $total_amount,
        ];

        $detail = [];
        foreach ($users as $v)
        {
            $award = $agencyAwardM->where(['user_id' => $user_id, 'from_user_id' => $v['id'], 'status' => 2])->sum('amount');
            $users2 = $memberM->getTeamByIdBreak($v['id'],1,2);
            $total = $agencyAwardM->where(['user_id' => $v['id'], 'status' => 2])->sum('amount');
            $register_time = $memberM->where('id',$v['id'])->value('register_time');
            $temp = [
                'username' => $v['username'],
                'amount'   => empty($award) ? 0 : $award,
                'register_time' => $register_time,
                'agency_level' => $memberM->where('id',$v['id'])->value('agency_level'),
                'num'      => count($users2),
                'total'    => empty($total) ? 0 : $total,
            ];

            $detail[] = $temp;
        }

        $data['detail'] = $detail;

        return $this->successJSON($data);
    }


}
















