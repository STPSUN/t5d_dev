<?php

namespace addons\fomo\model;

/**
 * @author shilinqing
 */
class Game extends \web\common\model\BaseModel{
    
    protected function _initialize() {
        $this->tableName = 'fomo_game';
    }
    
    public function getList($pageIndex = -1, $pageSize = -1, $filter = '', $order = 'id desc') {
        $m = new \addons\config\model\Coins();
        $u = new \addons\member\model\MemberAccountModel();
        $t = new \addons\fomo\model\Team();
        $p = new \addons\fomo\model\KeyPrice();
        $sql = 'select g.*,c.coin_name,u.username as winner_user_name,t.name as winner_team_name,p.drop_process from ' . $this->getTableName() . ' g left join '.$m->getTableName().' c on c.id=g.coin_id left join '.$u->getTableName().' u on u.id=g.winner_user_id left join '.$t->getTableName().' t on t.id=g.winner_team_id left join '.$p->getTableName().' p on p.game_id=g.id';
        if (!empty($filter))
            $sql = 'select * from ('.$sql.') as t where '.$filter;
        return $this->getDataListBySQL($sql, $pageIndex, $pageSize, $order);
    }
    
    public function startGame($id,$hour){
        $where['id'] = $id;
        $where['status'] = 0;
        $data['status'] = 1;
        $data['start_time'] = time();
        $data['end_game_time'] = strtotime("+".$hour." hour");
        $last_end_game = $this->getLastEndGame('id,to_next_amount');
        if(!empty($last_end_game)){
            $last_end_game = $last_end_game[0];
            $data['pool_total_amount'] = $last_end_game['to_next_amount'];
        }
        return $this->where($where)->update($data);
    }
    
    public function getRunGame($fields='id,status,rule_id,coin_id,coin_name,winner_rate,team_rate,fund_rate,drop_total_amount,total_buy_seconds,total_amount,pool_total_amount,release_total_amount,start_time,end_game_time'){
        $m = new \addons\config\model\Coins();
        $sql = 'select a.* ,b.coin_name from '.$this->getTableName().' a ,'.$m->getTableName().' b where a.status=1 and a.coin_id=b.id';
        if($fields!=''){
            $sql = 'select '.$fields.' from ('.$sql.') as tab';
        }
        $sql.=' limit 0,1';
        return $this->query($sql);
    }
    
    public function getLastEndGame($fields='id,status,rule_id,coin_id,coin_name,winner_rate,team_rate,fund_rate,drop_total_amount,total_buy_seconds,total_amount,pool_total_amount,release_total_amount,start_time,end_game_time'){
        $m = new \addons\config\model\Coins();
        $sql = 'select a.* ,b.coin_name from '.$this->getTableName().' a ,'.$m->getTableName().' b where a.status=2 and a.coin_id=b.id order by end_game_time desc';
        if($fields!=''){
            $sql = 'select '.$fields.' from ('.$sql.') as tab';
        }
        $sql.=' limit 0,1';
        return $this->query($sql);
    }
    
    /**
     * 更新空投总奖金池与已空投总数
     */
    public function updateDropKindData($id, $bonus){
        $m = new \addons\fomo\model\AirdropConf();
        $surplus = $m->getValByName('surplus');
        if($surplus == 0){
            $csql = 'drop_total_amount=drop_total_amount-'.$bonus.',';
        }else{
            $csql = 'drop_total_amount=0,';
        }
        $sql = 'update '.$this->getTableName().' set '.$csql.' already_drop_amount=already_drop_amount+'.$bonus.' where id='.$id;
        return $this->execute($sql);
        
    }
    
    
}
