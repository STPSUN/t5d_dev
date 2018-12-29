<?php

namespace addons\config\user\controller;

class About extends \web\user\controller\AddonUserBase{
    
    public function index() {
        if (IS_POST) {
            $about = $this->_post("about");
            $m = new \web\common\model\sys\SysOemModel();
            $ret = $m->where("id",1)->update(['about' => $about]);
            return $this->successData();
        } else {
            $m = new \web\common\model\sys\SysOemModel();
            $where['id'] = 1;
            $data = $m->where($where)->value("about");
            $data = preg_replace("/\/ueditor\//","http://cgcc.ifc007.com/ueditor/",$data);
            $this->assign('id', 1);
            $this->assign('about', $data);
            $this->setLoadDataAction('loadData');
            return $this->fetch();
        }
    }

    public function loadData() {
        $m = new \web\common\model\sys\SysOemModel();
        $where['id'] = 1;
        $data = $m->where($where)->find();
        $data = preg_replace("/\/ueditor\//","http://cgcc.ifc007.com/ueditor/",$data);
        $data['about'] = htmlspecialchars_decode(html_entity_decode($data['about']));
        return $data;
    }

}
