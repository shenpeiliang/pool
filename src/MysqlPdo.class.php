<?php
/**
 * MysqlPDO封装类
 * @author shenpeiliang
 * 20170829
 * 标注：此类的条件、更新使用到了预处理绑定，因此查询时要统一占位符，另更新条件中只能使用命名占位符
 * where('id = :id or sess_val = :val')
 * where(array('id = :id', 'sess_val = :val'))->bind(array(':id'=>17,':val'=>'c2'))
 * where(array('id = ?', 'sess_val = ?'))->bind(array(1=>17,2=>'c2'))
 * field('id,name') / field(array('id','name'))
 * order('id desc,expire asc') / order(array('id desc','expire asc'))
 * 更新set(array('sess_val'=>time(),'sess_key'=>30))->update()
 * 删除where(array('id = ?', 'sess_val = ?'))->bind(array(1=>17,2=>'c2'))->delete()
 * （只支持单条保存）插入set(array('sess_val'=>time(),'sess_key'=>30))->insert()
 *
 * 单例：
 * $pdo = MysqlPdo::getInstance($config);
 *
 *
 * 事务支持
自动方式
$this->transStart();
..
$this->transComplete();

手动方式
$this->transBegin();
$this->transRollback();
$this->transCommit();

 */
namespace src;
class MysqlPdo {
	/**
	 * 实例
	 * @var unknown
	 */
	protected static $_instance = null;

	public $connection = NULL;	//mysql对象
	protected $statement = NULL;	//预处理对象
	public $prefix = '';	//表前缀
	protected $debug = FALSE;	//是否开启调试模式
	protected $dsn = '';	//数据库dsn地址
	protected $charset = 'utf8';	//数据库编码格式
	//链式操作
	protected $table = '';
	//查询语句
	protected $where = array();
	protected $field = array();
	protected $order = array();
	/**
	 * 更新的值
	 * @var unknown
	 */
	protected $data = [];
	protected $param_data = [];

	protected $limit = '';

	protected $sql = '';
	//预处理绑定-查询
	protected $param = [];

	private $config = array(
		'debug' => 'false',
		'prefix' => '',
		'host' => '127.0.0.1',
		'database' => '',
		'user' => '',
		'passwd' => '',
		'persistent' => 'true',//是否持久化连接
		'charset' => 'utf8'
	);

	/**
	 * 事务状态
	 * @var unknown
	 */
	protected $transStatus = TRUE;

	/**
	 * 记录当前事务是否回滚
	 * @var unknown
	 */
	protected $rollbacked = FALSE;

	/**
	 * 事务嵌套级别
	 * @var unknown
	 */
	protected $transDepth = 0;

	/**
	 * 初始化
	 * @param unknown $config
	 */
	public function __construct(array $config = []){
		//配置初始化
		//$this->_parseConfig($config);
		//连接数据库
		//$this->_parseConnect();
	}

	/**
	 * 使用连接池
	 * @param PDO $mysql
	 */
	public function setConnection(\PDO $mysql){
		$this->connection = $mysql;
		$this->prefix = 'uv_';
	}


	/**
	 * 配置
	 */
	private function _parseConfig(array $config){
		if(!empty($config)) $this->config = array_merge($this->config,$config);

		$this->prefix = isset($this->config['prefix']) ? $this->config['prefix'] : '';

		$this->debug = empty($this->config['debug']) ? FALSE : TRUE;

		$this->charset = empty($this->config['charset']) ? $this->charset : $this->config['charset'];

		$this->dsn = 'mysql:host='.$this->config['host'].';dbname='.$this->config['database'];

	}
	/**
	 * 解析连接
	 */
	private function _parseConnect(){
		//实例化mysql对象
		try{

			$options = array(
				PDO::ATTR_PERSISTENT => isset($this->config['persistent']) ? $this->config['persistent'] : FALSE , //是否持久化连接
				PDO::ATTR_EMULATE_PREPARES => FALSE ,//启用或禁用预处理语句的模拟 ;使用此设置强制PDO总是模拟预处理语句（如果为 TRUE ），或试着使用本地预处理语句（如果为 FALSE ）
				PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES ' . $this->charset , //编码类型
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC ,//设置默认的提取模式 ;返回一个索引为结果集列名的数组
				PDO::ATTR_ERRMODE  =>  PDO::ERRMODE_WARNING //抛出错误异常
			);

			$this->connection = new \PDO($this->dsn, $this->config['user'], $this->config['passwd'], $options);

		}catch(PDOException $e){

			$this->_err($e->getMessage());

		}
	}
	/**
	 * 实例化对象
	 * @return PDO Connection
	 */
	public static function getInstance(array $config = []): MysqlPdo{
		if(null === self::$_instance){
			self::$_instance = new self($config);
		}
		return self::$_instance;
	}
	/**
	 * 获取预处理查询的sql语句
	 * @return string
	 */
	public function getSql(): string {
		return $this->sql;
	}
	/**
	 * 获取预处理绑定参数
	 * @return string|array
	 */
	public function getParam(): array{
		return $this->param;
	}
	/**
	 * 获取预处理绑定参数 Update
	 * @return string|array
	 */
	public function getDataParam(): array{
		return $this->param_data;
	}
	/**
	 * 组建绑定预处理 Select
	 * @return MysqlPdo
	 */
	private function _buildParam(): MysqlPdo{
		if(empty($this->param))
			return $this;
		foreach ($this->param as $key => $val){
			$this->statement->bindParam($key, $val);
		}
		return $this;
	}
	/**
	 * 组建绑定预处理 Update
	 * @return MysqlPdo
	 */
	private function _buildParamData(): MysqlPdo {
		if(empty($this->param_data))
			return $this;
		foreach ($this->param_data as $key => $val){
			$this->statement->bindParam($key, $val);
		}
		return $this;
	}
	/**
	 * 组建查询sql
	 */
	private function _buildSelect(): string {
		$this->sql = 'SELECT '
			. $this->buildField()
			. ' FROM ' . $this->table
			. $this->buildWhere()
			. $this->buildOrder()
			. $this->limit;
		return $this->sql;
	}
	/**
	 * 组建更新sql
	 */
	private function _buildUpdate(): string {
		$this->sql = 'UPDATE '
			. $this->table
			. $this->buildSet()
			. $this->buildWhere();
		return $this->sql;
	}
	/**
	 * 组建删除sql
	 */
	private function _buildDelete(): string {
		$this->sql = 'DELETE FROM '
			. $this->table
			. $this->buildWhere();
		return $this->sql;
	}
	/**
	 * 组建插入sql
	 * @return string
	 */
	private function _buildInsert(): string {
		$this->sql = 'INSERT INTO '
			. $this->table
			. $this->buildInsert();
		return $this->sql;
	}
	/**
	 * 构建插入sql
	 * @return string
	 */
	protected function buildInsert(): string {
		if (!$this->data) {
			return false;
		}
		$insert = ' ( ';
		$count = count($this->data);
		$i = 0;
		//字段名
		$insert_key = '';
		//字段值
		$insert_val = '';
		foreach($this->data as $key => $value){
			$fill = ($i == ($count - 1)) ? ' ' : ' , ';
			$insert_key .= ' ' . $key . $fill;
			$insert_val .= ' :' . $key . $fill;
			//绑定预处理
			$this->param_data [':' . $key] = $value;
			$i++;
		}
		$insert .= $insert_key . ' ) VALUE (' . $insert_val;
		$insert .= ' ) ';
		return $insert;
	}
	/**
	 * 构建where查询
	 * @return string
	 */
	protected function buildWhere(): string {
		if (!$this->where) {
			return '';
		}
		$where = ' WHERE ';
		$count = count($this->where);
		for ($i = 0; $i < $count; $i++){
			$fill = ($i == ($count - 1)) ? ' ' : ' AND ';
			$where .= $this->where[$i] . $fill;
		}
		return $where;
	}
	/**
	 * 构建更新sql
	 * @return string
	 */
	protected function buildSet(): string {
		if (!$this->data) {
			return false;
		}
		$set = ' SET ';
		$count = count($this->data);
		$i = 0;
		foreach($this->data as $key => $value){
			$fill = ($i == ($count - 1)) ? ' ' : ' , ';
			$set .= ' ' . $key . ' = :' . $key . $fill;
			//绑定预处理
			$this->param_data [':' . $key] = $value;
			$i++;
		}
		return $set;
	}
	/**
	 * 构建order查询
	 * @return string
	 */
	protected function buildField(): string {
		if (!$this->field) {
			return '*';
		}
		$field = ' ';
		$count = count($this->field);
		for ($i = 0; $i < $count; $i++){
			$fill = ($i == ($count - 1)) ? ' ' : ' , ';
			$field .= $this->field[$i] . $fill;
		}
		return $field;
	}
	/**
	 * 构建order查询
	 * @return string
	 */
	protected function buildOrder(): string {
		if (!$this->order) {
			return '';
		}
		$order = ' ORDER BY ';
		$count = count($this->order);
		for ($i = 0; $i < $count; $i++){
			$fill = ($i == ($count - 1)) ? ' ' : ' , ';
			$order .= $this->order[$i] . $fill;
		}
		return $order;
	}
	/**
	 * 更新字段信息
	 * @param unknown $value
	 * @return MysqlPdo
	 */
	public function set($value): MysqlPdo {
		if(empty($value) || !is_array($value))
			return false;
		foreach ($value as $key => $value){
			$this->data[$key] = $value;
		}
		return $this;
	}
	/**
	 * 查询字段信息
	 * @param string $value string|array
	 * @return MysqlPdo
	 */
	public function field($value): MysqlPdo{
		if(empty($value))
			return $this;
		if(is_array($value)){
			foreach ($value as $item){
				$this->field[] = $item;
			}
		}else{
			$this->field[] = $value;
		}
		return $this;
	}
	/**
	 * 表名设置
	 * @param unknown $value string
	 * @return MysqlPdo
	 */
	public function table($value): MysqlPdo{
		if(empty($value) || !is_string($value))
			return $this;
		$this->table = $this->prefix . $value;
		return $this;
	}
	/**
	 * 查询条件
	 * @param $value string|array
	 * name=:name / array('name:name','age:age')
	 */
	public function where($value): MysqlPdo{
		if(empty($value))
			return $this;
		if(is_array($value)){
			foreach ($value as $item){
				$this->where[]= $item;
			}
		}else{
			$this->where[]= $value;
		}
		return $this;
	}
	/**
	 * 查询条件
	 * @param $value array
	 */
	public function bind(array $value): MysqlPdo{
		if(!is_array($value) || empty($value))
			return $this;
		foreach ($value as $key => $val){
			if(!is_int($key)){//非问号占位符
				if(strpos($key, ':') === false){//是否以':'开头
					$key = ':' . trim($key);
				}
			}
			$this->param [$key] = $val;
		}
		return $this;
	}
	/**
	 * 排序
	 * @param unknown $value string|array
	 * @return MysqlPdo|boolean
	 */
	public function order($value): MysqlPdo{
		if(empty($value))
			return $this;
		if(is_array($value)){
			foreach ($value as $item){
				$this->order[] = $item;
			}
		}else{
			$this->order[] = $value;
		}
		return $this;
	}
	/**
	 * 查询数量
	 * @param unknown $value string|array
	 * @return MysqlPdo
	 */
	public function limit($value): MysqlPdo
	{
		if(empty($value))
			return $this;
		if(is_array($value)){
			$limit = (int) $value[0];
			$offset = (int) $value[1];
		}elseif (is_string($value)){
			$str_limit = explode(',', $value);
			if(empty($str_limit))
				return $this;
			$limit = (int)$str_limit[0];
			$offset = isset($str_limit[1]) ? (int)$str_limit[1] : 0;
		}
		$this->limit = 'LIMIT ' . $limit . ',' .$offset;
		return $this;
	}
	/**
	 * 新增数据
	 * @return boolean|int
	 */
	public function insert(): int{
		$this->_buildInsert();
		if($this->execute()){
			return $this->connection->lastInsertId();
		}
		return false;
	}
	/**
	 * 删除数据
	 * @return boolean|int
	 */
	public function delete(): int{
		$this->_buildDelete();
		if($this->execute()){
			return $this->statement->rowCount();
		}
		return false;
	}
	/**
	 * 更新操作
	 * @return boolean|int
	 */
	public function update(): int{
		$this->_buildUpdate();
		if($this->execute()){
			return $this->statement->rowCount();
		}
		return false;
	}

	/**
	 * 执行操作
	 * @return boolean
	 */
	public  function execute(): bool {
		/*使用长连接为避免出现错误： MySQL server has gone away in 出现，在使用query前都要判断
		有没有连接，close之后再重新创建连接
		*/
		if(!$this->connection){
			$this->close();
			$this->_parseConnect();
		}
		if($this->connection){
			if(empty($this->sql)){
				$this->_err('Error: Cannot find sql statement !<br/>');
				return false;
			}
			if($this->statement = $this->connection->prepare($this->sql)){
				//预处理绑定-查询条件
				$this->_buildParam();
				//预处理绑定-更新、插入
				$this->_buildParamData();
				//执行操作
				if($this->statement->execute()){
					return true;
				}
				//事务记录
				$this->transStatus = FALSE;
				$this->_err('Error: The execute failure !<br/>');
				return false;
			}
			$this->_err('Error: The prepare failure !<br/>');
			return false;
		}
		return false;
	}
	/**
	 * 返回结果数据（一维关联数据）
	 * @return Ambigous <multitype:, mixed>|boolean
	 */
	public function fetchRow(): array{
		$this->_buildSelect();
		if($this->execute()){
			return $this->statement->fetch();
		}
		return false;
	}
	/**
	 * 返回结果数据（一维关联数据）
	 * @return Ambigous <multitype:, mixed>|boolean
	 */
	public function fetchAll(): array{
		$this->_buildSelect();
		if($this->execute()){
			return $this->statement->fetchAll();
		}
		return false;
	}
	/**
	 * 事务状态
	 * @return unknown
	 */
	public function transStatus(): bool{
		return $this->transStatus;
	}
	/**
	 * 事务开启-自动
	 * @return MysqlPdo
	 */
	public function transStart(){
		$this->connection->beginTransaction();
	}
	/**
	 * 事务提交 -自动
	 * @return MysqlPdo
	 */
	public function transComplete(): bool{
		if($this->transStatus === FALSE){
			$this->connection->rollBack();
			return false;
		}
		$this->connection->commit();
		return true;
	}
	/**
	 * 开启事务 - 手动
	 * @return boolean
	 */
	public function transBegin(): bool{
		if($this->transDepth++ > 0){
			return true;
		}
		//$this->connection->exec('SET AUTOCOMMIT = 0');
		$this->connection->setAttribute(PDO::ATTR_AUTOCOMMIT, FALSE);
		$this->connection->beginTransaction();
		return true;
	}
	/**
	 * 事务回滚 - 手动
	 * @return boolean
	 */
	public function transRollback(): bool{
		if(--$this->transDepth > 0){
			$this->rollbacked = TRUE;
			return true;
		}
		$this->connection->rollBack();
		$this->rollbacked = FALSE;
		//$this->connection->exec('SET AUTOCOMMIT = 1');
		$this->connection->setAttribute(PDO::ATTR_AUTOCOMMIT, TRUE);
		return true;
	}
	/**
	 * 提交事务 - 手动
	 * @return boolean
	 */
	public function transCommit(): bool{
		if(--$this->transDepth > 0){
			$this->rollbacked = TRUE;
			return true;
		}
		if($this->rollbacked){
			$this->connection->rollBack();
			$result = FALSE;
		}else{
			$this->connection->commit();
			$result = TRUE;
		}
		$this->rollbacked = FALSE;
		//$this->connection->exec('SET AUTOCOMMIT = 1');
		$this->connection->setAttribute(PDO::ATTR_AUTOCOMMIT, TRUE);
		return $result;
	}
	/**
	 * 检查连接是否可用
	 * @param string $connection
	 * @return boolean
	 */
	public function pdo_ping($connection = NULL): bool{
		try{
			$connection->getAttribute(PDO::ATTR_SERVER_INFO);
		}catch (PDOException $e){
			if(strpos($e->getMessage(),'MySQL server has gone away') != false){
				return false;
			}
		}
		return true;
	}
	/**
	 * 关闭连接
	 */
	public function close(){
		$this->connection = NULL;
	}
	/**
	 * 输出错误信息
	 * @param unknown $msg
	 */
	private function _err($msg){
		if($this->debug){
			echo $msg;
		}
	}
}