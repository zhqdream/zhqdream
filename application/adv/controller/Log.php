<?php
namespace app\adv\controller;
use app\adv\controller\Base;
use think\Controller;
use think\Db;
use think\Session;
use think\captcha\Captcha;
use think\Request;
/**
 * @date:2018/04
 * @author : zhq
 * @desc : 操作日志系统
 */
class Log extends Base
{
    /**
     * @desc : 日志列表
     * @author : zhq
     * @date : 2018/04
     */
    public function logList(){
        return view('logList',['name'=>'Log']);
    }
    /**
     * @desc : 获取操作日志列表
     * @author : zhq
     * @date : 2018/04
     */
    public function getloglist(){
        $pageIndex = input('pageIndex');
        $pageSize = input('pageSize');
        $param = input('param');
        $offset = ceil($pageIndex*$pageSize);
        if($param){
            $where = "mobile like '%{$param}%'";
            $dataLength = Db::table('adv_opter_log')->alias('l')->join("adv_manager m",'l.uid = m.id')->where($where)->count();
        }else{
            $where = 1;
            $dataLength = Db::table('adv_opter_log')->count();
        }
        $loglist = Db::table('adv_opter_log')->query("SELECT l.id,l.message,l.`time`,m.mobile FROM adv_opter_log as l LEFT JOIN adv_manager as m ON l.uid = m.id WHERE {$where} ORDER BY l.id DESC LIMIT $offset,$pageSize");
        echo json_encode(array('rowDatas'=>$loglist,'dataLength'=>$dataLength));exit;
    }
    /**
     * @desc : 厂商型号列表
     * @author : zhq
     * @date : 2018/04
     */
    public function manuList(){
        $id = input('id');
        $this->assign('id',$id);
        //通过厂商ID获取厂商信息
        $customer_name = Db::table('adv_customer_group')->where("id = {$id}")->value('customer_name');
        $this->assign('customer_name',$customer_name);
        return view('manuList',['name'=>'Log']);
    }
    /**
     * @desc : 厂商组列表
     * @author : zhq
     * @date : 2018/05
     */
    public function customer_group(){
        //获取所有未绑定的厂商型号
        $maunlist = Db::table('adv_customer_list')->where("customer_id = 0")->field('id,customer_name')->select();
        $this->assign('maunlist',$maunlist);
        //将厂商型号添加到表中
        $modellist = db()->query("SELECT channel FROM device_info GROUP BY channel");
        foreach($modellist as $v){
            $moderes = Db::table('adv_customer_list')->where("customer = '{$v['channel']}'")->value('customer');
            if(!$moderes){
                $param = ['customer'=>$v['channel'],'customer_name'=>$v['channel']];
                Db::table('adv_customer_list')->insert($param);
            }
        }
        return view('customer_group',['name'=>'Log']);
    }
    /**
     * @desc : 获取厂商列表
     * @author : zhq
     * @date : 2017/05
     */
    public function getcustomerlist(){
        $pageIndex = input('pageIndex');
        $pageSize = input('pageSize');
        $param = input('param');
        $offset = ceil($pageIndex*$pageSize);
        if($param){
            $where = "customer_name like '%{$param}%'";
            $dataLength = Db::table('adv_customer_group')->where($where)->count();
        }else{
            $where = 1;
            $dataLength = Db::table('adv_customer_group')->count();
        }
        $adv_customer_group = Db::table('adv_customer_group')->query("SELECT * FROM adv_customer_group  WHERE {$where} ORDER BY id DESC LIMIT $offset,$pageSize");
        foreach($adv_customer_group as $k=>$v){
            $adv_customer_group[$k]['opt'] = "<a href='/index/adv/log/manuList/id/{$v["id"]}'>查看型号</a>";
        }
        echo json_encode(array('rowDatas'=>$adv_customer_group,'dataLength'=>$dataLength));exit;
    }

    /**
     * @desc : 获取厂商型号列表
     * @author : zhq
     * @date : 2018/04
     */
    public function getmanulist(){
        $pageIndex = input('pageIndex');
        $pageSize = input('pageSize');
        $param = input('param');
        $manu_id = input('manu_id');
        $offset = ceil($pageIndex*$pageSize);
        if($param){
            $where = "customer like '%{$param}%' and customer_id = {$manu_id}";
            $dataLength = Db::table('adv_customer_list')->where($where)->count();
        }else{
            $where = "customer_id = {$manu_id}";
            $dataLength = Db::table('adv_customer_list')->where($where)->count();
        }
        $customerlist = Db::table('adv_customer_list')->query("SELECT * FROM adv_customer_list  WHERE {$where} ORDER BY id DESC LIMIT $offset,$pageSize");
        echo json_encode(array('rowDatas'=>$customerlist,'dataLength'=>$dataLength));exit;
    }

    /**
     * @desc : 删除厂商信息
     * @author : zhq
     * @date : 2018/04
     */
    public function del_customer(){
        $role_id = Session::get('role_id');
        if($role_id!=1){
            return json_encode_conf(5000,'您无权限进行操作！');
        }
        $ids = input('ids');
        $ids = rtrim($ids,',');
        if(empty($ids)){
            return json_encode_conf(3005,'未选择用厂商！');
        }
        $userinfo = Db::table('adv_customer_group')->field('id,customer_name')->where("id in({$ids})")->select();
        $customer = '';
        foreach ($userinfo as $v){
            //查看当前厂商下面是否有关联厂商型号
            $cuslist = Db::table('adv_customer_list')->where("customer_id = ".$v['id'])->value('id');
            if($cuslist){
                return json_encode_conf(3007,'该厂商下面关联有厂商型号，不能删除！');
            }
            $customer.=$v['customer_name'].';';
        }
        $userdelres = Db::table('adv_customer_group')->where("id in({$ids})")->delete();
        if($userdelres){
            $message = "操作人[$this->mobile],删除厂商{$customer}";
            $controller = request()->controller();
            $action = request()->action();
            $opt_type = $controller.'/'.$action;
            //记录操作日志
            $this->add_optlog($message,$opt_type);
            return json_encode_conf(200,'删除成功');
        }else{
            return json_encode_conf(3006,'删除厂商失败');
        }
    }

    /**
     * @desc : 删除厂商型号信息
     * @author : zhq
     * @date : 2018/04
     */
    public function del_manu(){
        $role_id = Session::get('role_id');
        if($role_id!=1){
            return json_encode_conf(5000,'您无权限进行操作！');
        }
        $ids = input('ids');
        $ids = rtrim($ids,',');
        if(empty($ids)){
            return json_encode_conf(3005,'未选择用厂商！');
        }
        $userinfo = Db::table('adv_customer_list')->field('id,customer')->where("id in({$ids})")->select();
        $customer = '';
        foreach ($userinfo as $v){
            $customer.=$v['customer'].';';
        }
        $userdelres = Db::table('adv_customer_list')->where("id in({$ids})")->delete();
        if($userdelres){
            $message = "操作人[$this->mobile],删除厂商{$customer}";
            $controller = request()->controller();
            $action = request()->action();
            $opt_type = $controller.'/'.$action;
            //记录操作日志
            $this->add_optlog($message,$opt_type);
            return json_encode_conf(200,'删除成功');
        }else{
            return json_encode_conf(3006,'删除厂商失败');
        }
    }
    /**
     * @desc : 获取厂商信息
     * @author : zhq
     * @date : 2018/04
     */
    public function customer_info(){
        $id = input('id');
        if(empty($id)){
            return json_encode_conf(1000,'参数不能为空，请检查！');
        }
        $manuinfo = Db::table('adv_customer_group')->field('id,customer_name,customer_phone,customer_address')->where("id = {$id}")->find();
        return json_encode_conf(200,$manuinfo);
    }
    /**
     * @desc : 获取厂商型号信息
     * @author : zhq
     * @date : 2018/04
     */
    public function manu_info(){
        $id = input('id');
        if(empty($id)){
            return json_encode_conf(1000,'参数不能为空，请检查！');
        }
        $manuinfo = Db::table('adv_customer_list')->field('id,customer,customer_id,customer_name')->where("id = {$id}")->find();
        return json_encode_conf(200,$manuinfo);
    }

    /**
     * @desc : 修改厂商信息
     * @author : zhq
     * @date : 2018/04
     */
    public function update_customerinfo(){
        $role_id = Session::get('role_id');
        if($role_id!=1){
            return json_encode_conf(5000,'您无权限进行操作！');
        }
        $data = input();
        $id = $data['id'];
        $customer_name = $data['customer_name'];
        $customer_phone = $data['customer_phone'];
        $customer_address = $data['customer_address'];
        if(empty($id) || empty($customer_name)){
            $this->error('参数不能为空');
        }
        $upd = ['customer_name'=>$customer_name,'customer_phone'=>$customer_phone,'customer_address'=>$customer_address];
        $updres = Db::table('adv_customer_group')->where("id = $id")->update($upd);
        if($updres!==false){
            $message = "操作人[$this->mobile],修改了厂商{$customer_name}的信息";
            $controller = request()->controller();
            $action = request()->action();
            $opt_type = $controller.'/'.$action;
            //记录操作日志
            $this->add_optlog($message,$opt_type);
            $this->redirect("/index/adv/log/customer_group");
        }else{
            $this->error('修改失败！');
        }
    }

    /**
     * @desc : 修改厂商型号信息
     * @author : zhq
     * @date : 2018/04
     */
    public function update_manuinfo(){
        $role_id = Session::get('role_id');
        if($role_id!=1){
            return json_encode_conf(5000,'您无权限进行操作！');
        }
        $data = input();
        $id = $data['id'];
        $customer_id = $data['customer_id'];
        $customer_name = $data['customer_name'];
        if(empty($id)){
            $this->error('参数不能为空');
        }
        $upd = ['customer_name'=>$customer_name];
        $updres = Db::table('adv_customer_list')->where("id = $id")->update($upd);
        if($updres!==false){
            $message = "操作人[$this->mobile],修改了厂商型号{$customer_name}的信息";
            $controller = request()->controller();
            $action = request()->action();
            $opt_type = $controller.'/'.$action;
            //记录操作日志
            $this->add_optlog($message,$opt_type);
            $this->redirect("/index/adv/log/manuList/id/$customer_id");
        }else{
            $this->error('修改失败！');
        }
    }
    /**
     * @desc : 添加厂商信息
     * @author : zhq
     * @date : 2018/05
     */
    public function add_customer(){
        $role_id = Session::get('role_id');
        if($role_id!=1){
            return json_encode_conf(5000,'您无权限进行操作！');
        }
        $data = input();
        $customer_id = $data['customer_id'];
        $customer_name = $data['customer_name'];
        $customer_phone = $data['customer_phone'];
        $customer_address = $data['customer_address'];
        if(empty($customer_id) || empty($customer_name)){
            $this->error('参数不能为空');
        }
        //开启事物
        $customer = ['customer_name'=>$customer_name,'customer_phone'=>$customer_phone,'customer_address'=>$customer_address,'add_time'=>date('Y-m-d H:i:s')];
        Db::table('adv_customer_group')->startTrans();
        $customres = Db::table('adv_customer_group')->insert($customer);
        $groupid = Db::table('adv_customer_group')->getLastInsID();
        //将选择的型号绑定到该厂商下
        $customer_id = implode(',',$customer_id);
        $manures = Db::table('adv_customer_list')->where("id in ({$customer_id})")->update(array('customer_id'=>$groupid));
        if($customres&&$manures){
            $message = "操作人[$this->mobile],添加了厂商{$customer_name}的信息";
            $controller = request()->controller();
            $action = request()->action();
            $opt_type = $controller.'/'.$action;
            //记录操作日志
            $this->add_optlog($message,$opt_type);
            Db::table('adv_customer_group')->commit();
            $this->success('添加成功');
        }else{
            $this->error('添加失败');
            Db::table('adv_customer_group')->rollback();
        }
    }
}
