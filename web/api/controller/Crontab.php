<?php

namespace web\api\controller;

class Crontab extends ApiBase{
    public function getPayData(){

    }

    public  function index(){
      $m = new \addons\otc\model\OtcOrder();
      
      print_r($m->dealOverTimeOrder());
    }

}
