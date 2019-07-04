<?php

/**
 * Created by PhpStorm.
 * User: shenpeiliang
 * Date: 2019/6/20
 * Time: 11:39
 */
namespace src;
class MysqlPool extends AbstractPool
{
	protected $config = [
		'dsn' => 'mysql:host=172.18.0.4:3306;dbname=docker',
		'user' => 'root',
		'password' => 'zxc123'
	];

	//连接选项
	protected $option = [
		\PDO::ATTR_TIMEOUT => 2, //超时秒数
		\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8' //编码
	];

	public function __construct(\src\OptionDTO $option)
	{
		parent::__construct($option);
	}

	/**
	 * PDO方式连接数据库
	 * @return PDO
	 */
	protected function createPoolObject(): \PDO{
		try{
			return new \PDO($this->config['dsn'], $this->config['user'], $this->config['password'], $this->option);
		}catch(\PDOException $e){
			throw new \Exception('数据库连接失败:'. $e->getMessage());
		}
	}
}

