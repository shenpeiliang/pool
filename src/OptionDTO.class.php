<?php
/**
 * Created by PhpStorm.
 * User: shenpeiliang
 * Date: 2019/6/20
 * Time: 16:22
 */

namespace src;

/**
 * 数据传输对象
 * Class OptionDTO
 */
class OptionDTO
{
	public $min; //最少连接数
	public $max; //最大连接数
	public $spareTime; //用于空闲连接回收判断  超时时间应小于mysql会话超时时间，因为没有定时唤醒mysql，mysql会自动关闭连接
	public $maxConnection; //连接数洪峰预警数

	/**
	 * 创建传输对象
	 * @param int $min
	 * @param int $max
	 * @param int $spareTime
	 * @param int $maxConnection
	 * @return OptionDTO
	 */
	public static  function create(int $min, int $max, int $spareTime, int $maxConnection): OptionDTO{
		$self = new self();
		$self->min = $min;
		$self->max = $max;
		$self->spareTime = $spareTime;
		$self->maxConnection = $maxConnection;

		return $self;
	}
}