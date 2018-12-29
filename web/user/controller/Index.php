<?php

namespace web\user\controller;


/**
 * 后台首页
 */
class Index extends Base {

    public function index() {
       return $this->fetch();
    }
    
    public function edit_pwd() {
        if (IS_POST) {

            $m = new \web\common\model\user\AccountModel();
            $id = $this->login_user_id;
            $password = trim($_POST['password1']);
            $accountData['id'] = $id;
            $accountData['password'] = md5($password);
            $ret = $m->save($accountData);
            if ($ret >= 0) {
                $data = $m->getAccountDetail( $id);

                if ( $data['is_admin'] == 1) {
                    $ori_password = $data['password']; //原密码
                    //总部超级管理员

                    $ret = $m->updatePassword($id, $password);
                    if ($ret >= 0)
                        return $this->successData();
                    else {
                        //将密码修改为原密码
                        $m->updatePassword($id, $ori_password);
                        return $this->failData('修改密码失败');
                    }
                } else
                    return $this->successData();
            } else
                return $this->failData($m->getError());
        } else {
            $this->assign('username', $this->login_user_name);
            $this->assign('id', '');
            $this->assign('permission', array(1, 0, 1));
            return $this->fetch();
        }
    }
}