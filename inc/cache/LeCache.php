<?php

namespace lezaiyun\Leseo\inc\Cache;

class LeCache {
	private $cache_file;

	public function __construct($type)
	{
		// tmp 可修改，但目录必须存在于本文件所在目录，否则无法正常创建缓存文件。为避免每次创建实例时都判断形成浪费，直接手动创建目录tmp
		$this->cache_file = join(DIRECTORY_SEPARATOR, [dirname(__FILE__), 'tmp', 'cache_' . $type]);
	}

	public function set($key, $data) {
		$caches = file_get_contents($this->cache_file);
		if ( $caches === False ) $caches = "";
		$caches = json_decode($caches, True);  // 以 list 形式取出，当无法解析字符串内容时，返回null
		$datetime = new \DateTime('now', new \DateTimeZone('Asia/Shanghai'));
		$caches[$key] = [$data, $datetime->setTime(23, 59, 59)->getTimestamp()];

		file_put_contents(
			$this->cache_file,
			json_encode($caches),
			LOCK_EX
		);
		return True;
	}

	public function delete($key){
		$caches = file_get_contents($this->cache_file);
		if ( $caches === False ) $caches = "";
		$caches = json_decode($caches, True);  // 以 list 形式取出，当无法解析字符串内容时，返回null
		unset($caches[$key]);
		file_put_contents(
			$this->cache_file,
			json_encode($caches),
			LOCK_EX
		);
		return True;
	}

	public function get($key) {
		# 后续根据使用反馈再把返回值直接在此方法中判断并返回。
//        $default = 99999;
//        if ($key == 'remain_daily') $default = 10;

		$data = @file_get_contents($this->cache_file);
		if ( $data === False ) return $data;

		$data = json_decode($data, True);  // 以 list 形式取出，当无法解析字符串内容时，返回null
		if ( !isset($data[$key]) ) return False;
		if ( empty($data[$key]) ) return False;

		return $data[$key];
	}
}