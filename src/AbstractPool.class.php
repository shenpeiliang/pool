<?php
/**
 * Created by PhpStorm.
 * User: shenpeiliang
 * Date: 2019/6/20
 * Time: 11:39
 */
namespace src;
use src\OptionDTO;
use Swoole\Coroutine\Channel;
use Think\Exception;

abstract class AbstractPool
{
	private $min = 0; //最少连接数
	private $max = 0; //最大连接数
	private $count = 0; //当前已经创建的连接数
	private $connections = NULL; //连接池组
	private $spareTime = 0; //用于空闲连接回收判断
	private $maxConnection = 0; //连接数洪峰预警数
	protected $available = false; //连接池是否可用 - 用于平滑重启进程

	/**
	 * 获取连接池对象
	 * @return PDO
	 */
	protected abstract function createPoolObject(): \PDO;

	public function __construct(OptionDTO $option)
	{
		if(!$option instanceof OptionDTO)
			throw new \Exception('配置项错误');

		$this->min = $option->min;
		$this->max = $option->max;
		$this->spareTime = $option->spareTime;
		$this->maxConnection = $option->maxConnection;
	}

	/**
	 * 获取连接池对象
	 * @return AbstractPool
	 */
	protected function getPoolObject(): \PDO
	{
		try{
			$pool = $this->createPoolObject();

			$pool->last_used_time = time();

			return $pool;

		}catch(\PDOException $e){
			throw new \Exception($e->getMessage());
		}
	}

	/**
	 * 初始化最少连接池
	 */
	public function init(): AbstractPool
	{
		if($this->available)
			return null;

		try{
			//初始化协程通道
			$this->connections = new Channel($this->max + 1);

			for($i = 0; $i < $this->min; $i++){
				//创建连接
				$obj = $this->getPoolObject();

				//放入池中
				$this->connections->push($obj);

				//更新已创建的连接数
				$this->count++;
			}

			$this->available = true;

			return $this;

		}catch(\Exception $e){
			throw new \Exception($e->getMessage());
		}

	}

	/**
	 * 获取连接中的链接对象
	 * @param int $timeOut 出队最大等待时间
	 * @return mixed
	 */
	public function getConnection(int $timeOut = 1): \PDO
	{
		//连接池没有空闲连接且已创建的连接数小于最大连接数
		if($this->available && $this->connections->isEmpty() && $this->count < $this->max){
			//创建新连接
			$connection = $this->getPoolObject();

			//更新已创建的连接数
			$this->count++;

		}else{ //连接池还有空闲连接
			$connection = $this->connections->pop($timeOut);
		}

		return $connection;
	}

	/**
	 * 手动释放连接（返回连接池）
	 * @param AbstractPool $pool
	 */
	public function free(\PDO $pool){
		$this->connections->push($pool);
	}

	/**
	 * 连接池销毁 (进程退出)
	 */
	public function destruct(){
		//连接池销毁, 置不可用状态, 防止新的客户端进入常驻连接池, 导致服务器无法平滑退出
		$this->available = false;
		while (!$this->connections->isEmpty()) {
			$this->connections->pop(0.001);
		}
	}

	/**
	 * 回收空闲连接（定时清除）
	 */
	public function gcSpareConnection()
	{
		//达到连接数洪峰预警数进行回收
		if($this->connections->length() > $this->maxConnection){
			//记录可以继续使用的连接
			$list = [];

			while(!$this->connections->isEmpty()){
				//出队
				$obj = $this->connections->pop(0.001);
				if(!$obj) //出队超时
					continue;

				//连接是否空闲超时
				if(time() < $obj->last_used_time + $this->spareTime){
					//未超时继续使用
					array_push($list, $obj);
				}else{
					//删除连接后更新已创建的连接数
					$this->count--;
				}

			}

			//放回未过期的连接到池
			if($list){
				foreach ($list as $item) {
					$this->connections->push($item);
				}
			}

			//释放
			unset($list);
		}
	}


}