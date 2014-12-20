<?php
/*
Plugin Name: 快速邮件发送
Plugin URI: http://www.loveyu.org/
Description: 通过队列优化邮件发送速度
Author: loveyu
Author URI: http://wwww.loveyu.org/
Version: 0.1
Text Domain: quick-mail
*/
if(!defined('ABSPATH')){
	exit;
}

define('QuickMail_SADD', "127.0.0.1"); //Python 服务器地址
define('QuickMail_SPORT', 27889); //Python 服务器端口

if(!function_exists('wp_mail')) {
	/**
	 * 该函数为对原始函数的覆盖函数
	 * @param string|array $to          收信人
	 * @param string       $subject     邮件标题
	 * @param string       $message     邮件正文
	 * @param string       $headers     头信息
	 * @param array        $attachments 附件列表
	 * @return bool
	 */
	function wp_mail($to, $subject, $message, $headers = '', $attachments = array()){
		$atts = apply_filters( 'wp_mail', compact( 'to', 'subject', 'message', 'headers', 'attachments' ) );

		if ( isset( $atts['to'] ) ) {
			$to = $atts['to'];
		}

		if ( isset( $atts['subject'] ) ) {
			$subject = $atts['subject'];
		}

		if ( isset( $atts['message'] ) ) {
			$message = $atts['message'];
		}

		if ( isset( $atts['headers'] ) ) {
			$headers = $atts['headers'];
		}

		if ( isset( $atts['attachments'] ) ) {
			$attachments = $atts['attachments'];
		}

		if ( ! is_array( $attachments ) ) {
			$attachments = explode( "\n", str_replace( "\r\n", "\n", $attachments ) );
		}
		global $phpmailer;

		// (Re)create it, if it's gone missing
		if ( !is_object( $phpmailer ) || !is_a( $phpmailer, 'PHPMailer' ) ) {
			require_once ABSPATH . WPINC . '/class-phpmailer.php';
			require_once ABSPATH . WPINC . '/class-smtp.php';
			$phpmailer = new PHPMailer( true );
		}

		// Headers
		if ( empty( $headers ) ) {
			$headers = array();
		} else {
			if ( !is_array( $headers ) ) {
				// Explode the headers out, so this function can take both
				// string headers and an array of headers.
				$tempheaders = explode( "\n", str_replace( "\r\n", "\n", $headers ) );
			} else {
				$tempheaders = $headers;
			}
			$headers = array();
			$cc = array();
			$bcc = array();

			// If it's actually got contents
			if ( !empty( $tempheaders ) ) {
				// Iterate through the raw headers
				foreach ( (array) $tempheaders as $header ) {
					if ( strpos($header, ':') === false ) {
						if ( false !== stripos( $header, 'boundary=' ) ) {
							$parts = preg_split('/boundary=/i', trim( $header ) );
							$boundary = trim( str_replace( array( "'", '"' ), '', $parts[1] ) );
						}
						continue;
					}
					// Explode them out
					list( $name, $content ) = explode( ':', trim( $header ), 2 );

					// Cleanup crew
					$name    = trim( $name    );
					$content = trim( $content );

					switch ( strtolower( $name ) ) {
						// Mainly for legacy -- process a From: header if it's there
						case 'from':
							if ( strpos($content, '<' ) !== false ) {
								// So... making my life hard again?
								$from_name = substr( $content, 0, strpos( $content, '<' ) - 1 );
								$from_name = str_replace( '"', '', $from_name );
								$from_name = trim( $from_name );

								$from_email = substr( $content, strpos( $content, '<' ) + 1 );
								$from_email = str_replace( '>', '', $from_email );
								$from_email = trim( $from_email );
							} else {
								$from_email = trim( $content );
							}
							break;
						case 'content-type':
							if ( strpos( $content, ';' ) !== false ) {
								list( $type, $charset ) = explode( ';', $content );
								$content_type = trim( $type );
								if ( false !== stripos( $charset, 'charset=' ) ) {
									$charset = trim( str_replace( array( 'charset=', '"' ), '', $charset ) );
								} elseif ( false !== stripos( $charset, 'boundary=' ) ) {
									$boundary = trim( str_replace( array( 'BOUNDARY=', 'boundary=', '"' ), '', $charset ) );
									$charset = '';
								}
							} else {
								$content_type = trim( $content );
							}
							break;
						case 'cc':
							$cc = array_merge( (array) $cc, explode( ',', $content ) );
							break;
						case 'bcc':
							$bcc = array_merge( (array) $bcc, explode( ',', $content ) );
							break;
						default:
							// Add it to our grand headers array
							$headers[trim( $name )] = trim( $content );
							break;
					}
				}
			}
		}

		// Empty out the values that may be set
		$phpmailer->ClearAllRecipients();
		$phpmailer->ClearAttachments();
		$phpmailer->ClearCustomHeaders();
		$phpmailer->ClearReplyTos();

		// From email and name
		// If we don't have a name from the input headers
		if ( !isset( $from_name ) )
			$from_name = 'WordPress';

		/* If we don't have an email from the input headers default to wordpress@$sitename
		 * Some hosts will block outgoing mail from this address if it doesn't exist but
		 * there's no easy alternative. Defaulting to admin_email might appear to be another
		 * option but some hosts may refuse to relay mail from an unknown domain. See
		 * http://trac.wordpress.org/ticket/5007.
		 */

		if ( !isset( $from_email ) ) {
			// Get the site domain and get rid of www.
			$sitename = strtolower( $_SERVER['SERVER_NAME'] );
			if ( substr( $sitename, 0, 4 ) == 'www.' ) {
				$sitename = substr( $sitename, 4 );
			}

			$from_email = 'wordpress@' . $sitename;
		}

		/**
		 * Filter the email address to send from.
		 *
		 * @since 2.2.0
		 *
		 * @param string $from_email Email address to send from.
		 */
		$phpmailer->From = apply_filters( 'wp_mail_from', $from_email );

		/**
		 * Filter the name to associate with the "from" email address.
		 *
		 * @since 2.3.0
		 *
		 * @param string $from_name Name associated with the "from" email address.
		 */
		$phpmailer->FromName = apply_filters( 'wp_mail_from_name', $from_name );

		// Set destination addresses
		if ( !is_array( $to ) )
			$to = explode( ',', $to );

		foreach ( (array) $to as $recipient ) {
			try {
				// Break $recipient into name and address parts if in the format "Foo <bar@baz.com>"
				$recipient_name = '';
				if( preg_match( '/(.*)<(.+)>/', $recipient, $matches ) ) {
					if ( count( $matches ) == 3 ) {
						$recipient_name = $matches[1];
						$recipient = $matches[2];
					}
				}
				$phpmailer->AddAddress( $recipient, $recipient_name);
			} catch ( phpmailerException $e ) {
				continue;
			}
		}

		// Set mail's subject and body
		$phpmailer->Subject = $subject;
		$phpmailer->Body    = $message;

		// Add any CC and BCC recipients
		if ( !empty( $cc ) ) {
			foreach ( (array) $cc as $recipient ) {
				try {
					// Break $recipient into name and address parts if in the format "Foo <bar@baz.com>"
					$recipient_name = '';
					if( preg_match( '/(.*)<(.+)>/', $recipient, $matches ) ) {
						if ( count( $matches ) == 3 ) {
							$recipient_name = $matches[1];
							$recipient = $matches[2];
						}
					}
					$phpmailer->AddCc( $recipient, $recipient_name );
				} catch ( phpmailerException $e ) {
					continue;
				}
			}
		}

		if ( !empty( $bcc ) ) {
			foreach ( (array) $bcc as $recipient) {
				try {
					// Break $recipient into name and address parts if in the format "Foo <bar@baz.com>"
					$recipient_name = '';
					if( preg_match( '/(.*)<(.+)>/', $recipient, $matches ) ) {
						if ( count( $matches ) == 3 ) {
							$recipient_name = $matches[1];
							$recipient = $matches[2];
						}
					}
					$phpmailer->AddBcc( $recipient, $recipient_name );
				} catch ( phpmailerException $e ) {
					continue;
				}
			}
		}

		// Set to use PHP's mail()
		$phpmailer->IsMail();

		// Set Content-Type and charset
		// If we don't have a content-type from the input headers
		if ( !isset( $content_type ) )
			$content_type = 'text/plain';

		/**
		 * Filter the wp_mail() content type.
		 *
		 * @since 2.3.0
		 *
		 * @param string $content_type Default wp_mail() content type.
		 */
		$content_type = apply_filters( 'wp_mail_content_type', $content_type );

		$phpmailer->ContentType = $content_type;

		// Set whether it's plaintext, depending on $content_type
		if ( 'text/html' == $content_type )
			$phpmailer->IsHTML( true );

		// If we don't have a charset from the input headers
		if ( !isset( $charset ) )
			$charset = get_bloginfo( 'charset' );

		// Set the content-type and charset

		/**
		 * Filter the default wp_mail() charset.
		 *
		 * @since 2.3.0
		 *
		 * @param string $charset Default email charset.
		 */
		$phpmailer->CharSet = apply_filters( 'wp_mail_charset', $charset );

		// Set custom headers
		if ( !empty( $headers ) ) {
			foreach( (array) $headers as $name => $content ) {
				$phpmailer->AddCustomHeader( sprintf( '%1$s: %2$s', $name, $content ) );
			}

			if ( false !== stripos( $content_type, 'multipart' ) && ! empty($boundary) )
				$phpmailer->AddCustomHeader( sprintf( "Content-Type: %s;\n\t boundary=\"%s\"", $content_type, $boundary ) );
		}

		if ( !empty( $attachments ) ) {
			foreach ( $attachments as $attachment ) {
				try {
					$phpmailer->AddAttachment($attachment);
				} catch ( phpmailerException $e ) {
					continue;
				}
			}
		}

		/**
		 * Fires after PHPMailer is initialized.
		 *
		 * @since 2.2.0
		 *
		 * @param PHPMailer &$phpmailer The PHPMailer instance, passed by reference.
		 */
		do_action_ref_array( 'phpmailer_init', array( &$phpmailer ) );

		//$phpmailer->Send();
		//将对象存入数据库
		$quick_mail = new QuickMail();
		$status = $quick_mail->quick_mail_save($insert_id, $phpmailer, $to, $subject);
		if(!$status){
			//一旦存入数据库失败将邮件重新之间发送
			try{
				if($phpmailer->send()){
					$quick_mail->quick_mail_set_flag($insert_id, 5);
					return true;
				} else{
					return false;
				}
			} catch(Exception $ex){
				return false;
			}
		}
		return true;
	}
}


class QuickMail{
	var $error = "";

	/**
	 * 启用数据库前对表的创建操作
	 */
	static function quick_mail_active(){
		/**
		 * @var WPDB $wpdb
		 */
		global $wpdb;
		$wpdb->query("DROP TABLE IF EXISTS `{$wpdb->prefix}quick_mail`");
		$wpdb->query("CREATE TABLE `{$wpdb->prefix}quick_mail` (
  `qm_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `qm_subject` varchar(255) NOT NULL COMMENT '邮件主题',
  `qm_time` int(10) unsigned NOT NULL COMMENT '发送时间',
  `qm_to` varchar(255) NOT NULL COMMENT '收信人',
  `qm_obj` text NOT NULL COMMENT 'phpmailer 序列化对象',
  `qm_status` tinyint(3) unsigned DEFAULT '0' COMMENT '状态，0为未执行状态，1为执行状态，2为完成状态, 3为发送失败, 4为发送异常,5直接发送成功',
  PRIMARY KEY (`qm_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;");
	}

	/**
	 * 设置邮件的状态
	 * @param int $id     邮件ID
	 * @param int $status 邮件状态 0-5
	 * @return bool 是否设置成功
	 */
	function quick_mail_set_flag($id, $status){
		$id = (int)$id;
		if($id < 1){
			return false;
		}
		/**
		 * @var WPDB $wpdb
		 */
		global $wpdb;
		$rt = $wpdb->update("{$wpdb->prefix}quick_mail", ['qm_status' => $status], ['qm_id' => $id]);
		return $rt > 0;
	}

	/**
	 * 将邮件信息保存到数据库中
	 * @param int       $insert_id
	 * @param PHPMailer $obj
	 * @param string    $to    邮件发送的对象
	 * @param string    $title 邮件标题
	 * @return bool 是否成功发送，如果失败会返回原函数继续执行
	 */
	function quick_mail_save(&$insert_id, PHPMailer $obj, $to, $title){
		if(is_array($to)){
			$to = implode(",", $to);
		}
		$insert_id = NULL;
		/**
		 * @var WPDB $wpdb
		 */
		global $wpdb;
		$ret = $wpdb->insert("{$wpdb->prefix}quick_mail", [
			'qm_subject' => $title,
			'qm_to' => $to,
			'qm_time' => time(),
			'qm_obj' => base64_encode(serialize($obj))
		]);
		if(!$ret){
			return false;
		}
		$insert_id = $wpdb->insert_id;
		return $wpdb->insert_id > 0 && $this->quick_mail_notify($wpdb->insert_id);
	}

	/**
	 * 通知邮件服务有新邮件需要发送
	 * @param string $id
	 * @return bool
	 */
	function quick_mail_notify($id){
		$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		if(!$socket){
			$this->error = socket_strerror(socket_last_error());
			return false;
		}
		if(!@socket_connect($socket, QuickMail_SADD, QuickMail_SPORT)){
			$this->error = socket_strerror(socket_last_error());
			return false;
		}
		if(!socket_write($socket, "{$id}")){
			socket_close($socket);
			$this->error = socket_strerror(socket_last_error());
			return false;
		}
		$flag = false;
		$buff = socket_read($socket, 1024);    //服务返回的状态特别短，因此一次读取是足够的
		$buff = strtolower(trim($buff));
		if($buff == "ok"){
			$flag = true;
		}
		socket_close($socket);
		$this->error = socket_strerror(socket_last_error());
		return $flag;
	}

	function add_menu(){
		add_submenu_page("tools.php", 'Quick Mail', '邮件管理', 'manage_options', __FILE__, [
			$this,
			'menu_page'
		]);
	}

	function menu_page(){
		$msg = '';
		if(isset($_POST['mid']) && !empty($_POST['mid'])){
			$mail_obj = $this->get_mail_content($_POST['mid']);
			if($mail_obj === false){
				$msg .= "<p style='color: orange'>查询邮件ID失败</p>";
			}
		}
		if(isset($_POST['clear_action'])){
			$msg .= $this->clear_action($_POST['clear_action']);
		}
		if(isset($_POST['q_mid'])){
			$msg .= $this->try_again($_POST['q_mid']);
		}
		$list = $this->get_mail_list();
		if(is_array($list)){
			include_once __DIR__ . "/manager.php";
		} else{
			echo "<h3>没有查询到任何邮件</h3><p>您已清空所有邮件或刚启用插件无邮件发送记录。</p>";
		}
	}

	private function clear_action($status){
		$status = (int)$status;
		if($status < 0 || $status > 6){
			return "<p style='color: red'>未知的清除操作</p>";
		}
		/**
		 * @var WPDB $wpdb
		 */
		global $wpdb;
		$rt = $wpdb->query("delete from {$wpdb->prefix}quick_mail where `qm_status` = {$status}");
		if($rt > 0){
			return "<p style='color: blue'>成功清除 {$rt} 条记录！</p>";
		}
		return "<p style='color: orange'>没有数据被清除!</p>";
	}

	private function get_mail_content($id){
		$id = (int)$id;
		if($id < 1){
			return false;
		}
		/**
		 * @var WPDB $wpdb
		 */
		global $wpdb;
		$rt = $wpdb->get_results("select `qm_obj` from {$wpdb->prefix}quick_mail where `qm_id` = {$id}");
		if(isset($rt[0]->qm_obj)){
			require_once ABSPATH . WPINC . '/class-phpmailer.php';
			require_once ABSPATH . WPINC . '/class-smtp.php';
			/**
			 * @var phpmailer $phpmailer
			 */
			$phpmailer = unserialize(base64_decode($rt[0]->qm_obj));
			if(is_object($phpmailer) && !empty($phpmailer->Body)){
				return [
					'Subject' => $phpmailer->Subject,
					'Body' => (strpos($phpmailer->ContentType, 'plain') !== false) ? str_replace(["\r\n"], ["<br>\r\n"], $phpmailer->Body) : $phpmailer->Body
				];
			}
		}
		return false;
	}

	private function  try_again($id){
		if($this->quick_mail_notify($id)){
			return "<p style='color: blue'>已成功发送请求，请等待几秒后刷新查看状态</p>";
		} else{
			return "<p style='color:red'>发送失败，请检查服务器记录！" . (!empty($this->error) ? "错误消息:" . $this->encoding_cov($this->error) : "") . "</p>";
		}
	}

	private function encoding_cov($str){
		$code = (preg_match('/^.*$/u', $str) > 0) ? "UTF-8" : "GB2312";
		if(strtolower($code) == "utf-8"){
			return $str;
		}
		$content = iconv($code, "utf-8//IGNORE", $str);
		if($content !== $str){
			return $content;
		}
		return $str;
	}

	private function get_mail_list(){
		/**
		 * @var WPDB $wpdb
		 */
		global $wpdb;
		//0为未执行状态，1为执行状态，2为完成状态, 3为发送失败, 4为发送异常,5直接发送成功
		$arr = [
			0 => '未执行',
			1 => '执行中',
			2 => '已完成',
			3 => '发送失败',
			4 => '发送异常',
			5 => '直接发送',
			6 => '提醒成功'
		];
		$result = $wpdb->get_results("select `qm_id`, `qm_subject`, `qm_time`, `qm_to`, `qm_status` from {$wpdb->prefix}quick_mail where 1 ORDER by `qm_id` desc limit 0,100");
		if($result && isset($result[0])){
			$rt = [];
			foreach($result as $v){
				$rt[] = [
					'id' => $v->qm_id,
					'title' => $v->qm_subject,
					'to' => $v->qm_to,
					'time' => date("Y-m-d H:i:s", $v->qm_time),
					'status' => isset($arr[$v->qm_status]) ? $arr[$v->qm_status] : "未知:" . $v->qm_status
				];
			}
			return $rt;
		} else{
			return false;
		}
	}
}

register_activation_hook(__FILE__, [
	'QuickMail',
	'quick_mail_active'
]);//插件激活操作
//register_deactivation_hook();
//register_uninstall_hook();

if(is_admin()){
	$quick_mail = new QuickMail();
	add_action('admin_menu', [
		$quick_mail,
		'add_menu'
	]);
}
date_default_timezone_set(get_option('timezone_string'));