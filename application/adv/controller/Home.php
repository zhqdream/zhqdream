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
            $role_name = '系统管理员';
        }else{
            if(!$menu_id){
                $this->error('用户权限错误，请重新登录','/index/adv/login/index');
            }
            $where = "id in({$menu_id})";
            //获取当前的用户厂商
            $customer_id = Session::get('customer_id');
            $role_name = Db::table('adv_customer_group')->where("id = {$customer_id}")->value('customer_name').'厂商';
        }

        $data = db()->query("select id,p_id,title,icon,href from adv_menu WHERE $where order by m_level desc ");
        $countarr = array('usercount'=>$usercount,'upadvcount'=>$upadvcount,'popadvcount'=>$popadvcount,'imggroupcount'=>$imggroupcount,'optercount'=>$optercount,'customercount'=>$customercount);
        $menu = $this->getMmenu($data,0,$countarr);
        $this->assign('menu',$menu);
        $this->assign('customercount',$customercount);
        $this->assign('role_id',$role_id);
        $this->assign('role_name',$role_name);
        $this->assign('web_title',Session::get('web_title')?Session::get('web_title'):'锐捷车联网系统');
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
            if($role_id==1){
                $customer_id = input('customer_id');
            }else{
                $customer_id = Session::get('customer_id');
            }
            $quchannel = Db::table('adv_customer_list')->field('customer')->where("customer_id = $customer_id")->select();
            $quchannelstr = '[';
            foreach ($quchannel as $k=>$v){
                $quchannelstr.='"'.$v['customer'].'"'.',';
            }
            $quchannelstr = rtrim($quchannelstr,',');
            $quchannelstr.=']';
            $querychannel = rtrim($querychannel,',');
            $channelstr = rtrim($channelstr,',');
        }
        $this->assign('quchannel',$quchannelstr ? $quchannelstr : 0);
        if($role_id==1){
            if($querychannel){
                $where = "g.city!='' AND d.channel in({$querychannel})";
                $acwhere = "d.channel in({$querychannel})";
                $decwhere = "channel in({$querychannel})";
            }else{
                $where = "g.city!=''";
                $acwhere = 1;
                $decwhere = 1;
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
                $decwhere = "channel in({$querychannel})";
            }else{
                $where = "g.city!='' AND d.channel in({$channelid})";
                $acwhere = "d.channel in({$channelid})";
                $decwhere = "channel in({$channelid})";
            }

        }
        //获取设备激活/生产
        $activeinfo = Db::table('device_last_gps')->query("SELECT COUNT(o.device_id) as num FROM device_online as o INNER JOIN device_info as d ON o.device_id = d.device_id WHERE $acwhere GROUP BY o.activation");
        //获取设备分布数据
        $devicegps = Db::table('device_last_gps')->query("SELECT g.`device_id`,g.`lng`,g.`lat`,g.`address`,g.`city`,d.channel,count(g.device_id) as num FROM device_last_gps as g LEFT JOIN device_info as d ON g.device_id = d.device_id WHERE $where GROUP BY g.`city` ");
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
        //获取设备总数
        $devicecount = Db::table('device_last_gps')->query("SELECT COUNT(*) as devicecount FROM device_info WHERE $decwhere");
        $this->assign('devicecount',$devicecount[0]['devicecount']);
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
     * @desc : 获取设备在线数据
     * @author : zhq
     * @date : 2018/04
     */
    public function onlineDevice(){
        $devurl = $this->devurl;
        $role_id = Session::get('role_id');
        $querychanel = input('querychanel');
        if($role_id==1){
            $role_name = '全部';
        }else{
            $customer_id = Session::get('customer_id');
            $role_name = Db::table('adv_customer_group')->where("id = {$customer_id}")->value('customer_name').'厂商';
        }
        if(!$querychanel){
            $querytitle = "当前显示是 {$role_name}厂商";
        }else{
            $querytitle = "当前选择的厂商型号为: {$querychanel}";
        }
        $this->assign('querychanel',$querychanel ? '"'.$querychanel.'"' : 0);
        $this->assign('querychanelacc',$querychanel ? $querychanel : 0);
        //最近一周设备上线总数
        $beforeweek = date('Ymd', strtotime('-7 days'));
        $currentweek = date('Ymd');
        $weektotal = $this->get_curl("{$devurl}/v1/counter/period_active_count?s={$beforeweek}&e={$currentweek}");
        if($role_id!=1){
            $customer_id = Session::get('customer_id');
            //获取当前用户所有的厂商型号
            $channel = Db::table('adv_customer_list')->field('customer')->where("customer_id = $customer_id")->select();
            //如果筛选了厂商型号，按厂商型号查询
            if($querychanel) {
                $querychanel = explode(',', $querychanel);
                $querychanelstr = '';
                $channelidstr = '';
                $cusdevcount = 0;
                foreach ($querychanel as $k => $v) {
                    $cusdevcount+=$this->get_curl("{$devurl}/v1/channel/{$v}/count");
                    $querychanelstr .= '"' . $v . '"' . ',';
                    $channelidstr.=$v.',';
                }
                $channelid = rtrim($querychanelstr, ',');
                $channelidstr = rtrim($channelidstr,',');
            }else{
                $channelid = '';
                $channelidstr = '';
                $cusdevcount = 0;
                foreach($channel as $k=>$v){
                    $cusdevcount+=$this->get_curl("{$devurl}/v1/channel/{$v['customer']}/count");
                    $channelid.= '"'.$v['customer'].'"'.',';
                    $channelidstr.=$v['customer'].',';
                }
                $channelid = rtrim($channelid,',');
                $channelidstr = rtrim($channelidstr,',');
            }
            $where = "d.channel in($channelid) and date_sub(curdate(), INTERVAL 7 DAY) <= date(s.online_time) AND s.online_time<=curdate()";
            $weekonlincount = db()->query("SELECT COUNT(DISTINCT s.device_id) as devcount  FROM device_session_history as s INNER JOIN device_info d ON s.device_id = d.device_id WHERE d.channel in ({$channelid}) AND date_sub(curdate(), INTERVAL 7 DAY) <= date(s.online_time) AND s.online_time<=curdate()");
            $currentonlincount = db()->query("SELECT COUNT(DISTINCT s.device_id) as devcount  FROM device_session_history as s INNER JOIN device_info d ON s.device_id = d.device_id WHERE d.channel in ({$channelid}) AND date(s.online_time)=curdate()");
            $this->assign('channel',$channel);
        }else{
            //获取所有厂商
            $customerlist = Db::table('adv_customer_group')->field('id,customer_name')->select();
            $this->assign('customerlist',$customerlist);
            //如果有查询条件，按条件进行查询
            if($querychanel){
                $querychanel = explode(',',$querychanel);
                $querychanelstr = '';
                foreach ($querychanel as $k=>$v){
                    $querychanelstr.='"'.$v.'"'.',';
                }
                $querychanelstr = rtrim($querychanelstr,',');
                $where = "date_sub(curdate(), INTERVAL 7 DAY) <= date(s.online_time) AND date(s.online_time)<=curdate() AND d.channel in ({$querychanelstr})";
                $currentonlincount = db()->query("SELECT COUNT(DISTINCT s.device_id) as devcount  FROM device_session_history as s INNER JOIN device_info d ON s.device_id = d.device_id WHERE d.channel in ({$querychanelstr}) AND date(s.online_time)=curdate()");
                $weektotalarr = db()->query("SELECT COUNT(DISTINCT s.device_id) as devcount  FROM device_session_history as s INNER JOIN device_info d ON s.device_id = d.device_id WHERE d.channel in ({$querychanelstr}) AND date_sub(curdate(), INTERVAL 7 DAY) <= date(s.online_time) AND s.online_time<=curdate()");
                $weektotal = $weektotalarr[0]['devcount'];
            }else{
                $where = "date_sub(curdate(), INTERVAL 7 DAY) <= date(s.online_time) AND date(s.online_time)<=curdate()";
            }
        }
        if($role_id==1){
            //获取今日每个小时在线设备情况
            $curh = date('H');
            $curdata = array();
            for($i=0;$i<=$curh;$i++){
                if($i<10){
                    $i = '0'.$i;
                }
                $curdata[$i] = 0;
            }
            $currentdata = db()->query('SELECT DATE_FORMAT( `datetime`, "%Y-%m-%d %H" ) as curr_date , max(`online`) as dev_online FROM devices_statistic where date(`datetime`) = curdate() GROUP BY DATE_FORMAT( `datetime`, "%Y-%m-%d %H" ) ');
//            $date = ['00'=>0,'01'=>0,'02'=>0,'03'=>0,'04'=>0,'05'=>0,'06'=>0,'07'=>0,'08'=>0,'09'=>0,'10'=>0,'11'=>0,'12'=>0,'13'=>0,'14'=>0,'15'=>0,'16'=>0,'17'=>0,'18'=>0,'19'=>0,'29'=>0,'21'=>0,'22'=>0,'23'=>0];
            $date = $curdata;
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
        }

        $weekdata ='[';
        $weeddate = '[';
        //获取近一周的在线设备情况
        $weekdevdata = db()->query("SELECT COUNT(DISTINCT s.device_id) as devcount,DATE_FORMAT(s.online_time, '%Y%m%d' ) as devtime  FROM device_session_history as s INNER JOIN device_info d ON s.device_id = d.device_id WHERE $where GROUP BY DATE_FORMAT(s.online_time, '%Y-%m-%d' )");
        foreach($weekdevdata as $v){
            $weekdata.= $v['devcount'].',';
            $weeddate.= $v['devtime'].',';
        }
        $weeddate = rtrim($weeddate,',');
        $weekdata = rtrim($weekdata,',');
        //最近一周的日期
        $weeddate.=']';
        //最近一周的上线数
        $weekdata.=']';
        //总车机属
        $devcount = $this->get_curl("{$devurl}/v1/counter/count");
        //如果有筛选条件
        if($querychanel){
            $devcount = 0;
            foreach($querychanel as $k=>$v){
                $devcount+=$this->get_curl("{$devurl}/v1/channel/{$v}/count");
            }
        }
        $this->assign('weekdata',$weekdata);
        $this->assign('weeddate',$weeddate);
        $this->assign('online_num',$online_num);
        $this->assign('weektotal',$role_id == 1 ? $weektotal : $weekonlincount[0]['devcount']);
        $this->assign('devcount',$role_id == 1 ? $devcount : $cusdevcount);
        $this->assign('role_id',$role_id);
        $this->assign('channelid',$channelidstr ? '"'.$channelidstr.'"' : 0);
        $this->assign('devurl','"'.$devurl.'"');
        $this->assign('currentonlincount',$currentonlincount[0]['devcount'] ? $currentonlincount[0]['devcount'] : 0);
        $this->assign('querytitle',$querytitle);
        return view('onlineDevice',['name'=>'Home']);
    }
    /**
     * @desc : 获取今日设备累计在线时长
     * @author : zhq
     * @date : 2018/05
     */
    public function gettodayonlinedate(){
        $querychanel = input('querychanel');
        $role_id = Session::get('role_id');
        if($role_id==1){
            if($querychanel){
                $querychanel = explode(',',$querychanel);
                $querychanelstr = '';
                foreach ($querychanel as $k=>$v){
                    $querychanelstr.='"'.$v.'"'.',';
                }
                $querychanelstr = rtrim($querychanelstr,',');
                $duwhere = "date(s.online_time)=curdate() AND d.channel in ({$querychanelstr})";
            }else{
                $duwhere = "date(s.online_time)=curdate()";
            }
        }else{
            //如果有筛选了厂商型号
            if($querychanel){
                $querychanel = explode(',',$querychanel);
                $querychanelstr = '';
                foreach ($querychanel as $k=>$v){
                    $querychanelstr.='"'.$v.'"'.',';
                }
                $channelid = rtrim($querychanelstr,',');
            }else{
                //获取当前用户所有的厂商型号
                $customer_id = Session::get('customer_id');
                $channel = Db::table('adv_customer_list')->field('customer')->where("customer_id = $customer_id")->select();
                $channelid = '';
                foreach($channel as $k=>$v){
                    $channelid.= '"'.$v['customer'].'"'.',';
                }
                $channelid = rtrim($channelid,',');
            }
            $duwhere = "date(s.online_time)=curdate() and d.channel in ({$channelid})";
        }
        //获取当前车型累计在线时长
        $currentdevduration = db()->query("SELECT SUM(s.duration) as `value`,d.channel as `name`,d.channel,(SUM(s.duration)/60)/COUNT(DISTINCT s.device_id) as devavg FROM device_session_history as s INNER JOIN device_info d ON s.device_id = d.device_id WHERE $duwhere GROUP BY d.channel");
        $channeldata = '[';
        $devavg = '[';
        foreach ($currentdevduration as $k=>$v){
            $channeldata.= "'".$v['channel']."'".',';
            $currentdevduration[$k]['value'] = ceil($v['value']/3600);
            $devavg.=ceil($v['devavg']).',';
        }
        $channeldata = rtrim($channeldata,',');
        $devavg = rtrim($devavg,',');
        $devavg.=']';
        $channeldata.=']';
        $data['current'] = $currentdevduration;
        $data['channeldata'] = $channeldata;
        $data['devavg'] = $devavg;
        return json_encode_conf(200,$data);
    }
    /**
     * @desc : 获取近一周设备累计在线时长
     * @author : zhq
     * @date : 2018/05
     */
    public function getweekonlinedate(){
        $querychanel = input('querychanel');
        $role_id = Session::get('role_id');
        if($role_id==1){
            if($querychanel){
                $querychanel = explode(',',$querychanel);
                $querychanelstr = '';
                foreach ($querychanel as $k=>$v){
                    $querychanelstr.='"'.$v.'"'.',';
                }
                $querychanelstr = rtrim($querychanelstr,',');
                $duwhere = "date_sub(curdate(), INTERVAL 7 DAY) <= date(s.online_time)  AND d.channel in ({$querychanelstr})";
            }else{
                $duwhere = "date_sub(curdate(), INTERVAL 7 DAY) <= date(s.online_time)";
            }

        }else{
            //如果有筛选了厂商型号
            if($querychanel){
                $querychanel = explode(',',$querychanel);
                $querychanelstr = '';
                foreach ($querychanel as $k=>$v){
                    $querychanelstr.='"'.$v.'"'.',';
                }
                $channelid = rtrim($querychanelstr,',');
            }else{
                //获取当前用户所有的厂商型号
                $customer_id = Session::get('customer_id');
                $channel = Db::table('adv_customer_list')->field('customer')->where("customer_id = $customer_id")->select();
                $channelid = '';
                foreach($channel as $k=>$v){
                    $channelid.= '"'.$v['customer'].'"'.',';
                }
                $channelid = rtrim($channelid,',');
            }
            $duwhere = "date_sub(curdate(), INTERVAL 7 DAY) <= date(s.online_time)  and d.channel in ({$channelid})";
        }
        //获取当前车型累计在线时长
        $currentdevduration = db()->query("SELECT SUM(s.duration) as `value`,d.channel as `name`,d.channel,(SUM(s.duration)/60)/COUNT(DISTINCT s.device_id) as devavg FROM device_session_history as s INNER JOIN device_info d ON s.device_id = d.device_id WHERE $duwhere GROUP BY d.channel");
        $channeldata = '[';
        $devavg = '[';
        foreach ($currentdevduration as $k=>$v){
            $channeldata.= "'".$v['channel']."'".',';
            $currentdevduration[$k]['value'] = ceil($v['value']/3600);
            $devavg.=ceil($v['devavg']).',';
        }
        $channeldata = rtrim($channeldata,',');
        $devavg = rtrim($devavg,',');
        $devavg.=']';
        $channeldata.=']';
        $data['current'] = $currentdevduration;
        $data['channeldata'] = $channeldata;
        $data['devavg'] = $devavg;
        return json_encode_conf(200,$data);
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
        if(!$province){
            return json_encode_conf(1000,'参数不能为空，请检查！');
        }
        //全国设备
        if($province==10000){
            $provincewhere = 1;
            $group = "g.`province`";
            $field = "g.`province` as city";
        }else{
            $provincewhere = "g.province like '%{$province}%'";
            $group = "g.`city`";
            $field = "g.`city`";
        }
        $role_id = Session::get('role_id');
        if($querychannel){
            $querychannel = rtrim($querychannel,',');
        }
        if($role_id==1){
            if($querychannel){
                $where = "d.channel in({$querychannel}) AND {$provincewhere}";
            }else{
                $where = "{$provincewhere}";
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
                if($provincewhere==1){
                    $where = "d.channel in({$querychannel})";
                }else{
                    $where = "d.channel in({$querychannel}) ADN {$provincewhere}";
                }
            }else{
                if($provincewhere==1){
                    $where = "d.channel in({$channelid})";
                }else{
                    $where = "d.channel in({$channelid}) AND {$provincewhere}";
                }
            }
        }
        $devicegps = Db::table('device_last_gps')->query("SELECT g.`device_id`,g.`lng`,g.`lat`,g.`address`,{$field},d.channel,count(d.device_id) as num FROM device_info as d  left JOIN device_last_gps as g ON g.device_id = d.device_id WHERE $where GROUP BY $group ");
        $devcount = 0;
        if($province==10000){
            $cityinfo[] = '';
            $devnum[]= '';
        }
        foreach($devicegps as $k=>$v){
            if($v['city']===null){
                $cityinfo[] = '其他';
            }elseif($v['city']===''){
                $cityinfo[] = '未知区域';
            }elseif($v['city']==='���������'){
                $cityinfo[] = '区域未知';
            }else{
                $cityinfo[] = $v['city'];
            }
            $devnum[] = $v['num'];
            $devcount+=$v['num'];
        }
        if($province==10000){
            $cityinfo[] = '';
            $devnum[]= '';
        }
        $deviceinfo = array('cityinfo'=>$cityinfo,'devnum'=>$devnum,'devcount'=>$devcount);
        return json_encode_conf(200,$deviceinfo);
    }
    /**
     * @desc : 设备实时位置
     * @author : zhq
     * @date : 2018/05
     */
    public function carRealLocation(){
        return view('carRealLocation',['name'=>'Home']);
    }
}
