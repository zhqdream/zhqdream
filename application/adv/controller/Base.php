<?php
namespace app\adv\controller;
use think\Controller;
use think\Db;
use think\Session;
error_reporting(0);
/**
 * @date:2018/03
 * @author : zhq
 * @desc : 父类主控制器
 */
class Base extends Controller
{
    /**
     * @desc : 构造方法
     * @author : zhq
     * @date : 2018/03
     */
    public function _initialize()
    {
        //检测用户的是否登录了
        $uid = Session::get('uid');
        $token = Session::get('token');
        $userinfo = Db::name('adv_manager')->field('id,mobile')->where('id',$uid)->find();
        $this->mobile = $userinfo['mobile'];
        if(!$uid){
            $this->redirect('/index/adv/login/index?refresh=refresh');
        }
        //判断用户的token
        $data = Db::table('adv_manager')->field('token')->where('id',$uid)->find();
        if($data['token']!=$token){
            $this->redirect('/index/adv/login/index?token=tokenerr');
        }
        $this->devurl = 'http://120.78.95.224:8080';

    }
    /**
     * @desc : 无限分类获取菜单栏
     * @author : zhq
     * @date : 2018/03
     */
    public function getMmenu($data, $pId,$countarr)
    {
        $tree = array();
        foreach($data as $k => $v)
        {
            if($v['p_id'] == $pId)
            {
                //父亲找到儿子
                $v['p_id'] = $this->getMmenu($data, $v['id'],$countarr);
                $v['children'] = $this->getMmenu($data, $v['id'],$countarr);
                switch ($v['id']){
                    case 5:
                        $v['count'] = $countarr['usercount'];
                        break;
                    case 7:
                        $v['count'] = $countarr['imggroupcount'];
                        break;
                    case 8:
                        $v['count'] = $countarr['upadvcount'];
                        break;
                    case 9:
                        $v['count'] = $countarr['popadvcount'];
                        break;
                    case 11:
                        $v['count'] = $countarr['customercount'];
                        break;
                    case 13:
                        $v['count'] = $countarr['optercount'];
                        break;
                }

                $tree[] = $v;
            }
        }
        return $tree;
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
     * @desc : 上传文件到本地服务器
     * @author : zhq
     * @date : 2018/03
     */
    public function upload(){
        // 获取表单上传文件
        $file = request()->file('file');
        // 移动到框架应用根目录/public/uploads/ 目录下
        if($file){
            $info = $file->move('/tmp/');
            if($info){
                return '/tmp/'.$info->getSaveName();
            }else{
                return false;
            }
        }
    }
    /**
     * @desc : 模拟get进行url请求
     * @author : zhq
     * @date : 2018/03
     */
    public function get_curl($url){
        $curlobj = curl_init();
        //设置访问的url
        curl_setopt($curlobj, CURLOPT_URL, $url );
        //执行后不直接打印出
        curl_setopt($curlobj, CURLOPT_RETURNTRANSFER, true);
        //设置https 支持
        date_default_timezone_get('PRC');   //使用cookies时，必须先设置时区
        curl_setopt($curlobj, CURLOPT_SSL_VERIFYPEER, 0);  //终止从服务端验证
        $output = curl_exec($curlobj);  //执行获取内容
        curl_close($curlobj);          //关闭curl
        return $output;
    }
    /**
     * @desc : 模拟post(raw请求)进行url请求
     * @author : zhq
     * @date : 2018/03
     */
    function post_curl($url = '', $data_string = '') {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'X-AjaxPro-Method:ShowList',
                'Content-Type: application/json; charset=utf-8',
                'Content-Length: ' . strlen($data_string))
        );
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }
    /**
     * @desc : 模拟post进行url请求
     * @params : string $url
     * @params string $param
     */
    public function request_post($url = '', $param = '') {
        if (empty($url) || empty($param)) {
            return false;
        }

        $postUrl = $url;
        $curlPost = $param;
        $ch = curl_init();//初始化curl
        curl_setopt($ch, CURLOPT_URL,$postUrl);//抓取指定网页
        curl_setopt($ch, CURLOPT_HEADER, 0);//设置header
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_POST, 1);//post提交方式
        curl_setopt($ch, CURLOPT_POSTFIELDS, $curlPost);
        $data = curl_exec($ch);//运行curl
        curl_close($ch);
        return $data;
    }
    /**
     * 根据经纬度 获取地址
     * @param $address23.2322,12.15544
     * @return mixed
     */
    public function getaddress($address){
        $url="http://restapi.amap.com/v3/geocode/regeo?output=json&location={$address}&key=6fdb5c1bdf3d4b280b86419f87f2ba70&radius=1000&extensions=all";
        if($result=file_get_contents($url))
        {
            $result = json_decode($result,true);
            if(!empty($result['status'])&&$result['status']==1){

                return $result['regeocode']['formatted_address'];

            }else{
                return false;
            }
        }
    }

}
