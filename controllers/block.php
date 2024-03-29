<?php
/**
 * @brief 公共模块
 * @class Block
 */
class Block extends IController
{
	public $layout='';

	public function init()
	{

	}

 	/**
	 * @brief Ajax获取规格值
	 */
	function spec_value_list()
	{
		// 获取POST数据
		$spec_id = IFilter::act(IReq::get('id'),'int');

		//初始化spec商品模型规格表类对象
		$specObj = new IModel('spec');
		//根据规格编号 获取规格详细信息
		$specData = $specObj->getObj("id = ".$spec_id." and is_del = 0");
		if($specData)
		{
			echo JSON::encode($specData);
		}
		else
		{
			//返回失败标志
			echo '';
		}
	}

	//列出筛选商品
	function products_list()
	{
		$goods_id    = IFilter::act( IReq::get('goods_id'),'int');
		$productDB   = new IModel('products');
		$productData = $productDB->query('goods_id = '.$goods_id);
		$this->data  = $productData;
		$this->redirect('products_list');
	}
	/**
	 * @brief 获取地区
	 */
	public function area_child()
	{
		$parent_id = intval(IReq::get("aid"));
		$areaDB    = new IModel('areas');
		$data      = $areaDB->query("parent_id=$parent_id",'*','sort asc');
		echo JSON::encode($data);
	}

	//[公共方法]通过解析products,goods表中的spec_array转化为格式：key:规格名称;value:规格值
    public static function show_spec($specJson,$param = array())
    {
    	$specArray = JSON::decode($specJson);
    	$spec      = array();
    	if($specArray)
    	{
    		$imgSize = isset($param['size']) ? $param['size'] : 20;
	    	foreach($specArray as $val)
	    	{
	    		//goods表规格数据
	    		if(is_array($val['value']))
	    		{
	    			foreach($val['value'] as $tip => $sval)
	    			{
	    				if(!isset($spec[$val['name']]))
	    				{
	    					$spec[$val['name']] = array();
	    				}

	    				list($tip,$specVal) = each($sval);

			    		if($val['type'] == 1)
			    		{
			    			$spec[$val['name']][] = $specVal;
			    		}
			    		else
			    		{
			    			$spec[$val['name']][] = strlen($tip) >= 3 ? $tip : '<img src="'.IUrl::creatUrl($specVal).'" style="border: 1px solid #ddd;width:'.$imgSize.'px;height:'.$imgSize.'px;" title="'.$tip.'" />';
			    		}
	    			}
	    			$spec[$val['name']] = join("&nbsp;&nbsp;",$spec[$val['name']]);
	    		}
	    		//goods表老版本格式逗号分隔符
	    		else if(strpos($val['value'],",") && $val['value'] = explode(",",$val['value']))
	    		{
	    			foreach($val['value'] as $tip => $sval)
	    			{
	    				if(!isset($spec[$val['name']]))
	    				{
	    					$spec[$val['name']] = array();
	    				}

			    		if($val['type'] == 1)
			    		{
			    			$spec[$val['name']][] = $sval;
			    		}
			    		else
			    		{
			    			$spec[$val['name']][] = '<img src="'.IUrl::creatUrl($sval).'" style="border: 1px solid #ddd;width:'.$imgSize.'px;height:'.$imgSize.'px;" />';
			    		}
	    			}
	    			$spec[$val['name']] = join("&nbsp;&nbsp;",$spec[$val['name']]);
	    		}
	    		//products表规格数据
	    		else
	    		{
		    		if($val['type'] == 1)
		    		{
		    			$spec[$val['name']] = $val['value'];
		    		}
		    		else
		    		{
		    			$tip = isset($val['tip']) ? $val['tip'] : "";
		    			$spec[$val['name']] = strlen($tip) >= 3 ? $tip : '<img src="'.IUrl::creatUrl($val['value']).'" style="border: 1px solid #ddd;width:'.$imgSize.'px;height:'.$imgSize.'px;" title="'.$tip.'" />';
		    		}
	    		}
	    	}
    	}
    	return $spec;
    }

	/**
	 * @brief 获得配送方式ajax
	 */
	public function order_delivery()
    {
    	$productId    = IFilter::act(IReq::get("productId"),'int');
    	$goodsId      = IFilter::act(IReq::get("goodsId"),'int');
    	$province     = IFilter::act(IReq::get("province"),'int');
    	$distribution = IFilter::act(IReq::get("distribution"),'int');
    	$num          = IReq::get("num") ? IFilter::act(IReq::get("num"),'int') : 1;
		$data         = array();
		if($distribution)
		{
			$data = Delivery::getDelivery($province,$distribution,$goodsId,$productId,$num);
		}
		else
		{
			$delivery     = new IModel('delivery');
			$deliveryList = $delivery->query('is_delete = 0 and status = 1');
			foreach($deliveryList as $key => $item)
			{
				$data[$item['id']] = Delivery::getDelivery($province,$item['id'],$goodsId,$productId,$num);
			}
		}
    	echo JSON::encode($data);
    }
	/**
    * @brief 【重要】进行支付支付方法
    */
	public function doPay()
	{
		//获得相关参数
		$order_id   = IReq::get('order_id');
		$recharge   = IReq::get('recharge');
		$payment_id = IFilter::act(IReq::get('payment_id'),'int');

		if($order_id)
		{
			$order_id = explode("_",IReq::get('order_id'));
			$order_id = IFilter::act($order_id,'int');

			//获取订单信息
			$orderDB  = new IModel('order');
			$orderRow = $orderDB->getObj('id = '.current($order_id));

			if(empty($orderRow))
			{
				IError::show(403,'要支付的订单信息不存在');
			}

			//判断订单是否已经支付成功了
			if($orderRow['pay_status'] == 1)
			{
				plugin::trigger('setCallback','/ucenter/order');
				$this->redirect('/site/success/message/'.urlencode("订单已经支付成功"));
				return;
			}

			//更新支付方式
			if($payment_id)
			{
			    $orderDB->setData(['pay_type' => $payment_id]);
			    foreach($order_id as $id)
			    {
			        $orderDB->update($id);
			    }
			}
			else
			{
			    $payment_id = $orderRow['pay_type'];
			}
		}

		//获取支付方式类库
		$paymentInstance = Payment::createPaymentInstance($payment_id);

		//在线充值
		if($recharge !== null)
		{
			$recharge   = IFilter::act($recharge,'float');
			$paymentRow = Payment::getPaymentById($payment_id);

			//account:充值金额; paymentName:支付方式名字
			$reData   = array('account' => $recharge , 'paymentName' => $paymentRow['name']);
			$sendData = $paymentInstance->getSendData(Payment::getPaymentInfo($payment_id,'recharge',$reData));
		}
		//订单支付
		else if($order_id)
		{
			$sendData = $paymentInstance->getSendData(Payment::getPaymentInfo($payment_id,'order',$order_id));
		}
		else
		{
			IError::show(403,'发生支付错误');
		}

		$paymentInstance->doPay($sendData);
	}

	/**
     * @brief 【重要】支付回调[同步]
	 */
	public function callback()
	{
		//从URL中获取支付方式
		$payment_id      = IFilter::act(IReq::get('_id'),'int');
		$paymentInstance = Payment::createPaymentInstance($payment_id);

		if(!is_object($paymentInstance))
		{
			IError::show(403,'支付方式不存在');
		}

		//初始化参数
		$money   = '';
		$message = '支付失败';
		$orderNo = '';

		//执行接口回调函数
		$callbackData = array_merge($_POST,$_GET);
		unset($callbackData['controller']);
		unset($callbackData['action']);
		unset($callbackData['_id']);
		$return = $paymentInstance->callback($callbackData,$payment_id,$money,$message,$orderNo);

		//支付成功
		if($return == 1)
		{
			//充值方式
			if(stripos($orderNo,'recharge') !== false)
			{
				$tradenoArray = explode('recharge',$orderNo);
				$recharge_no  = isset($tradenoArray[1]) ? $tradenoArray[1] : 0;
				if(payment::updateRecharge($recharge_no))
				{
					plugin::trigger('setCallback','/ucenter/account_log');
					$this->redirect('/site/success?message='.urlencode("充值成功"));
					return;
				}
				IError::show(403,'充值失败');
			}
			else
			{
				//订单批量结算缓存机制
				$moreOrder = Order_Class::getBatch($orderNo);
				if($money >= array_sum($moreOrder))
				{
					foreach($moreOrder as $key => $item)
					{
						$order_id = Order_Class::updateOrderStatus($key);
						if(!$order_id)
						{
							IError::show(403,'订单修改失败');
						}
					}
					plugin::trigger('setCallback','/ucenter/order');
					$this->redirect('/site/success/message/'.urlencode("支付成功"));
					return;
				}
				$message = '付款金额与订单金额不符合';
			}
		}
		//支付失败
		$message = $message ? $message : '支付失败';
		IError::show(403,$message);
	}

	/**
     * @brief 【重要】支付回调[异步]
	 */
	function server_callback()
	{
		//从URL中获取支付方式
		$payment_id      = IFilter::act(IReq::get('_id'),'int');
		$paymentInstance = Payment::createPaymentInstance($payment_id);

		if(!is_object($paymentInstance))
		{
			die('fail');
		}

		//初始化参数
		$money   = '';
		$message = '支付失败';
		$orderNo = '';

		//执行接口回调函数
		$callbackData = array_merge($_POST,$_GET);
		unset($callbackData['controller']);
		unset($callbackData['action']);
		unset($callbackData['_id']);
		$return = $paymentInstance->serverCallback($callbackData,$payment_id,$money,$message,$orderNo);

		//支付成功
		if($return == 1)
		{
			//充值方式
			if(stripos($orderNo,'recharge') !== false)
			{
				$tradenoArray = explode('recharge',$orderNo);
				$recharge_no  = isset($tradenoArray[1]) ? $tradenoArray[1] : 0;
				if(payment::updateRecharge($recharge_no))
				{
					$paymentInstance->notifyStop();
				}
			}
			else
			{
				//订单批量结算缓存机制
				$moreOrder = Order_Class::getBatch($orderNo);
				if($money >= array_sum($moreOrder))
				{
					foreach($moreOrder as $key => $item)
					{
						$order_id = Order_Class::updateOrderStatus($key);
						if(!$order_id)
						{
							throw new IException("异步支付回调修改状态错误，订单ID：".$order_id);
						}
					}
					$paymentInstance->notifyStop();
				}
			}
		}
		//支付失败
		else
		{
			die('fail');
		}
	}

	/**
     * @brief 【重要】支付中断处理
	 */
	public function merchant_callback()
	{
		$this->redirect('/ucenter/order');
	}

	/**
    * @brief 根据省份名称查询相应的province
    */
	public function searchProvince()
	{
		$province = IFilter::act(IReq::get('province'));

		$tb_areas = new IModel('areas');
		$areas_info = $tb_areas->getObj('parent_id = 0 and area_name like "'.$province.'%"','area_id');
		$result = array('flag' => 'fail','area_id' => 0);
		if($areas_info)
		{
			$result = array('flag' => 'success','area_id' => $areas_info['area_id']);
		}
		echo JSON::encode($result);
	}
    //添加实体优惠券
    function add_download_ticket()
    {
    	$isError = true;

    	$ticket_num = IFilter::act(IReq::get('ticket_num'));
    	$ticket_pwd = IFilter::act(IReq::get('ticket_pwd'));

		//优惠券状态是否正常
    	$propObj = new IModel('prop');
    	$propRow = $propObj->getObj('card_name = "'.$ticket_num.'" and card_pwd = "'.$ticket_pwd.'" and type = 0 and is_userd = 0 and is_send = 1 and is_close = 0 and NOW() between start_time and end_time');
    	if(!$propRow)
    	{
    		$message = '优惠券不可用，请确认优惠券的卡号密码并且此优惠券在有效期内从未被使用过';
	    	$result = array(
	    		'isError' => $isError,
	    		'message' => $message,
	    	);
	    	die(JSON::encode($result));
    	}

    	//优惠券是否已经被领取
    	$memberObj = new IModel('member');
		$isRev     = $memberObj->query('FIND_IN_SET('.$propRow['id'].',prop)');
		if($isRev)
		{
    		$message = '优惠券已经被领取';
	    	$result = array(
	    		'isError' => $isError,
	    		'message' => $message,
	    	);
	    	die(JSON::encode($result));
		}

		//登录用户
		$isError = false;
		$message = '添加成功';
		if($this->user['user_id'])
		{
    		$memberRow = $memberObj->getObj('user_id = '.$this->user['user_id'],'prop');
    		if($memberRow['prop'] == '')
    		{
    			$propUpdate = ','.$propRow['id'].',';
    		}
    		else
    		{
    			$propUpdate = $memberRow['prop'].$propRow['id'].',';
    		}

    		$dataArray = array('prop' => $propUpdate);
    		$memberObj->setData($dataArray);
    		$memberObj->update('user_id = '.$this->user['user_id']);
		}
		//游客方式
		else
		{
			ISafe::set("ticket_".$propRow['id'],$propRow['id']);
		}

    	$result = array(
    		'isError' => $isError,
    		'data'    => $propRow,
    		'message' => $message,
    	);

    	die(JSON::encode($result));
    }

	/*
	 * @breif ajax添加商品扩展属性
	 * */
	function attribute_init()
	{
		$id = IFilter::act(IReq::get('model_id'),'int');
		$tb_attribute = new IModel('attribute');
		$attribute_info = $tb_attribute->query('model_id='.$id);
		echo JSON::encode($attribute_info);
	}

	//获取商品数据
	public function getGoodsData()
	{
		$id = IFilter::act(IReq::get('id'),'int');

		$productDB = new IQuery('products as p');
		$productDB->join  = 'left join goods as go on go.id = p.goods_id';
		$productDB->where = 'go.id = '.$id;
		$productDB->fields= 'p.*,go.name';
		$productData = $productDB->find();

		if(!$productData)
		{
			$goodsDB   = new IModel('goods');
			$productData = $goodsDB->query('id = '.$id);
		}
		echo JSON::encode($productData);
	}

	//获取商品的推荐标签数据
	public function goodsCommend()
	{
		//商品字符串的逗号间隔
		$id = IFilter::act(IReq::get('id'));
		if($id)
		{
			$idArray = explode(",",$id);
			$idArray = IFilter::act($idArray,'int');
			$id = join(',',$idArray);
		}

		if(!$id)
		{
		    return;
		}

		$goodsDB = new IModel('goods');
		$goodsData = $goodsDB->query("id in (".$id.")","id,name");

		$goodsCommendDB = new IModel('commend_goods');
		foreach($goodsData as $key => $val)
		{
			$goodsCommendData = $goodsCommendDB->query("goods_id = ".$val['id']);
			foreach($goodsCommendData as $k => $v)
			{
				$goodsData[$key]['commend'][$v['commend_id']] = 1;
			}
		}
		die(JSON::encode($goodsData));
	}

	//获取自提点数据
	public function getTakeselfList()
	{
		$id   = IFilter::act(IReq::get('id'),'int');
		$type = IFilter::act(IReq::get('type'));
		$takeselfObj = new IQuery('takeself');
		$where = "";
		switch($type)
		{
			case "province":
			{
				$where = "province = ".$id;
				$takeselfObj->group = 'city';
			}
			break;

			case "city":
			{
				$where = "city = ".$id;
				$takeselfObj->group = 'area';
			}
			break;

			case "area":
			{
				$where = "area = ".$id;
			}
			break;
		}

		$takeselfObj->where = $where;
		$takeselfList = $takeselfObj->find();

		foreach($takeselfList as $key => $val)
		{
			$temp = area::name($val['province'],$val['city'],$val['area']);
			$takeselfList[$key]['province_str'] = $temp[$val['province']];
			$takeselfList[$key]['city_str']     = $temp[$val['city']];
			$takeselfList[$key]['area_str']     = $temp[$val['area']];
		}
		die(JSON::encode($takeselfList));
	}

	//物流轨迹查询,2种参数形式：(1)发货单ID; (2)物流公司编号+快递单号
	public function freight()
	{
		$id   = IFilter::act(IReq::get('id'),'int');
		$code = IFilter::act(IReq::get('code'));

		if($id)
		{
		    if($code)
		    {
		        $db  = new IModel('freight_company');
		        $row = $db->getObj($id);
		        $freightData = [["freight_type" => $row['freight_type'],"delivery_code" => $code]];
		    }
		    else
		    {
    			$tb_freight = new IQuery('delivery_doc as d');
    			$tb_freight->join  = 'left join freight_company as f on f.id = d.freight_id';
    			$tb_freight->where = 'd.id = '.$id;
    			$tb_freight->fields= 'd.*,f.freight_type';
    			$freightData = $tb_freight->find();
		    }

			if($freightData)
			{
				$freightData = current($freightData);
				if($freightData['freight_type'] && $freightData['delivery_code'])
				{
					$result = freight_facade::line($freightData['freight_type'],$freightData['delivery_code']);
					if($result['result'] == 'success')
					{
						$this->data = $result['data'];
						$this->redirect('freight');
						return;
					}
					else
					{
						die(isset($result['reason']) ? $result['reason'] : '物流接口发生错误');
					}
				}
				else
				{
					die('缺少物流信息');
				}
			}
		}
		die('发货单信息不存在');
	}

	//收货地址弹出框
    public function address()
    {
    	$user_id = $this->user['user_id'];
    	$id      = IFilter::act(IReq::get('id'),'int');
    	if($user_id)
    	{
    		if($id)
    		{
	    		$addressDB        = new IModel('address');
	    		$this->addressRow = $addressDB->getObj('user_id = '.$user_id.' and id = '.$id);
    		}
    	}
    	else
    	{
			$this->addressRow = ISafe::get('address');
    	}
    	$this->redirect('address');
    }

	//添加地址ajax
	function address_add()
	{
		$id          = IFilter::act(IReq::get('id'),'int');
		$accept_name = IFilter::act(IReq::get('accept_name'),'name');
		$province    = IFilter::act(IReq::get('province'),'int');
		$city        = IFilter::act(IReq::get('city'),'int');
		$area        = IFilter::act(IReq::get('area'),'int');
		$address     = IFilter::act(IReq::get('address'));
		$zip         = IFilter::act(IReq::get('zip'));
		$telphone    = IFilter::act(IReq::get('telphone'));
		$mobile      = IFilter::act(IReq::get('mobile'));
        $user_id     = $this->user['user_id'];

		//整合的数据
        $sqlData = array(
        	'user_id'     => $user_id,
        	'accept_name' => $accept_name,
        	'zip'         => $zip,
        	'telphone'    => $telphone,
        	'province'    => $province,
        	'city'        => $city,
        	'area'        => $area,
        	'address'     => $address,
        	'mobile'      => $mobile,
        );

        $checkArray = $sqlData;
        unset($checkArray['telphone'],$checkArray['zip'],$checkArray['user_id']);
        foreach($checkArray as $key => $val)
        {
        	if(!$val)
        	{
        		$result = array('result' => false,'msg' => '请完整填写收件信息');
				die(JSON::encode($result));
        	}
        }

        if($user_id)
        {
        	$model = new IModel('address');
        	$model->setData($sqlData);
        	if($id)
        	{
        		$model->update("id = ".$id." and user_id = ".$user_id);
        	}
        	else
        	{
        		$id = $model->add();
        	}
        }
        //访客地址保存
        else
        {
        	ISafe::set("address",$sqlData);
        }

        $areaList = area::name($province,$city,$area);
        $sqlData['id']           = $id;
		$sqlData['province_str'] = $areaList[$province];
		$sqlData['city_str']     = $areaList[$city];
		$sqlData['area_str']     = $areaList[$area];
		$result = array('result' => true,'data' => $sqlData);
		die(JSON::encode($result));
	}

    //优惠券弹出框
    public function ticket()
    {
		$this->prop       = array();
		$this->sellerInfo = IFilter::act(IReq::get('sellerString'));
		$user_id          = $this->user['user_id'];

		//获取优惠券
		if($user_id)
		{
			$memberObj = new IModel('member');
			$memberRow = $memberObj->getObj('user_id = '.$user_id,'prop');

			if(isset($memberRow['prop']) && ($propId = trim($memberRow['prop'],',')))
			{
				$porpObj = new IModel('prop');
				$this->prop = $porpObj->query('id in ('.$propId.') and NOW() between start_time and end_time and type = 0 and is_close = 0 and is_userd = 0 and is_send = 1');
			}
		}
		$this->redirect('ticket');
    }

	//推送接受物流信息
    public function deliveryCallback()
    {
		$result = freight_facade::subCallback($_POST);
    	if($result === true)
    	{
    		die(JSON::encode(array("Success" => true)));
    	}
    }

	//发票管理页面
    public function invoice()
    {
    	$user_id = $this->user['user_id'];
    	$id      = IFilter::act(IReq::get('id'),'int');
    	if($user_id)
    	{
    		if($id)
    		{
	    		$invoiceDB        = new IModel('invoice');
	    		$this->invoiceRow = $invoiceDB->getObj('user_id = '.$user_id.' and id = '.$id);
    		}
    	}
    	else
    	{
			$this->invoiceRow = ISafe::get('invoice');
    	}
    	$this->redirect('invoice');
    }

	//发票信息ajax
	function invoice_add()
	{
		$id           = IFilter::act(IReq::get('id'),'int');
		$company_name = IFilter::act(IReq::get('company_name'));
		$address      = IFilter::act(IReq::get('address'));
		$taxcode      = IFilter::act(IReq::get('taxcode'));
		$telphone     = IFilter::act(IReq::get('telphone'));
		$bankname     = IFilter::act(IReq::get('bankname'));
		$bankno       = IFilter::act(IReq::get('bankno'));
		$type         = IFilter::act(IReq::get('type'));
        $user_id      = $this->user['user_id'];

		//整合的数据
        $sqlData = array(
        	'user_id'     => $user_id,
        	'company_name'=> $company_name,
        	'address'     => $address,
        	'taxcode'     => $taxcode,
        	'telphone'    => $telphone,
        	'bankname'    => $bankname,
        	'bankno'      => $bankno,
        	'type'        => $type,
        );

        $checkArray = $sqlData;

        //普通发票
        if($type == 1)
        {
        	$checkArray = array($company_name,$taxcode);
        }
        //专用发票
        else
        {
        	unset($checkArray['user_id']);
        }

        foreach($checkArray as $key => $val)
        {
        	if(!$val)
        	{
        		$result = array('result' => false,'msg' => '请完整填写收件信息');
				die(JSON::encode($result));
        	}
        }

        if($user_id)
        {
        	$model = new IModel('invoice');
        	$model->setData($sqlData);
        	if($id)
        	{
        		$model->update("id = ".$id." and user_id = ".$user_id);
        	}
        	else
        	{
        		$id = $model->add();
        	}
        	$sqlData['id'] = $id;
        }
        //访客地址保存
        else
        {
        	ISafe::set("invoice",$sqlData);
        }
		$result = array('result' => true,'data' => $sqlData);
		die(JSON::encode($result));
	}

	//获取城市定位
	public function iplookupAjax()
	{
		die( JSON::encode(IClient::getLocal()) );
	}

	//选择自提点
	public function takeself()
	{
	    $id = IFilter::act(IReq::get('id'),'int');
	    $accept_name = IFilter::act(IReq::get('accept_name'));
	    $accept_mobile = IFilter::act(IReq::get('accept_mobile'),'mobile');

	    $takeselfDB = new IModel('takeself');
	    $takeselfRow = $takeselfDB->getObj($id);
	    if(!$takeselfRow)
	    {
	        die('自提点信息不存在');
	    }

	    $takeselfRow['accept_name']  = $accept_name;
	    $takeselfRow['accept_mobile']= $accept_mobile;
	    $this->takeselfRow = $takeselfRow;
	    $this->redirect('takeself');
	}
}