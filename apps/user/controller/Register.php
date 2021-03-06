<?php
// 注册
// +----------------------------------------------------------------------
// | Copyright (c) 2016-2018 http://www.eacoo123.com, All rights reserved.
// +----------------------------------------------------------------------
// | [EacooPHP] 并不是自由软件,可免费使用,未经许可不能去掉EacooPHP相关版权。
// | 禁止在EacooPHP整体或任何部分基础上发展任何派生、修改或第三方版本用于重新分发
// +----------------------------------------------------------------------
// | Author:  yyyvy <76836785@qq.com>
// +----------------------------------------------------------------------

namespace app\user\controller;
use app\home\controller\Home;

use app\common\model\User as UserModel;

class Register extends Home{

    function _initialize(){
        parent::_initialize();
        $this->userModel = new UserModel;
    }



    public function index(){
        if (IS_POST){
            $data = input('post.');
            //检测用户名或昵称是否被禁止注册
            $check_username = UserModel::checkDenyUser($data['username']);
            $check_nickname = UserModel::checkDenyUser($data['nickname']);
            if ($check_username || $check_nickname){
                $this->error('用户名或昵称包含违规关键字，禁止注册');
            }
            //验证数据
            $this->validateData($data,'Register.register');
            $uid = isset($data['uid']) && $data['uid']>0 ? intval($data['uid']) : false;
            // 提交数据
            $result = $this->userModel->editData($data,$uid,'uid');
            if ($result) {
                if ($uid>0) {//如果是编辑状态下
                    $this->userModel->updateLoginSession($uid);
                }
                $this->success('注册成功', url('index'));
            } else {
                $this->error($this->userModel->getError());
            }
        }else{
            return $this->fetch();
        }
    }

}