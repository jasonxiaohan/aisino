/**
 * 订单对象
 * address:收货地址; delivery:配送方式; payment:支付方式;
 */
function orderFormClass()
{
	_self = this;

	//商家信息
	this.seller = null;

	//默认数据
	this.deliveryId   = 0;

	//免运费的商家ID
	this.freeFreight  = [];

	//订单各项数据
	this.orderAmount  = 0;//订单金额
	this.goodsSum     = 0;//商品金额
	this.deliveryPrice= 0;//运费金额
	this.taxPrice     = 0;//税金
	this.protectPrice = 0;//保价
	this.ticketPrice  = 0;//优惠券

	this.deliveryInit = function(defaultDeliveryId)
	{
		this.deliveryId = defaultDeliveryId;
	}

	/**
	 * 算账
	 */
	this.doAccount = function()
	{
		//税金
		this.taxPrice = $('input[type="checkbox"][name="taxes"]:checked').length > 0 ? $('input[type="checkbox"][name="taxes"]:checked').val() : 0;
		//最终金额
		this.orderAmount = parseFloat(this.goodsSum) - parseFloat(this.ticketPrice) + parseFloat(this.deliveryPrice) + parseFloat(this.taxPrice) + parseFloat(this.protectPrice);
		this.orderAmount = this.orderAmount <=0 ? 0 : this.orderAmount.toFixed(2);

		//刷新DOM数据
		$('#final_sum').html(this.orderAmount);
		$('[name="ticket_value"]').html(this.ticketPrice);
		$('#delivery_fee_show').html(this.deliveryPrice);
		$('#protect_price_value').html(this.protectPrice);
		$('#tax_fee').html(this.taxPrice);
	}

	//地址修改
	this.addressEdit = function(addressId)
	{
		art.dialog.open(creatUrl("block/address/id/"+addressId),
		{
			"id":"addressWindow",
			"title":"修改收货地址",
			"ok":function(iframeWin, topWin){
				var formObject = iframeWin.document.forms[0];
				if(formObject.onsubmit() === false)
				{
					alert("请正确填写各项信息");
					return false;
				}
				$.getJSON(formObject.action,$(formObject).serialize(),function(content){
					if(content.result == false)
					{
						alert(content.msg);
						return;
					}
					addressId ? $('#addressItem'+addressId).remove() : $('#addressItem').first().remove();

					//修改后的节点增加
					var addressLiHtml = template.render('addressLiTemplate',{"item":content.data});
					$('#addr_list').prepend(addressLiHtml);
					$('input[type="radio"][name="radio_address"]').first().trigger('click');

					art.dialog({"id":"addressWindow"}).close();
				});
				return false;
			},
			"okVal":"修改",
			"cancel":true,
			"cancelVal":"取消",
		});
	}

	//地址删除
	this.addressDel  = function(addressId)
	{
		$('#addressItem'+addressId).remove();
		if(addressId > 0)
		{
			$.get(creatUrl("ucenter/address_del"),{"id":addressId});
		}
	}

	//地址增加
	this.addressAdd  = function()
	{
		//如果游客已经添加了地址
		if($('#addressItem0').length > 0)
		{
			tips('收货地址已经存在');
			return;
		}

		art.dialog.open(creatUrl("block/address"),
		{
			"id":"addressWindow",
			"title":"添加收货地址",
			"ok":function(iframeWin, topWin){
				var formObject = iframeWin.document.forms[0];
				if(formObject.onsubmit() === false)
				{
					alert("请正确填写各项信息");
					return false;
				}
				$.getJSON(formObject.action,$(formObject).serialize(),function(content){
					if(content.result == false)
					{
						alert(content.msg);
						return;
					}
					var addressLiHtml = template.render('addressLiTemplate',{"item":content.data});
					$('#addr_list').prepend(addressLiHtml);
					$('input[type="radio"][name="radio_address"]').first().trigger('click');

					art.dialog({"id":"addressWindow"}).close();
				});
				return false;
			},
			"okVal":"添加",
			"cancel":true,
			"cancelVal":"取消",
		});
	}

	//根据省份地区ajax获取配送方式和运费
	this.getDelivery = function(province)
	{
	    //取消自提点选择
	    $('[name="takeself"]').prop('checked',false);

	    //配送方式显示
	    $('#deliveryBox').show();

		//整合当前的商品信息
		var goodsId   = [];
		var productId = [];
		var num       = [];
		$('[id^="deliveryFeeBox_"]').each(function(i)
		{
			var idValue = $(this).attr('id');
			var dataArray = idValue.split("_");

			goodsId.push(dataArray[1]);
			productId.push(dataArray[2]);
			num.push(dataArray[3]);
		});

		//获取配送信息和运费
		$.getJSON(creatUrl("block/order_delivery"),{"province":province,"goodsId":goodsId,"productId":productId,"num":num,"random":Math.random()},function(json){
			for(indexVal in json)
			{
				var content = json[indexVal];
				//正常可以送达
				if(content.if_delivery == 0)
				{
					for(var tIndex in _self.freeFreight)
					{
						var sellerId  = _self.freeFreight[tIndex];
						content.price = parseFloat(content.price) - parseFloat(content.seller_price[sellerId]);
						content.price = content.price <= 0 ? 0 : content.price;
					}
					$('input[type="radio"][name="delivery_id"][value="'+content.id+'"]').data("protectPrice",parseFloat(content.protect_price));
					$('input[type="radio"][name="delivery_id"][value="'+content.id+'"]').data("deliveryPrice",parseFloat(content.price));
					var deliveryHtml = template.render("deliveryTemplate",{"item":content});
					$("#deliveryShow"+content.id).html(deliveryHtml);
					$('input[type="radio"][name="delivery_id"][value="'+content.id+'"]').prop("disabled",false);
				}
				//配送方式不能配送
				else
				{
					$('input[type="radio"][name="delivery_id"][value="'+content.id+'"]').prop("disabled",true);
					$('input[type="radio"][name="delivery_id"][value="'+content.id+'"]').prop("checked",false);
					$("#deliveryShow"+content.id).html("<span style='color:red'>"+content.reason+"</span>");
				}
			}
			var checkVal = $('input[type="radio"][name="delivery_id"]:checked');
			if(checkVal.length > 0)
			{
			    $('input[type="radio"][name="delivery_id"]:checked').trigger('click');
			}
			else if(_self.deliveryId > 0 && $('input[type="radio"][name="delivery_id"][value="'+_self.deliveryId+'"]').prop('disabled') != "disabled")
			{
				$('input[type="radio"][name="delivery_id"][value="'+_self.deliveryId+'"]').trigger('click');
			}
		});
	}

	/**
	 * address初始化
	 */
	this.addressInit = function()
	{
		var addressList = $('input[type="radio"][name="radio_address"]');
		if(addressList.length > 0 && $('#addressBox').is(":visible") == true)
		{
			addressList.first().trigger('click');
		}
		_self.doAccount();
	}

	/**
	 * payment初始化
	 */
	this.paymentInit = function(defaultPaymentId)
	{
		if(defaultPaymentId > 0)
		{
			$('input[type="radio"][name="payment"][value="'+defaultPaymentId+'"]').trigger('click');
		}
	}

	/**
	 * payment选择
	 */
	this.paymentSelected = function(paymentId)
	{
		var paymentObj = $('input[type="radio"][name="payment"][value="'+paymentId+'"]');
		this.paymentPrice = paymentObj.attr("alt");
		this.doAccount();
	}

	/**
	 * delivery选中
	 * @param int deliveryId 配送方式ID
	 */
	this.deliverySelected = function(deliveryId)
	{
		var deliveryObj = $('input[type="radio"][name="delivery_id"][value="'+deliveryId+'"]');
		this.protectPrice  = deliveryObj.data("protectPrice") > 0 ? deliveryObj.data("protectPrice") : 0;
		this.deliveryPrice = deliveryObj.data("deliveryPrice")> 0 ? deliveryObj.data("deliveryPrice"): 0;

		//先发货后付款
		if(deliveryObj.attr('paytype') == '1')
		{
			$('input[type="radio"][name="payment"]').prop('checked',false);
			$('input[type="radio"][name="payment"]').prop('disabled',true);
			$('#paymentBox').hide("slow");
		}
		else
		{
			$('input[type="radio"][name="payment"]').prop('disabled',false);
			$('#paymentBox').show("slow");
		}

		_self.doAccount();
	}

	/**
	 * 检查表单是否可以提交
	 */
	this.isSubmit = function()
	{
		var addressObj  = $('input[type="radio"][name="radio_address"]:checked');
		var takeselfObj = $('input[type="radio"][name="takeself"]:checked');
		var deliveryObj = $('input[type="radio"][name="delivery_id"]:checked');
		var paymentObj  = $('input[type="radio"][name="payment"]:checked');				

        //收货地址和自提点必须选择其一
		if(addressObj.length == 0)
		{
		    if(takeselfObj && takeselfObj.length > 0)
		    {
                var accept_name = $('[name="accept_name"]').val();
                var accept_mobile = $('[name="accept_mobile"]').val();
                if(!accept_name || !accept_mobile)
                {
                    alert('待完善自提点信息');
                    return false;
                }
		    }
		    else
		    {
    			alert("请选择收件地址");
    			return false;
		    }
		}

		if($('#deliveryBox').is(":visible") && deliveryObj.length == 0)
		{
			alert("请选择配送方式");
			return false;
		}

		if(paymentObj.length == 0 && deliveryObj.attr('paytype') != "1")
		{
			alert("请选择支付方式");
			return false;
		}
		return true;
	}

	/**
	 * 点击选择自提点
	 */
	this.selectTakeself = function(id)
	{
	    var accept_name = $('[name="accept_name"]').val();
	    var accept_mobile = $('[name="accept_mobile"]').val();
	    var url = "block/takeself/id/"+id;
	        url+= accept_name   ? "/accept_name/"+encodeURIComponent(accept_name)     : "";
	        url+= accept_mobile ? "/accept_mobile/"+encodeURIComponent(accept_mobile) : "";

		art.dialog.open(creatUrl(url),{
		    width:__iwebshopMobile ? '100%' : 'auto',
			title:'选择自提点',
			okVal:'选择',
			ok:function(iframeWin, topWin)
			{
				var acceptName   = $(iframeWin.document).find('[name="accept_name"]').val();
				var acceptMobile = $(iframeWin.document).find('[name="accept_mobile"]').val();
				var takeselfJson = $(iframeWin.document).find('[name="takeselfItem"]:checked').attr('data');

				if(!takeselfJson)
				{
					alert('请选择自提点');
					return false;
				}

				if(!acceptName || !acceptMobile)
				{
					alert('收件人和手机号必填');
					return false;
				}

				var json = $.parseJSON(takeselfJson);
				json.accept_name   = acceptName;
				json.accept_mobile = acceptMobile;

                //渲染生成JS模板
				$('#takeself_list').empty();
				$('#takeself_list').html(template.render('takeselfLiTemplate',{"item":json}));
				$('[name="takeself"]').first().trigger('click');
				return true;
			}
		});
	}

	/**
	 * 选中自提点
	 */
    this.takeselfSelected = function(id)
    {
	    //取消用户地址
	    $('[name="radio_address"]').prop('checked',false);

	    //配送方式隐藏
	    $('#deliveryBox').hide();
	    $('input[type="radio"][name="delivery_id"]').prop('checked',false);
	    this.deliveryPrice = 0;

	    _self.doAccount();
    }

	/**
	 * 优惠券显示
	 */
	this.ticketShow = function()
	{
		var sellerArray = [];
		for(var seller_id in this.seller)
		{
			sellerArray.push(seller_id);
		}

		art.dialog.open(creatUrl("block/ticket/sellerString/"+sellerArray.join("_")),{
			title:'选择优惠券',
			okVal:'使用',
			ok:function(iframeWin, topWin)
			{
				//动态创建优惠券节点
				_self.getForm().find("input[name='ticket_id[]']").remove();

				var formObject   = iframeWin.document.forms["ticketForm"];
				var resultTicket = 0;
				$(formObject).find("[name='ticket_id']:checked").each(function()
				{
					var sid    = $(this).attr('seller');
					var tprice = parseFloat($(this).attr('price'));

					//专用优惠券
					if(_self.seller[sid] > 0)
					{
						resultTicket += (tprice >= _self.seller[sid]) ? _self.seller[sid] : tprice;
					}
					//通用优惠券
					else if(sid == '0')
					{
						var maxPrice = 0;
						for(var sellerId in _self.seller)
						{
							if(_self.seller[sellerId] > maxPrice)
							{
								maxPrice = _self.seller[sellerId];
							}
						}
						resultTicket += (tprice >= maxPrice) ? maxPrice : tprice;
					}
					//动态插入节点
					_self.getForm().prepend("<input type='hidden' name='ticket_id[]' value='"+$(this).val()+"' />");
					tips('使用了优惠券');
				});
				_self.ticketPrice = resultTicket;
				_self.doAccount();
			},
			"cancel":true,
			"cancelVal":"取消",
		});
	}

	//获取form表单
	this.getForm = function()
	{
		return $('form[name="order_form"]').length == 1 ? $('form[name="order_form"]') : $('form').first();
	}

	//发票编辑
	this.editInvoice = function()
	{
		art.dialog.open(creatUrl("block/invoice"),
		{
			"id":"taxWindow",
			"title":"发票编辑",
			"ok":function(iframeWin, topWin){
				var formObject = iframeWin.document.forms[0];
				if(formObject.onsubmit() === false)
				{
					alert("请正确填写各项信息");
					return false;
				}
				$.getJSON(formObject.action,$(formObject).serialize(),function(content){
					if(content.result == false)
					{
						alert(content.msg);
						return;
					}
					$('select[name="invoice_id"]').prepend("<option selected='selected' value='"+content.data['id']+"'>"+content.data['company_name']+"</option>");
					art.dialog({"id":"taxWindow"}).close();
				});
				return false;
			},
			"okVal":"提交",
			"cancel":true,
			"cancelVal":"取消",
		});
	}

	//商品类型实体或虚拟改变UI
	this.goodsType = function(type)
	{
	    if(type == 'default')
	    {
	        return;
	    }

	    //收货地址隐藏
	    $('#addressBox').hide();

	    //配送方式隐藏
	    $('#deliveryBox').hide();

        //结算项目隐藏
        var hideItem = {"delivery_fee_show":"运费","protect_price_value":"保价"};
        var selectedItem = $("[name='ticket_value']").parent().contents().each(function()
        {
            for(var j in hideItem)
            {
                if($(this).text().indexOf(hideItem[j]) !== -1)
                {
                    $(this).remove();
                    $("#"+j).remove();
                    break;
                }
            }
        });
	}
}

//加载完成初始化数据
$(function()
{
    //默认选中第一个快递方式
    var deliveryId = $('[name="delivery_id"]').first().val();
    if(deliveryId)
    {
        orderFormInstance.deliveryId = deliveryId;
    }

    //默认选中第一个支付方式
    $('input[type="radio"][name="payment"]').first().trigger('click');
})