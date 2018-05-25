<?php
namespace app\adv\controller;
use app\adv\controller\Base;
use think\Controller;
use think\Db;
use think\Session;
use think\captcha\Captcha;
use think\Request;
header("Content-Type:text/html;charset=utf-8");
error_reporting(0);
/**
 * @date:2018/04
 * @author : zhq
 * @desc : 广告系统首页
 */
class Home extends Base
{
    /**
     * @desc : 核心主页面(iframe)
     * @author : zhq
     * @date : 2018/04
     */
    public function main(){
        //查看有共有多少用户
        $usercount = Db::table('adv_manager')->count();
        $this->assign('usercount',$usercount);
        //查看有多少开机广告
        $upadvcount = Db::table('adv_advlist')->where('adv_type = 1')->count();
        $this->assign('upadvcount',$upadvcount);
        //查看有多少弹窗广告
        $popadvcount = Db::table('adv_advlist')->where('adv_type = 2')->count();
        $this->assign('popadvcount',$popadvcount);
        //查看有多少广告图片组
        $imggroupcount = Db::table('adv_img_group')->count();
        $this->assign('imggroupcount',$imggroupcount);
        //查看有多少条系统日志
        $optercount = Db::table('adv_opter_log')->count();
        $this->assign('optercount',$optercount);
        //查看有多少厂商
        $customercount = Db::table('adv_customer_group')->count();
        //获取菜单
        //查看用户角色，获取用户的菜单
        $role_id = Session::get('role_id');
        $menu_id = Session::get('menu_id');
        if($role_id==1){
            $where = 1;
        }else{
            if(!$menu_id){
                $this->error('用户权限错误，请重新登录','/index/adv/login/index');
            }
            $where = "id in({$menu_id})";
        }
        $data = db()->query("select id,p_id,title,icon,href from adv_menu WHERE $where order by m_level desc ");
        $countarr = array('usercount'=>$usercount,'upadvcount'=>$upadvcount,'popadvcount'=>$popadvcount,'imggroupcount'=>$imggroupcount,'optercount'=>$optercount,'customercount'=>$customercount);
        $menu = $this->getMmenu($data,0,$countarr);
        $this->assign('menu',$menu);
        $this->assign('customercount',$customercount);
        $this->assign('role_id',$role_id);
        return view('main',['name'=>'Home']);
    }
    /**
     * @desc : 广告首页系统（设备分布图）
     * @author : zhq
     * @date : 2018/04
     */
    public function index(){
        //判断用户角色进行分配设备分布图
        $role_id = Session::get('role_id');
        $querychannel = input('querychannel');
        $channelstr = input('channelstr');
        $customer_text = input('customer_text');
        if($querychannel){
            $querychannel = rtrim($querychannel,',');
            $channelstr = rtrim($channelstr,',');
        }
        if($role_id==1){
            if($querychannel){
                $where = "g.city!='' AND d.channel in({$querychannel})";
                $acwhere = "d.channel in({$querychannel})";
            }else{
                $where = "g.city!=''";
                $acwhere = 1;
            }
            //获取所有厂商
            $customerlist = Db::table('adv_customer_group')->field('id,customer_name')->select();
            $this->assign('customerlist',$customerlist);
        }else{
            $customer_id = Session::get('customer_id');
            //获取当前用户所有的厂商型号
            $channel = Db::table('adv_customer_list')->field('customer')->where("customer_id = $customer_id")->select();
            $this->assign('channel',$channel);
            $channelid = '';
            foreach($channel as $k=>$v){
                $channelid.= '"'.$v['customer'].'"'.',';
            }
            $channelid = rtrim($channelid,',');
            if($querychannel){
                $where = "g.city!='' AND d.channel in({$querychannel})";
                $acwhere = "d.channel in({$querychannel})";
            }else{
                $where = "g.city!='' AND d.channel in({$channelid})";
                $acwhere = "d.channel in({$channelid})";
            }

        }
        //获取设备激活/生产
        $activeinfo = Db::table('device_last_gps')->query("SELECT COUNT(o.device_id) as num FROM device_online as o INNER JOIN device_info as d ON o.device_id = d.device_id WHERE $acwhere GROUP BY o.activation");
        //获取设备分布数据
        $devicegps = Db::table('device_last_gps')->query("SELECT g.`device_id`,g.`lng`,g.`lat`,g.`address`,g.`city`,d.channel,count(g.device_id) as num FROM device_last_gps as g INNER JOIN device_info as d ON g.device_id = d.device_id WHERE $where GROUP BY g.`city` ");
        $geoCoordMap = '{';
        foreach($devicegps as $k=>$v){
            $geoCoordMap.='"'.$v['city'].'"'.':'.'['.$v['lng'].','.$v['lat'].']'.',';
            $data[$k]['name'] = $v['city'];
            $data[$k]['value'] = $v['num'] ? $v['num'] : 0;

        }
        $geoCoordMap = rtrim($geoCoordMap,',');
        $geoCoordMap.='}';
        $this->assign('geoCoordMap',$geoCoordMap);
        $this->assign('convertData',json_encode($data,JSON_UNESCAPED_UNICODE));
        $production = $activeinfo[0]['num'];
        $active = $activeinfo[1]['num'];
        $this->assign('production',$production);
        $this->assign('active',$active);
        $this->assign('customer_text',$customer_text);
        $this->assign('querychannel',$channelstr ? '"'.$channelstr.'"' : 0);
        $commonchannel = explode(',',$channelstr);
        $this->assign('commonchannel',$commonchannel);
        $this->assign('role_id',$role_id);
        //获取所有设备的位置
        return view('index',['name'=>'Home']);
    }
    /**
     * @desc : 获取今日设备在线数据
     * @author : zhq
     * @date : 2018/04
     */
    public function onlineDevice(){
        //获取今日每个小时在线设备情况
        $currentdata = Db::table('device_last_gps')->query('SELECT DATE_FORMAT( `datetime`, "%Y-%m-%d %H" ) as curr_date , max(`online`) as dev_online FROM devices_statistic where date(`datetime`) = curdate() GROUP BY DATE_FORMAT( `datetime`, "%Y-%m-%d %H" ) ');
        $date = ['00'=>0,'01'=>0,'02'=>0,'03'=>0,'04'=>0,'05'=>0,'06'=>0,'07'=>0,'08'=>0,'09'=>0,'10'=>0,'11'=>0,'12'=>0,'13'=>0,'14'=>0,'15'=>0,'16'=>0,'17'=>0,'18'=>0,'19'=>0,'29'=>0,'21'=>0,'22'=>0,'23'=>0];
        foreach($currentdata as $v){
            $cur_date = explode(' ',$v['curr_date']);
            $dateindex = $cur_date[1];
            $date[$dateindex] = $v['dev_online'];
        }
        $online_num = '[';
        foreach($date as $v){
            $online_num.=$v.',';
        }
        $online_num = rtrim($online_num,',');
        $online_num.=']';
        $weekdata ='[';
        $weeddate = '[';
        //获取近一周的在线设备情况
        for ($i=0;$i<=6;$i++){
            $week = date('Ymd',strtotime( '+'. $i+1-7 .' days',time()));
            $weeddate.= $week.',';
            $weekonline = $this->get_curl("http://120.78.95.224:8080/v1/counter/period_active_count?s={$week}&e={$week}");
            $weekdata.=$weekonline.',';
        }
        $weeddate = rtrim($weeddate,',');
        $weekdata = rtrim($weekdata,',');
        //最近一周的日期
        $weeddate.=']';
        //最近一周的上线数
        $weekdata.=']';
        $beforeweek = date('Ymd', strtotime('-7 days'));
        $currentweek = date('Ymd');
        $weektotal = $this->get_curl("http://120.78.95.224:8080/v1/counter/period_active_count?s={$beforeweek}&e={$currentweek}");

        $this->assign('weekdata',$weekdata);
        $this->assign('weeddate',$weeddate);
        $this->assign('online_num',$online_num);
        $this->assign('weektotal',$weektotal);
        return view('onlineDevice',['name'=>'Home']);
    }
    /**
     * @desc : 通过厂商获取所有厂商型号
     * @author : zhq
     * @date : 2018/05
     */
    public function getmanulist(){
        $customer_id = input('customer_id');
        if(!$customer_id){
            return json_encode_conf(1000,'参数不能为空，请检查！');
        }
        $manulist = Db::table('adv_customer_list')->field('id,customer,customer_name')->where("customer_id = {$customer_id}")->select();
        if($manulist){
            return json_encode_conf(200,$manulist);
        }else{
            return json_encode_conf(2000,'未找到数据');
        }
    }
    /**
     * @desc : 获取当前城市的有多少设备
     * @author : zhq
     * @date : 2018/05
     */
    public function getcitydevice(){
        $province = input('province');
        $querychannel = input('querychannel');
        $municipality = array('北京','上海','天津','重庆');
        var_dump($province);exit;

//        $province = iconv('utf-8','gb2312',$province);

//        if(in_array($province,$municipality)){
//            $province = $province.'市';
//        }else{
//            $province = $province.'省';
//        }

        if(!$province){
            return json_encode_conf(1000,'参数不能为空，请检查！');
        }
        $role_id = Session::get('role_id');
        if($querychannel){
            $querychannel = rtrim($querychannel,',');
        }
        if($role_id==1){
            if($querychannel){
                $where = "g.city!='' AND d.channel in({$querychannel}) AND g.province = '{$province}'";
            }else{
                $where = "g.city!='' AND g.province = '{$province}'";
            }
        }else{
            $customer_id = Session::get('customer_id');
            //获取当前用户所有的厂商型号
            $channel = Db::table('adv_customer_list')->field('customer')->where("customer_id = $customer_id")->select();
            $channelid = '';
            foreach($channel as $k=>$v){
                $channelid.= '"'.$v['customer'].'"'.',';
            }
            $channelid = rtrim($channelid,',');
            if($querychannel){
                $where = "g.city!='' AND d.channel in({$querychannel}) AND g.province = '{$province}'";
            }else{
                $where = "g.city!='' AND d.channel in({$channelid}) AND g.province = '{$province}'";
            }
        }
        echo "SELECT g.`device_id`,g.`lng`,g.`lat`,g.`address`,g.`city`,d.channel,count(g.device_id) as num FROM device_last_gps as g INNER JOIN device_info as d ON g.device_id = d.device_id WHERE $where GROUP BY g.`city` ";exit;
        $devicegps = Db::table('device_last_gps')->query("SELECT g.`device_id`,g.`lng`,g.`lat`,g.`address`,g.`city`,d.channel,count(g.device_id) as num FROM device_last_gps as g INNER JOIN device_info as d ON g.device_id = d.device_id WHERE $where GROUP BY g.`city` ");
        return json_encode_conf(200,$devicegps);
    }

    /**
     * @desc : 测试git
     * @date : 2018/05
     */
    public function test(){
        echo 333555;
    }
}
