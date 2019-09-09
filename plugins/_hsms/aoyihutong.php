<?php
/**
 * @copyright (c) 2011 aircheng.com
 * @file aoyihutong.php
 * @brief **短信发送接口
 * @author nswe
 * @date 2015/5/30 15:46:38
 * @version 3.3
 */

 /**
 * @class aoyihutong
 * @brief 短信发送接口 短信后台地址 http://www.ztsms.cn/home
 */
class aoyihutong extends hsmsBase
{
	private $submitUrl  = "http://101.200.228.238/smsport/default.asmx/SendSms";						   

	/**
	 * @brief 获取config用户配置
	 * @return array
	 */
	public function getConfig()
	{
		$siteConfigObj = new Config("site_config");

		return array(
			'username' => $siteConfigObj->sms_username,
			'userpwd'  => $siteConfigObj->sms_pwd,
			'longnum'  => $siteConfigObj->sms_userid,
		);
	}

	/**
	 * @brief 发送短信
	 * @param string $mobile
	 * @param string $content
	 * @return
	 */
	public function send($mobile,$content)
	{
		$config = self::getConfig();

		$post_data = array(
			'username' => $config['username'],
			'password' => urldecode(htmlspecialchars_decode($config['userpwd'])),
			'msg'  	   => $content,
			'phonelist'=> $mobile,
			'longnum'  => $config['longnum'],
		);

		$url    = $this->submitUrl;
		$string = '';
		foreach ($post_data as $k => $v)
		{
		   $string .="$k=".urlencode($v).'&';
		}

		$post_string = substr($string,0,-1);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_URL,$url);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //如果需要将结果直接返回到变量里，那加上这句。
		$result = curl_exec($ch);
		if($result === false)
		{
            $error = curl_error($ch);
            curl_close($ch);
			return "CURL错误：".$error;
		}
		return $this->response($result);
	}

	/**
	 * @brief 解析结果
	 * @param $result 发送结果
	 * @return string success or fail
	 */
	public function response($result)
	{
		$xmlObj = simplexml_load_string($result);
        
        if (strpos($result,'|') !== false){
            return 'success';
        }else{ 
            return $this->getMessage(json_decode($xmlObj, true));
        }
	}

	/**
	 * @brief 获取参数
	 */
	public function getParam()
	{
		return array(
			"username" => "用户名",
			"userpwd"  => "密码",
			"longnum" => "扩展码",
		);
	}


	//返回消息提示
	public function getMessage($code)
	{
		$messageArray = array(
			-1 =>"用户名或者密码不正确或用户禁用",
			-2  =>"手机号码为空或含有不合法的手机号码",
			-3  =>"内容为空或含有非法字符",
			-4  =>"账号余额不足",
			-5  =>"longnum 不合法",
			-6  =>"低于起发限制",
			-10 =>"其他错误",
		);
		return isset($messageArray[$code]) ? $messageArray[$code] : $code;
	}
}