<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2015/8/20
 * Time: 15:45
 */

namespace Admin\Controller;


use Think\Controller;

class SupplierController extends Controller
{

    public function index()
    {

        //>>3.存放查询条件
        $wheres = array();
        //>>2.接收查询参数
        $keyword = I('get.keyword','');
        if(!empty($keyword)){
            $wheres['name'] =  array('like',"%{$keyword}%");
        }

        $model = D('Supplier');
        //>>1.需要$model提供分页中需要使用的数据
        /**
         * 分页数据:
         * array(
            rows=>分页列表中的数据
         *  pageHtml=>'分页工具条'
         * )
         */
        $pageResult = $model->getPageResult($wheres);
        $this->assign($pageResult);

        //保存当前请求的url地址到cookie中,为了做其他操作再通过该url回去...
        cookie('__forward__',$_SERVER['REQUEST_URI']);

        $this->display('index');
    }


    public function add()
    {
        if (IS_POST) {
            $model = D('Supplier');
            if ($model->create() !== false) { //1.接收数据  2.自动验证  3.自动完成
                if ($model->add() !== false) {
                    $this->success('保存成功!', U('index'));
                    return;
                }
            }
            $this->error('保存失败!' . showModelError($model));
        } else {
            $this->display('edit');
        }
    }


    public function edit($id)
    {
        $model = D('Supplier');
        if (IS_POST) {
            if ($model->create() !== false) { //1.接收数据  2.自动验证  3.自动完成
                if ($model->save() !== false) {
                    $this->success('更新成功!', cookie('__forward__'));
                    return;
                }
            }
            $this->error('更新失败!' . showModelError($model));
        } else {
            $row = $model->find($id);
            $this->assign($row);
            $this->display('edit');
        }
    }

    /**
     * 改变一行数据的状态
     * @param $id
     * @param $status  -1 表示删除
     */
    public function changeStatus($id, $status=-1)
    {
        $model = D('Supplier');
        if ($model->changeStatus($id, $status) !== false) {
            $this->success('操作成功!', cookie('__forward__'));
        } else {
            $this->error('操作失败!');
        }
    }

}