#Wordpress快速邮件发送，外加Pushbullet通知
## 修改配置
### quick_mail.py
这里的信息主要为对Python程序文件进行配置

	PHP_SCRIPT = "/wordpress/quick_mail.php" # 你的qucik_mail.php文件路径，一般可存放在wordpress根目录
	S_IP = "127.0.0.1" # 当前服务监听的IP地址
	S_PORT = 2789 # 当前监听的端口

### quick_mail.php 执行修改
这里主要涉及到一些服务器的参数，数据库信息，Pushbullet信息等等

	define('DB_NAME','{Your database}'); // 数据库名称
	define('DB_HOST', '127.0.0.1'); // 数据库地址
	define('DB_USER', '{Your database user}'); // 数据库用户名
	define('DB_PWD', '{Your database password}'); // 数据库密码
	define('DB_TABLE', 'wp_quick_mail'); // 你的Wordpress建立的表名称，默认为wp_quick_mail
	define('WP_ROOT_DIR', __DIR__); // Wordpress 根目录，默认当前目录
	define('EXCLUDE_MAIL', '{Your email}'); // 要排除发送邮件的地址，对此地址使用Pushbullet进行通知
	define('PushBullet_KEY', '{Your push key}'); // 你的Pushbullet key密钥
	define('PushBullet_DRIVER', NULL); // 是否指定推送的设备，默认NULL，推送全部设备

### wordpress插件修改
如果你服务器Python监听信息进行修改这里也要进行对应的修改否者会导致连接失败

	define('QuickMail_SADD', "127.0.0.1"); //Python 服务器地址
	define('QuickMail_SPORT', 27889); //Python 服务器端口

## 安装
* Python 环境为 Py3，直接执行就好

> nohup ./quick_mail.py >> /var/log/quick_mail.log 2>&1 & 

* Wordpress 插件安装，复制loveyu-quick-mail*(quick-mail插件冲突)*文件夹过去，然后启用插件就好，或者压缩后上传

## 后台管理
启用插件后在`工具` > `邮件管理` 下有一个列表信息，可以进行清空等操作

## 反馈

> [http://www.loveyu.org/3811.html](http://www.loveyu.org/3811.html)