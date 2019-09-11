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
		try {
			// $result = Util::curl_message($apiUrl.'/api/point/mobile',$params);
			$client = new \GuzzleHttp\Client();				
			$response = $client->post($apiUrl.'/api/point/mobile',['form_params' => $params]);
			if ($response->getStatusCode() == 200) {
				$result = json_decode($response->getBody()->getContents(), true);
        		return $result;
			}
			return array();
			/*$promise = $client->requestAsync('POST',$apiUrl.'/api/point/mobile',['form_params' => $params]);
			$promise->then(
	            function (ResponseInterface $res) use($pointLogObj,$point_id) {		             		            	          	
	            	if ($res->getStatusCode() == 200) {
	            		$result = json_decode($res->getBody()->getContents(), true);
	            		if ($result['code'] == 0) {
	            			return $result;
						}
	            	}
	            },
	            function (RequestException $e) {		            	
	            	return array();
	            }
	        );
	        $promise->wait();*/

		} catch(Exception $e) {
			return array();	
		}
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
		            function (ResponseInterface $res) use($pointLogObj,$pointLog) {		             		            	          	
		            	if ($res->getStatusCode() == 200) {
		            		$result = json_decode($res->getBody()->getContents(), true);
		            		if ($result['code'] == 0) {
								$pointLogObj->setData(['status' => 1]);
								$pointLogObj->update("id = ".$pointLog['id']);										            
							} else {
								$notice_time = Aisino_class::noticeTime($pointLog['retry_num']);
								$pointLogObj->setData(['notice_time' => $notice_time, 'retry_num' => $pointLog['retry_num'] + 1]);
								$pointLogObj->update("id = ".$pointLog['id']);
							}
		            	}
		            },
		            function (RequestException $e) {		            	
		            	$notice_time = Aisino_class::noticeTime($pointLog['retry_num']);
						$pointLogObj->setData(['notice_time' => $notice_time, 'retry_num' => $pointLog['retry_num'] + 1]);
						$pointLogObj->update("id = ".$pointLog['id']);
		            }
		        );
		        $promise->wait();

				/*$result = Util::curl_message($apiUrl.'/api/point/pushpoint',$params);
				if ($result['code'] == 0) {
					$poinLogObj->setData(['status' => 1]);
					$poinLogObj->update("id=".$point_id);
				}*/
			}catch(Exception $e) {
				$notice_time = Aisino_class::noticeTime($pointLog['retry_num']);
				$pointLogObj->setData(['notice_time' => $notice_time, 'retry_num' => $pointLog['retry_num'] + 1]);
				$pointLogObj->update("id = ".$pointLog['point_id']);
			}
		}	
	}

	/**
	 * 生成时间戳
	 * @param  [type] $retry_num [description]
	 * @return [type]            [description]
	 */
	public static function noticeTime($retry_num) {
		$notice_time = [5, 15, 30, 60];
		switch ($retry_num) {
			case 0:
			return strtotime("+5 minutes");
			break;
			case 1:
			return strtotime("+15 minutes");
			break;
			case 2:
			return strtotime("+30 minutes");
			break;
			case 3:
			return strtotime("+60 minutes");
			break;			
			default:
			return strtotime("+120 minutes");
			break;
		}
	}

	/**
	 * @brief：计算两个时间戳之间相差的日时分秒
	 * @param  [type] $begin_time [description]
	 * @param  [type] $end_time   [description]
	 * @return [type]             [description]
	 */
	public static function timediff($begin_time,$end_time)
	{
	      // if($begin_time < $end_time){
	         $starttime = $begin_time;
	         $endtime = $end_time;
	      // }else{
	      //    $starttime = $end_time;
	      //    $endtime = $begin_time;
	      // }

	      //计算天数
	      $timediff = $endtime-$starttime;
	      $days = intval($timediff/86400);
	      //计算小时数
	      $remain = $timediff%86400;
	      $hours = intval($remain/3600);
	      //计算分钟数
	      $remain = $remain%3600;
	      $mins = intval($remain/60);
	      //计算秒数
	      $secs = $remain%60;
	      $res = array("day" => $days,"hour" => $hours,"min" => $mins,"sec" => $secs);
	      return $res;
	}
}