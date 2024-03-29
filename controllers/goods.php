<?php
/**
 * @brief 商品模块
 * @class Goods
 * @note  后台
 */
class Goods extends IController implements adminAuthorization
{
	public $checkRight  = 'all';
    public $layout = 'admin';
    public $data = array();

	public function init()
	{

	}
	/**
	 * @brief 商品添加中图片上传的方法
	 */
	public function goods_img_upload()
	{
	 	//调用文件上传类
		$photoObj = new PhotoUpload();
		$result   = current($photoObj->run());
		echo JSON::encode($result);
	}
    /**
	 * @brief 商品模型添加/修改
	 */
    public function model_update()
    {
    	// 获取POST数据
    	$model_id   = IFilter::act(IReq::get("model_id"),'int');
    	$model_name = IFilter::act(IReq::get("model_name"));
    	$attribute  = IFilter::act(IReq::get("attr"));

    	//初始化Model类对象
		$modelObj = new Model();

		//更新模型数据
		$result = $modelObj->model_update($model_id,$model_name,$attribute);

		if($result)
		{
			$this->redirect('model_list');
		}
		else
		{
			//处理post数据，渲染到前台
    		$result = $modelObj->postArrayChange($attribute);
			$this->data = array(
				'id'         => $model_id,
				'name'       => $model_name,
				'model_attr' => $result['model_attr'],
			);
    		$this->setRenderData($this->data);
			$this->redirect('model_edit',false);
		}
    }
	/**
	 * @brief 商品模型修改
	 */
    public function model_edit()
    {
    	// 获取POST数据
    	$id = IFilter::act(IReq::get("id"),'int');
    	if($id)
    	{
    		//初始化Model类对象
    		$modelObj = new Model();
    		//获取模型详细信息
			$model_info = $modelObj->get_model_info($id);
			//向前台渲染数据
			$this->setRenderData($model_info);
    	}
		$this->redirect('model_edit');
    }

	/**
	 * @brief 商品模型删除
	 */
    public function model_del()
    {
    	//获取POST数据
    	$id = IFilter::act(IReq::get("id"),'int');
    	$id = !is_array($id) ? array($id) : $id;

    	if($id)
    	{
	    	foreach($id as $key => $val)
	    	{
	    		//初始化goods_attribute表类对象
	    		$goods_attrObj = new IModel("goods_attribute");

	    		//获取商品属性表中的该模型下的数量
	    		$attrData = $goods_attrObj->query("model_id = ".$val);
	    		if($attrData)
	    		{
	    			$this->redirect('model_list',false);
	    			Util::showMessage("无法删除此模型，请确认该模型下以及回收站内都无商品");
	    		}

	    		//初始化Model表类对象
	    		$modelObj = new IModel("model");

	    		//删除商品模型
				$result = $modelObj->del("id = ".$val);
	    	}
    	}
		$this->redirect('model_list');
    }

	/**
	 * @breif 后台添加为每一件商品添加会员价
	 * */
	function member_price()
	{
		$this->layout = '';

		$goods_id   = IFilter::act(IReq::get('goods_id'),'int');
		$product_id = IFilter::act(IReq::get('product_id'),'int');
		$sell_price = IFilter::act(IReq::get('sell_price'),'float');

		$date = array(
			'sell_price' => $sell_price
		);

		if($goods_id)
		{
			$where  = 'goods_id = '.$goods_id;
			$where .= $product_id ? ' and product_id = '.$product_id : '';

			$priceRelationObject = new IModel('group_price');
			$priceData = $priceRelationObject->query($where);
			$date['price_relation'] = $priceData;
		}

		$this->setRenderData($date);
		$this->redirect('member_price');
	}
	/**
	 * @brief 商品添加和修改视图
	 */
	public function goods_edit()
	{
		$goods_id = IFilter::act(IReq::get('id'),'int');

		//初始化数据
		$goods_class = new goods_class();

		//获取所有商品扩展相关数据
		$data = $goods_class->edit($goods_id);

		if($goods_id && !$data)
		{
			die("没有找到相关商品！");
		}

        if($data)
        {
            $data['type'] = $data['form']['type'];
        }
        else
        {
            $data = array('type' => IReq::get('type') ? IReq::get('type') : "default");
        }
		$this->setRenderData($data);
		$this->redirect('goods_edit');
	}
	/**
	 * @brief 保存修改商品信息
	 */
	function goods_update()
	{
		$id       = IFilter::act(IReq::get('id'),'int');
		$callback = IReq::get('callback');
		$callback = strpos($callback,'goods_list') === false ? '' : $callback;

		//检查表单提交状态
		if(!$_POST)
		{
			die('请确认表单提交正确');
		}

		if($saleRow = Active::isSale($id))
		{
			die('当前商品正处于【营销->特价】中的【'.$saleRow['name'].'】活动中，请先关闭或者删除活动后才能进行修改');
		}

		//初始化商品数据
		unset($_POST['id']);
		unset($_POST['callback']);

		$goodsObject = new goods_class();
		$goodsObject->update($id,$_POST);

		//记录日志
		$logObj = new log('db');
		$logObj->write('operation',array("管理员:".$this->admin['admin_name'],"修改商品信息","商品ID：".$id."，名称：".IFilter::act(IReq::get('name'))));

		$callback ? $this->redirect($callback) : $this->redirect("goods_list");
	}

	/**
	 * @brief 删除商品
	 */
	function goods_del()
	{
		//post数据
	    $id = IFilter::act(IReq::get('id'),'int');

	    //生成goods对象
	    $tb_goods = new IModel('goods');
	    $tb_goods->setData(array('is_del'=>1));
	    if($id)
		{
			$tb_goods->update(Util::joinStr($id));
		}
		else
		{
			die('请选择要删除的数据');
		}
		$this->redirect("goods_list");
	}
	/**
	 * @brief 商品上下架
	 */
	function goods_stats()
	{
		//post数据
	    $id   = IFilter::act(IReq::get('id'),'int');
	    $type = IFilter::act(IReq::get('type'));

	    //生成goods对象
	    $tb_goods = new IModel('goods');
	    if($type == 'up')
	    {
	    	$updateData = array('is_del' => 0,'up_time' => ITime::getDateTime(),'down_time' => null);
	    }
	    else if($type == 'down')
	    {
	    	$updateData = array('is_del' => 2,'up_time' => null,'down_time' => ITime::getDateTime());
	    }
	    else if($type == 'check')
	    {
	    	$updateData = array('is_del' => 3,'up_time' => null,'down_time' => null);
	    }

	    $tb_goods->setData($updateData);

	    if($id)
		{
			$tb_goods->update(Util::joinStr($id));
		}
		else
		{
			Util::showMessage('请选择要操作的数据');
		}

		if(IClient::isAjax() == false)
		{
			$this->redirect("goods_list");
		}
	}
	/**
	 * @brief 商品彻底删除
	 * */
	function goods_recycle_del()
	{
		//post数据
	    $id = IFilter::act(IReq::get('id'),'int');

	    //生成goods对象
	    $goods = new goods_class();
	    if($id)
		{
			if(is_array($id))
			{
				foreach($id as $key => $val)
				{
					$goods->del($val);
				}
			}
			else
			{
				$goods->del($id);
			}
		}

		$this->redirect("goods_recycle_list");
	}
	/**
	 * @brief 商品还原
	 * */
	function goods_recycle_restore()
	{
		//post数据
	    $id = IFilter::act(IReq::get('id'),'int');
	    //生成goods对象
	    $tb_goods = new IModel('goods');
	    $tb_goods->setData(array('is_del'=>0));
	    if($id)
		{
			$tb_goods->update(Util::joinStr($id));
		}
		else
		{
			Util::showMessage('请选择要删除的数据');
		}
		$this->redirect("goods_recycle_list");
	}

	/**
	 * @brief 商品列表
	 */
	function goods_list()
	{
		//搜索条件
		$search = IFilter::act(IReq::get('search'));
		$page   = IReq::get('page') ? IFilter::act(IReq::get('page'),'int') : 1;

		//条件筛选处理
		list($join,$where) = goods_class::getSearchCondition($search);
		$searchString      = http_build_query(array('search' => $search));

		//拼接sql
		$goodsHandle = new IQuery('goods as go');
		$goodsHandle->order  = "go.id desc";
		$goodsHandle->fields = "distinct go.id,go.name,go.sell_price,go.market_price,go.store_nums,go.img,go.is_del,go.seller_id,go.is_share,go.sort,go.promo,go.type,co.point";
		$goodsHandle->page   = $page;
		$goodsHandle->where  = $where;
		$goodsHandle->join   = $join;
		$this->goodsHandle   = $goodsHandle;
		$this->setRenderData(array('search' => $searchString));
		$this->redirect("goods_list");
	}

	//商品导出 Excel
	public function goods_report()
	{
		//搜索条件
		$search = IFilter::act(IReq::get('search'));
		//条件筛选处理
		list($join,$where) = goods_class::getSearchCondition($search);
		//拼接sql
		$goodsHandle = new IQuery('goods as go');
		$goodsHandle->order    = "go.id desc";
		$goodsHandle->fields   = "go.id, go.name,go.sell_price,go.store_nums,go.sale,go.is_del,go.create_time";
		$goodsHandle->join     = $join;
		$goodsHandle->where    = $where;
		$goodsList = $goodsHandle->find();

		//构建 Excel table;
		$reportObj = new report('goods');
		$reportObj->setTitle(array("商品名称","分类","售价","库存","销量","发布时间","状态"));
		foreach($goodsList as $k => $val)
		{
			$insertData = array(
				$val['name'],
				goods_class::getGoodsCategory($val['id']),
				$val['sell_price'],
				$val['store_nums'],
				$val['sale'],
				$val['create_time'],
				goods_class::statusText($val['is_del']),
			);
			$reportObj->setData($insertData);
		}
		$reportObj->toDownload();
	}

	/**
	 * @brief 商品分类添加、修改
	 */
	function category_edit()
	{
		$category_id = IFilter::act(IReq::get('cid'),'int');
		if($category_id)
		{
			$categoryObj = new IModel('category');
			$this->categoryRow = $categoryObj->getObj('id = '.$category_id);
		}
		$this->redirect('category_edit');
	}

	/**
	 * @brief 保存商品分类
	 */
	function category_save()
	{
		//获得post值
		$category_id = IFilter::act(IReq::get('id'),'int');
		$name = IFilter::act(IReq::get('name'));
		$parent_id = IFilter::act(IReq::get('parent_id'),'int');
		$visibility = IFilter::act(IReq::get('visibility'),'int');
		$sort = IFilter::act(IReq::get('sort'),'int');
		$title = IFilter::act(IReq::get('title'));
		$keywords = IFilter::act(IReq::get('keywords'));
		$descript = IFilter::act(IReq::get('descript'));

		$childString = goods_class::catChild($category_id);//父类ID不能死循环设置成其子分类
		if($parent_id > 0 && stripos(",".$childString.",",",".$parent_id.",") !== false)
		{
			$this->redirect('/goods/category_list/_msg/父分类设置错误');
			return;
		}

		$tb_category = new IModel('category');
		$category_info = array(
			'name'      => $name,
			'parent_id' => $parent_id,
			'sort'      => $sort,
			'visibility'=> $visibility,
			'keywords'  => $keywords,
			'descript'  => $descript,
			'title'     => $title
		);
		$tb_category->setData($category_info);
		if($category_id)									//保存修改分类信息
		{
			$where = "id=".$category_id;
			$tb_category->update($where);
		}
		else												//添加新商品分类
		{
			$tb_category->add();
		}
		$this->redirect('category_list');
	}

	/**
	 * @brief 删除商品分类
	 */
	function category_del()
	{
		$category_id = IFilter::act(IReq::get('cid'),'int');
		if($category_id)
		{
			$tb_category = new IModel('category');
			$catRow      = $tb_category->getObj('parent_id = '.$category_id);

			//要删除的分类下还有子节点
			if($catRow)
			{
				$this->category_list();
				Util::showMessage('无法删除此分类，此分类下还有子分类，或者回收站内还留有子分类');
				exit;
			}

			if($tb_category->del('id = '.$category_id))
			{
				$tb_category_extend  = new IModel('category_extend');
				$tb_category_extend->del('category_id = '.$category_id);
				//删除分类手续费
				$categoryRateObj = new IModel('category_rate');
				$categoryRateObj->del('category_id = ' .$category_id);

				$this->redirect('category_list');
			}
			else
			{
				$this->category_list();
				$msg = "没有找到相关分类记录！";
				Util::showMessage($msg);
			}
		}
		else
		{
			$this->category_list();
			$msg = "没有找到相关分类记录！";
			Util::showMessage($msg);
		}
	}

	/**
	 * @brief 商品分类列表
	 */
	function category_list()
	{
		$isCache = false;
		$tb_category = new IModel('category');
		$cacheObj = new ICache('file');
		$data = $cacheObj->get('sortdata');
		if(!$data)
		{
			$goods = new goods_class();
			$data = $goods->sortdata($tb_category->query(false,'*','sort asc'));
			$isCache ? $cacheObj->set('sortdata',$data) : "";
		}
		$this->data['category'] = $data;
		$this->setRenderData($this->data);
		$this->redirect('category_list',false);
	}

	//修改规格页面
	function spec_edit()
	{
		$this->layout = '';

		$id        = IFilter::act(IReq::get('id'),'int');
		$seller_id = IFilter::act(IReq::get('seller_id'),'int');

		$dataRow = array(
			'id'        => '',
			'name'      => '',
			'type'      => '',
			'value'     => '',
			'note'      => '',
			'seller_id' => $seller_id,
		);

		if($id)
		{
			$obj     = new IModel('spec');
			$dataRow = $obj->getObj("id = {$id}");
		}

		$this->setRenderData($dataRow);
		$this->redirect('spec_edit');
	}

	//增加或者修改规格
    function spec_update()
    {
    	$id         = IFilter::act(IReq::get('id'),'int');
    	$name       = IFilter::act(IReq::get('name'));
    	$specType   = IFilter::act(IReq::get('type'));
    	$showValue  = $specType == 1 ? IReq::get('showText') : IFilter::act(IReq::get('showImage'));
    	$note       = IFilter::act(IReq::get('note'));
    	$seller_id  = IFilter::act(IReq::get('seller_id'),'int');
    	$valueData  = IReq::get('valueData');

		//组合规格显示数据和数据实际数据
		$valueData = $valueData ? array_filter($valueData) : "";
		$showValue = $showValue ? array_filter($showValue) : "";
    	$value = $valueData && $showValue && count($valueData) == count($showValue) ? array_filter(array_combine($valueData,$showValue)) : "";

		//要插入的数据
    	if(is_array($value))
    	{
    		$value = $value ? IFilter::act(JSON::encode($value)) : '';
		}

		if(!$value)
		{
			die( JSON::encode(array('flag' => 'fail','message' => '规格值和提示信息不能为空或者0，请填写正确文字')) );
		}

		if(!$name)
		{
			die( JSON::encode(array('flag' => 'fail','message' => '规格名称不能为空')) );
		}

    	$editData = array(
    		'id'        => $id,
    		'name'      => $name,
    		'value'     => $value,
    		'type'      => $specType,
    		'note'      => $note,
    		'seller_id' => $seller_id,
    	);

		//执行操作
		$obj = new IModel('spec');
    	$obj->setData($editData);

    	//更新修改
    	if($id)
    	{
    		$where = 'id = '.$id;
    		if($seller_id)
    		{
    			$where .= ' and seller_id = '.$seller_id;
    		}
    		$result = $obj->update($where);
    	}
    	//添加插入
    	else
    	{
    		$result = $obj->add();
    	}

		//执行状态
    	if($result===false)
    	{
			die( JSON::encode(array('flag' => 'fail','message' => '数据库更新失败')) );
    	}
    	else
    	{
    		//获取自动增加ID 处理返回json便于视图使用
    		$editData['id']    = $id ? $id : $result;
    		$editData['id']    = strval($editData['id']);
    		$editData['value'] = IFilter::stripSlash($editData['value']);
    		die( JSON::encode(array('flag' => 'success','data' => $editData)) );
    	}
    }

	//批量删除规格
    function spec_del()
    {
    	$id = IFilter::act(IReq::get('id'),'int');
		if($id)
		{
			$obj = new IModel('spec');
			$obj->setData(array('is_del'=>1));
			$obj->update(Util::joinStr($id));
			$this->redirect('spec_list');
		}
		else
		{
			$this->redirect('spec_list',false);
			Util::showMessage('请选择要删除的规格');
		}
    }
	//彻底批量删除规格
    function spec_recycle_del()
    {
    	$id = IFilter::act(IReq::get('id'),'int');
		if($id)
		{
			$obj = new IModel('spec');
			$obj->del(Util::joinStr($id));
			$this->redirect('spec_recycle_list');
		}
		else
		{
			$this->redirect('spec_recycle_list',false);
			Util::showMessage('请选择要删除的规格');
		}
    }
	//批量还原规格
    function spec_recycle_restore()
    {
    	$id = IFilter::act(IReq::get('id'),'int');
		if($id)
		{
			$obj = new IModel('spec');
			$obj->setData(array('is_del'=>0));
			$obj->update(Util::joinStr($id));
			$this->redirect('spec_recycle_list');
		}
		else
		{
			$this->redirect('spec_recycle_list',false);
			Util::showMessage('请选择要还原的规格');
		}
    }
    //规格图片删除
    function spec_photo_del()
    {
    	$id = IReq::get('id','post');
    	if(isset($id[0]) && $id[0]!='')
    	{
    		$obj = new IModel('spec_photo');
    		$id_str = '';
    		foreach($id as $rs)
    		{
    			if($id_str!='')
    			{
    				$id_str.=',';
    			}
    				$id_str.=$rs;

    			$photoRow = $obj->getObj('id = '.$rs,'address');
    			if(file_exists($photoRow['address']))
    			{
    				unlink($photoRow['address']);
    			}
    		}

	    	$where = ' id in ('.$id_str.')';
	    	$obj->del($where);
	    	$this->redirect('spec_photo');
    	}
    	else
    	{
    		$this->redirect('spec_photo',false);
    		Util::showMessage('请选择要删除的id值');
    	}
    }

	/**
	 * @brief 分类排序
	 */
	function category_sort()
	{
		$category_id = IFilter::act(IReq::get('id'),'int');
		$sort = IFilter::act(IReq::get('sort'),'int');

		$flag = 0;
		if($category_id)
		{
			$tb_category = new IModel('category');
			$category_info = $tb_category->getObj('id='.$category_id);
			if(count($category_info)>0)
			{
				if($category_info['sort']!=$sort)
				{
					$tb_category->setData(array('sort'=>$sort));
					if($tb_category->update('id='.$category_id))
					{
						$flag = 1;
					}
				}
			}
		}
		echo $flag;
	}
	/**
	 * @brief 品牌分类排序
	 */
	public function brand_sort()
	{
		$brand_id = IFilter::act(IReq::get('id'),'int');
		$sort = IFilter::act(IReq::get('sort'),'int');
		$flag = 0;
		if($brand_id)
		{
			$tb_brand = new IModel('brand');
			$brand_info = $tb_brand->getObj('id='.$brand_id);
			if(count($brand_info)>0)
			{
				if($brand_info['sort']!=$sort)
				{
					$tb_brand->setData(array('sort'=>$sort));
					if($tb_brand->update('id='.$brand_id))
					{
						$flag = 1;
					}
				}
			}
		}
		echo $flag;
	}

	//修改排序
	public function ajax_sort()
	{
		$id   = IFilter::act(IReq::get('id'),'int');
		$sort = IFilter::act(IReq::get('sort'),'int');

		$goodsDB = new IModel('goods');
		$goodsDB->setData(array('sort' => $sort));
		$goodsDB->update("id = {$id}");
	}

	//更新库存
	public function update_store()
	{
		$data     = IFilter::act(IReq::get('data'),'int'); //key => 商品ID或货品ID ; value => 库存数量
		$goods_id = IFilter::act(IReq::get('goods_id'),'int');//存在即为货品
		$goodsSum = array_sum($data);

		if(!$data)
		{
			die(JSON::encode(array('result' => 'fail','data' => '商品数据不存在')));
		}

		//货品方式
		if($goods_id)
		{
			$productDB = new IModel('products');
			foreach($data as $key => $val)
			{
				$productDB->setData(array('store_nums' => $val));
				$productDB->update('id = '.$key);
			}
		}
		else
		{
			$goods_id = key($data);
		}

		$goodsDB = new IModel('goods');
		$goodsDB->setData(array('store_nums' => $goodsSum));
		$goodsDB->update('id = '.$goods_id);

		die(JSON::encode(array('result' => 'success','data' => $goodsSum)));
	}

	//更新商品价格
	public function update_price()
	{
		$data     = IFilter::act(IReq::get('data')); //key => 商品ID或货品ID ; value => 库存数量
		$goods_id = IFilter::act(IReq::get('goods_id'),'int');//存在即为货品

		if(!$data)
		{
			die(JSON::encode(array('result' => 'fail','data' => '商品数据不存在')));
		}

		//货品方式
		if($goods_id)
		{
			$productDB  = new IModel('products');
			$updateData = current($data);
			foreach($data as $pid => $item)
			{
				$productDB->setData($item);
				$productDB->update("id = ".$pid);
			}
		}
		else
		{
			$goods_id   = key($data);
			$updateData = current($data);
		}

		$goodsDB = new IModel('goods');

		if($saleRow = Active::isSale($goods_id))
		{
			$goodsDB->rollback();
			die(JSON::encode(array('result' => 'fail','data' => '当前商品正处于【营销->特价】中的【'.$saleRow['name'].'】活动中，请先关闭或者删除活动后才能进行修改')));
		}

		$goodsDB->setData($updateData);
		$goodsDB->update('id = '.$goods_id);

		die(JSON::encode(array('result' => 'success','data' => number_format($updateData['sell_price'],2))));
	}

	//更新商品推荐标签
	public function update_commend()
	{
		$data = IFilter::act(IReq::get('data')); //key => 商品ID或货品ID ; value => commend值 1~4
		if(!$data)
		{
			die(JSON::encode(array('result' => 'fail','data' => '商品数据不存在')));
		}

		$goodsCommendDB = new IModel('commend_goods');

		//清理旧的commend数据
		$goodsIdArray = array_keys($data);
		$goodsCommendDB->del("goods_id in (".join(',',$goodsIdArray).")");

		//插入新的commend数据
		foreach($data as $id => $commend)
		{
			foreach($commend as $k => $value)
			{
				if($value > 0)
				{
					$goodsCommendDB->setData(array('commend_id' => $value,'goods_id' => $id));
					$goodsCommendDB->add();
				}
			}
		}
		die(JSON::encode(array('result' => 'success')));
	}

	//商品共享
	public function goods_share()
	{
		$idArray = explode(',',IReq::get('id'));
		$id      = IFilter::act($idArray,'int');

		$goodsDB = new IModel('goods');
		$goodsData = $goodsDB->query('id in ('.join(',',$id).')');

		foreach($goodsData as $key => $val)
		{
			$is_share = $val['is_share'] == 1 ? 0 : 1;
			$goodsDB->setData(array('is_share' => $is_share));
			$goodsDB->update('id = '.$val['id'].' and seller_id = 0');
		}
	}

	/**
	 * @brief 商品批量设置
	 */
	function goods_setting()
	{
		$idArray   = explode(',',IReq::get('id'));
		$id        = IFilter::act($idArray,'int');
		$seller_id = IFilter::act(IReq::get('seller_id'),'int');

		if (empty($id))
		{
			exit('请选择您要操作的商品');
		}
		$data = array();
		$data['goods_id']  = implode(",", $id);
		$data['seller_id'] = $seller_id;

		$this->layout = '';
		$this->setRenderData($data);
		$this->redirect('goods_setting');
	}

	/**
	 * @brief 保存商品批量设置
	 */
	function goods_setting_save()
	{
		$idArray   = explode(',',IReq::get('goods_id', 'post'));
		$seller_id = IFilter::act(IReq::get('seller_id'),'int');
		$idArray   = IFilter::act($idArray,'int');

		if (empty($idArray))
		{
			exit('请首先选择您要操作的商品');
		}

		$goodsObject = new goods_class($seller_id);
		$goodsObject->multiUpdate($idArray, $_POST);
		die('<script type="text/javascript">parent.artDialogCallback();</script>');
	}
	/**
	 * @brief 商品分类ajax调整
	 */
	public function categoryAjax()
	{
		$id        = IFilter::act(IReq::get('id'),'int');
		$parent_id = IFilter::act(IReq::get('parent_id'),'int');
		if($id && is_array($id))
		{
			foreach($id as $category_id)
			{
				$childString = goods_class::catChild($category_id);//父类ID不能死循环设置成其子分类
				if($parent_id > 0 && stripos(",".$childString.",",",".$parent_id.",") !== false)
				{
					die(JSON::encode(array('result' => 'fail')));
				}
			}

			$catDB     = new IModel('category');
			$catDB->setData(array('parent_id' => $parent_id));
			$result = $catDB->update('id in ('.join(",",$id).')');
			if($result)
			{
				die(JSON::encode(array('result' => 'success')));
			}
		}
		die(JSON::encode(array('result' => 'fail')));
	}

	//商品筛选页面
	function search()
	{
		$this->setRenderData($_GET);
		$this->redirect('search');
	}

	//列出筛选商品
	function search_result()
	{
		//搜索条件
		$search      = IFilter::act(IReq::get('search'));
		$page        = IReq::get('page') ? IFilter::act(IReq::get('page'),'int') : 1;
		$is_products = IFilter::act(IReq::get('is_products'));
		$type        = IReq::get('type') == "checkbox" ? "checkbox" : "radio";

		//条件筛选处理
		list($join,$where) = goods_class::getSearchCondition($search);

		$goodsHandle = new IQuery('goods as go');
		$goodsHandle->order  = "go.id desc";
		$goodsHandle->fields = "distinct go.id as goods_id,go.name,go.img,go.store_nums,go.goods_no,go.sell_price,go.spec_array";
		$goodsHandle->limit  = 20;
		$goodsHandle->where  = $where;
		$goodsHandle->join   = $join;
		$data = $goodsHandle->find();

		//包含货品信息
		if($is_products && $data)
		{
			$goodsIdArray = array();
			foreach($data as $key => $val)
			{
				//有规格有货品
				if($val['spec_array'])
				{
					$goodsIdArray[$key] = $val['goods_id'];
					unset($data[$key]);
				}
			}

			if($goodsIdArray)
			{
				$productsDB        = new IQuery('products as pro');
				$productsDB->join  = "left join goods as go on go.id = pro.goods_id";
				$productsDB->where = "pro.goods_id in (".join(',',$goodsIdArray).")";
				$productsDB->fields="pro.goods_id,go.name,go.img,pro.id as product_id,pro.products_no as goods_no,pro.spec_array,pro.sell_price,pro.store_nums";
				$productDate       = $productsDB->find();
				$data              = array_merge($data,$productDate);
			}
		}

		$this->goodsData = $data;
		$this->type      = $type;
		$this->redirect('search_result');
	}
	/**
	 * @brief 单品手续费添加、修改
	 */
	public function goods_rate_edit()
	{
	    $id = IFilter::act(IReq::get('id'),'int');
	    if($id)
	    {
	        $goodsRateObj = new IModel('goods_rate');
	        $where        = 'goods_id = '.$id;
	        $goodsRateRow = $goodsRateObj->getObj($where);
	        if(!$goodsRateRow)
	        {
	            die("要查看的数据信息不存在");
	        }
	        //商品信息
	        $goodsObj = new IModel('goods');
	        $goodsRow = $goodsObj->getObj('id = '.$goodsRateRow['goods_id']);
	        $result = array(
	            'isError' => false,
	            'data'    => $goodsRow,
	        );
	        $goodsRateRow['goodsRow'] = JSON::encode($result);
	        $this->goodsRateRow = $goodsRateRow;
	    }
	    $this->redirect('goods_rate_edit');
	}
	/**
	 * 保存单品手续费
	 */
	public function goods_rate_save()
	{
	    $goods_id = IFilter::act(IReq::get('goods_id'),'int');
	    $goods_rate = IFilter::act(IReq::get('goods_rate'),'float');

	    $dataArray = array(
	        'goods_id' => $goods_id,
	        'goods_rate' => $goods_rate,
	        'goodsRow' => null,
	    );

	    if ($goods_id)
	    {
	        //商品信息
	        $goodsObj = new IModel('goods');
	        $goodsRow = $goodsObj->getObj('id = '.$goods_id);
	        $result = array(
	            'isError' => false,
	            'data'    => $goodsRow,
	        );
	        $dataArray['goodsRow'] = JSON::encode($result);
	    }
	    else
	    {
	        $this->goodsRateRow = $dataArray;
	        $this->redirect('goods_rate_edit', false);
	        Util::showMessage('请选择商品');
	    }

	    if (0 > $goods_rate || 100 < $goods_rate)
	    {
	        $this->goodsRateRow = $dataArray;
	        $this->redirect('goods_rate_edit', false);
	        Util::showMessage('单品手续费请填写0~100的数字');
	    }

	    $goodsRateObj = new IModel('goods_rate');
	    $goodsRateArray = array(
	        'goods_id' => $goods_id,
	        'goods_rate' => $goods_rate,
	    );
	    $goodsRateObj->setData($goodsRateArray);
	    $goodsRateObj->replace();

	    $this->redirect('goods_rate_list');
	}
	/**
	 * 删除单品手续费
	 */
	public function goods_rate_del()
	{
	    $ids = IFilter::act(IReq::get('check'),'int');
	    $ids = is_array($ids) ? $ids : array($ids);
	    if ($ids)
	    {
	        $ids = implode(',', $ids);
	        $goodsRateObj = new IModel('goods_rate');
	        $where        = 'goods_id in(' .$ids. ')';
	        $goodsRateObj->del($where);
	    }
	    $this->redirect('goods_rate_list');
	}
	/**
	 * @brief 分类手续费添加、修改
	 */
	public function category_rate_edit()
	{
	    $id = IFilter::act(IReq::get('id'),'int');
	    if($id)
	    {
	        $categoryRateObj = new IModel('category_rate');
	        $where        = 'category_id = '.$id;
	        $categoryRateRow = $categoryRateObj->getObj($where);
	        if(!$categoryRateRow)
	        {
	            die("要查看的数据信息不存在");
	        }
	        $this->categoryRateRow = $categoryRateRow;
	    }
	    $this->redirect('category_rate_edit');
	}
	/**
	 * 保存分类手续费
	 */
	public function category_rate_save()
	{
	    $category_id = IFilter::act(IReq::get('category_id'),'int');
	    $category_rate = IFilter::act(IReq::get('category_rate'),'float');

	    $dataArray = array(
	        'category_id' => $category_id,
	        'category_rate' => $category_rate,
	    );
	    // 分类信息
	    $categoryObj = new IModel('category');
	    $categoryRow = $categoryObj->getObj('id = '.$category_id);
	    if (!$categoryRow)
	    {
	        $this->categoryRateRow = $dataArray;
	        $this->redirect('category_rate_edit', false);
	        Util::showMessage('请选择分类');
	    }

	    if (0 > $category_rate || 100 < $category_rate)
	    {
	        $this->categoryRateRow = $dataArray;
	        $this->redirect('category_rate_edit', false);
	        Util::showMessage('分类手续费请填写0~100的数字');
	    }

	    $categoryRateObj = new IModel('category_rate');
	    $categoryRateObj->setData($dataArray);
	    $categoryRateObj->replace();

	    $this->redirect('category_rate_list');
	}
	/**
	 * 删除分类手续费
	 */
	public function category_rate_del()
	{
	    $ids = IFilter::act(IReq::get('check'),'int');
	    $ids = is_array($ids) ? $ids : array($ids);
	    if ($ids)
	    {
	        $ids = implode(',', $ids);
	        $categoryRateObj = new IModel('category_rate');
	        $where           = 'category_id in(' .$ids. ')';
	        $categoryRateObj->del($where);
	    }
	    $this->redirect('category_rate_list');
	}
}
