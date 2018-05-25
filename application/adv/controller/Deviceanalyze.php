<?php
namespace app\adv\controller;
use app\adv\controller\Base;
use think\Controller;
use think\Db;
use think\Session;
use think\captcha\Captcha;
use think\Request;
use think\Loader;
header("Content-Type:text/html;charset=utf-8");
error_reporting(0);
/**
 * @date:2018/05
 * @author : zhq
 * @desc : 设备分析图
 */
class Deviceanalyze extends Base
{
   /**
    * @desc : 设备生产/激活分析图
    * @author : zhq
    * @date : 2018/05
    */
   public function deviceProductionactive(){
       $role_id = Session::get('role_id');
       $querychanel = input('querychanel');
       //生产=0 激活=1
       $type = input('type');
       if($type==1){
           $typetitle = '激活';
           $warmtitle = '已装车';
           $datefile = 'o.activation_datetime';
       }else{
           $typetitle = '生产';
           $warmtitle = '未激活';
           $datefile = 'o.first_online_datetime';
       }
       if($role_id==1){
           $role_name = '全部厂商';
       }else{
           $customer_id = Session::get('customer_id');
           $role_name = Db::table('adv_customer_group')->where("id = {$customer_id}")->value('customer_name').'厂商';
       }
       if(!$querychanel){
           $querytitle = "当前显示是 {$role_name}";
       }else{
           $querytitle = "当前选择的厂商型号为: {$querychanel}";
       }
       $this->assign('querychanel',$querychanel ? $querychanel : 0);
       if($role_id==1){
           //获取所有厂商
           $customerlist = Db::table('adv_customer_group')->field('id,customer_name')->select();
           $this->assign('customerlist',$customerlist);
           //如果有筛选条件
           if($querychanel){
               $querychanel = explode(',', $querychanel);
               $querychanelstr = '';
               foreach ($querychanel as $k => $v) {
                   $querychanelstr .= '"' . $v . '"' . ',';
               }
               $channelid = rtrim($querychanelstr, ',');
               $pwhere = "o.activation = {$type} AND i.channel in({$channelid})";
               $where = " AND i.channel in({$channelid})";
           }else{
               $pwhere = "o.activation = {$type}";
               $where = '';
           }

       }else{
           $customer_id = Session::get('customer_id');
           //获取当前用户所有的厂商型号
           $channel = Db::table('adv_customer_list')->field('customer')->where("customer_id = $customer_id")->select();
           if($querychanel){
               $querychanel = explode(',', $querychanel);
               $querychanelstr = '';
               foreach ($querychanel as $k => $v) {
                   $querychanelstr .= '"' . $v . '"' . ',';
               }
               $channelid = rtrim($querychanelstr, ',');
           }else{
               $channelid = '';
               $channelidstr = '';
               foreach($channel as $k=>$v){
                   $channelid.= '"'.$v['customer'].'"'.',';
                   $channelidstr.=$v['customer'].',';
               }
           }
           //获取当前用户所有的厂商型号
           $this->assign('channel',$channel);
           $channelid = rtrim($channelid,',');
           $where = " AND i.channel in({$channelid})";
           $pwhere = "o.activation = {$type} AND i.channel in({$channelid})";
       }
       //今天生产设备数量
       $currdevcount = Db::table('device_online')->alias('o')->join('device_info i','o.device_id = i.device_id')->where("o.activation = {$type} AND date({$datefile}) = CURDATE() {$where}")->count();
       //近一个月生产数量
       $monthdevcount = Db::table('device_online')->alias('o')->join('device_info i','o.device_id = i.device_id')->where("o.activation = {$type} AND date_sub(curdate(), INTERVAL 1 MONTH) <= date({$datefile}) {$where}")->count();
       //近1年生产数量
       $yeardevcount = Db::table('device_online')->alias('o')->join('device_info i','o.device_id = i.device_id')->where("o.activation = {$type} AND date_sub(curdate(), INTERVAL 12 MONTH) <= date({$datefile}) {$where}")->count();
       //获取最近生产设备信息
       $prodeviceinfo = db()->query("SELECT o.device_id,{$datefile} as first_online_datetime,l.address,l.lng,l.lat,i.channel FROM device_online as o INNER JOIN device_last_gps as l ON o.device_id = l.device_id INNER JOIN device_info as i ON o.device_id = i.device_id WHERE $pwhere ORDER BY {$datefile}  DESC LIMIT 10");
       foreach($prodeviceinfo as $k=>$v){
           $devaddress = $this->getaddress($v['lng'].','.$v['lat']);
           $prodeviceinfo[$k]['address'] = $v['address'] ? $v['address'] : $devaddress;
       }
       $this->assign('prodeviceinfo',$prodeviceinfo);
       $this->assign('currdevcount',$currdevcount);
       $this->assign('monthdevcount',$monthdevcount);
       $this->assign('yeardevcount',$yeardevcount);
       $this->assign('role_id',$role_id);
       $this->assign('querytitle',$querytitle);
       $this->assign('typetitle',$typetitle);
       $this->assign('warmtitle',$warmtitle);
       $this->assign('type',$type);
       return view('deviceProductionactive',['name'=>'Deviceanalyze']);
   }
   /**
    * @desc : 获取设备生产动态图
    * @author : zhq
    * @date : 2018/05
    */
   public function getdevproline(){
       $type = input('type');
       $devtype = input('devtype');
       if(!$type){
           return false;
       }
       if($devtype==1){
           $datefile = 'd.activation_datetime';
       }else{
           $datefile = 'd.first_online_datetime';
       }
       $role_id = Session::get('role_id');
       $querychanel = input('querychanel');
       if($role_id==1){
           //如果有筛选条件
           if($querychanel){
               $querychanel = explode(',', $querychanel);
               $querychanelstr = '';
               foreach ($querychanel as $k => $v) {
                   $querychanelstr .= '"' . $v . '"' . ',';
               }
               $channelid = rtrim($querychanelstr, ',');
               $rwhere = " AND i.channel in ({$channelid})";
           }else{
               $rwhere = '';
           }
       }else{
           $customer_id = Session::get('customer_id');
           //获取当前用户所有的厂商型号
           $channel = Db::table('adv_customer_list')->field('customer')->where("customer_id = $customer_id")->select();
           if($querychanel){
               $querychanel = explode(',', $querychanel);
               $querychanelstr = '';
               foreach ($querychanel as $k => $v) {
                   $querychanelstr .= '"' . $v . '"' . ',';
               }
               $channelid = rtrim($querychanelstr, ',');
           }else{
               $channelid = '';
               foreach($channel as $k=>$v){
                   $channelid.= '"'.$v['customer'].'"'.',';
               }
               $channelid = rtrim($channelid,',');
           }
           $rwhere = " AND i.channel in ({$channelid})";
       }
       if($type==1){
           $where = "d.activation = {$devtype} AND date_sub(curdate(), INTERVAL 10 DAY) <= date({$datefile})";
           $field = "DATE_FORMAT({$datefile}, '%Y%m%d' ) as prodate,COUNT(d.device_id) as devicecount";
           $group = "DATE_FORMAT({$datefile}, '%Y-%m-%d' )";
       }elseif($type==2){
           $where = "d.activation = {$devtype} AND date_sub(curdate(), INTERVAL 1 MONTH) <= date({$datefile})";
           $field = "DATE_FORMAT({$datefile}, '%Y%m%d' ) as prodate,COUNT(d.device_id) as devicecount";
           $group = "DATE_FORMAT({$datefile}, '%Y-%m-%d' )";
       }elseif($type==3){
           $where = "d.activation = {$devtype} AND date_sub(curdate(), INTERVAL 12 MONTH) <= date({$datefile})";
           $field = "DATE_FORMAT({$datefile}, '%Y%m' ) as prodate,COUNT(d.device_id) as devicecount";
           $group = "DATE_FORMAT({$datefile}, '%Y-%m' )";
       }
       $prodeviceinfo = db()->query("SELECT $field FROM device_online as d INNER JOIN device_last_gps as l ON d.device_id = l.device_id INNER JOIN device_info as i ON i.device_id = l.device_id WHERE {$where}{$rwhere} GROUP BY $group");
       $prodate = '[';
       $prodline = '[';
       foreach ($prodeviceinfo as $k=>$v){
           $prodate.=$v['prodate'].',';
           $prodline.=$v['devicecount'].',';
       }
       $prodate = rtrim($prodate,',');
       $prodline = rtrim($prodline,',');
       $prodline.=']';
       $prodate.=']';
       echo json_encode(array('prodate'=>$prodate,'prodline'=>$prodline));exit;
   }
   /**
    * @desc : 获取设备生产数量饼状图数据
    * @author : zhq
    * @date : 2018/05
    */
   public function getdevpieinfo(){
       $role_id = Session::get('role_id');
       $querychanel = input('querychanel');
       $devtype = input('devtype');
       if($role_id==1){
           //如果有筛选条件
           if($querychanel){
               $querychanel = explode(',', $querychanel);
               $querychanelstr = '';
               foreach ($querychanel as $k => $v) {
                   $querychanelstr .= '"' . $v . '"' . ',';
               }
               $channelid = rtrim($querychanelstr, ',');
               $rwhere = " AND i.channel in ({$channelid})";
           }else{
               $rwhere = '';
           }
       }else{
           $customer_id = Session::get('customer_id');
           //获取当前用户所有的厂商型号
           $channel = Db::table('adv_customer_list')->field('customer')->where("customer_id = $customer_id")->select();
           if($querychanel){
               $querychanel = explode(',', $querychanel);
               $querychanelstr = '';
               foreach ($querychanel as $k => $v) {
                   $querychanelstr .= '"' . $v . '"' . ',';
               }
               $channelid = rtrim($querychanelstr, ',');
           }else{
               $channelid = '';
               $channelidstr = '';
               foreach($channel as $k=>$v){
                   $channelid.= '"'.$v['customer'].'"'.',';
                   $channelidstr.=$v['customer'].',';
               }
               $channelid = rtrim($channelid,',');
           }
           $rwhere = " AND i.channel in ({$channelid})";
       }
       $devinfo = db()->query("SELECT COUNT(o.device_id) as `value`,i.channel as `name` FROM device_online as o INNER JOIN device_info as i ON o.device_id = i.device_id WHERE o.activation ={$devtype} {$rwhere} GROUP BY i.channel");
       $pieline = '[';
       foreach($devinfo as $k=>$v){
           $pieline.="'".$v['name']."'".',';
       }
       $pieline = rtrim($pieline,',');
       $pieline.=']';
       echo json_encode(array('pieline'=>$pieline,'devinfo'=>$devinfo));exit;
   }
   /**
    * @desc : 获取设备列表，查看行车轨迹(页面)
    * @author : zhq
    * @date : 2018/05
    */
   public function car_track(){
       $dwhere = input('dwhere');
       $this->assign('dwhere',$dwhere);
       return view('car_track',['name'=>'Deviceanalyze']);
   }
   /**
    * @desc : 获取设备数据信息
    * @author : zhq
    * @date : 2018/05
    */
   public function getdevicelist(){
       $pageIndex = input('pageIndex');
       $pageSize = input('pageSize');
       $param = input('param');
       $searchtype = input('searchtype');
       $manu = input('manu');
       $model = input('model');
       $area = input('area');
       $carmodel = input('carmodel');
       $offset = ceil($pageIndex*$pageSize);
       $is_active = input('is_active');
       $dwhere = input('dwhere');
       if($searchtype){
           $where = 1;
       }
       //条件筛选的数据
       //如果厂商型号和厂商都都，只选厂商
       if($manu){
           $customerid = Db::table('adv_customer_group')->where("customer_name = '{$manu}'")->value('id');
           $customerinfo = Db::table('adv_customer_list')->field('customer')->where("customer_id = {$customerid}")->select();
           $cusinfo = '';
           foreach($customerinfo as $v){
              $cusinfo.= '"'.$v['customer'].'"'.',';
           }
           $cusinfo = rtrim($cusinfo,',');
           $where.= " AND i.channel in ({$cusinfo})";
       }elseif($model){
           $where.= " AND i.channel = '{$model}'";
       }

       if($area){
           $where.= " AND l.province = '{$area}'";
       }
       if($carmodel){
           $where.= " AND i.car_model = '{$carmodel}'";
       }
       if($is_active){
           if($is_active=='激活'){
               $where.=" AND o.activation = 1";
           }else{
               $where.=" AND o.activation = 0";
           }
       }
       //返回时保留上次的数据
       if(!$searchtype){
           if($dwhere){
               $where = base64_decode($dwhere);
           }
       }

       //搜索框搜索的数据
       if($param){
           $querylselect = input('querylselect') ? input('querylselect') : 1;
           if($querylselect==1){
               $where = "l.device_id like '%{$param}%'";
           }elseif($querylselect==2){
               $where = "i.iccid like '%{$param}%'";
           }else{
               $where = "i.imei like '%{$param}%'";
           }
       }
       if(!$where){
           $where = 1;
       }

//       echo "SELECT l.id,l.device_id,l.address,l.lng,l.lat,i.mcu_version FROM device_last_gps as l INNER JOIN device_info as i ON l.device_id = i.device_id INNER JOIN device_online as o ON l.device_id = o.device_id WHERE $where ORDER BY id DESC LIMIT $offset,$pageSize";exit;
       $dataarr = Db::table('device_last_gps')->query("SELECT COUNT(*) as devcount FROM device_last_gps as l INNER JOIN device_info as i ON l.device_id = i.device_id INNER JOIN device_online as o ON l.device_id = o.device_id WHERE $where");
       $dataLength = $dataarr[0]['devcount'];
       $devicelist = Db::table('device_last_gps')->query("SELECT l.id,l.device_id,l.address,l.lng,l.lat,i.mcu_version,i.car_brand,i.car_model FROM device_last_gps as l INNER JOIN device_info as i ON l.device_id = i.device_id INNER JOIN device_online as o ON l.device_id = o.device_id WHERE $where ORDER BY id DESC LIMIT $offset,$pageSize");
       $wherestr = base64_encode($where);
       foreach($devicelist as $k=>$v){
           $device_id = $v['device_id'];
           $devicelist[$k]['opt'] = "<a onclick=trackloding('$device_id','$wherestr')>查看轨迹</a> | <a  onclick=deviceinfo($v[id])>查看设备</a>";
           $devaddress = $this->getaddress($v['lng'].','.$v['lat']);
           $devicelist[$k]['address'] = $v['address'] ? $v['address'] : $devaddress;
       }
       echo json_encode(array('rowDatas'=>$devicelist,'dataLength'=>$dataLength));exit;
   }
   /**
    * @desc : 通过搜索类型获取相应的数据
    * @author : zhq
    * @date : 2018/05
    */
   public function getsearinfo(){
       $seartype = input('seartype');
       //1=>按厂商 2=>按厂商型号 3=>按区域 4=>按车型
       switch($seartype){
           case 1:
               $datainfo = Db::table('adv_customer_group')->field('id,customer_name as `name`')->select();
               break;
           case 2:
               $datainfo = Db::table('adv_customer_list')->field('id,customer as `name`')->select();
               break;
           case 3:
               $datainfo = Db::table('adv_area')->field('name,code')->where('parent_code = 0')->select();
               break;
           case 4:
               $datainfo = Db::table('device_info')->field('car_model as `name`')->group('car_model')->select();
               break;
           case 5:
               $datainfo[] = array('name'=>'激活');
               $datainfo[] = array('name'=>'未激活');
               break;
       }
       echo json_encode($datainfo);exit;
   }
   /**
    * @desc : 获取轨迹列表
    * @author : zhq
    * date : 2018/05
    */
   public function tracklist(){
       $device_id = input('device_id');
       $time = input('time');
       $where = input('where');
       $tableinfo = $this->gettablestoreinfo($device_id,$time);
       $tableinfo = json_decode($tableinfo,true);
       if($tableinfo['code']==200){
           foreach($tableinfo['data'] as $k=>$v){
               $firaddress = $v['firstlat']['longitude'].','.$v['firstlat']['latitude'];
               $lastaddress = $v['lastlng']['longitude'].','.$v['lastlng']['latitude'];
               $tableinfo['data'][$k]['firstaddres'] = $this->getaddress($firaddress);
               $tableinfo['data'][$k]['lastaddres'] = $this->getaddress($lastaddress);
               $tableinfo['data'][$k]['firsttime'] = date('Y-m-d H:i:s',$v['firstlat']['timestamp']/1000);
               $tableinfo['data'][$k]['lasttime'] = date('Y-m-d H:i:s',$v['lastlng']['timestamp']/1000);
               $difftime = $this->time_diff($v['lastlng']['timestamp']/1000,$v['firstlat']['timestamp']/1000);
               $tableinfo['data'][$k]['difftime'] = $difftime['hours'] >0 ? $difftime['hours'].'小时'.$difftime['minutes'].'分钟'.$difftime['seconds'].'秒' : $difftime['minutes'].'分钟'.$difftime['seconds'].'秒';
               if($v['lastlng']['timestamp']>$v['firstlat']['timestamp']){
                   $tableinfo['data'][$k]['firsttime'] = date('Y-m-d H:i:s',$v['lastlng']['timestamp']/1000);
                   $tableinfo['data'][$k]['lasttime'] = date('Y-m-d H:i:s',$v['firstlat']['timestamp']/1000);
                   $difftime = $this->time_diff($v['firstlat']['timestamp']/1000,$v['lastlng']['timestamp']/1000);
                   $tableinfo['data'][$k]['difftime'] = $difftime['hours'] >0 ? $difftime['hours'].'小时'.$difftime['minutes'].'分钟'.$difftime['seconds'].'秒' : $difftime['minutes'].'分钟'.$difftime['seconds'].'秒';
               }
               $tableinfo['data'][$k]['lnglat'] = '['.$v['lnglat'].']';
           }
           $this->assign('tabledata',$tableinfo['data']);
           $this->assign('is_track',1);
       }else{
           $this->assign('is_track',2);
       }
       $this->assign('device_id',$device_id);
       $this->assign('time',$time);
       $this->assign('where',$where);
       return view('tracklist',['name'=>'Deviceanalyze']);
   }
   /**
    * @desc : 获取tablestore的轨迹数据
    * @author ： zhq
    * @date ： 2018/05
    */
   public function gettablestoreinfo($device_id,$time){
       //获取tablestore类
       Loader::import('TableStore/src/examples/GetRangeWithColumnFilter2');
       $object = new \TableStore();
       // echo date('H');exit;
       //开始时间结束时间
       $starttime = $time.' 00:00:00';
       $endtime =  $time.' 23:59:59';

       $start_date = strtotime($starttime);
       $end_date = strtotime($endtime);
       $start_date = $start_date*1000;
       $end_date = $end_date*1000;
       $data = $object->returnTablestore($start_date,$end_date,$device_id);
       if(!$data){
           return json_encode(array('code'=>1001,'msg'=>'未找到数据'));
       }
       $data = $this->sortArray($data,'timestamp');
       //找出阶段轨迹，上下时间大于600秒
       foreach($data as $k=>$v){
           $curr = $data[$k-1]['timestamp'];
           if((($v['timestamp']-$curr)/1000 > 600)){
               $stageDiff[] = $v;
           }
       }
       //过滤轨迹阶段数据
       for($i=0,$len = count($stageDiff);$i<$len;$i++){
           $curr = $stageDiff[$i]['timestamp'];
           $next = $stageDiff[$i+1]['timestamp'];
           for($j=0,$lens = count($data);$j<$lens;$j++){
               if($data[$j]['timestamp']>$curr && $data[$j]['timestamp']<$next){
                   $stageOne[$i][] = $data[$j];
               }
           }
       }
       //最后一个轨迹的数据
       $lasttrack = $stageDiff[count($stageDiff)-1];
       //查找最后一次的轨迹数据
       foreach($data as $k1=>$v1){
           if($v1['timestamp']>$lasttrack['timestamp']){
               $stageTwo[] = $v1;
           }
       }
       //将轨迹进行合并
       $stageTwoinfo[] = $stageTwo;
       if($stageTwo&&$stageOne){
           $finalInfo = array_merge($stageOne,$stageTwoinfo);
       }elseif($stageOne){
           $finalInfo = $stageOne;
       }elseif($stageTwoinfo){
           $finalInfo = $stageTwoinfo;
       }
       if(!$finalInfo){
           return json_encode(array('code'=>1001,'msg'=>'未找到数据'));
       }
       foreach ($finalInfo as $key => $value) {
           foreach($value as $vk=>$val){
               //轨迹定位星大于0
//                if($val['satellites']>0){
               $cattrack[$key]['lnglat'][]= '[' .$val['longitude'].','.$val['latitude'].']';
               $cattrack[$key]['lastlng'] =  $value[0];
               $cattrack[$key]['firstlat'] = $value[count($value)-1];
//                }
           }
       }
       //将最终的轨迹数据进行组合
       foreach($cattrack as $ck=>$cv){
           $firsttime = $cv['lastlng']['timestamp'];
           $lasttime = $cv['firstlat']['timestamp'];
           $difftime = (($lasttime-$firsttime)/1000)/60;
           //只去轨迹记录大于3分钟的数据
//            if($difftime>3){
           $cattrackinfo[$ck]['lnglat'] = implode(',',$cv['lnglat']);
           $cattrackinfo[$ck]['lastlng'] = $cv['lastlng'];
           $cattrackinfo[$ck]['firstlat'] = $cv['firstlat'];
//            }
       }
       if(!$cattrackinfo){
           return json_encode(array('code'=>1001,'msg'=>'未找到数据'));
       }
       return json_encode(array('code'=>200,'data'=>array_values($cattrackinfo)));
   }
    /**
     * @desc : 对二维数组进行排序
     * @author : zhq
     * @date : 2017/06/13
     */
    public function sortArray($data,$field){
        $sort = array(
            'direction' => 'SORT_ASC', //排序顺序标志 SORT_DESC 降序；SORT_ASC 升序
            'field'     => $field,       //排序字段
        );
        $arrSort = array();
        foreach($data AS $uniqid => $row){
            foreach($row AS $key=>$value){
                $arrSort[$key][$uniqid] = $value;
            }
        }
        if($sort['direction']){
            array_multisort($arrSort[$sort['field']], constant($sort['direction']), $data);
        }

        return $data;
    }

    /**
     * 计算时间差
     * @param int $timestamp1 时间戳开始
     * @param int $timestamp2 时间戳结束
     * @return array
     */
    public function time_diff($timestamp1, $timestamp2)
    {
        if ($timestamp2 <= $timestamp1)
        {
            return ['hours'=>0, 'minutes'=>0, 'seconds'=>0];
        }
        $timediff = $timestamp2 - $timestamp1;
        // 时
        $remain = $timediff%86400;
        $hours = intval($remain/3600);
        // 分
        $remain = $timediff%3600;
        $mins = intval($remain/60);
        // 秒
        $secs = $remain%60;
        $time = ['hours'=>$hours, 'minutes'=>$mins, 'seconds'=>$secs];
        return $time;
    }
    /**
     * @desc : 轨迹详情
     * @author : zhq
     * @date : 2018/05
     */
    public function track_detail(){
        return view('track_detail',['name'=>'Deviceanalyze']);
    }
    /**
     * @desc : 获取设备详情
     * @author : zhq
     * @date : 2018/05
     */
    public function getdevicedetail(){
        $device_id = input('device_id');
        if(!$device_id){
            return false;
        }
        $devicedetail = db()->query("SELECT d.device_id,d.iccid,d.imei,d.mcu_version,d.svc_version_name,d.channel,d.car_model,l.address,l.lng,l.lat,c.vin FROM device_info as d INNER JOIN device_last_gps as l ON d.device_id = l.device_id LEFT JOIN car_info as c ON d.device_id = c.device_id WHERE l.id = '{$device_id}' LIMIT 1");
        $lnglat = $devicedetail[0]['lng'].','.$devicedetail[0]['lat'];
        $devaddress = $this->getaddress($lnglat);
        $devicedetail[0]['address'] = $devicedetail[0]['address'] ? $devicedetail[0]['address'] : $devaddress;
        echo json_encode($devicedetail[0]);exit;
    }
    /**
     * @desc : 车辆在线时长
     * @author : zhq
     * @date : 2018/05
     */
    public function carOnline(){
        $customer_list = Db::table('adv_customer_list')->field('id,customer')->select();
//        $area_list = Db::table('adv_area')->field('name,code')->where('parent_code = 0')->select();
        $carmodel_list = Db::table('device_info')->field('car_model')->group('car_model')->select();
        $this->assign('customer_list',$customer_list);
//        $this->assign('area_list',$area_list);
        $this->assign('carmodel_list',$carmodel_list);
        return view('carOnline',['name'=>'Deviceanalyze']);
    }
    /**
     * @desc : 获取车辆在线时长数据
     * @author : zhq
     * @date : 2018/05
     */
    public function getcaronline(){
        $pageIndex = input('pageIndex');
        $pageSize = input('pageSize');
        $param = input('param');
        $cardate = input('cardate') ? input('cardate') : 10;
        $othercardate = input('othercardate');
        $customerid = input('customerid');
//        $areaid = input('areaid');
        $carmodelid = input('carmodelid');
        if($othercardate){
            $date = "date_sub(curdate(), INTERVAL {$cardate} DAY) > date(h.online_time)";
        }else{
            $date = "date_sub(curdate(), INTERVAL {$cardate} DAY) <= date(h.online_time) AND date(h.online_time) <= CURDATE()";
        }
        $where = '';
        if($customerid){
            $where.= " AND i.channel = '{$customerid}'";
        }
//        if($areaid){
//            $where.= " AND l.province = '{$areaid}'";
//        }
        if($carmodelid){
            $where.= " AND i.car_model = '{$carmodelid}'";
        }
        if($param){
            $where = "h.device_id like '{$param}%'";
            $date = '';
        }
        $offset = ceil($pageIndex*$pageSize);
        $dataarr = db()->query("SELECT count(*) FROM device_session_history as h INNER JOIN device_info as i ON h.device_id = i.device_id  WHERE $date $where GROUP BY h.device_id");
//        $dataLength = $dataarr[0]['devcount'];
        $dataLength = count($dataarr);
        //获取车辆在线时长
//        echo "SELECT * FROM (SELECT h.id,SUM(duration)/60 as duration,h.device_id,i.channel,h.online_time FROM device_session_history as h INNER JOIN device_info as i ON h.device_id = i.device_id INNER JOIN device_last_gps as l ON h.device_id = l.device_id WHERE $date $where GROUP BY h.device_id) as t ORDER BY t.online_time DESC LIMIT $offset,$pageSize ";
        $onlinedata = db()->query("SELECT * FROM (SELECT h.id,SUM(duration) as duration,h.device_id,i.channel,h.online_time FROM device_session_history as h INNER JOIN device_info as i ON h.device_id = i.device_id  WHERE $date $where GROUP BY h.device_id) as t ORDER BY t.online_time DESC LIMIT $offset,$pageSize ");
        foreach($onlinedata as $k=>$v){
            $onlinedata[$k]['duration'] = ceil($v['duration']/60).'分钟';
        }
        echo json_encode(array('rowDatas'=>$onlinedata,'dataLength'=>$dataLength));exit;
    }
    /**
     * @desc : 获取阶段时间设备在线饼状图
     * @author : zhq
     * @date : 2018/05
     */
    public function getpiconline(){
        $time = input('time') ? input('time') : 10;
        $customerid = input('customerid') ? input('customerid') : '';
//        $areaid = input('areaid') ? input('areaid') : '';
        $carmodelid = input('carmodelid') ? input('carmodelid') : '';
        $where = '';
//        if($customerid){
//            $where.= " AND i.channel = '{$customerid}'";
//        }
////        if($areaid){
////            $where.= " AND l.province = '{$areaid}'";
////        }
//        if($carmodelid){
//            $where.= " AND i.car_model = '{$carmodelid}'";
//        }
        //近段时间之前的数据
//        $otheronline = db()->query("SELECT COUNT(*) as devcount FROM device_session_history as h INNER JOIN device_info as i ON h.device_id = i.device_id WHERE date_sub(curdate(), INTERVAL {$time} DAY) > date(h.online_time) $where GROUP BY h.device_id");
//        $currentonline = db()->query("SELECT COUNT(*) as devcount FROM device_session_history as h INNER JOIN device_info as i ON h.device_id = i.device_id WHERE date_sub(curdate(), INTERVAL {$time} DAY) <= date(h.online_time) AND date(h.online_time) <= CURDATE() $where GROUP BY h.device_id");
        $otheronline = db()->query("SELECT COUNT(*) as devcount FROM device_session_history as h  WHERE date_sub(curdate(), INTERVAL {$time} DAY) > date(h.online_time) GROUP BY h.device_id");
        $currentonline = db()->query("SELECT COUNT(*) as devcount FROM device_session_history as h  WHERE date_sub(curdate(), INTERVAL {$time} DAY) <= date(h.online_time) AND date(h.online_time) <= CURDATE() GROUP BY h.device_id");
        $currentonlinecount = count($currentonline);
        $otheronlinecount = count($otheronline);
        $onlinline = ["近{$time}天在线量({$currentonlinecount} 台)","{$time}天外在线量({$otheronlinecount} 台)"];
        $onlindata[] = array('name'=>"近{$time}天在线量({$currentonlinecount} 台)",'value'=>($currentonlinecount));
        $onlindata[] = array('name'=>"{$time}天外在线量({$otheronlinecount} 台)",'value'=>($otheronlinecount));
        echo json_encode(array('dataline'=>$onlinline,'onlindata'=>$onlindata));exit;
    }
    /**
     * @desc : 流量分析
     * @author : zhq
     * @date : 2018/05
     */
    public function flowAnalyze(){
        $customer_list = Db::table('adv_customer_list')->field('id,customer')->select();
        $carmodel_list = Db::table('device_info')->field('car_model')->group('car_model')->select();
        $this->assign('customer_list',$customer_list);
        $this->assign('carmodel_list',$carmodel_list);
        return view('flowAnalyze',['name'=>'Deviceanalyze']);
    }
    /**
     * @desc : 通过条件获取流量信息
     * @author : zhq
     * @date : 2018/05
     */
    public function getflowinfo(){
        $pageIndex = input('pageIndex');
        $pageSize = input('pageSize');
        $param = input('param');
        $cardate = input('cardate') ? input('cardate') : 10;
        $customerid = input('customerid');
        $date = "date_sub(curdate(), INTERVAL {$cardate} DAY) <= date(u.date) AND date(u.date) <= CURDATE()";
        $where = '';
        if($customerid){
            $where.= " AND i.channel = '{$customerid}'";
        }

        if($param){
            $where .= " AND u.device_id like '{$param}%'";
        }
        $offset = ceil($pageIndex*$pageSize);
        if($cardate==10 || $cardate==20) {
            $dataarr = db()->query("SELECT count(*) FROM device_daily_data_usage as u INNER JOIN device_info as i ON u.device_id = i.device_id WHERE {$date} {$where} GROUP BY u.device_id");
            $dataLength = count($dataarr);
            $flowdata = db()->query("SELECT u.id,u.device_id,SUM(u.total) as flow,i.channel,i.iccid FROM device_daily_data_usage as u INNER JOIN device_info as i ON u.device_id = i.device_id WHERE {$date} {$where} GROUP BY u.device_id LIMIT $offset,$pageSize");
        }else{
            switch ($cardate){
                case 30:
                    $month = 1;
                    break;
                case 182:
                    $month = 6;
                    break;
                case 365:
                    $month = 12;
                    break;
            }
            $dataarr = db()->query("SELECT count(*) FROM device_daily_data_usage as u INNER JOIN device_info as i ON u.device_id = i.device_id WHERE DATE_SUB(CURDATE(), INTERVAL {$month} MONTH) <= date(u.date) {$where} GROUP BY u.device_id");
            $dataLength = count($dataarr);
            $flowdata = db()->query("SELECT u.id,u.device_id,SUM(u.total) as flow,i.channel,i.iccid FROM device_daily_data_usage as u INNER JOIN device_info as i ON u.device_id = i.device_id WHERE DATE_SUB(CURDATE(), INTERVAL {$month} MONTH) <= date(u.date) {$where} GROUP BY u.device_id LIMIT $offset,$pageSize");
        }
        foreach($flowdata as $k=>$v){
            $flowdata[$k]['flow'] = sprintf("%.2f",$v['flow']/1024/1024).'M';
        }
        echo json_encode(array('rowDatas'=>$flowdata,'dataLength'=>$dataLength));exit;
    }
    /**
     * @desc : 获取厂商平均使用流量
     * @author : zhq
     * @date : 2018/05
     */
    public function channelavgflow(){
        $time = input('time') ? input('time') : 10;
        $customerid = input('customerid');
        $where = '';
        $date = "date_sub(curdate(), INTERVAL {$time} DAY) <= date(u.date) AND date(u.date) <= CURDATE()";
        if($customerid){
            $where.= " AND i.channel = '{$customerid}'";
        }
        if($time==10 || $time==20){
            $channelavg = db()->query("SELECT i.channel,SUM(u.total)/1024/1024 as totalflow,COUNT(DISTINCT u.device_id) as devcount, (SUM(u.total)/1024/1024)/COUNT(DISTINCT u.device_id) as flowavg FROM device_daily_data_usage as u INNER JOIN device_info as i ON u.device_id = i.device_id WHERE {$date} {$where} GROUP BY i.channel");
        }else{
            switch ($time){
                case 30:
                    $month = 1;
                    break;
                case 182:
                    $month = 6;
                    break;
                case 365:
                    $month = 12;
                    break;
            }
            $channelavg = db()->query("SELECT i.channel,SUM(u.total)/1024/1024 as totalflow,COUNT(DISTINCT u.device_id) as devcount, (SUM(u.total)/1024/1024)/COUNT(DISTINCT u.device_id) as flowavg FROM device_daily_data_usage as u INNER JOIN device_info as i ON u.device_id = i.device_id WHERE DATE_SUB(CURDATE(), INTERVAL {$month} MONTH) <= date(u.date) {$where} GROUP BY i.channel");
        }
        foreach ($channelavg as $v){
            $linedata[] = $v['channel'].'共用：'.sprintf("%.2f",$v['totalflow']).'M'.' 平均使用：'.sprintf("%.2f",$v['flowavg']).'M';
            $data[] = array('name'=>$v['channel'].'共用：'.sprintf("%.2f",$v['totalflow']).'M'.' 平均使用：'.sprintf("%.2f",$v['flowavg']).'M','value'=>$v['flowavg']);
        }
        echo json_encode(array('linedata'=>$linedata ? $linedata : [$customerid.'共用0，平均0'],'data'=>$data ? $data : array(array('name'=>$customerid.'共用0，平均0','value'=>0))));exit;
    }
    /**
     * @desc : 通过车架号获取车辆信息
     * @author : zhq
     * @date : 2018/05
     */
    public function getvincarinfo(){
        $vin = input('vin');
        $host = "http://jisuvindm.market.alicloudapi.com";
        $path = "/vin/query";
        $method = "GET";
        $appcode = "2af7ee06d4f0400dade6b3175be8585e";
        $headers = array();
        array_push($headers, "Authorization:APPCODE " . $appcode);
        $querys = "vin={$vin}";
        $bodys = "";
        $url = $host . $path . "?" . $querys;

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        if (1 == strpos("$".$host, "https://"))
        {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }
        $result = curl_exec($curl);
        $data = json_decode($result,true);
        //将返回的数据保存下来
        if($data['status']==0){
            //判断当前vin数据是否存在
            $vinres = Db::table('car_vin_detail')->where("vin = '$vin'")->value('id');
            if(!$vinres){
                $params = ['vin'=>$vin,'car_info'=>json_encode($data['result'],JSON_UNESCAPED_UNICODE)];
                Db::table('car_vin_detail')->insert($params);
            }
        }
        echo $result;exit;
    }
}
