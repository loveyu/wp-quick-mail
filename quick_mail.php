<?php
/**
 * 快速邮件发送执行脚本
 * @author loveyu admin@loveyu.info
 */

define('DB_NAME','{Your database}'); // 数据库名称
define('DB_HOST', '127.0.0.1'); // 数据库地址
define('DB_USER', '{Your database user}'); // 数据库用户名
define('DB_PWD', '{Your database password}'); // 数据库密码
define('DB_TABLE', 'wp_quick_mail'); // 你的Wordpress建立的表名称，默认为wp_quick_mail
define('WP_ROOT_DIR', __DIR__); // Wordpress 根目录，默认当前目录
define('EXCLUDE_MAIL', '{Your email}'); // 要排除发送邮件的地址，对此地址使用Pushbullet进行通知
define('PushBullet_KEY', '{Your push key}'); // 你的Pushbullet key密钥
define('PushBullet_DRIVER', NULL); // 是否指定推送的设备，默认NULL，推送全部设备

$qm_id = isset($argv[1]) ? $argv[1] : NULL; //此处的设置将只允许通过命令行来执行
if($qm_id === NULL){
	die("param error");
}
$qm_id = (int)$qm_id;
if($qm_id < 1){
	die("mail id read error!");
}
try{
	$pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PWD);
} catch(Exception $ex){
	die($ex->getMessage());
}
$stmt = $pdo->prepare("select `qm_to`,`qm_obj` from `".DB_TABLE."` where `qm_id`={$qm_id} and `qm_status`=0");
if(!$stmt->execute()){
	die("read database error");
}
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt->closeCursor();
set_flag($pdo, $qm_id, 1);
if(!isset($data[0]['qm_obj'])){
	die("read mail error:" . $qm_id);
}

require_once WP_ROOT_DIR . '/wp-includes/class-phpmailer.php';
require_once WP_ROOT_DIR . '/wp-includes/class-smtp.php';

/**
 * @var $mail PHPMailer
 */
$mail = unserialize(base64_decode($data[0]['qm_obj']));
try{
	if(strtolower($data[0]['qm_to']) == EXCLUDE_MAIL){
		if(notice($mail)){
			set_flag($pdo, $qm_id, 6);
			die("notice ok:" . $qm_id);
		}
	}
	if($mail->send()){
		set_flag($pdo, $qm_id, 2);
		die("send ok:" . $qm_id);
	} else{
		set_flag($pdo, $qm_id, 3);
		die("send fail:" . $qm_id);
	}
} catch(Exception $ex){
	set_flag($pdo, $qm_id, 4);
	die($ex->getMessage());
}

function set_flag(PDO $pdo, $qm_id, $status){
	$status = (int)$status;
	$qm_id = (int)$qm_id;
	$stmt = $pdo->prepare("update `".DB_TABLE."` set `qm_status` = {$status} where `qm_id`={$qm_id}");
	if(!$stmt->execute()){
		die("mail set status error.ID:" . $qm_id . ",status:" . $status);
	}
}

/**
 * @param $mail PHPMailer
 * @return bool
 */
function notice($mail){
	$body = [];
	foreach(explode("\n",strip_tags($mail->Body)) as $v){
		$v = trim($v);
		if(empty($v))continue;
		$body[] = $v;
	}
	$data = _push(PushBullet_DRIVER, "note", $mail->Subject, count($body)?implode("\n",$body):"【消息为空】");
	if($data == false){
		return false;
	}
	return isset($data['active']) && $data['active'];
}

/**
 * Send a push.
 * @param string $recipient Recipient of the push.
 * @param mixed  $type      Type of the push notification.
 * @param mixed  $arg1      Property of the push notification.
 * @param mixed  $arg2      Property of the push notification.
 * @param mixed  $arg3      Property of the push notification.
 * @return object Response.
 * @link https://github.com/ivkos/PushBullet-for-PHP
 */
function _push($recipient, $type, $arg1, $arg2 = NULL, $arg3 = NULL){
	$PUSH = "https://api.pushbullet.com/v2/pushes";
	$queryData = array();
	if(!empty($recipient)){
		if(filter_var($recipient, FILTER_VALIDATE_EMAIL) !== false){
			$queryData['email'] = $recipient;
		} else{
			if(substr($recipient, 0, 1) == "#"){
				$queryData['channel_tag'] = substr($recipient, 1);
			} else{
				$queryData['device_iden'] = $recipient;
			}
		}
	}
	$queryData['type'] = $type;
	switch($type){
		case 'note':
			$queryData['title'] = $arg1;
			$queryData['body'] = $arg2;
			break;
		case 'link':
			$queryData['title'] = $arg1;
			$queryData['url'] = $arg2;
			if($arg3 !== NULL){
				$queryData['body'] = $arg3;
			}
			break;
		case 'address':
			$queryData['name'] = $arg1;
			$queryData['address'] = $arg2;
			break;
		case 'list':
			$queryData['title'] = $arg1;
			$queryData['items'] = $arg2;
			break;
		default:
			return false;
	}
	return _curlRequest($PUSH, 'POST', $queryData);
}

/**
 * Send a request to a remote server using cURL.
 * @param string $url        URL to send the request to.
 * @param string $method     HTTP method.
 * @param array  $data       Query data.
 * @param bool   $sendAsJSON Send the request as JSON.
 * @param bool   $auth       Use the API key to authenticate
 * @return object Response.
 * @link https://github.com/ivkos/PushBullet-for-PHP
 */
function _curlRequest($url, $method, $data = NULL, $sendAsJSON = true, $auth = true){
	$curl = curl_init();
	if($method == 'GET' && $data !== NULL){
		$url .= '?' . http_build_query($data);
	}
	curl_setopt($curl, CURLOPT_URL, $url);
	if($auth){
		curl_setopt($curl, CURLOPT_USERPWD, PushBullet_KEY);
	}
	curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
	if($method == 'POST' && $data !== NULL){
		if($sendAsJSON){
			$data = json_encode($data);
			curl_setopt($curl, CURLOPT_HTTPHEADER, array(
				'Content-Type: application/json',
				'Content-Length: ' . strlen($data)
			));
		}
		curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
	}
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_HEADER, false);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
	$response = curl_exec($curl);
	if($response === false){
		echo curl_error($curl);
		curl_close($curl);
		return false;
	}
	$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
	if($httpCode >= 400){
		echo curl_error($curl);
		curl_close($curl);
		return false;
	}
	curl_close($curl);
	return json_decode($response, true);
}