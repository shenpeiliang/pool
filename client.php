<?php
/**
 * 客户端模拟请求
 */
use Swoole\Client;
//https://wiki.swoole.com/wiki/page/p-client.html
/* 异步
$client = new Client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_SYNC);
$client->on("connect", function($cli) {
	$cli->send("hello world\n");
});

$client->on("receive", function($cli, $data) {
	echo "received: $data\n";
	sleep(1);
	$cli->send("hello\n");
});

$client->on("close", function($cli){
	echo "closed\n";
});

$client->on("error", function($cli){
	exit("error\n");
});
*/
$client = new Client(SWOOLE_SOCK_TCP);
if (!$client->connect('127.0.0.1', 9502, -1))
{
	exit("connect failed. Error: {$client->errCode}\n");
}
$client->send("hello world\n");
echo $client->recv(). "\n";
$client->close();
