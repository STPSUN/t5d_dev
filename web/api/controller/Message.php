<?php

namespace web\api\controller;

class Message extends \web\api\controller\ApiBase{

   public function getNotice(){
        $m = new \addons\fomo\model\Remark();
        $rows = $m->getDataList();
        foreach($rows as &$val){
            $val['content'] = preg_replace("/\/ueditor\//","{$_SERVER['SERVER_NAME']}/ueditor/",$val['content']);
            $val['content'] = htmlspecialchars_decode(html_entity_decode($val['content']));
        }
       return $this->successJSON($rows);
   }
   
   /**
     * 系统公告
     */
    public function notice(){
        $filter= '1=1 ';
        $m = new \addons\config\model\Notice();
        $rows = $m->getDataList($this->getPageIndex(), $this->getPageSize(), $filter, '', 'id desc');
        foreach($rows as &$val){
            $val['content'] = preg_replace("/\/ueditor\//","{$_SERVER['SERVER_NAME']}/ueditor/",$val['content']);
        }
        return $this->successJSON($rows);
    }

    /**
     * 系统公告详情
     */

    public function noticeDetail(){
        $id = $this->_get('id/d');
        if(!$id){
            return $this->failJSON("missing arguments");
        }
        $filter= '1=1 and id ='.$id;
        $m = new \addons\config\model\Notice();
        $data = $m->getDetail($id);
        return $this->successJSON($data);
    }

    /**
     * 系统公告条数
     */

    public function noticeCount(){
        $filter= '1=1 ';
        $m = new \addons\config\model\Notice();
        $count = $m->count();
        $data=[];
        $data['count'] = $count;
        return $this->successJSON($data);
    }



    /**
     * 帮助列表
     */

    public function help(){
        $type = $this->_get('type',1);
        $filter= '1=1 and type = '.$type;
        $m = new \addons\config\model\Help();
        $rows = $m->getDataList($this->getPageIndex(), $this->getPageSize(), $filter, '', 'id asc');
        foreach($rows as &$val){
            $val['content'] = preg_replace("/\/ueditor\//","{$_SERVER['SERVER_NAME']}/ueditor/",$val['content']);
            $val['content'] = htmlspecialchars_decode(html_entity_decode($val['content']));
        }
        return $this->successJSON($rows);
    }


    /**
     * 提交用户反馈
     */
    public function postSuggest(){
        $user_id = $this->user_id;
        if(!$user_id || $user_id<=0){
            return $this->failJSON("请登录后操作");
        }
        $content = $this->_post('content');
        $title = $this->_post('title');
        if(empty($user_id) || !isset($content) || !isset($title)){
            return $this->failJSON('missing arguments');
        }
        try{
            $base64 = $this->_post('file');
            if($base64){
                $savePath = 'member/chat/'.$user_id.'/';
                $upload = $this->base_img_upload($base64, $user_id,$savePath);
                if(!$upload['success']){
                    return $this->failJSON($upload['message']);
                }
                $data['pic']=$upload['path'];
            }
            $m = new \addons\member\model\Suggest();
            $data['user_id'] = $user_id;
            $data['content'] = urldecode($content);
            $data['title'] = urldecode($title);
            $data['create_time'] = NOW_DATETIME;
            $id = $m->add($data);
            if($id > 0){
                return $this->successJSON();
            }
        } catch (\Exception $ex) {
            return $this->failJSON($ex->getMessage());
        }

    }



    //留言详情
    public function FeedbackList(){
        $user_id = $this->user_id;
        $content = $this->_post('content');
        $title = $this->_post('title');
        if(empty($user_id) || !isset($content) || !isset($title)){
            return $this->failJSON('missing arguments');
        }
        $m = new \addons\member\model\Suggest();
        $filter = 'user_id='.$user_id;
        $rows = $m->getDataList($this->getPageIndex(), $this->getPageSize(), $filter, '', 'id desc');

        foreach($rows as &$val){
            $val['update_time'] = $val['create_time'];
        }

        return $this->successJSON($rows);
    }



    //留言详情
    public function FeedbackFid(){
        $user_id = $this->user_id;
        $msg_id = $this->_post('id/d');
        if(empty($user_id) || !$msg_id || $user_id <= 0){
            return $this->failJSON('missing arguments');
        }
        $m = new \addons\member\model\Suggest();
        $info = $m->getMsgDetail($msg_id,$user_id);
        if(!$info){
            return $this->failJSON($m->getError().'1');
        }
        $info['back_content'] = preg_replace("/\/ueditor\//","{$_SERVER['SERVER_NAME']}/ueditor/",$info['back_content']);

        $info['back_content'] = htmlspecialchars_decode(html_entity_decode($info['back_content']));
        return $this->successJSON($info);
    }

    public function about(){
        try{
            $m = new \web\common\model\sys\SysOemModel();
            $where['id'] = 1;
            $data = $m->where($where)->value("about");
            $data = preg_replace("/\/ueditor\//","{$_SERVER['SERVER_NAME']}/",$data);
            $data = htmlspecialchars_decode(html_entity_decode($data));
            return $this->successJSON($data);
        } catch (\Exception $ex) {
            return $this->failJSON($ex->getMessage());
        }
    }


}