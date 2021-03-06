<?php
// 授权管理控制器
// +----------------------------------------------------------------------
// | Copyright (c) 2016-2017 http://www.eacoo123.com, All rights reserved.         
// +----------------------------------------------------------------------
// | [EacooPHP] 并不是自由软件,可免费使用,未经许可不能去掉EacooPHP相关版权。
// | 禁止在EacooPHP整体或任何部分基础上发展任何派生、修改或第三方版本用于重新分发
// +----------------------------------------------------------------------
// | Author:  心云间、凝听 <981248356@qq.com>
// +----------------------------------------------------------------------
namespace app\admin\controller;

use app\admin\model\AuthRule;
use app\admin\model\AuthGroup;
use app\admin\model\AuthGroupAccess;
use app\common\model\User as UserModel;

use app\admin\builder\Builder;
use eacoo\Tree;

class Auth extends Admin {

    protected $authRuleModel;
    protected $authGroupModel;
    protected $moduleList;
    protected $userModel;

    function _initialize()
    {
        parent::_initialize();

        $this->authRuleModel  = new AuthRule();
        $this->authGroupModel = new AuthGroup();
        $this->userModel     = new UserModel;

        $default_module = [ 
                        'admin'   =>'后台模块',
                        'home'    =>'前台模块',
                        ];
        $moduleList = db('modules')->where('status',1)->column('title','name');                
        $this->moduleList = $default_module+$moduleList;

    }
    
    /**
     * 规则管理
     * @return [type] [description]
     */
    public function index(){
        
        // 搜索
        $keyword = input('param.keyword');
        if ($keyword) {
            $this->authRuleModel->where('id|name|title','like','%'.$keyword.'%');
        }
        $pid = input('param.pid',0);
        // 获取所有节点信息
        //$map['pid'] = input('param.pid',0);//是否存在父ID
        //$map['is_menu']=1;//只显示菜单
        $map = [];
        $meta_title='规则管理';

        $depend_flag = input('param.depend_flag','all');//管理类型
        if ($depend_flag!='all') {
            $this->authRuleModel->where('depend_flag',$depend_flag);
        }
        $data_list = $this->authRuleModel->where($map)->order('depend_flag,pid asc,sort asc')->field(true)->paginate(20);
        foreach ($data_list as $key=>$list) {
            $data_list[$key]['p_menu']= $this->authRuleModel->where(['id'=>(int)$list['pid']])->value('title');
        }

         //移动模块按钮属性
        $movemodule_attr['title'] = '<i class="fa fa-exchange"></i> 移动模块';
        $movemodule_attr['class'] = 'btn btn-info btn-sm';
        $movemodule_attr['onclick'] = 'move_module()';

        //移动上级按钮属性
        $moveparent_attr['title'] = '<i class="fa fa-exchange"></i> 移动位置';
        $moveparent_attr['class'] = 'btn btn-info btn-sm';
        $moveparent_attr['onclick'] = 'move_menuparent()';

        $extra_html=$this->moveMenuHtml();//添加移动按钮html
        $tab_list = ['all'=>['title'=>'全部','href'=>url('index')]];
        foreach ($this->moduleList as $key => $row) {
            $tab_list[$key] = ['title'=>$row,'href'=>url('index',['depend_flag'=>$key])];
        }
        
        Builder::run('List')
            ->setMetaTitle($meta_title)
            ->addTopBtn('addnew',array('href'=>url('ruleEdit',['pid'=>$pid])))  // 添加新增按钮
            ->addTopBtn('resume',array('model'=>'auth_rule'))  // 添加启用按钮
            ->addTopBtn('forbid',array('model'=>'auth_rule'))  // 添加禁用按钮
            ->addTopBtn('delete',array('model'=>'auth_rule'))  // 添加删除按钮
            ->setTabNav($tab_list, $depend_flag)  // 设置页面Tab导航
            //->addTopButton('self', $movemodule_attr) //移动模块
            ->addTopButton('self', $moveparent_attr) //移动菜单位置
            ->addTopBtn('sort',['model'=>'auth_rule','href'=>url('ruleSort',['pid'=>$pid])])  // 添加排序按钮
            //->setSearch('', url('rule'))
            ->keyListItem('id','ID')
            ->keyListItem('title','名称')
            ->keyListItem('p_menu','上级菜单')
            ->keyListItem('name', 'URL')
            ->keyListItem('depend_flag', '来源标识')
            ->keyListItem('sort', '排序')
            ->keyListItem('is_menu','菜单','array',[0=>'否',1=>'是'])
            ->keyListItem('status','状态','status')
            ->keyListItem('right_button', '操作', 'btn')
            ->setListDataKey('id')
            ->setListData($data_list)    // 数据列表
            ->setListPage($data_list->render()) // 数据列表分页
            ->setExtraHtml($extra_html)
            ->addRightButton('edit',array('href'=>url('ruleEdit',array('id'=>'__data_id__'))))      // 添加编辑按钮
            ->addRightButton('forbid',array('model'=>'auth_rule'))// 添加删除按钮
            ->alterListData(
                array('key' => 'pid', 'value' =>'0'),
                array('p_menu' => '无'))
            ->fetch();
    }

    /**
     * 后台菜单管理(规则)
     * @return [type] [description]
     */
    public function adminMenu(){
        $page = null;
        $menus = $this->authRuleModel->field(true)->select();
        $menus = collection($menus)->toArray();
        $tree_obj = new Tree;
        $menus = $tree_obj->toFormatTree($menus,'title');

         //移动模块按钮属性
        $movemodule_attr['title'] = '<i class="fa fa-exchange"></i> 移动模块';
        $movemodule_attr['class'] = 'btn btn-info btn-sm';
        $movemodule_attr['onclick'] = 'move_module()';

        //移动上级按钮属性
        $moveparent_attr['title'] = '<i class="fa fa-exchange"></i> 移动位置';
        $moveparent_attr['class'] = 'btn btn-info btn-sm';
        $moveparent_attr['onclick'] = 'move_menuparent()';
        $extra_html=$this->moveMenuHtml();//添加移动按钮html

        //是否标记为菜单：0否，1是
        $marker_menu0_attr['title'] = '取消菜单标记';
        $marker_menu0_attr['class'] = 'btn btn-primary btn-sm confirm ajax-post';
        $marker_menu0_attr['href'] = url('markerMenu',['status'=>0]);
        $marker_menu0_attr['target-form'] ="ids";

        $marker_menu1_attr['title'] = '标记为菜单';
        $marker_menu1_attr['class'] = 'btn btn-primary btn-sm ajax-post';
        $marker_menu1_attr['href'] = url('markerMenu',['status'=>1]);
        $marker_menu1_attr['target-form'] ="ids";

        Builder::run('List')
            ->setMetaTitle('后台菜单管理')
            ->addTopBtn('addnew',['href'=>url('ruleEdit',['pid'=>0])])  // 添加新增按钮
            ->addTopBtn('resume',['model'=>'auth_rule'])  // 添加启用按钮
            ->addTopBtn('forbid',['model'=>'auth_rule'])  // 添加禁用按钮
            ->addTopBtn('delete',['model'=>'auth_rule'])  // 添加删除按钮
            ->addTopButton('self', $marker_menu0_attr)->addTopButton('self', $marker_menu1_attr)
            ->addTopButton('self', $movemodule_attr) //移动模块
            ->addTopButton('self', $moveparent_attr) //移动菜单位置
            ->addTopBtn('sort',array('model'=>'auth_rule','href'=>url('ruleSort')))  // 添加排序按钮
            //->setSearch('', url('rule'))
            ->keyListItem('id','ID')
            ->keyListItem('title_show','名称')
            ->keyListItem('name', 'URL','url',['url_callback'=>'url'])
            ->keyListItem('icon','图标','icon')
            ->keyListItem('depend_type', '来源类型','array',[1=>'模块',2=>'插件',3=>'主题'])
            ->keyListItem('depend_flag', '来源标识')
            ->keyListItem('sort', '排序')
            ->keyListItem('is_menu','菜单','array',[0=>'否',1=>'是'])
            ->keyListItem('no_pjax','Pjax加载','array',[0=>'是',1=>'否'])
            ->keyListItem('status','状态','status')
            ->keyListItem('right_button', '操作', 'btn')
            ->setListDataKey('id')
            ->setListData($menus)    // 数据列表
            ->setListPage($page) // 数据列表分页
            ->setExtraHtml($extra_html)
            ->addRightButton('edit',array('href'=>url('ruleEdit',array('id'=>'__data_id__'))))      // 添加编辑按钮
            ->addRightButton('forbid',['model'=>'auth_rule'])// 添加删除按钮
            ->alterListData(
                array('key' => 'pid', 'value' =>'0'),
                array('p_menu' => '无'))
            ->fetch();
    }

    /**
     * 菜单编辑
     * @param  integer $id [description]
     * @return [type]      [description]
     */
    public function ruleEdit($id=0){
        $title=$id ? "编辑":"新增";
        if ($id==0) {//新增
            $pid       = (int)input('param.pid');
            $pid_data  = $this->authRuleModel->get($pid);
            $menu_data = array('depend_flag'=>$pid_data['depend_flag'],'pid'=>$pid);
        }
        
        if(IS_POST){
            // 提交数据
            $data = $this->request->param();
            //验证数据
            $this->validateData($data,'AuthRule');
            $data['depend_type']=1;//后台添加默认依赖模块
            $id   = isset($data['id']) && $data['id']>0 ? $data['id']:false;

            if ($this->authRuleModel->editData($data,$id)) {
                cache('admin_sidebar_menus_'.$this->currentUser['uid'],null);//清空后台菜单缓存
                $this->success($title.'菜单成功', url('index',['pid'=>input('param.pid')]));
            } else {
                $this->error($this->authRuleModel->getError());
            }   

        } else{
            // 获取菜单数据
            if ($id!=0) {
                $menu_data = $this->authRuleModel->find($id);
            }
            $menus = $this->authRuleModel->select();
            if (!empty($menus)) {
                $menus = collection($menus)->toArray();
                $tree_obj = new Tree;
                $menus = $tree_obj->toFormatTree($menus,'title');
            }
            
            $menus = array_merge([0=>['id'=>0,'title_show'=>'顶级菜单']], $menus);

            Builder::run('Form')
                    ->setMetaTitle($title.'菜单')  // 设置页面标题
                    ->addFormItem('id', 'hidden', 'ID', 'ID')
                    ->addFormItem('title', 'text', '标题', '用于后台显示的配置标题')
                    ->addFormItem('pid', 'multilayer_select', '上级菜单', '上级菜单',$menus)
                    ->addFormItem('depend_type', 'select', '来源类型', '来源类型。分别是模块，插件，主题',[1=>'模块',2=>'插件',3=>'主题'])
                    ->addFormItem('depend_flag', 'text', '来源标识', '如模块、插件、主题的标识名')
                    ->addFormItem('icon', 'icon', '字体图标', '字体图标')
                    ->addFormItem('name', 'text', '链接', '链接')
                    ->addFormItem('is_menu', 'radio', '后台菜单', '是否标记为后台菜单',[1=>'是',0=>'否'])
                    ->addFormItem('no_pjax', 'radio', 'Pjax加载', '标记后台菜单后，是否Pjax方式打开该页面',[0=>'是',1=>'否'])
                    ->addFormItem('sort', 'number', '排序', '排序')
                    ->setFormData($menu_data)
                    ->addButton('submit')->addButton('back')    // 设置表单按钮
                    ->fetch();
        }   
        
    }

    /**
     * 对菜单进行排序
     * @author 心云间、凝听 <981248356@qq.com>
     */
    public function ruleSort($ids = null)
    {
        $builder    = Builder::run('Sort');
        $pid = input('param.pid',false);//是否存在父ID
        $map = [];
        if ($pid>0 || $pid===0) {
            $map['pid'] = $pid;
        } 
        
        if (IS_POST) {
            cache('admin_sidebar_menus_'.$this->currentUser['uid'],null);//清空后台菜单缓存
            $builder->doSort('auth_rule', $ids);
        } else {
            //$map['status'] = array('egt', 0);
            $list = $this->authRuleModel->selectByMap($map, 'sort asc', 'id,title,sort');
            foreach ($list as $key => $val) {
                $list[$key]['title'] = $val['title'];
            }
            $builder->setMetaTitle('配置排序')
                    ->setListData($list)
                    ->addButton('submit')->addButton('back')
                    ->fetch();
        }
    }

    /**
     * 标记菜单
     * @return [type] [description]
     * @date   2017-08-27
     * @author 心云间、凝听 <981248356@qq.com>
     */
    public function markerMenu(){
        //是否标记菜单：0否，1是
        $model='AuthRule';
        if (IS_POST) {

            cache('admin_sidebar_menus_'.$this->currentUser['uid'],null);//清空后台菜单缓存

            $ids    = input('post.ids/a');
            $status = input('param.status');
            if (empty($ids)) {
                $this->error('请选择要操作的数据');
            }

            $map['id'] = ['in',$ids];
            switch ($status) {
                case 0 :  
                    $data = ['is_menu' => 0];
                    $this->editRow(
                        $model,
                        $data,
                        $map,
                        array('success'=>'标记成功','error'=>'标记失败')
                    );
                    break;
                case 1 :  
                    $data = ['is_menu' => 1];
                    $this->editRow(
                        $model,
                        $data,
                        $map,
                        ['success'=>'标记成功','error'=>'标记失败']
                    );
                    break;
                default :
                    $this->error('参数错误');
                    break;
            }
        }
    }

    /**
     * 移动菜单所属模块
     * @author 心云间、凝听 <981248356@qq.com>
     */
    public function moveModule() {
        if (IS_POST) {
            $ids       = input('param.ids');
            $to_module = input('param.to_module');
            if ($to_module) {
                $map['id'] = ['in',$ids];
                $data      = ['depend_flag' => $to_module];
                $this->editRow('auth_rule', $data, $map, array('success'=>'移动成功','error'=>'移动失败',U('index')));

            } else {
                $this->error('请选择目标模块');
            }
        }
    }

    /**
     * 移动菜单位置
     * @author 心云间、凝听 <981248356@qq.com>
     */
    public function moveMenuParent() {
        if (IS_POST) {
            $ids    = input('param.ids');
            $to_pid = input('param.to_pid');
            if ($to_pid || $to_pid==0) {
                cache('admin_sidebar_menus_'.$this->currentUser['uid'],null);
                $map['id'] = ['in',$ids];
                $data = array('pid' => $to_pid);
                $this->editRow('auth_rule', $data, $map, ['success'=>'移动成功','error'=>'移动失败',url('index')]);

            } else {
                $this->error('请选择目标菜单'.$to_pid);
            }
        }
    }

    /**
     * 构建列表移动配置分组按钮
     * @author 心云间、凝听 <981248356@qq.com>
     */
    protected function moveMenuHtml(){
            //构造移动文档的目标分类列表
            $options = '';
            foreach ($this->moduleList as $key => $val) {
                $options .= '<option value="'.$key.'">'.$val.'</option>';
            }
            //文档移动POST地址
            $move_url = url('moveModule');

            //移动菜单位置
            $menus = db('auth_rule')->select();
            $tree_obj = new Tree;
            $menus = $tree_obj->toFormatTree($menus,'title');
            $menu_options = array_merge(array(0=>array('id'=>0,'title_show'=>'顶级菜单')), $menus);
            $menu_options_str='';
            foreach ($menu_options as $key => $option) {
                    if(is_array($option)){
                        $menu_options_str.='<option value="'.$option['id'].'">'.$option['title_show'].'</option>';
                    }else{
                        $menu_options_str.='<option value="'.$option['id'].'">'.$option.'</option>';
                    }
            }
            $move_menuparent_url = url('moveMenuParent');
            return <<<EOF
            <div class="modal fade mt100" id="movemoduleModal">
                <div class="modal-dialog modal-sm">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">×</span><span class="sr-only">关闭</span></button>
                            <p class="modal-title">移动至</p>
                        </div>
                        <div class="modal-body">
                            <form action="{$move_url}" method="post" class="form-movemodule">
                                <div class="form-group">
                                    <select name="to_module" class="form-control">{$options}</select>
                                </div>
                                <div class="form-group">
                                    <input type="hidden" name="ids">
                                    <button class="btn btn-primary btn-block submit ajax-post" type="submit" target-form="form-movemodule">确 定</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal fade mt100" id="movemenuParentModal">
                <div class="modal-dialog modal-sm">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">×</span><span class="sr-only">关闭</span></button>
                            <p class="modal-title">移动至</p>
                        </div>
                        <div class="modal-body">
                            <form action="{$move_menuparent_url}" method="post" class="form-movemenu">
                                <div class="form-group">
                                    <select name="to_pid" class="form-control">{$menu_options_str}</select>
                                </div>
                                <div class="form-group">
                                    <input type="hidden" name="ids">
                                    <button class="btn btn-primary btn-block submit ajax-post" type="submit" target-form="form-movemenu">确 定</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <script type="text/javascript">
                function move_module(){
                    var ids = '';
                    $('input[name="ids[]"]:checked').each(function(){
                       ids += ',' + $(this).val();
                    });
                    if(ids != ''){
                        ids = ids.substr(1);
                        $('input[name="ids"]').val(ids);
                        $('.modal-title').html('移动选中的菜单至：');
                        $('#movemoduleModal').modal('show', 'fit')
                    }else{
                        updateAlert('请选择需要移动的菜单', 'warning');
                    }
                }
                function move_menuparent(){
                    var ids = '';
                    $('input[name="ids[]"]:checked').each(function(){
                       ids += ',' + $(this).val();
                    });
                    if(ids != ''){
                        ids = ids.substr(1);
                        $('input[name="ids"]').val(ids);
                        $('.modal-title').html('移动选中的菜单至：');
                        $('#movemenuParentModal').modal('show', 'fit')
                    }else{
                        updateAlert('请选择需要移动的菜单', 'warning');
                    }
                }
            </script>
EOF;
    }

    //角色管理
    public function role(){
        // 搜索
        $keyword = input('param.keyword');
        if ($keyword) {
            $this->authGroupModel->where('title','like','%'.$keyword.'%');
        }
        // 获取所有角色
        $map['status'] = array('egt', '0'); // 禁用和正常状态
        list($data_list,$page) = $this->authGroupModel->getListByPage($map,'id asc','*',20);
        // 使用Builder快速建立列表页面。

        Builder::run('List')        
                ->setMetaTitle('角色管理') // 设置页面标题
                ->addTopButton('addnew',array('href'=>url('roleEdit')))  // 添加新增按钮
                ->addTopButton('delete',['model'=>'AuthGroup'])  // 添加删除按钮
                ->setSearch('搜索角色','')
                ->keyListItem('id', 'ID')
                ->keyListItem('title', '角色名')
                ->keyListItem('description', '描述')
                ->keyListItem('status', '状态','status')
                ->keyListItem('right_button', '操作', 'btn')
                ->setListData($data_list)    // 数据列表
                ->setListPage($page) // 数据列表分页
                ->addRightButton('edit',['href'=>url('roleEdit',['group_id'=>'__data_id__']),'class'=>'btn btn-success btn-xs']) 
                ->addRightButton('edit',['title'=>'权限分配','href'=>url('access',['group_id'=>'__data_id__']),'class'=>'btn btn-info btn-xs'])  
                ->addRightButton('edit',array('title'=>'成员授权','href'=>url('accessUser',array('group_id'=>'__data_id__'))))    
                ->fetch();
    }
    
    //角色编辑
    public function roleEdit($group_id=0){
        $title = $group_id ? '编辑':'新增';
    
         $info =$this->authGroupModel->find($group_id);
         if (IS_POST) {
            $data = $this->request->param();
            $this->validateData($data,  
                                [
                                    ['title','require|chsAlpha','用户组名称不能为空|用户组名称只能是汉字和字母'],
                                    ['description','chsAlphaNum','描述只能是汉字字母数字']
                                ]
                            );
            $id   = isset($data['id']) && $data['id']>0 ? $data['id']:false;

            if ($this->authGroupModel->editData($data,$id)) {
                $this->success($title.'成功', url('role'));
            } else {
                $this->error($this->authGroupModel->getError());
            }

        } else {
            if ($group_id!=0) {
                $this->assign('group_id',$group_id);
            }
            $this->assign('meta_title',$title.'角色');
            $this->assign('info',$info);
            return $this->fetch();
        }
    }

    /**
     * 权限分配
     * @param  integer $group_id 组ID
     * @return [type] [description]
     * @date   2017-08-27
     * @author 心云间、凝听 <981248356@qq.com>
     */
    public function access($group_id=0){
        if ($group_id!=0) {
            $this->assign('group_id',$group_id);
        }
        $title='权限分配';
        $this->assign('meta_title',$title);

        if (IS_POST && $group_id!=0) {
            $data['id']    = $group_id;
            $menu_auth     = input('post.menu_auth/a','');//获取所有授权菜单
            $data['rules'] = implode(',',$menu_auth);
            $id   = isset($data['id']) && $data['id']>0 ? $data['id']:false;

            //开发过程中先关闭这个限制
            //if($group_id==1){
                //$this->error('不能修改超级管理员'.$title);
           // }else{
                if ($this->authGroupModel->editData($data,$id)) {
                    cache('admin_sidebar_menus_'.$this->currentUser['uid'],null);
                    $this->success($title.'成功', url('role'));
                }else{
                    $this->error($this->authGroupModel->getError());
                }
                
            //}

        } else{
            $role_auth_rule = $this->authGroupModel->where(['id'=>intval($group_id)])->value('rules');
            $this->assign('menu_auth_rules',explode(',',$role_auth_rule));//获取指定获取到的权限
        }
        $menu = $this->authRuleModel->where(['pid'=>0,'status'=>1])->order('sort asc')->select();
        foreach($menu as $k=>$v){
            $menu[$k]['_child']=$this->authRuleModel->where(['pid'=>$v['id']])->order('sort asc')->select();
        }
        $this->assign('all_auth_rules',$menu);//所以规则
        return $this->fetch();
    }

    /**
     * 用户组授权用户列表
     * @author 心云间、凝听 <981248356@qq.com>
     */
    public function accessUser($group_id=0)
    {
        if ($group_id!=0) {
            $this->assign('group_id',$group_id);
        }

        $auth_group = $this->authGroupModel->where(['status'=>['egt','0']])->field('id,title,rules')->select();
        foreach ($auth_group as $key => $row) {
            $authGroup[$row['id']]=$row;
        }
        //$list = $this->lists($model,array('a.group_id'=>$group_id,'m.status'=>array('egt',0)),'m.uid asc',null,'m.uid,m.nickname,m.last_login_time,m.last_login_ip,m.status');
        $list= $this->userModel->alias('m')->join ('__AUTH_GROUP_ACCESS__ a','m.uid=a.uid' )->where(['a.group_id'=>$group_id,'m.status'=>['egt',0]])->order('m.uid asc')->field('m.uid,m.nickname,m.last_login_time,m.last_login_ip,m.status')->paginate(20);

        $this->assign( '_list',     $list );
        $this->assign( 'page',     $list->render());
        $this->assign('auth_group', $authGroup);
        $this->assign('this_group', $authGroup[(int)$group_id]);
        $this->assign('meta_title','成员授权');
        return $this->fetch();
    }

    /**
     * 创建管理员用户组
     * @author 朱亚杰 <zhuyajie@topthink.net>
     */
    public function createGroup(){
        if ( empty($this->auth_group) ) {
            $this->assign('auth_group',['title'=>null,'id'=>null,'description'=>null,'rules'=>null]);//排除notice信息
        }
        $this->assign('meta_title','新增用户组');
        return $this->fetch('editgroup');
    }

    /**
     * 编辑管理员用户组
     * @author 朱亚杰 <zhuyajie@topthink.net>
     */
    public function editGroup(){
        $auth_group = $this->authGroupModel->find( (int)$_GET['id'] );
        $this->assign('auth_group',$auth_group);
        $this->assign('meta_title','编辑用户组');
        return $this->fetch();
    }

    /**
     * 管理员用户组数据写入/更新
     * @author 朱亚杰 <zhuyajie@topthink.net>
     */
    public function writeGroup(){
        $data = input('param.');
        if(isset($data['rules'])){
            sort($data['rules']);
            $data['rules']  = implode( ',' , array_unique($data['rules']));
        }

        $id   = isset($data['id']) && $data['id']>0 ? $data['id']:false;
        if ($this->authGroupModel->editData($data,$id)) {
            $this->success('操作成功!',url('index'));
        } else {
            $this->error('操作失败'.$this->authGroupModel->getError());
        }

    }
    /**
     * 修改用户组描述
     */
    public function descriptionGroup()
    {
        $title               = input('param.title');
        $description         = input('param.description');
        $id                  = input('param.id');
        $data['description'] = $description;
        $data['title']       = $title;
        $res=$this->authGroupModel->where('id='.$id)->save($data);
        if($res)
        {
            $this->success('修改成功!');
        }
        else{
            $this->error('修改失败!');
        }

    }
    /**
     * 将用户添加到用户组,入参uid,group_id
     * @author 朱亚杰 <zhuyajie@topthink.net>
     */
    public function addToGroup(){
        $uids = input('uids',false);//新增批量用户
        if ($uids) {
            $uid = explode(',',$uids);
        }else{
            $uid = input('uid');
        }
        
        $gid = input('param.group_id');
        if( empty($uid) ){
            $this->error('参数有误');
        }
        if(is_numeric($uid)){
            if ( is_administrator($uid) ) {
                $this->error('该用户为超级管理员');
            }
            if( !$this->userModel->where(['uid'=>$uid])->find() ){
                $this->error('用户不存在');
            }
        }

        if( $gid && !$this->authGroupModel->checkGroupId($gid)){
            $this->error($this->authGroupModel->error);
        }
        if ( $this->authGroupModel->addToGroup($uid,$gid) ){
            $this->success('操作成功');
        }else{
            $this->error($this->authGroupModel->getError());
        }
    }

    /**
     * 将用户从用户组中移除  入参:uid,group_id
     * @author 朱亚杰 <zhuyajie@topthink.net>
     */
    public function removeFromGroup(){
        $uid = input('param.uid');
        $gid = input('param.group_id');
        if( $uid==UID ){
            $this->error('不允许解除自身授权');
        }
        if( empty($uid) || empty($gid) ){
            $this->error('参数有误');
        }
        if( !$this->authGroupModel->find($gid)){
            $this->error('用户组不存在');
        }
        if ( $this->authGroupModel->removeFromGroup($uid,$gid) ){
            $this->success('操作成功');
        }else{
            $this->error('操作失败');
        }
    }

    /**
     * 设置角色的状态
     */
    public function setStatus($model ='auth_rule',$script = false){
        $ids = input('request.ids/a');
        if ($model =='AuthGroup') {
            if (is_array($ids)) {
                if(in_array(1, $ids)) {
                    $this->error('超级管理员不允许操作');
                }
            } else{
                if($ids === 1) {
                    $this->error('超级管理员不允许操作');
                }
            }
        } else{
            cache('admin_sidebar_menus_'.$this->currentUser['uid'],null);//清空后台菜单缓存
        }
        
        parent::setStatus($model);
    }
}