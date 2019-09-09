<?php 
class Redis {
	private $redis;
	private static $instance = null;
	private function __construct() {
		$this->redis = new Predis\Client(IWeb::$app->config['REDIS']);
	}

	private function __clone() {

	}
	public static function getInstance() {
		if (self::$instance == null) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}