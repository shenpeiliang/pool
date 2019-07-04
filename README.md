php很少有说到数据库连接池的概念，这里使用swoole做简单的数据库连接池

数据库连接池主要有几个概念：

1、创建连接：连接池启动后，初始化一定的空闲连接，指定为最少的连接min。当连接池为空，不够用时，创建新的连接放到池里，但不能超过指定的最大连接max数量；

2、连接释放：每次使用完连接，一定要调用释放方法，把连接放回池中，给其他程序或请求使用；

3、连接分配：连接池中用pop和push的方式对等入队和出队分配与回收。能实现阻塞分配，也就是在池空并且已创建数量大于max，阻塞一定时间等待其他请求的连接释放，超时则返回null；

4、连接管理：对连接池中的连接，定时检活和释放空闲连接；


图解：

![image](https://github.com/shenpeiliang/pool/blob/master/images/img_1.jpg)

运行环境：

docker环境（php+mysql+redis），其中php需要安装swoole扩展

![image](https://github.com/shenpeiliang/pool/blob/master/images/img_2.png)

测试步骤：

进入php容器：

docker-compose exec php7.3 bash

进入代码目录开启服务：

cd /var/www/html/swoole

php ./server.php


进入mysql容器：

docker-compose exec mysql-master bash

mysql命令行：

mysql -h127.0.0.1 -uroot -p -Ddocker

#查看连接

show full processlist;

未开启服务前，如图：

![image](https://github.com/shenpeiliang/pool/blob/master/images/img_3.png)

未开启服务后，如图：

![image](https://github.com/shenpeiliang/pool/blob/master/images/img_4.png)


连接池配置了最小连接为10，最大连接数为20，连接空闲时间为10s（空闲回收判断，应小于mysql会话超时时间）,最大连接洪峰预警数位16（超过这个值的时候就进行回收处理）


并发测试：

ab -c 80 -n 2000 http://192.168.137.129/swoole/client.php

结果：

![image](https://github.com/shenpeiliang/pool/blob/master/images/img_5.png)


