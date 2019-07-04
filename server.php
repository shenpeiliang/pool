<?php
/**
 * 服务器启动
 */
declare(strict_types = 1);
use src\{MysqlPool,MysqlPdo,OptionDTO};

include './Loader.class.php';
// 注册自动加载
spl_autoload_register('Loader::autoload');

//mysql连接池
$pool = new MysqlPool(OptionDTO::create(10, 20, 10, 16)); //最少连接10，最大连接20， 超时10s，最大连接洪峰预警数16

$server = new \Swoole\Server('127.0.0.1', 9502);
$server->set([
	// 如开启异步安全重启, 需要在workerExit释放连接池资源
	'reload_async' => true,
	'worker_num' => 1
]);

//开启事件
$server->on('WorkerStart', function (\Swoole\Server $serv, int $worker_id) use ($pool) {
	//初始化连接池
	$pool->init();

	//定时清除空闲连接池
	if(!$serv->taskworker){
		//定时任务清除回收连接 1s
		$serv->tick(1000, function () use ($pool) {
			$pool->gcSpareConnection();
		});
	}

});

//退出事件
$server->on('WorkerExit', function (\Swoole\Server $serv, int $worker_id) use ($pool) {
	$pool->destruct();
});

//接收事件
$server->on('Receive', function (\Swoole\Server$serv, int $fd, int $reactor_id, string $data) use ($pool) {
	try
	{
		//从数据库连接池中获取一个协程客户端
		$mysql = $pool->getConnection();
		if(!$mysql){
			echo "获取数据库连接失败\n";
			return ;
		}

		//业务操作
		$pdo = new MysqlPdo();
		$pdo->setConnection($mysql);
		$res = $pdo->table('user')->where('uname = :uname')->field('id,uname,utype,score')->bind(array(':uname'=>'buyer'))->fetchRow();

		//释放连接
		$pool->free($mysql);

		//发送给客户端
		$serv->send($fd, json_encode($res));
	}
	catch(Exception $e)
	{
		echo '异常：'. $e->getMessage();
	}

});


$server->start();

