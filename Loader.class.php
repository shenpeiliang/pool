<?php
/**
 * PSR-4规范
\<顶级命名空间>(\<子命名空间>)*\<类名>
PSR-4 规范中必须要有一个顶级命名空间，它的意义在于表示某一个特殊的目录（文件基目录）。子命名空间代表的是类文件相对于文件基目录的这一段
路径（相对路径），类名则与文件名保持一致（注意大小写的区别）
 * User: shenpeiliang
 * Date: 2019/1/9
 * Time: 10:39
 */
class Loader
{
	/**
	 * 路径映射
	 * @var array
	 */
	public static $psr4Map = [
		'src' => __DIR__ . DIRECTORY_SEPARATOR . 'src',
	];

	/**
	 * 文件后缀
	 * @var string
	 */
	public static $fileSuffixes = '.class.php';

	/**
	 * 自动加载处理
	 * @param String $class
	 */
	public static function autoload(String $class)
	{
		self::includeFile(self::parseFile($class));
	}

	/**
	 * 解析文件路径
	 * @param String $class
	 * @return String
	 */
	private static  function parseFile(String $class): String
	{
		//顶级命名空间
		$vendor = substr($class, 0, strpos($class, '\\'));

		//文件基目录
		if(!isset(self::$psr4Map[$vendor]))
			throw new \Exception('Vendor Not Found');

		$vendor_dir = self::$psr4Map[$vendor];

		//文件相对路径
		$file_path = substr($class, strlen($vendor)) . self::$fileSuffixes;
		// 文件绝对路径
		return strtr($vendor_dir . $file_path, '\\', DIRECTORY_SEPARATOR);
	}

	/**
	 * 包含文件
	 * @param String $file
	 */
	private static function includeFile(String $file)
	{
		if(!is_file($file))
			throw new \Exception('file Not Found');

		require_once $file;
	}
}
