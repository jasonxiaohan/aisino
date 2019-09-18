<?php

class Aisino extends IController {

	/**
	 * 同步积分结果通知
	 * @return [type] [description]
	 */
	function pushpoint() {		
		$pointLogObj = new IModel('point_log');	
		$pointRows = $pointLogObj->query('status = 0',['id','notice_time']);
		foreach($pointRows as $key=>$point) {
			Aisino_class::pushPoint($point['id']);	
		}
	}	

	/**
	 * 每天晚上同步用户账户积分
	 * @return [type] [description]
	 */
	function pushMemberPoint() {
		Aisino_class::updateMemberPoint();
	}
} 