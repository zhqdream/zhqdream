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
 * @desc : 用户系统
 */
class User extends Base
{
    /**
     * @desc : 用户列表
     * @author : zhq
     * @date : 2018/04
     */
    public function userList(){
        //获取所有角色
        $role_list = Db::table('adv_role')->field('id,role_name')->select();
        $this->assign('role_list',$role_list);
        //获取所有厂商
        $customerlist = Db::table('adv_customer_group')->field('id,customer_name')->select();
        $this->assign('customerlist',$customerlist);
        //获取菜单
        $data = db()->query("select id,p_id,title,icon,href from adv_menu order by m_level desc ");
        $menu = $this->getMmenu($data,0,array());
        $role_id = Session::get('role_id');
        $this->assign('role_id',$role_id);
        $this->assign('menu',$menu);
        return view('userList',['name'=>'User']);
    }
    /**
     * @desc : 获取用户列表数据
     * @author : zhq
     * @date : 2018/04
     */
    public function getuserlist(){
        $pageIndex = input('pageIndex');
        $pageSize = input('pageSize');
        $param = input('param');
        $offset = ceil($pageIndex*$pageSize);
        $role_id = Session::get('role_id');
        $uid = Session::get('uid');
        if($param){
            $where = "mobile like '%{$param}%'";
            if($role_id != 1){
                $where.=" and id = {$uid}";
            }
            $dataLength = Db::table('adv_manager')->where($where)->count();
        }else{
            if($role_id != 1){
                $where ="id = {$uid}";
            }else{
                $where = 1;
            }
            $dataLength = Db::table('adv_manager')->where($where)->count();
        }
        if($role_id != 1){
            $where.=" and id = {$uid}";
        }
        $userlist = Db::table('adv_manager')->query("SELECT id,mobile,reg_time,login_time,`status`,logintimes FROM adv_manager WHERE {$where} ORDER BY id DESC LIMIT $offset,$pageSize");
        foreach($userlist as $k=>$v){
            //登陆状态(1:正常  2：禁用  3：冻结  )
            if($v['status']==1){
                $userlist[$k]['status'] = '<b style="color: #3c763d">正常</b>';
            }elseif($v['status']==2){
                $userlist[$k]['status'] = '<b style="color: red">禁用</b>';
            }else{
                $userlist[$k]['status'] = '<b style="color: #f9d21a">冻结</b>';
            }
        }
        echo json_encode(array('rowDatas'=>$userlist,'dataLength'=>$dataLength));exit;
    }
    /**
     * @desc : 删除用户数据
     * @author : zhq
     * @date : 2018/04
     */
    public function del_user(){
        $role_id = Session::get('role_id');
        if($role_id!=1){
            return json_encode_conf(5000,'您无权限进行操作！');
        }
        $ids = input('ids');
        $ids = rtrim($ids,',');
        if(empty($ids)){
            return json_encode_conf(2005,'未选择用户！');
        }
        $uid = Session::get('uid');
        $userinfo = Db::table('adv_manager')->field('id,mobile')->where("id in({$ids})")->select();
        $mobile = '';
        foreach ($userinfo as $v){
            if($v['id']==$uid){
                return json_encode_conf(2001,'您不能删除自己！');
            }
            $mobile.=$v['mobile'].';';
        }
        $userdelres = Db::table('adv_manager')->where("id in({$ids})")->delete();
        if($userdelres){
            $message = "操作人[$this->mobile],删除用户{$mobile}";
            $controller = request()->controller();
            $action = request()->action();
            $opt_type = $controller.'/'.$action;
            //记录操作日志
            $this->add_optlog($message,$opt_type);
            return json_encode_conf(200,'删除成功');
        }else{
            return json_encode_conf(2006,'删除用户失败');
        }
    }
    /**
     * @desc : 系统添加用户
     * @author : zhq
     * @date : 2018/04
     */
    public function add_user(){
        $role_id = Session::get('role_id');
        if($role_id!=1){
            return json_encode_conf(5000,'您无权限进行操作！');
        }
        $data = input();
        $username = $data['username'];
        $password = $data['password'];
        $role = $data['role'];
        $status = $data['userstatus'];
        $is_note = $data['is_note'];
        $customer_id = $data['customer_id'];
        $web_title = $data['web_title'];
        $menu_id = implode(',',$data['menu_id']);
        if(empty($username) || empty($password) || empty($role)){
            $this->error('参数不能为空，请检查！');
        }
        $userstatus = Db::table('adv_manager')->field('id')->where("mobile = {$username}")->find();
        if($userstatus){
            $this->error('该用户已经存在，请不要重复添加！');
        }
        $param = ['mobile'=>$username,'password'=>md5($password),'role_id'=>$role,'reg_time'=>date('Y-m-d H:i:s'),'status'=>$status,'is_note'=>$is_note,'customer_id'=>$customer_id,'menu_id'=>$menu_id,'web_title'=>$web_title];
        $userres = Db::table('adv_manager')->insert($param);
        if($userres){
            $message = "操作人[$this->mobile],添加用户{$username}";
            $controller = request()->controller();
            $action = request()->action();
            $opt_type = $controller.'/'.$action;
            //记录操作日志
            $this->add_optlog($message,$opt_type);
            $this->redirect('/index/adv/user/userList');
        }else{
            $this->error('添加用户失败！');
        }
    }
    /**
     * @desc : 获取用户的信息
     * @author : zhq
     * @date : 2018/04
     */
    public function get_userinfo(){
        $id = input('id');
        if(empty($id)){
            return json_encode_conf(1000,'参数不能为空，请检查！');
        }
        $userinfo = Db::table('adv_manager')->field('id,mobile,role_id,status,is_note,menu_id,customer_id,web_title')->where("id = {$id}")->find();
        return json_encode_conf(200,$userinfo);
    }
    /**
     * @desc : 修改用户的基本信息
     * @author : zhq
     * @date : 2018/04
     */
    public function update_user(){
        $role_id = Session::get('role_id');
        if($role_id!=1){
            return json_encode_conf(5000,'您无权限进行操作！');
        }
        $data = input();
        $id = $data['id'];
        $password = $data['updpassword'];
        $role = $data['updrole'];
        $username = $data['mobile'];
        $userstatus = $data['userstatus'];
        $is_note = $data['is_note'];
        $customer_id = $data['customer_id'];
        $menu_id = implode(',',$data['upmenu_id']);
        $web_title = $data['web_title'];
        if(empty($id)){
            $this->error('参数不能为空');
        }
        if($password){
            $upd = ['role_id'=>$role,'password'=>md5($password),'status'=>$userstatus,'is_note'=>$is_note,'customer_id'=>$customer_id,'menu_id'=>$menu_id,'web_title'=>$web_title];
        }else{
            $upd = ['role_id'=>$role,'status'=>$userstatus,'is_note'=>$is_note,'customer_id'=>$customer_id,'menu_id'=>$menu_id,'web_title'=>$web_title];
        }
        $updres = Db::table('adv_manager')->where("id = $id")->update($upd);
        if($updres!==false){
            $message = "操作人[$this->mobile],修改了用户{$username}的信息";
            $controller = request()->controller();
            $action = request()->action();
            $opt_type = $controller.'/'.$action;
            //记录操作日志
            $this->add_optlog($message,$opt_type);
            $this->redirect('/index/adv/user/userList');
        }else{
            $this->error('修改失败');
        }
    }
}
