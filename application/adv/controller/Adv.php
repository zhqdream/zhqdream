<?php
namespace app\adv\controller;
use app\adv\controller\Base;
use think\Controller;
use think\Db;
use think\Session;
use think\captcha\Captcha;
use think\Request;
use think\Loader;
error_reporting(0);
/**
 * @date:2018/04
 * @author : zhq
 * @desc : 广告系统
 */
class Adv extends Base
{
    /**
     * @desc : 开机广告列表
     * @author : zhq
     * @date : 2018/04
     */
    public function upadvList(){
        //判断当前所有广告是否已经过期，如果过期，将该广告关闭
        $advlist = Db::table('adv_advlist')->field('id,end_time')->select();
        $advids = array();
        foreach($advlist as $k=>$v){
            if(time()>strtotime($v['end_time'])){
                $advids[] = $v['id'];
            }
        }
        if(count($advids)>0){
            $advid = implode(',',$advids);
            Db::table('adv_advlist')->where("id in({$advid})")->update(array('adv_status'=>2));
        }
        //获取所有开机广告
        $upadvlist = Db::table('adv_advlist')->alias('ad')->join('adv_img_group g','ad.adv_img_group = g.id','left')->field('ad.*,g.img_url')->where('ad.adv_type = 1')->order('ad.id desc')->paginate(9)
            ->each(function($upadvlist, $key){
                $adv_img = explode(';',$upadvlist['img_url']);
                $upadvlist['img_url'] = $adv_img;
                $upadvlist['adv_desc'] = mb_substr($upadvlist['adv_desc'],0,30).'...';
            return $upadvlist;
        });
        //获取所有广告
        $upstartadvcount = Db::table('adv_advlist')->where("adv_type = 1")->count();
        if($upstartadvcount>0){
            $this->assign('upadvlist',$upadvlist);
            return view('upadvList',['name'=>'Adv']);
        }else{
            $this->assign('button','添加开机广告');
            $this->assign('link','/index/adv/adv/upstartdev_add');
            return view('empty_page',['name'=>'Adv']);
        }
    }
    /**
     * @desc : 添加广告(页面)
     * @author : zhq
     * @date : 2018/04
     */
    public function upstartadv_add(){
        //获取所有厂商
        $manu = Db::table('adv_customer_list')->field('id,customer,customer_name')->select();
        $this->assign('manu',$manu);
        //获取广告图片组信息
        $imggroup = Db::table('adv_img_group')->field('id,img_url,group_title')->where('adv_type = 1')->select();
        $this->assign('imggroup',$imggroup);
        //获取所有地区
        $area = Db::table('adv_area')->field('code,parent_code,name')->where('parent_code = 0')->select();
        $this->assign('area',$area);
        return view('upstartadv_add',['name'=>'Adv']);
    }
    /**
     * @desc : 上传广告图片到oss
     * @author : zhq
     * @date : 2018/04
     */
    public function upload_advimg(){
        //上传应用到oss
        $fileinfo = $this->upload();
        $filearr = explode('/tmp', $fileinfo);
        $objname = 'adv_img' . $filearr[1];
        $ossres = $this->obj_oss_upload($objname, $fileinfo);
        if($ossres['info']['url']){
            $imginfo = getimagesize($ossres['info']['url']);
            echo json_encode(array('code'=>200,'img_url'=>$ossres['info']['url'],'width'=>$imginfo[0],'height'=>$imginfo[1]));exit;
        }else{
            echo json_encode(array('code'=>500));exit;
        }
    }
    /**
     * @desc : oss上传文件
     * @author : zhq
     * @date : 2018/03
     */
    public function obj_oss_upload($objname,$file){
        //获取oss类
        Loader::import('oss/samples/Object');
        $oss = new \Object();
        $res = $oss->ossuploadFile($objname,$file);
        return $res;
    }
    /**
     * @desc : 添加开机广告
     * @author : zhq
     * @date : 2018/04
     */
    public function add_upstartadv(){
        $role_id = Session::get('role_id');
        if($role_id!=1){
            return json_encode_conf(5000,'您无权限进行操作！');
        }
        $data = input();
        $adv_img_group = $data['adv_img_group'];
        $adv_title = $data['adv_title'];
        $adv_desc = $data['adv_desc'];
        $adv_manufacturer = $data['adv_manufacturer'];
        $loop_time = $data['loop_time'];
        $adv_status = $data['adv_status'];
        $end_time = $data['end_time'];
        $play_num = $data['play_num'];
        $adv_province = $data['adv_province'];
        $adv_city = $data['adv_city'];
        if(empty($adv_img_group) || empty($adv_title) || empty($adv_desc) || empty($adv_manufacturer) || empty($loop_time) || empty($adv_status) || empty($end_time)){
            $this->error('缺少必要参数，请检查！');
        }
        //判断该厂商是否有一个开机广告正在运行,如果存在，将不能添加
        $manuadvres = Db::table('adv_advlist')->field('id')->where("adv_manufacturer = '{$adv_manufacturer}' and adv_status = 1 and adv_type = 1 and adv_city = '{$adv_city}'")->find();
        if($manuadvres){
            $this->error("该厂商在'{$adv_city}'下面已经有一个开机广告正在运行，请不要重复添加！");
        }
        $advinfo = ['adv_title'=>$adv_title,'adv_desc'=>$adv_desc,'adv_img_group'=>$adv_img_group,'adv_manufacturer'=>$adv_manufacturer,'loop_time'=>$loop_time,'play_num'=>$play_num,'adv_type'=>1,'add_time'=>date('Y-m-d H:i:s'),'end_time'=>$end_time,'adv_province'=>$adv_province,'adv_city'=>$adv_city];
        $upadvres = Db::table('adv_advlist')->insert($advinfo);
        if($upadvres){
            $message = "操作人[$this->mobile],添加开机广告：{$adv_title}";
            $controller = request()->controller();
            $action = request()->action();
            $opt_type = $controller.'/'.$action;
            //记录操作日志
            $this->add_optlog($message,$opt_type);
            $this->success('添加开机广告成功','/index/adv/adv/upadvList');
        }else{
            $this->error('添加开机广告失败');
        }
    }
    /**
     * @desc : 删除广告
     * @author : zhq
     * @date : 2018/04
     */
    public function del_advinfo(){
        $role_id = Session::get('role_id');
        if($role_id!=1){
            return json_encode_conf(5000,'您无权限进行操作！');
        }
        $id = input('id');
        if(empty($id)){
            return json_encode_conf(1000,'参数不能为空，请检查！');
        }
        $advinfo = Db::table('adv_advlist')->field('id,adv_title')->where("id = {$id}")->find();
        $deladvres = Db::table('adv_advlist')->where("id = {$id}")->delete();
        if($deladvres){
            $message = "操作人[$this->mobile],删除广告：{$advinfo['adv_title']}";
            $controller = request()->controller();
            $action = request()->action();
            $opt_type = $controller.'/'.$action;
            //记录操作日志
            $this->add_optlog($message,$opt_type);
            return json_encode_conf(200,'删除广告成功！');
        }else{
            return json_encode_conf(3000,'删除广告失败！');
        }

    }
    /**
     * @desc : 广告图片组列表
     * @author : zhq
     * @date : 2018/04
     */
    public function adv_imggroup(){
        $imggroup = Db::table('adv_img_group')->count();
        //获取所有广告图片组
        $advimggroup = Db::table('adv_img_group')->order('id desc')->paginate(6)
            ->each(function($advimggroup, $key){
                $group_img = explode(';',$advimggroup['img_url']);
                $advimggroup['img_url'] = $group_img;
                return $advimggroup;
            });
        $this->assign('advimggroup',$advimggroup);
        if($imggroup>0){
            return view('adv_imggroup',['name'=>'Adv']);
        }else{
            $this->assign('button','添加广告组图片');
            $this->assign('link','/index/adv/adv/imggroup_add');
            return view('empty_page',['name'=>'Adv']);
        }
    }
    /**
     * @desc : 添加广告图片组(页面)
     * @author : zhq
     * @date : 2018/04
     */
    public function imggroup_add(){
        return view('imggroup_add',['name'=>'Adv']);
    }
    /**
     * @desc : 添加广告组数据
     * @author : zhq
     * @date : 2018/04
     */
    public function add_imggroupinfo(){
        $role_id = Session::get('role_id');
        if($role_id!=1){
            return json_encode_conf(5000,'您无权限进行操作！');
        }
        $data = input();
        $adv_img = ltrim($data['adv_img'],';');
        $group_title = $data['group_title'];
        $group_desc = $data['group_desc'];
        $adv_type = $data['adv_type'];
        $img_width = $data['img_width'];
        $img_height = $data['img_height'];
        if(empty($adv_img) || empty($group_title) || empty($group_desc)){
            $this->error('缺少必要参数，请检查!');
        }
        $imggroup = ['group_title'=>$group_title,'group_desc'=>$group_desc,'img_url'=>$adv_img,'add_time'=>date('Y-m-d H:i:s'),'adv_type'=>$adv_type,'img_width'=>$img_width,'img_height'=>$img_height];
        $imggroupres = Db::table('adv_img_group')->insert($imggroup);
        if($imggroupres){
            $message = "操作人[$this->mobile],添加广告图片：{$group_title}";
            $controller = request()->controller();
            $action = request()->action();
            $opt_type = $controller.'/'.$action;
            //记录操作日志
            $this->add_optlog($message,$opt_type);
            $this->success('添加广告图片组成功','/index/adv/adv/adv_imggroup');
        }else{
            $this->error('添加广告图片组失败');
        }
    }
    /**
     * @desc : 删除广告组信息
     * @author : zhq
     * @date : 2018/04
     */
    public function del_imggroup(){
        $role_id = Session::get('role_id');
        if($role_id!=1){
            return json_encode_conf(5000,'您无权限进行操作！');
        }
        $id = input('id');
        if(empty($id)){
            return json_encode_conf(1000,'参数不能为空，请检查！');
        }
        //查看该广告组是否被使用
        $advlistres = Db::table('adv_advlist')->field('id')->where("adv_img_group = {$id}")->find();
        if($advlistres){
            return json_encode_conf(3001,'该广告组正在被其他广告所使用，您不能删除！');
        }
        $imggroupinfo = Db::table('adv_img_group')->field('id,group_title')->where("id = {$id}")->find();
        $deladvres = Db::table('adv_img_group')->where("id = {$id}")->delete();
        if($deladvres){
            $message = "操作人[$this->mobile],删除广告组图片：{$imggroupinfo['group_title']}";
            $controller = request()->controller();
            $action = request()->action();
            $opt_type = $controller.'/'.$action;
            //记录操作日志
            $this->add_optlog($message,$opt_type);
            return json_encode_conf(200,'删除广告组图片成功！');
        }else{
            return json_encode_conf(3000,'删除广告组图片失败！');
        }
    }
    /**
     * @desc : 修改开机广告(页面)
     * @author : zhq
     * @date : 2018/04
     */
    public function upstartadv_update(){
        $id = input('id');
        //通过当前广告的ID获取该广告信息
        $advinfo = Db::table('adv_advlist')->alias('ad')->join('adv_img_group g','ad.adv_img_group = g.id')->field('ad.*,g.img_url')->where("ad.id = {$id}")->order('ad.id desc')->find();
        //获取所有厂商
        $manu = Db::table('adv_customer_list')->field('id,customer,customer_name')->select();
        $this->assign('manu',$manu);
        //获取广告图片组信息
        $imggroup = Db::table('adv_img_group')->field('id,img_url,group_title')->where('adv_type=1')->select();
        //获取所有地区
        $area = Db::table('adv_area')->field('code,parent_code,name')->where('parent_code = 0')->select();
        $this->assign('area',$area);

        $this->assign('imggroup',$imggroup);
        $this->assign('advinfo',$advinfo);
        return view('upstartadv_update',['name'=>'Adv']);
    }
    /**
     * @desc : 修改开机广告信息
     * @author : zhq
     * @date : 2018/04
     */
    public function update_upstartadv(){
        $role_id = Session::get('role_id');
        if($role_id!=1){
            return json_encode_conf(5000,'您无权限进行操作！');
        }
        $data = input();
        $id = $data['adv_id'];
        $adv_title = $data['adv_title'];
        $adv_desc = $data['adv_desc'];
        $adv_img_group = $data['adv_img_group'];
        $adv_manufacturer = $data['adv_manufacturer'];
        $loop_time = $data['loop_time'];
        $play_num = $data['play_num'];
        $adv_status = $data['adv_status'];
        $end_time = $data['end_time'];
        $adv_province = $data['adv_province'];
        $adv_city = $data['adv_city'];
        if(empty($id) || empty($adv_title) || empty($adv_desc) || empty($adv_img_group) || empty($adv_manufacturer) || empty($loop_time) || empty($adv_status) || empty($end_time)){
            $this->error('缺少必要参数，请检查！');
        }

        //判断该厂商是否有一个开机广告正在运行,如果存在，将不能添加
        $manuadvres = Db::table('adv_advlist')->field('id')->where("adv_manufacturer = '{$adv_manufacturer}' and adv_status = 1 and adv_type = 1 and id != {$id} and adv_city = '{$adv_city}'")->find();
        if($manuadvres){
            $this->error("该厂商在'{$adv_city}'下面已经有一个开机广告正在运行，请不要重复添加！");
        }

        $updinfo = Db::table('adv_advlist')->field('adv_title,adv_version')->where("id = {$id}")->find();
        //进行修改开机广告信息
        $upadvinfo = ['adv_title'=>$adv_title,'adv_desc'=>$adv_desc,'adv_img_group'=>$adv_img_group,'adv_manufacturer'=>$adv_manufacturer,'loop_time'=>$loop_time,'play_num'=>$play_num,'adv_status'=>$adv_status,'end_time'=>$end_time,'adv_version'=>$updinfo['adv_version']+0.1,'adv_province'=>$adv_province,'adv_city'=>$adv_city];
        $upadvres = Db::table('adv_advlist')->where("id = {$id}")->update($upadvinfo);
        if($upadvres){
            $message = "操作人[$this->mobile],将开机广告：{$updinfo['adv_title']} 修改为:{$adv_title}";
            $controller = request()->controller();
            $action = request()->action();
            $opt_type = $controller.'/'.$action;
            //记录操作日志
            $this->add_optlog($message,$opt_type);
            $this->success('修改开机广告成功！','/index/adv/adv/upadvList');
        }else{
            $this->error('修改开机广告失败！');
        }
    }
    /**
     * @desc : 修改广告组图片（页面）
     * @author : zhq
     * @date : 2018/04
     */
    public function imggroup_update(){
        //获取广告组图片
        $id = input('id');
        if(empty($id)){
            $this->error('缺少必要参数');
        }
        $imgroupinfo = Db::table('adv_img_group')->field('id,group_title,group_desc,img_url,adv_type')->where("id = {$id}")->find();
        $this->assign('imgroupinfo',$imgroupinfo);
        return view('imggroup_update',['name'=>'Adv']);
    }
    /**
     * @desc : 修改广告组图片信息
     * @author : zhq
     * @date : 2018/03
     */
    public function update_imggroup(){
        $role_id = Session::get('role_id');
        if($role_id!=1){
            return json_encode_conf(5000,'您无权限进行操作！');
        }
        $data= input();
        $id = $data['id'];
        $adv_img = ltrim($data['adv_img'],';');
        $group_title = $data['group_title'];
        $group_desc = $data['group_desc'];
        $adv_type = $data['adv_type'];
        $img_width = $data['img_width'];
        $img_height = $data['img_height'];
        if(empty($id) || empty($group_title) || empty($group_desc)){
            $this->error('缺少必要参数');
        }
        $imgroupinfo = ['group_title'=>$group_title,'group_desc'=>$group_desc,'adv_type'=>$adv_type];
        if($adv_img){
            $imgroupinfo['img_url'] = $adv_img;
        }
        if($img_width&&$img_height){
            $imgroupinfo['img_width'] = $img_width;
            $imgroupinfo['img_height'] = $img_height;
        }
        //修改广告组信息
        $groupimgres = Db::table('adv_img_group')->where("id = {$id}")->update($imgroupinfo);
        //修改关联该广告组图片的广告版本好
        $advres = Db::table('adv_advlist')->where("adv_img_group = {$id}")->update(array('adv_version' => ['exp','adv_version+0.1']));
        if($groupimgres&&$advres!==false){
            $message = "操作人[$this->mobile],将开机广告图片组ID为：{$id} 的信息修改为:{$group_title}";
            $controller = request()->controller();
            $action = request()->action();
            $opt_type = $controller.'/'.$action;
            //记录操作日志
            $this->add_optlog($message,$opt_type);
            $this->success('修改广告组图片成功！','/index/adv/adv/adv_imggroup');
        }else{
            $this->error('修改广告组图片失败');
        }
    }
    /**
     * @desc : 弹窗广告
     * @author : zhq
     * @date : 2018/04
     */
    public function popupAdvlist(){
        //判断当前所有广告是否已经过期，如果过期，将该广告关闭
        $advlist = Db::table('adv_advlist')->field('id,end_time')->select();
        $advids = array();
        foreach($advlist as $k=>$v){
            if(time()>strtotime($v['end_time'])){
                $advids[] = $v['id'];
            }
        }
        if(count($advids)>0){
            $advid = implode(',',$advids);
            Db::table('adv_advlist')->where("id in({$advid})")->update(array('adv_status'=>2));
        }
        $category = input('category') ? input('category') : 0;
        if($category){
            $where = "ad.adv_type = 2 and ad.adv_category = '{$category}'";
        }else{
            $where = "ad.adv_type = 2";
        }
        //获取所有弹窗广告
        $popadvlist = Db::table('adv_advlist')->alias('ad')->join('adv_img_group g','ad.adv_img_group = g.id','left')->field('ad.*,g.img_url')->where($where)->order('ad.id desc')->paginate(9);
        $upstartadvcount = Db::table('adv_advlist')->where("adv_type = 2")->count();
        if($upstartadvcount>0){
            //获取所有广告的分类总数
            $advgroup = Db::table('adv_advlist')->field('adv_category,count(*) as num')->where("adv_type = 2")->group('adv_category')->select();
            foreach($advgroup as $k=>$v){
                $group[$v['adv_category']] =$v['num'];
            }
            $imgcount = $group[1] ? $group[1] : 0;
            $textcount = $group[2] ? $group[2] : 0;
            $voicecount = $group[3] ? $group[3] : 0;
            $this->assign('imgcount',$imgcount);
            $this->assign('textcount',$textcount);
            $this->assign('voicecount',$voicecount);
            $this->assign('popadvlist',$popadvlist);
            $this->assign('category',$category);
            return view('popupAdvlist',['name'=>'Adv']);
        }else{
            $this->assign('button','添加弹窗广告');
            $this->assign('link','/index/adv/adv/upstartdev_add');
            return view('empty_page',['name'=>'Adv']);
        }
    }
    /**
     * @desc : 添加弹窗广告
     * @author : zhq
     * @date : 2018/04
     */
    public function popadv_add(){
        //获取所有厂商
        $manu = Db::table('adv_customer_list')->field('id,customer,customer_name')->select();
        $this->assign('manu',$manu);
        //获取广告图片组信息
        $imggroup = Db::table('adv_img_group')->field('id,img_url,group_title')->where('adv_type = 2')->select();
        $this->assign('imggroup',$imggroup);
        //获取所有地区
        $area = Db::table('adv_area')->field('code,parent_code,name')->where('parent_code = 0')->select();
        $this->assign('area',$area);
        return view('popadv_add',['name'=>'Adv']);
    }
    /**
     * @desc : 添加弹窗广告信息
     * @author : zhq
     * @date : 2018/04
     */
    public function add_popadvinfo(){
        $role_id = Session::get('role_id');
        if($role_id!=1){
            return json_encode_conf(5000,'您无权限进行操作！');
        }
        $data = input();
        $adv_category = $data['adv_category'];
        $adv_title = $data['adv_title'];
        $adv_desc = $data['adv_desc'];
        $adv_img_group = $data['adv_img_group'];
        $advxy = $data['advxy'];
        $adv_manufacturer = $data['adv_manufacturer'];
        $adv_status = $data['adv_status'];
        $end_time = $data['end_time'];
        $adv_url = $data['adv_url'];
        $adv_province = $data['adv_province'];
        $adv_city = $data['adv_city'];

        //判断该厂商是否有一个开机广告正在运行,如果存在，将不能添加
        $manuadvres = Db::table('adv_advlist')->field('id')->where("adv_manufacturer = '{$adv_manufacturer}' and adv_status = 1 and adv_type = 2 and adv_city='{$adv_city}'")->find();
        if($manuadvres){
            $this->error("该厂商在'{$adv_city}'下面已经有一个弹窗广告正在运行，请不要重复添加！");
        }

        if(empty($adv_category) || empty($adv_title) || empty($adv_manufacturer) || empty($adv_status) || empty($end_time)){
            $this->error('缺少必要参数！');
        }
        if($adv_category==1){
            if(empty($adv_img_group) || empty($advxy)){
                $this->error('缺少必要参数！');
            }
//            $advxy = explode(',',$advxy);
//            $advxy = $advxy[0].','.$advxy[1];
        }else{
            if(empty($adv_desc)){
                $this->error('缺少必要参数！');
            }
        }
        $popdata = ['adv_title'=>$adv_title,'adv_desc'=>$adv_desc,'adv_url'=>$adv_url,'adv_img_group'=>$adv_img_group,'adv_manufacturer'=>$adv_manufacturer,'adv_type'=>2,'add_time'=>date('Y-m-d H:i:s'),'end_time'=>$end_time,'adv_category'=>$adv_category,'adv_xy'=>$advxy,'adv_status'=>$adv_status,'adv_province'=>$adv_province,'adv_city'=>$adv_city];
        $popaddres = Db::table('adv_advlist')->insert($popdata);
        if($popaddres){
            $message = "操作人[$this->mobile],添加弹窗广告：{$adv_title}";
            $controller = request()->controller();
            $action = request()->action();
            $opt_type = $controller.'/'.$action;
            //记录操作日志
            $this->add_optlog($message,$opt_type);
            $this->success('添加弹窗广告成功！','/index/adv/adv/popupAdvlist');
        }else{
            $this->error('添加弹窗广告失败！');
        }
    }
    /**
     * @desc ：修改弹窗广告（页面）
     * @author : zhq
     * @date : 2018/04
     */
    public function popadv_update(){
        $id = input('id');
        if(empty($id)){
            $this->error('缺少必要参数！');
        }
        //获取当前广告信息
        $popadvinfo = Db::table('adv_advlist')->alias('ad')->join('adv_img_group g','ad.adv_img_group = g.id','left')->field('ad.*,g.img_url')->where("ad.id = {$id}")->find();
        //获取所有厂商
        $manu = Db::table('adv_customer_list')->field('id,customer,customer_name')->select();
        $this->assign('manu',$manu);
        //获取广告图片组信息
        $imggroup = Db::table('adv_img_group')->field('id,img_url,group_title')->where('adv_type = 2')->select();
        $this->assign('imggroup',$imggroup);
        $this->assign('popadvinfo',$popadvinfo);
        //获取所有地区
        $area = Db::table('adv_area')->field('code,parent_code,name')->where('parent_code = 0')->select();
        $this->assign('area',$area);
        return view('popadv_update',['name'=>'Adv']);
    }
    /**
     * @desc : 修改弹窗广告数据
     * @author : zhq
     * @date : 2018/04
     */
    public function update_popadvinfo(){
        $role_id = Session::get('role_id');
        if($role_id!=1){
            return json_encode_conf(5000,'您无权限进行操作！');
        }
        $data = input();
        $id = $data['id'];
        $adv_category = $data['adv_category'];
        $adv_title = $data['adv_title'];
        $adv_desc = $data['adv_desc'];
        $adv_img_group = $data['adv_img_group'];
        $advxy = $data['advxy'];
        $adv_manufacturer = $data['adv_manufacturer'];
        $adv_status = $data['adv_status'];
        $end_time = $data['end_time'];
        $adv_url = $data['adv_url'];
        $adv_province = $data['adv_province'];
        $adv_city = $data['adv_city'];

        //判断该厂商是否有一个开机广告正在运行,如果存在，将不能添加
        $manuadvres = Db::table('adv_advlist')->field('id')->where("adv_manufacturer = '{$adv_manufacturer}' and adv_status = 1 and adv_type = 2 and id != {$id} and adv_city = '{$adv_city}'")->find();
        if($manuadvres){
            $this->error("该厂商在'$adv_city'下面已经有一个弹窗广告正在运行，请不要重复添加！");
        }

        if(empty($id) || empty($adv_category) || empty($adv_title) || empty($adv_manufacturer) || empty($adv_status) || empty($end_time)){
            $this->error('缺少必要参数！');
        }
        if($adv_category==1){
            if(empty($adv_img_group)){
                $this->error('缺少必要参数！');
            }
//            $advxy = explode(',',$advxy);
//            $advxy = $advxy[0].','.$advxy[1];
        }else{
            if(empty($adv_desc)){
                $this->error('缺少必要参数！');
            }
        }
        $popinfo = Db::table('adv_advlist')->field('adv_title')->where("id = {$id}")->find();
        $popdata = ['adv_title'=>$adv_title,'adv_desc'=>$adv_desc,'adv_url'=>$adv_url,'adv_img_group'=>$adv_img_group,'adv_manufacturer'=>$adv_manufacturer,'adv_type'=>2,'add_time'=>date('Y-m-d H:i:s'),'end_time'=>$end_time,'adv_category'=>$adv_category,'adv_status'=>$adv_status,'adv_version'=>['exp','adv_version+0.1'],'adv_province'=>$adv_province,'adv_city'=>$adv_city];
        if($advxy){
            $popdata['adv_xy'] = $advxy;
        }
        $popaddres = Db::table('adv_advlist')->where("id = {$id}")->update($popdata);
        if($popaddres){
            $message = "操作人[$this->mobile],将弹窗广告:{$popinfo['adv_title']} 修改为：{$adv_title}";
            $controller = request()->controller();
            $action = request()->action();
            $opt_type = $controller.'/'.$action;
            //记录操作日志
            $this->add_optlog($message,$opt_type);
            $this->success('修改弹窗广告成功！','/index/adv/adv/popupAdvlist');
        }else{
            $this->error('修改弹窗广告失败！');
        }
    }
    /**
     * @desc : 通过省份获取城市数据
     * @author : zhq
     * @date : 2018/04
     */
    public function getcity(){
        $code = input('code');
        if(empty($code)){
            return json_encode_conf(1000,'参数不能为空，请检查！');
        }
        $area = Db::table('adv_area')->field('code,parent_code,name')->where("parent_code = {$code}")->select();
        return json_encode_conf(200,$area);
    }
}
