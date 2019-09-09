<?php

class Aisino extends IController {

	private function Generation() {
		for ($i=0; $i < 3; $i++) { 
			echo "<br>输出存在感1".PHP_EOL;
			yield $i;
			echo "输出存在感2".PHP_EOL;
		}
	}

	function pushpoint() {		

		$arr = [5,3,7,1,8,2,9,4,7,2,6,6];
		$len = count($arr);
		for($i = 0;$i < 6; $i++) {
			for($j = 0;$j<$len-$i-1;$j++) {
				if($arr[$j]<$arr[$j+1]) {
					$temp = $arr[$j];
					$arr[$j] = $arr[$j+1];
					$arr[$j+1] = $temp;
				}
			}
		}
		var_dump($arr);exit;

		// $result = Aisino_class::pushPoint(3);		
		// 红包人数
		$people = 10; // n
		// 红包总金额
		$money = 100; // m
		$data = [];

		$remainMoney = $money;		
		$restPeopleNum = $people;

		for($i = 0;$i<$people;$i++) {
			if($people-$i==1){
				$data[] = $remainMoney;
				break;
			}	
			$_tmp = floor($remainMoney/($people-$i)*2);
			$_red_bag = rand(1, $_tmp - ($people - $i) - 1);
			$data[] = $_red_bag;
			$remainMoney -=$_red_bag;			
		}
		print_r($data);
		exit;
	}	

	// 	
} 