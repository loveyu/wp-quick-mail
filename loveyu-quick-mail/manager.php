<?php
/**
 * @var array     $list
 * @var array $mail_obj
 * @var string    $msg
 */
?>
<style>
	#Quick-Mail table{text-align:left;width:100%;margin-top:15px;border-collapse:collapse;}
	#Quick-Mail table thead th{border-bottom:1px solid #888;line-height:2;}
	#Quick-Mail table td a{text-decoration:none;}
	#Quick-Mail table td{line-height:1.8;border-bottom:solid 1px #aaa;padding-left:2px;}
	#Quick-Mail table tbody tr:hover{background:#fff;}
	.qm_action{margin:15px 0;}
	.qm_action label{font-weight:600;}
	#message{margin-left:0;font-weight:800;}
	#Outbox{background:#fff;border:solid 2px #000;padding:4px 4px 15px 4px;width:70%;position:absolute;z-index:9999;left:15%;top:10%;}
	#Outbox h2.title{line-height:2;margin:0 0 10px 0;padding:0;background:#eee;}
	#Outbox h2.title a{float:right;margin:0 5px 0 0;text-decoration:none;padding:0;}
</style>
<h2>邮件发送管理</h2>
<?php if(!empty($msg)): ?>
	<div id="message" class="updated"><?php echo $msg ?></div>
<?php endif; ?>
<div id="Quick-Mail">
	<form class="qm_action" action="" method="post">
		<label for="QM-action">清除操作：</label>
		<select id="QM-action" name="clear_action">
			<option value="2">已完成</option>
			<option value="6">提醒成功</option>
			<option value="5">直接发送</option>
			<option value="1">执行中</option>
			<option value="0">未执行</option>
			<option value="3">发送失败</option>
			<option value="4">发送异常</option>
		</select>
		<button type="submit" class="button button-primary">提交</button>
	</form>
	<table>
		<thead>
		<tr>
			<th>ID</th>
			<th>时间</th>
			<th>标题</th>
			<th>收信人</th>
			<th>状态</th>
		</tr>
		</thead>
		<tbody>
		<?php foreach($list as $value): ?>
			<tr>
				<td><?php echo $value['id'] ?></td>
				<td><?php echo $value['time'] ?></td>
				<td><?php echo $value['title'] ?><a href="#" onclick="return preview_mail(<?php echo $value['id'] ?>)">[预览]</a></td>
				<td><?php echo $value['to'] ?></td>
				<td><?php echo $value['status'];
					if($value['status'] == "未执行"):?>
						<a href="#" onclick="return send_again(<?php echo $value['id'] ?>)">[重试]</a>
					<?php endif; ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<form id="PostForm" action="" method="post"><input name="mid" value="" type="hidden"></form>
	<form id="PostForm2" action="" method="post"><input name="q_mid" value="" type="hidden"></form>
</div>
<div id="Outbox" style="display: none"></div>
<?php if(isset($mail_obj) && is_array($mail_obj)): ?>
	<script>
		var mail_obj = <?php echo json_encode($mail_obj,JSON_UNESCAPED_UNICODE)?>;
		var out = jQuery("#Outbox");
		out.append("<h2 class='title'>"+mail_obj.Subject+"<a href='#' onclick='mail_close()'>X</a></h2>");
		out.append("<div class='content'>"+mail_obj.Body+"</div>");
		out.show("fast");
		function mail_close(){
			out.hide("fast");
			return false;
		}
	</script>
<?php endif; ?>
<script>
	function preview_mail(id) {
		jQuery("#PostForm input").val(id);
		jQuery("#PostForm").submit();
		return false;
	}
	function send_again(id){
		jQuery("#PostForm2 input").val(id);
		jQuery("#PostForm2").submit();
		return false;
	}
</script>