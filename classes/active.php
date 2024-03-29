<?php
/**
 * @copyright Copyright(c) 2015-2018 aircheng.com
 * @file active.php
 * @brief 促销活动处理类
 * @author nswe
 * @date 2018/4/12 19:26:27
 * @version 5.1
 */
class Active
{
	//活动的类型,groupon(团购),time(限时抢购),costpoint(积分兑换商品)
	private $promo;

	//参加活动的用户ID
	private $user_id;

	//活动的ID编号
	private $active_id;

	//商品ID 或 货品ID
	private $id;

	//goods 或 product
	private $type;

	//购买数量
	private $buy_num;

	//原始的商品或者货品数据
	public $originalGoodsInfo;

	//活动价格
	public $activePrice;

    //所需积分
    public $spendPoint = 0;

	//活动类型和名称的对应关系
	public static $typeToNameMapping = array('groupon' => '团购','time' => '限时抢购','costpoint' => '积分兑换');

	/**
	 * @brief 构造函数创建活动
	 * @param $promo string 活动的类型,groupon(团购),time(限时抢购),costpoint(积分兑换)
	 * @param $activeId int 活动的ID编号
	 * @param $user_id int 用户的ID编号
	 * @param $id  int 根据$type的不同而表示：商品id,货品id
	 * @param $type string 商品：goods; 货品：product
	 * @param $buy_num int 购买的数量；默认1
	 */
	public function __construct($promo,$active_id,$user_id = 0,$id,$type = "goods",$buy_num = 1)
	{
		$this->promo     = $promo;
		$this->active_id = $active_id;
		$this->user_id   = $user_id;
		$this->id        = $id;
		$this->type      = $type;
		$this->buy_num   = $buy_num;
	}

	/**
	 * @brief 检查活动的合法性
	 * @param int $order_id 订单ID
	 * @return string(有错误) or true(处理正确)
	 */
	public function checkValid($order_id = '')
	{
		if(!$this->id)
		{
			return "商品ID不存在";
		}
		$goodsData = ($this->type == 'product') ? Api::run('getProductInfo',array('id' => $this->id)) : Api::run('getGoodsInfo',array('id' => $this->id));

		//库存判断
		if(!$goodsData || $this->buy_num <= 0 || $this->buy_num > $goodsData['store_nums'])
		{
			return "购买的数量不正确或大于商品的库存量";
		}

		$this->originalGoodsInfo = $goodsData;
		$this->activePrice       = $goodsData['sell_price'];
		$goods_id                = $goodsData['goods_id'];

		//具体促销活动的合法性判断
		switch($this->promo)
		{
			//团购
			case "groupon":
			{
				if(!$this->user_id)
				{
					return "参加团购活动请您先登录";
				}

				$regimentRow = Api::run('getRegimentRowById',array("id" => $this->active_id));
				if($regimentRow)
				{
					if($regimentRow['goods_id'] != $goodsData['goods_id'])
					{
						return "该商品没有参与团购活动";
					}

					if($regimentRow['store_nums'] <= $regimentRow['sum_count'])
					{
						return "团购商品已经销售一空";
					}

					if($this->buy_num + $regimentRow['sum_count'] > $regimentRow['store_nums'])
					{
						return "当前团购库存不足，无法购买";
					}

					//检查团购订单
					$orderDB   = new IModel('order as o,order_goods as og');
					$orderData = $orderDB->query('o.user_id = '.$this->user_id.' and o.type = "groupon" and o.id = og.order_id and active_id = '.$this->active_id);
					$hasBugNum = 0;
					foreach($orderData as $key => $val)
					{
						//此ID的订单不作为统计判断,用于已下单后支付时候的判断情况
						if($order_id && $val['order_id'] == $order_id)
						{
							continue;
						}
						$orderStatus = Order_class::getOrderStatus($val);
						if(in_array($orderStatus,array(2,1,11)))
						{
							return "您参与的该团购订单还没有完成";
						}

						if(in_array($orderStatus,array(3,4,6)))
						{
							$hasBugNum += $val['goods_nums'];
						}
					}

					//批量购买(薄利多销)
					if($regimentRow['limit_min_count'] > 0)
					{
						if($this->buy_num < $regimentRow['limit_min_count'])
						{
							return "购买数量必须超过 ".$regimentRow['limit_min_count']." 件才能下单";
						}
					}

					//限制购买(限购，要多人参与)
					if($regimentRow['limit_max_count'] > 0)
					{
						if($this->buy_num > $regimentRow['limit_max_count'])
						{
							return "购买数量不能超过 ".$regimentRow['limit_max_count']." 件";
						}

						if(($hasBugNum + $this->buy_num) > $regimentRow['limit_max_count'])
						{
							return "此团购为限购活动，您累计购买数量不能超过".$regimentRow['limit_max_count'];
						}
					}

					if($this->buy_num > $regimentRow['store_nums'])
					{
						return "购买数量超过了团购剩余量";
					}

					$this->activePrice = $regimentRow['regiment_price'];
				}
				else
				{
					return "当前时间段内不存在此团购活动";
				}
				return true;
			}
			break;

			//抢购
			case "time":
			{
				$promotionRow = Api::run('getPromotionRowById',array("id" => $this->active_id));
				if($promotionRow)
				{
					if($promotionRow['condition'] != $goodsData['goods_id'])
					{
						return "该商品没有参与抢购活动";
					}

					$memberObj = new IModel('member');
					$memberRow = $memberObj->getObj('user_id = '.$this->user_id,'group_id');
					if($promotionRow['user_group'] == '' || (isset($memberRow['group_id']) && stripos(','.$promotionRow['user_group'].',',','.$memberRow['group_id'].',')!==false))
					{
						$this->activePrice = $promotionRow['award_value'];
					}
					else
					{
						return "此活动仅限指定的用户组";
					}
				}
				else
				{
					return "不存在此限时抢购活动";
				}
				return true;
			}
			break;

            //积分兑换
            case "costpoint":
            {
                if(!$this->user_id)
                {
                    return "参加积分兑换请您先登录";
                }

                $promotionRow = Api::run('getCostPointRowById',array("id" => $this->active_id));
                if($promotionRow)
                {
                    if($promotionRow['goods_id'] != $goodsData['goods_id'])
                    {
                        return "该商品没有参与积分兑换活动";
                    }

                    $memberDB  = new IModel('member');
                    $memberRow = $memberDB->getObj('user_id = '.$this->user_id,'point,group_id');
                    if(!$memberRow)
                    {
                        return "用户信息不存在";
                    }

                    if($memberRow['point'] < $promotionRow['point'] * $this->buy_num)
                    {
                        return "用户积分不足";
                    }

                    if($promotionRow['user_group'] == '' || (isset($memberRow['group_id']) && stripos(','.$promotionRow['user_group'].',',','.$memberRow['group_id'].',')!==false))
                    {
                        $this->activePrice = 0;
                        $this->spendPoint  = $this->buy_num * $promotionRow['point'];
                    }
                    else
                    {
                        return "此活动仅限指定的用户组";
                    }
                }
                else
                {
                    return "不存在此积分兑换活动";
                }
                return true;
            }
            break;
		}
		return "未知促销活动";
	}

	public function checkCart($spend_point) {
		if(!$this->user_id)
        {
            return "参加积分兑换请您先登录";
        }

        $memberDB  = new IModel('member');
        $memberRow = $memberDB->getObj('user_id = '.$this->user_id,'point,group_id');
        if(!$memberRow)
        {
            return "用户信息不存在";
        }

        if($memberRow['point'] < $spend_point)
        {
            return "用户积分不足";
        }
	}

	//获取活动名称
	public static function name($promo)
	{
		$result = self::$typeToNameMapping;
		return isset($result[$promo]) ? $result[$promo] : '';
	}

	/**
	 * @brief 订单付款后的回调
	 * @param $orderNo string 订单号
	 * @param $orderType 订单类型
	 */
	public static function payCallback($orderNo,$orderType,$history_point=0)
	{
		switch($orderType)
		{
            //积分兑换
            case "costpoint":
            {
                $tableModel = new IModel('order');
                $orderRow   = $tableModel->getObj("order_no = '{$orderNo}'","id,spend_point,user_id,order_no");
                if($orderRow)
                {
                    $user_id = $orderRow['user_id'];
                    $pointConfig = array(
                        'user_id' => $user_id,
                        'order_id' => $orderRow['id'],
                        'point'   => -$orderRow['spend_point'],
                        'log'     => '成功购买订单号：'.$orderRow['order_no'].'中的商品,消耗积分'.$orderRow['spend_point'],
                        'history_point' => $history_point - $orderRow['spend_point']                        
                    );
                    $pointObj = new Point();
                    if(!$pointObj->update($pointConfig)) {  
                    	$dataArray = array(
							'status'     => 4,
							'pay_status' =>0,
						);

                    	// 回滚订单状态
                    	$tableModel->setData($dataArray);
                    	if($tableModel->update("id = ".$orderRow['id'])) {
                    		IError::show("兑换商品过程中出现异常");
                    	}
                    	return true;
                    }	

                }
            }
            break;

            default:
            {
                IError::show("无法识别活动类型");
            }
            break;
		}
	}

	//获取活动数据
	public function data()
	{
		switch($this->promo)
		{
			case "groupon":
			{
				$data = Api::run("getRegimentRowById",array("id" => $this->active_id));
				if($data && $data['goods_id'] ==  $this->id)
				{
					return $data;
				}
				return "团购活动不存在";
			}
			break;

			case "time":
			{
				$data = Api::run("getPromotionRowById",array("id" => $this->active_id));
				if($data && $data['condition'] == $this->id)
				{
					return $data;
				}
				return "限时抢购活动不存在";
			}
			break;

            case "costpoint":
            {
                $data = Api::run("getCostPointRowById",array("id" => $this->active_id));
                if($data && $data['goods_id'] == $this->id)
                {
                    return $data;
                }
                return "积分兑换活动不存在";
            }
            break;

            default:
            {
                IError::show("无法识别活动类型");
            }
            break;
		}
	}

	/**
	 * @brief 订单退款后的回调
	 * @param $orderNo string 订单号
	 * @param $orderType 订单类型
	 */
	public static function refundCallback($orderNo,$orderType)
	{
		switch($orderType)
		{
			//团购
			case "groupon":
			{
				$tableModel = new IModel('order as o,order_goods as og');
				$orderRow   = $tableModel->getObj("o.order_no = '{$orderNo}' and o.id = og.order_id","og.goods_nums,o.active_id");
				if($orderRow)
				{
					$regimentModel = new IModel('regiment');
					$regimentModel->setData(array('sum_count' => 'sum_count - '.$orderRow['goods_nums']));
					$regimentModel->update('id = '.$orderRow['active_id'],array('sum_count'));
				}
			}
			break;

			//抢购
			case "time":
			{

			}
			break;

            //积分兑换
            case "costpoint":
            {
                $tableModel = new IModel('order');
                $orderRow   = $tableModel->getObj("order_no = '{$orderNo}'","spend_point,user_id,order_no");
                if($orderRow)
                {
                    $user_id = $orderRow['user_id'];
                    $pointConfig = array(
                        'user_id' => $user_id,
                        'point'   => $orderRow['spend_point'],//需要返还的积分
                        'log'     => '退款订单号：'.$orderRow['order_no'].'中的商品,退还积分'.$orderRow['spend_point'],
                    );
                    $pointObj = new Point();
                    $pointObj->update($pointConfig);
                }
            }
            break;

            default:
            {
                IError::show("无法识别活动类型");
            }
            break;
		}
	}

	/**
	 * @brief 团购活动的状态
	 * @param array $row 表数据
	 * @param string
	 */
	public static function statusRegiment($row)
	{
		if($row['is_close'] == 1)
		{
			return '关闭';
		}

		if($row['is_close'] == 2)
		{
			return '待审';
		}

		$nowTime = time();
		if($nowTime < strtotime($row['start_time']))
		{
			return '未开始';
		}

		if($nowTime > strtotime($row['end_time']))
		{
			return '已过期';
		}

        $goodsRow = Api::run("getGoodsInfo",array('id' => $row['goods_id']));
		if(!$goodsRow)
		{
			return '商品不存在';
		}

		if($goodsRow['promo'] == '' || $goodsRow['active_id'] == 0)
		{
			return '状态错误';
		}
		return '正常';
	}

	/**
	 * @brief 抢购活动的状态
	 * @param array $row 表数据
	 * @param string
	 */
	public static function statusTime($row)
	{
		if($row['is_close'] == 1)
		{
			return '关闭';
		}

		$nowTime = time();
		if($nowTime < strtotime($row['start_time']))
		{
			return '未开始';
		}

		if($nowTime > strtotime($row['end_time']))
		{
			return '已过期';
		}

        $goodsRow = Api::run("getGoodsInfo",array('id' => $row['condition']));
		if(!$goodsRow)
		{
			return '商品不存在';
		}

		if($goodsRow['promo'] == '' || $goodsRow['active_id'] == 0)
		{
			return '状态错误';
		}
        return '正常';
	}

    /**
     * @brief 积分兑换活动的状态
     * @param array $row 表数据
     * @param string
     */
    public static function statusCostPoint($row)
    {
        if($row['is_close'] == 1)
        {
            return '关闭';
        }

        $goodsRow = Api::run("getGoodsInfo",array('id' => $row['goods_id']));
        if(!$goodsRow)
        {
            return '商品不存在';
        }

		if($goodsRow['promo'] == '' || $goodsRow['active_id'] == 0)
		{
			return '状态错误';
		}
        return '正常';
    }

	//检测商品ID是否处于特价活动中
	public static function isSale($id)
	{
		$promoDB = new IModel('promotion');
		$promoRow= $promoDB->getObj('find_in_set('.$id.',intro) and is_close = 0');
		if($promoRow)
		{
			return $promoRow;
		}
		return false;
	}

	//商品详情页面的活动视图路径
	public function productTemplate()
	{
		switch($this->promo)
		{
			case "groupon":
			{
				return "_products_groupon";
			}
			break;

			case "time":
			{
				return "_products_time";
			}
			break;

			case "costpoint":
			{
				return "_products_costpoint";
			}
			break;
		}
	}

    /*
     * 编辑商品活动记录
     * @param $active_id 活动ID
     * @param $promo     活动类型
     */
    public static function goodsActiveEdit($active_id,$promo)
    {
        $goods_id = '';
        switch($promo)
        {
            case "groupon":
            {
                $modelObj = new IModel('regiment');
                $data = $modelObj->getObj('id = '.$active_id);
                if($data && $data['is_close'] == 0)
                {
                    $goods_id = $data['goods_id'];
                }
            }
            break;

            case "time":
            {
                $modelObj = new IModel('promotion');
                $data = $modelObj->getObj('id = '.$active_id);
                if($data && $data['is_close'] == 0)
                {
                    $goods_id = $data['condition'];
                }
            }
            break;

            case "costpoint":
            {
                $modelObj = new IModel('cost_point');
                $data = $modelObj->getObj('id = '.$active_id);
                if($data && $data['is_close'] == 0)
                {
                    $goods_id = $data['goods_id'];
                }
            }
            break;

            default:
            {
                IError::show("无法识别活动类型");
            }
            break;
        }
        $goodsObj = new IModel('goods');

        //清空之前的商品状态
        $dataArray = array(
            'active_id'  => 0,
            'promo'      => '',
        );
        $goodsObj->setData($dataArray);
        $goodsObj->update('active_id = '.$active_id.' and promo = "'.$promo.'"');

        //更新商品活动属性
        if($goods_id)
        {
            $dataArray = array(
                'active_id'  => $active_id,
                'promo'      => $promo,
            );
            $goodsObj->setData($dataArray);
            $goodsObj->update('id = '. $goods_id);
        }
    }

    /*
     * 删除商品活动属性
     * @param $idString ID字符串逗号拼接
     * @param $promo 活动名称
     */
    public static function goodsActiveDel($idString,$promo)
    {
        $goodsObj  = new IModel('goods');
        $goods_ids = array();
        switch($promo)
        {
            case "groupon":
            {
                $modelObj = new IModel('regiment');
                $data = $modelObj->query('id in ('.$idString.')','goods_id');
                if($data)
                {
                    $goods_ids = array_column($data,'goods_id');
                }
            }
            break;

            case "time":
            {
                $modelObj = new IModel('promotion');
                $data = $modelObj->query('id in ('.$idString.')','`condition`');
                if($data)
                {
                    $goods_ids = array_column($data,'condition');
                }
            }
            break;

            case "costpoint":
            {
                $modelObj = new IModel('cost_point');
                $data = $modelObj->query('id in ('.$idString.')','goods_id');
                if($data)
                {
                    $goods_ids = array_column($data,'goods_id');
                }
            }
            break;

            default:
            {
                IError::show("无法识别活动类型");
            }
            break;
        }

        if($goods_ids)
        {
            $dataArray = array(
                'active_id'  => '',
                'promo'      => '',
            );
            $goodsObj->setData($dataArray);
            $goodsObj->update('id in ('.join(',',$goods_ids).')');
        }
    }
}