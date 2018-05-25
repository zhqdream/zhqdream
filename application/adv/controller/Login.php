<?php
namespace app\adv\controller;
use think\Controller;
use think\Db;
use think\Session;
use think\captcha\Captcha;
use think\Request;
use think\Loader;
/**
 * @date:2018/04
 * @author : zhq
 * @desc : 登陆控制器
 */
class Login extends Controller
{
    /**
     * @desc : 渲染登陆页面
     * @author : zhq
     * @date : 2018/04
     */
    public function index(){
        $token = input('token');
        if($token=='tokenerr'){
            $this->assign('token',$token);
        }else{
            $this->assign('token','');
        }
        return view('login',['name'=>'login']);
    }
    /**
     * @desc : 验证用户登录
     * @author : zhq
     * @date : 2018/03
     */
    public function checkUser(){
        //用户登录的账号
        $mobile = input('post.mobile');
        //用户登录的密码
        $password = input('post.password');
        //验证码
        $code = input('post.code');
        //短信验证码
        $msgcode = input('post.msgcode');
        if(empty($mobile) || empty($password) || empty($code)){
            return json_encode_conf(1000,'参数不能为空，请检查！');
        }
        //判断验证码是否正确
        if(!captcha_check($code)){
            return json_encode_conf(1001,'验证码错误，请检查！');
        };
        //检查用户名和密码是否正确
        $data = Db::table('adv_manager')->field('id,mobile,password,status,logintimes,login_time,token,role_id,menu_id,is_note,customer_id,web_title')->where('mobile',$mobile)->find();
        //判断是否启用短信验证码登录
        if($data['is_note']==1){
            if(!$msgcode){
                return json_encode_conf(1006,'系统已开启短信验证码验证！');
            }
            //验证短信验证码
            $key = 'verfy'.$mobile;
            $smsverfy = Session::get($key);
            if($smsverfy!=$msgcode){
                return json_encode_conf(1007,'短信验证码错误，请重新输入！');
            }

        }
        if(!$data){
            return json_encode_conf(1002,'该用户不存在，请检查！');
        }
        if($data['status']==2){
            return json_encode_conf(1004,'该账号被禁用，请联系管理员！');
        }
        if($data['status']==3){
            return json_encode_conf(1005,'该账号被冻结，请联系管理员！');
        }
        if($mobile==$data['mobile'] && md5($password)==$data['password']){
            //更新登录信息
            $token = md5($data['mobile'].time());
            Db::table('adv_manager')->where('id', $data['id'])->update(['login_time' => date('Y-m-d H:i:s'),'logintimes'=>$data['logintimes']+1,'token'=>$token]);
            //记录用户的token
            Session::set('token',$token);
            //记录用户的uid
            Session::set('uid',$data['id']);
            //记录用户的角色
            Session::set('role_id',$data['role_id']);
            //记录用户的标题
            Session::set('web_title',$data['web_title']);
            //获取当前菜单的主菜单
            if($data['role_id']==1){
                $where = 1;
            }else{
                $where = "id in({$data['menu_id']})";
            }
            $pmenu = Db::table('adv_menu')->distinct('p_id')->where($where)->field('p_id')->select();
            foreach($pmenu as $k=>$v){
                $pids[] = $v['p_id'];
            }
            $p_id = implode(',',$pids);
            $data['menu_id'].=','.$p_id;
            //记录用户的菜单
            Session::set('menu_id',$data['menu_id']);
            //记录用户所属厂商
            Session::set('customer_id',$data['customer_id']);

            //记录登录日志
            $message = "登录人：[$mobile],登录ip:".$this->getip();
            $controller = request()->controller();
            $action = request()->action();
            $opt_type = $controller.'/'.$action;
            //记录操作日志
            $this->add_optlog($message,$opt_type);

            return json_encode_conf(200,'登录成功！');
        }else{
            return json_encode_conf(1003,'账号或密码错误！');
        }
    }
    //获取IP
    function getip() {
        $unknown = 'unknown';
        if ( isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARDED_FOR'] && strcasecmp($_SERVER['HTTP_X_FORWARDED_FOR'], $unknown) ) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif ( isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], $unknown) ) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        /*
        处理多层代理的情况
        或者使用正则方式：$ip = preg_match("/[\d\.]{7,15}/", $ip, $matches) ? $matches[0] : $unknown;
        */
        if (false !== strpos($ip, ','))
            $ip = reset(explode(',', $ip));
        return $ip;
    }

    /**
     * @desc : 记录日志操作
     * @author : zhq
     * @date : 2018/03
     */
    public function add_optlog($message,$opt_type){
        $uid = Session::get('uid');
        $log = ['time'=>date('Y-m-d H:i:s'),'uid'=>$uid,'message'=>$message,'opt_type'=>$opt_type];
        $result = Db::table('adv_opter_log')->insert($log);
        return $result;
    }
    /**
     * @desc : 检测用是否需要短信验证
     * @author : zhq
     * @date : 2018/05
     */
    public function checknote(){
        $mobile = input('mobile');
        $data = Db::table('adv_manager')->where('mobile',$mobile)->value('is_note');
        if($data==1){
            echo 'error';exit;
        }else{
            echo 'ok';exit;
        }

    }
    /**
     * @desc : 退出登录
     * @author : zhq
     * @date : 2018/03
     */
    public function logOut(){
        //删除uid和token
        Session::delete('uid');
        Session::delete('token');
        $this->redirect('/index/adv/login/index');

    }
    /**
     * @desc : 生成验证码
     * @author : zhq
     * @date : 2018/03
     */
    public function captcha(){
        $config =    [
            // 验证码字体大小
            'fontSize'    =>    30,
            // 验证码位数
            'length'      =>    4,
            // 关闭验证码杂点
            'useNoise'    =>    false,
        ];
        $captcha = new Captcha($config);
        return $captcha->entry();
    }

    /**
     * @desc : (阿里)发送短信验证码
     * @author : zhq
     * @date : 2017/11
     */
    public function sendSms(){
        $verf = rand(pow(10,(6-1)), pow(10,6)-1);
        $mobile = input('mobile');
        //获取sms类
        Loader::import('sms/api_demo/SmsDemo');
        $response = \SmsDemo::sendSms($mobile,$verf);
        $key = 'verfy'.$mobile;
        Session::set($key,$verf);
        $content = $this->object_to_array($response);
        if($content['Code']=='OK'){
            echo 'ok';exit;
        }else{
            echo 'error';exit;
        }
    }
    /**
     * @desc : 将对象转为数组
     * @author : zhq
     * @date : 2018/03
     */
    public function object_to_array($obj) {
        $obj = (array)$obj;
        foreach ($obj as $k => $v) {
            if (gettype($v) == 'resource') {
                return;
            }
            if (gettype($v) == 'object' || gettype($v) == 'array') {
                $obj[$k] = (array)object_to_array($v);
            }
        }

        return $obj;
    }



}
