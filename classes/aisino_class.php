<?php 
/**
 * @copyright (c) 2019
 * @file Aisino_Class.php
 * @brief 与外部接口通信
 * @author jasonxiaohan
 * @date 2019-09-02
 * @version 0.1
 */
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;

class Aisino_class 
{
	// 同步手机、积分信息
	public static function getMobile ($mobile) {
		if (empty($mobile)) {
			return false;
		}
		$siteConfigObj = new Config("site_config");
		$site_config   = $siteConfigObj->getInfo();
		$apiUrl = $site_config['api_url'];
		$key = $site_config['key'];
		$sign = strtoupper(md5($mobile.$key));
		$params = ["mobile" => $mobile, "sign" => $sign];
		$result = Util::curl_message($apiUrl.'/api/point/mobile',$params);
		return $result;		
	}

	/**
	 * 同步用户积分
	 * @return [type] [description]
	 */
	public static function pushPoint($point_id) {		
		if (!is_numeric($point_id)) {
			return;
		}

		$siteConfigObj = new Config("site_config");
		$site_config   = $siteConfigObj->getInfo();
		$apiUrl = $site_config['api_url'];
		$key = $site_config['key'];
		

		$pointLogObj    = new IModel('point_log');
		$pointLog = $pointLogObj->getObj('id = '.$point_id.' and status = 0');

		if ($pointLog) {
			$userObj = new IModel('user');
			$user = $userObj->getObj('id='.$pointLog['user_id']);
			if (!$user) {
				return;
			}
			$mobile = $user['username'];
			$data = ['unique' => $pointLog['unique'], 'point' => $pointLog['value'],'timestamp' => strtotime($pointLog['datetime']),'desc' => $pointLog['intro']];
			$sign = strtoupper(md5($mobile.json_encode($data).$key));
			$params = [
				'mobile' => $mobile,
				'sign'	 => $sign,
				'data'	 => json_encode($data),			
			];	
			try {
				$client = new \GuzzleHttp\Client();				
				$promise = $client->requestAsync('POST',$apiUrl.'/api/point/pushpoint',['form_params' => $params]);
				$promise->then(
		            function (ResponseInterface $res) use($pointLogObj,$point_id) {		             		            	          	
		            	if ($res->getStatusCode() == 200) {
		            		$result = json_decode($res->getBody()->getContents(), true);
		            		if ($result['code'] == 0) {
								$pointLogObj->setData(['status' => 1]);
								$pointLogObj->update("id=".$point_id);
							}
		            	}
		            },
		            function (RequestException $e) {		            	
		            }
		        );
		        $promise->wait();

				/*$result = Util::curl_message($apiUrl.'/api/point/pushpoint',$params);
				if ($result['code'] == 0) {
					$poinLogObj->setData(['status' => 1]);
					$poinLogObj->update("id=".$point_id);
				}*/
			}catch(Exception $e) {
			}
		}	
	}
}