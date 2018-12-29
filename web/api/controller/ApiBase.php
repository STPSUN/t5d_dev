<?php

namespace web\api\controller;

use think\Lang;

class ApiBase extends \web\common\controller\Controller {

    /**
     * 获取提交的参数数据
     * @var type
     */
    private $paramData = null;

    /**
     * 提交的签名值
     * @var type
     */
    private $signValue = '';

    /**
     * 加密key
     * @var type
     */
    protected $apikey = 'OGFhNDk5NGYzZjgxYzk0ZTJmN2UxNTUyMThmNTE5YTA';
    protected $user_id = 0;

    /**
     * 全局redis缓存
     * @var type
     */
    protected $globalCache = null; //think\cache\driver\Redis
    protected $dataCashe = null;

    /**
     * 设置当前请求绑定的缓存对象实例--redis
     * @param type $request
     */
    private function bindCache(&$request) {
        if (!$request->__isset('data_cache')) {
            $dataCache = \think\Cache::connect(\think\Config::get('data_cache'));
            $request->bind('data_cache', $dataCache);
        }
        if (!$request->__isset('global_cache')) {
            $globalCache = \think\Cache::connect(\think\Config::get('global_cache'));
            $request->bind('global_cache', $globalCache);
        }
    }

    protected function _initialize() {
        $this->bindCache($this->request);
        $this->globalCache = $this->request->__get('global_cache');
        $this->dataCashe = $this->request->__get('data_cache');
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            return true;
        }
        $this->_setLang();
//        $this->is_frozen($this->user_id);
        $token = $this->_post('token', null);
        if (!empty($token)) {
            $this->user_id = intval($this->getGlobalCache($token)); //redis中获取user_id
            if(!$this->user_id){
                return $this->failJSON("登录失效，请重新登录");
            }
        }
    }

    /**
     * 获取全局缓存数据
     * @param type $key
     * @return type
     */
    protected function getGlobalCache($key = null) {
        $data = null;
        if ($this->globalCache) {
            if (empty($key)){
                return $this->failJSON("登录已失效");
            }
            $data = $this->globalCache->get($key);
        }
        return $data;
    }

    /**
     * 设置全局缓存数据
     * @param type $data
     * @param type $key
     * @param type $expire
     */
    protected function setGlobalCache($data, $key = null, $expire = null) {
        if ($this->globalCache) {
            if (empty($key))
                $key = $this->getCacheKey();
            return $this->globalCache->set($key, $data, $expire);
        }
    }

    protected function is_frozen($user_id) {
        $memberM = new \addons\member\model\MemberAccountModel();
        $user = $memberM->getDetail($user_id, "is_frozen");
        if (intval($user['is_frozen']) == 1) {
            return $this->failJSON("账号已冻结，无法使用");
        }
    }

    /**
     * 设置语言包
     */
    protected function _setLang() {
        $lang = $this->_get("lang", "zh-cn");
        $lang = strtolower($lang);
//        lang($message);
        switch ($lang) {
            case "en-us":
                Lang::range('en-us');
                $_file = APP_PATH . 'common/lang/en-us.php';
                Lang::load($_file);
                break;
            default:
                Lang::range('zh-cn');
                $_file = APP_PATH . 'common/lang/zh-cn.php';
                Lang::load($_file);
                break;
        }
    }

    /**
     *
     * 检测签名
     */
    protected function checkSign() {
        $sign = $this->makeSign();
        if ($this->signValue == $sign) {
            return true;
        } else {
            $this->failJSON('签名数据有误！' . $sign);
        }
    }

    /**
     * 生成签名
     */
    private function makeSign() {
        //签名1:按字典序排序参数
        ksort($this->paramData);
        $string = $this->toUrlParams();
        $string = md5($string);
        //签名2：所有字符转为大写
        $result = strtoupper($string);
        return $result;
    }

    /**
     * 格式化参数格式化成url参数(字典排序后重组)
     */
    private function toUrlParams() {
        $buff = '';
        foreach ($this->paramData as $k => $v) {
            if ($v != '' && !is_array($v)) {
                if (preg_match('/[\x80-\xff]./', $v))
                    $buff .= $k . '=' . urldecode($v) . '&';
                else
                    $buff .= $k . '=' . $v . '&';
            }
        }
        $buff = trim($buff, '&');
        return $buff;
    }

    /**
     * 获取参数值.
     * @param type $name
     * @param type $no_null 不能为空
     * @return type
     */
    protected function getParamVal($name, $no_null = true, $type = '') {
        $data = $this->paramData[$name];
        if ($no_null && $data == null) {
            $this->failJSON('缺少必填参数' . $name . '！');
        } else {
            if ($data != null) {
                if ($type == 'int') {
                    if (!is_numeric($data))
                        $this->failJSON('参数' . $name . '类型有误!');
                    else
                        $data = intval($data);
                } else if ($type == 'float') {
                    if (!is_numeric($data))
                        $this->failJSON('参数' . $name . '类型有误！');
                    else
                        $data = floatval($data);
                } else if ($type == 'double') {
                    if (!is_numeric($data))
                        $this->failJSON('参数' . $name . '类型有误！');
                    else
                        $data = doubleval($data);
                }
            }
            $this->paramData[$name] = $data;
            return $data;
        }
    }

    /**
     * 获取提交过来的参数数据 (数组)
     * @return type
     */
    private function getRequestData() {
        $data = $_GET;
        unset($data['token']);
        unset($data['sign']);
        $REDIRECT_URL = $_SERVER['REDIRECT_URL'];
        unset($data[$REDIRECT_URL]);
        $data['api_key'] = $this->apikey;
        return $data;
    }

    /**
     * 获取POST提交的数据。
     * @return type
     */
    protected function getPostData() {
        $data = array();
        if (isset($_POST)) {
            $post_data = $_POST;
            $data = !empty($post_data) ? $post_data : array();
            unset($data['sign']);
            $data['api_key'] = $this->apikey;
        } else {
            return $this->failJSON('post data is empty');
        }
        return $data;
    }

    /**
     * 输出错误JSON信息。
     * @param type $message
     */
    protected function failJSON($message) {
        $message = lang($message);
        $jsonData = array('success' => false, 'message' => $message);
        $json = json_encode($jsonData, true);
        echo $json;
        exit;
    }

    /**
     * 输出成功JSON信息
     * @param type $data
     */
    protected function successJSON($data = NULL, $msg = "success") {
        if (is_array($data) || is_object($data)) {
            $data = $this->_setDataLang($data);
        }
        $jsonData = array('success' => true, 'data' => $data, 'message' => $msg);
        $json = json_encode($jsonData, 1);
        echo $json;
        exit;
    }

    /**
     * 设置返回数据语言包
     * @param $data
     * @return mixed
     */
    private function _setDataLang($data) {
        if (is_array($data) || is_object($data)) {
            foreach ($data as &$val) {
                if (is_string($val)) {
                    $val = is_string(lang($val)) ? lang($val) : $val;
                } elseif (is_array($data) || is_object($data)) {
                    $val = $this->_setDataLang($val);
                }
            }
        }
        return $data;
    }

    /**
     * 获取当前页码
     * @return type
     */
    protected function getPageIndex() {
        $pageIndex = $this->_get('page');
        if (empty($pageIndex))
            $pageIndex = -1;
        return $pageIndex;
    }

    /**
     * 获取每页显示数量
     * @return type
     */
    protected function getPageSize() {
        $pageSize = $this->_get('rows');
        if (empty($pageSize))
            $pageSize = -1;
        if ($pageSize > 50)
            $pageSize = 50;
        return $pageSize;
    }

    /**
     * 外网转入记录获取。
     * @return type
     */
    protected function getEthOrders($user_id) {
        set_time_limit(200);
        $ethApi = new \EthApi();
        $userM = new \addons\member\model\MemberAccountModel();
        $eth_address = $userM->getUserAddress($user_id);
        if (!$eth_address)
            return false;

        $coinM = new \addons\config\model\Coins();
        $coins = $coinM->select();
        foreach ($coins as $coin) {
            $ethApi->set_byte($coin['byte']);
            if (!empty($coin['contract_address'])) {
                $ethApi->set_contract($coin['contract_address']);
            }
            $transaction_list = $ethApi->erscan_order($eth_address, $coin['is_token']);
            if (empty($transaction_list)) {
                continue;
            }
            $res = $this->checkOrder($user_id, $eth_address, $coin['id'], $transaction_list);
        }
        return true;
    }

    /**
     * 外网数据写入
     * @param type $user_id 用户id
     * @param type $address 用户地址
     * @param type $list    抓取到的数据
     * @param type $coin_id 币种id
     * @return boolean
     */
    private function checkOrder($user_id, $address, $coin_id, $list) {
        $m = new \addons\eth\model\EthTradingOrder();
        $balanceM = new \addons\member\model\Balance();
        $recordM = new \addons\member\model\TradingRecord();
        foreach ($list as $val) {
            $txhash = $val['hash'];
            $block_number = $val['block_number'];
            $from_address = $val['from'];
            try {
                $res = $m->getDetailByTxHash($txhash); //订单匹配
                if ($res) {
                    return true;
                }
                $m->startTrans();
                $amount = $val['amount'];
                $eth_order_id = $m->transactionIn($user_id, $from_address, $address, $coin_id, $amount, $txhash, $block_number, 0, 1, 1, "外网转入");
                if ($eth_order_id > 0) {
                    //插入转入eth记录成功
                    $balance = $balanceM->updateBalance($user_id, $amount, $coin_id, true);

                    if (!$balance) {
                        $m->rollback();
                        return false;
                    }

                    $type = 2;
                    $before_amount = $balance['before_amount'];
                    $after_amount = $balance['amount'];
                    $change_type = 1; //减少
                    $remark = '外网转入';
                    $r_id = $recordM->addRecord($user_id, $coin_id, $amount, $before_amount, $after_amount, $type, $change_type, $user_id, $address, '', $remark);
                    if (!$r_id) {
                        $m->rollback();
                        return false;
                    }
                    $m->commit();
                    return true;
                } else {
                    $m->rollback();
                    return false;
                }
            } catch (\Exception $ex) {
                return false;
            }
        }
        return true;
    }

    protected function countRate($total_price, $rate) {
        return $total_price * $rate / 100;
    }

    /**
     * 保存base64 图片
     * @param type $base64
     * @param type $user_id
     * @return boolean|string
     */
    protected function base_img_upload($base64 ,$user_id,$savePath){
        // 获取表单上传文件 例如上传了001.jpg
        if(empty($base64)){
            return false;
        }
        $_message = array(
            'success' =>false,
            'message' =>'',
        );
        $rootPath = UPLOADFOLDER;
        $uploadFolder = substr($rootPath, 1);
        $uploadPath = $uploadFolder . $savePath;
        $path = $_SERVER['DOCUMENT_ROOT'] . $uploadPath;
        $file_name= time(). getMD5Name(3,$user_id);
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64, $result)){
            $ext = array('jpg', 'gif', 'png', 'jpeg');
            $type = $result[2];
            if(!in_array($type, $ext)){
                $_message['message'] = '图片格式错误';
                return $_message;
            }
            $pic_path = $path. $file_name. "." .$type;
            $file_size = file_put_contents($pic_path, base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64)));
            if(!$pic_path || $file_size > 10 * 1024 * 1024){
                unlink($pic_path);
                $_message['message'] = '图片保存失败';
                return $_message;
            }

        }else{
            $_message['message'] = '图片格式编码错误';
            return $_message;
        }
        $_message['success'] = true;
        $_message['message'] = '上传成功';
        $_message['path'] = $uploadPath.$file_name.'.'.$type;
        return $_message;
    }

    protected function getBalanceByCache($coin_id)
    {
        $balance_cache = $this->getGlobalCache('balance_user_id');
        $rewardM = new \addons\fomo\model\RewardRecord();
        $user_id = $this->user_id;
//        $data['invite_reward'] = 0;
//        $data['other_reward'] = 0;
//        $data['update_time'] = NOW_DATETIME;
//        $this->setGlobalCache($data,'balance_user_id');
//        return $data;
        if(empty($balance_cache))
        {
            $data['invite_reward'] = $rewardM->getTotalByType($user_id, $coin_id,3);
            $data['other_reward'] = $rewardM->getTotalByType($user_id, $coin_id,'0,1,2'); //1
            $data['update_time'] = NOW_DATETIME;
            $this->setGlobalCache($data,'balance_user_id');

            return $data;
        }else
        {
            $update_time = $balance_cache['update_time'];
            $record_time = $rewardM->field('update_time')->where('user_id',$user_id)->order('update_time desc')->find();
            if(strtotime($record_time['update_time']) <= (strtotime($update_time) + 600))
            {
                return $balance_cache;
            }

            $data['invite_reward'] = $rewardM->getTotalByType($user_id, $coin_id,3);
            $data['other_reward'] = $rewardM->getTotalByType($user_id, $coin_id,'0,1,2'); //1
            $data['update_time'] = $record_time['update_time'];
            $this->setGlobalCache($data,'balance_user_id');

            return $data;
        }
    }

    protected function getBalanceByCacheAndType($coin_id,$type)
    {
        $balance_cache = $this->getGlobalCache('balance_type_user_id');
        $rewardM = new \addons\fomo\model\RewardRecord();
        $user_id = $this->user_id;
        if(empty($balance_cache))
        {
            $data['amount'] = $rewardM->getTotalByType($user_id, $coin_id,6);
            $data['update_time'] = NOW_DATETIME;
            $this->setGlobalCache($data,'balance_type_user_id');

            return $data;
        }else
        {
            $update_time = $balance_cache['update_time'];
            $where['user_id'] = $user_id;
            $where['type'] = $type;
            $record_time = $rewardM->field('update_time')->where($where)->order('update_time desc')->find();
            if(strtotime($record_time['update_time']) <= (strtotime($update_time) + 600))
                return $balance_cache;

            $data['amount'] = $rewardM->getTotalByType($user_id, $coin_id,6);
            $data['update_time'] = $record_time['update_time'];
            $this->setGlobalCache($data,'balance_type_user_id');

            return $data;
        }
    }


}





















