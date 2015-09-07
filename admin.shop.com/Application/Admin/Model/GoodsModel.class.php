<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2015/8/20
 * Time: 15:46
 */

namespace Admin\Model;


use Think\Model;
use Think\Page;

class GoodsModel extends BaseModel
{
    // 自动验证定义   $fields 表中的每个字段信息
    protected $_validate_1 = array(
        array('name', 'require', '名称不能够为空!', self::EXISTS_VALIDATE, '', self::MODEL_BOTH),
        array('sn', 'require', '货号不能够为空!', self::EXISTS_VALIDATE, '', self::MODEL_BOTH),
        array('goods_category_id', 'require', '商品分类不能够为空!', self::EXISTS_VALIDATE, '', self::MODEL_BOTH),
        array('brand_id', 'require', '品牌不能够为空!', self::EXISTS_VALIDATE, '', self::MODEL_BOTH),
        array('supplier_id', 'require', '供货商不能够为空!', self::EXISTS_VALIDATE, '', self::MODEL_BOTH),
        array('market_price', 'require', '市场价格不能够为空!', self::EXISTS_VALIDATE, '', self::MODEL_BOTH),
        array('shop_price', 'require', '本店价格不能够为空!', self::EXISTS_VALIDATE, '', self::MODEL_BOTH),
        array('stock', 'require', '库存不能够为空!', self::EXISTS_VALIDATE, '', self::MODEL_BOTH),
        array('goods_status', 'require', '商品状态不能够为空!', self::EXISTS_VALIDATE, '', self::MODEL_BOTH),
        array('is_on_sale', 'require', '是否上架不能够为空!', self::EXISTS_VALIDATE, '', self::MODEL_BOTH),
    );

    protected $_auto = array(
        array('inputtime',NOW_TIME),
        array('goods_status','handler_good_status',self::MODEL_BOTH,'callback'),
    );

    /**
     * 该方法主要是被子类覆盖.
     */
    protected function _setModel()
    {
        $this->field('obj.*,gc.name as goods_category_name,b.name as brand_name,s.name as supplier_name');
        $this->join('__GOODS_CATEGORY__ as  gc on  obj.goods_category_id=gc.id');
        $this->join('__BRAND__ as  b on obj.brand_id = b.id');
        $this->join('__SUPPLIER__ as  s on obj.supplier_id = s.id');
    }

    /**
     * 主要是对查询出来的数据列表进一步处理
     */
    protected function _handleRows(&$rows){
        foreach($rows as &$row){
            $goods_status = $row['goods_status'];
            //是否为精品
            $row['is_best'] = $goods_status & 1==0?0:1;
            //是否为新品
            $row['is_new'] = $goods_status & 2==0?0:1;
            //是否为热销
            $row['is_hot'] = $goods_status & 4==0?0:1;
        }
    }

    /**
     * 自动处理商品状态
     * @param $goods_status  用户选择的商品状态
     */
    public function handler_good_status($goods_status){
        $init_status = 0;  //初始化状态 0
        if(!empty($goods_status)){
           foreach($goods_status as $v){
               $init_status = $init_status |$v;
           }
           return $init_status;
       }
        return $init_status;
    }


    /**
     * 将请求中的数据保存到数据库中
     * @param mixed|string $requestData   请求中的所有数据
     * @return bool
     */
    public function add($requestData){
        //$this->data: create收集到的当前表中的数据
        //$requestData: 请求中的所有数据

        $this->startTrans();  //开启事务

        //>>1.将请求中的数据保存到数据库中
        $id = parent::add();
        if($id===false){
            $this->rollback();
            return false;
        }
        //>>2.生成sn之后将sn更新到sn字段上
             //2015082500000001
             //2015082500000011
             //2015082500000111
        $sn =  date('Ymd').str_pad($id,8, "0", STR_PAD_LEFT);  ;
        $result = parent::save(array('id'=>$id,'sn'=>$sn));
        if($result===false){
            $this->rollback();
            return false;
        }

        //>>3.处理商品描述
        $result  = $this->handleGoodsIntro($id,$requestData['intro']);
        if($result===false){
            $this->error = '添加简介信息失败!';
            $this->rollback();
            return false;
        }
        //>>4.处理商品相册中的图片
        $result = $this->handlerGallery($id,$requestData['gallery_path']);
        if($result===false){
            $this->error = '添加商品图片失败!';
            $this->rollback();
            return false;
        }
        //>>5.处理商品相关文章
        $result = $this->handleArticle($id,$requestData['article_ids']);
        if($result===false){
            $this->error = '添加商品文章失败!';
            $this->rollback();
            return false;
        }
        //>>6.处理会员价格
        $result = $this->handleMemberPrice($id,$requestData['member_goods_price']);
        if($result===false){
            $this->error = '处理会员价格失败!';
            $this->rollback();
            return false;
        }

        return $this->commit();//提交事务
    }


    /**
     * 将商品的会员价格保存到数据库中
     * @param $goods_id
     * @param $member_goods_prices
     *
     *
     *  ["member_goods_price"] => array(3) {
            [1] => string(3) "100"    键: 会员级别id  值: 会员价格
            [2] => string(2) "90"
            [3] => string(2) "80"
    }
     */
    private function handleMemberPrice($goods_id,$member_goods_prices){

        $rows = array();
        foreach($member_goods_prices as $member_level_id=>$price){
            $rows[] =  array('member_level_id'=>$member_level_id,'price'=>$price,'goods_id'=>$goods_id);
        }

        if(!empty($rows)){
            $memberGoodsPriceModel = M('MemberGoodsPrice');
            return $memberGoodsPriceModel->addAll($rows);
        }
    }

    private function handleArticle($goods_id,$article_ids){
        if(!empty($article_ids)){
            $goodsArticleModel = M('GoodsArticle');
            //先删除当前商品的相关文章
            $goodsArticleModel->where(array('goods_id'=>$goods_id))->delete();

            //再将这次的文章保存.
            $rows = array();
            foreach($article_ids as $article_id){
                $rows[] = array('goods_id'=>$goods_id,'article_id'=>$article_id);
            }
            return$goodsArticleModel->addAll($rows);
        }

    }

    /**
     * 将相册图片路径保存到goods_gallery表中
     * @param $goods_id
     * @param $gallery_paths
     * @return bool|string
     */
    private function handlerGallery($goods_id,$gallery_paths){
         if(!empty($gallery_paths)){
             $rows = array();
             foreach($gallery_paths as $gallery_path){
                 $rows[] =  array('path'=>$gallery_path,'goods_id'=>$goods_id);
             }

            return M('GoodsGallery')->addAll($rows);
         }
    }

    /**
     * 将goods_id和intro保存到goods_intro表中
     * @param $goods_id
     * @param $intro
     */
    private function handleGoodsIntro($goods_id,$intro){
        $goodsIntro = M('GoodsIntro');
        //>>1.删除原来的简介内容
        $goodsIntro->where(array('goods_id'=>$goods_id))->delete();
        //>>2.再将新的内容添加进去
        $data =array('goods_id'=>$goods_id,'content'=>$intro);
        return $goodsIntro->add($data);
    }


    public function find($id){
        $goods = parent::find($id);
        if(!empty($goods)){
            //说明根据id找到一行记录, 需要把这行记录中其他的信息找到

            //>>1.从goods_intro表中找到简介
            $intro  = M('GoodsIntro')->getFieldByGoods_id($id,'content');
            $goods['intro'] = $intro;


            //>>2.goods_gallery表中获取当前商品的图片路径
            $gallerys  = M('GoodsGallery')->field('id,path')->where(array('goods_id'=>$id))->select();
            $goods['gallerys'] =  $gallerys;

            //>>3.从goods_article表中获取相关文章的id, 再到article表中找到相关文章的name
            $articles = M()->query("SELECT a.id,a.name FROM `goods_article` as obj  join article as a on obj.article_id=a.id  where obj.goods_id = $id");
            $goods['articles']=$articles;


            //>>4.需要根据商品的id查询出该商品的会员价格
            $memberGoodsPriceModel = M('MemberGoodsPrice');
            $memberGoodsPrices = $memberGoodsPriceModel->field('member_level_id,price')->where(array('goods_id'=>$id))->select();
            if(!empty($memberGoodsPrices)){
                //将$memberGoodsPrices中的member_level_id 作为索引, price作为索引的值
                $member_level_ids = array_column($memberGoodsPrices,'member_level_id');
                $prices = array_column($memberGoodsPrices,'price');

                $memberGoodsPrices = array_combine($member_level_ids,$prices);
                $goods['memberGoodsPrices'] = $memberGoodsPrices;
            }

        }

        return $goods;
    }


    /**
     * 需要将请求中的数据更新到 goods表和goods_intro表中
     */
    public function save($requestData){
        //>>1.将$this->data的数据更新到goods表中
        $this->startTrans();
        $result = parent::save();
        if($result===false){
            $this->rollback();
            return false;
        }

        //>>2.将简介数据更新到goods_intro表中
        $result = $this->handleGoodsIntro($requestData['id'],$requestData['intro']);
        if($result===false){
            $this->error = '更新简介信息失败!';
            $this->rollback();
            return false;
        }

        //>>3.将新上传的图片添加到goods_gallery表中
        $result = $this->handlerGallery($requestData['id'],$requestData['gallery_path']);
        if($result===false){
            $this->error = '更新相册失败!';
            $this->rollback();
            return false;
        }


        //>>5.处理商品相关文章
        $result = $this->handleArticle($requestData['id'],$requestData['article_ids']);
        if($result===false){
            $this->error = '更新商品文章失败!';
            $this->rollback();
            return false;
        }


        return $this->commit();
    }
}