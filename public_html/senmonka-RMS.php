<?php 
$pdo = null;
connect_db();
$ad_form_data = get_adformdata();

function connect_db() {
	global $pdo;

	$path = parse_ini_file("../rms.cnf");		
	foreach($path as $key => $db_path) {
		$configs = parse_ini_file($db_path);
	}
	foreach($configs as $key =>$value){
		if($key == "db_request") {
			$db_request = $value;
		}
		if($key == "db_cdr") {
			$db_portal = $value;
		}
		if($key == "host") {
			$host = $value;
		}	
		if($key == "name") {
			$name = $value;
		}
		if($key == "pass"){
			$pass = $value;
		}	
	}

	$dsn = "mysql:dbname=$db_request;host=$host";
	$user= "$name";
	$pass= "$pass";
	try {
		$pdo = new PDO($dsn,$user,$pass);
	}
	catch(PDOException $e) {
		exit ('接続ミス'.$e->getMessage());
	}
	$stmt = $pdo->query('SET NAMES utf8');
	if(!$stmt) {
		$info = $pdo->errorinfo();
		exit($info[2]);
	}
}
function get_adformdata() {
	global $pdo;
	$stmt = $pdo->query("SELECT req_id,req_ad_name FROM ad_req_data");
	return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_when_selected_time_call_data($year, $month) {
	global $pdo;
	$stmt = $pdo->query("SELECT req_id,year,month FROM ad_monthly_valid_call WHERE year=$year AND month=$month");
	return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_when_selected_time_mail_data($year, $month) {
	global $pdo;
	$stmt = $pdo->query("SELECT req_id FROM ad_monthly_mail_num WHERE year=$year AND month=$month");
	return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="ja">
<head> 
<meta charset="UTF-8">
<title>request_manager</title>
<script src="//code.jquery.com/jquery-2.1.3.min.js"></script>
<script type="text/javascript">
<!--
function changeYM() {
	var year = $("#id_year").val();
	var month = $("#id_month").val();
	form1.year.value = year;
	form1.month.value = month;
	form2.year.value = year;
	form2.month.value = month;
}

$(function(){
	changeYM();
});
-->
</script>
</head>
<body>
<select id="id_year" name="year" onChange="changeYM()">
<?php 
$now_year =date("Y");
for($y=2004;$y<=($now_year-1);$y++):
?>
<option value="<?php echo $y;?>"><?php echo $y;?></option>
<?php endfor;?>
		<option value="<?php echo $now_year;?>"selected><?php echo $now_year;?></option>
</select>
年
<select id="id_month" name="month" onChange="changeYM()">
<?php 
$now_month = date("n");
for($m=01;$m<=12;$m++):?>
	<?php if($m!=$now_month-1):?>
		<option value="<?php echo $m;?>"><?php echo $m;?></option>
	<?php elseif($m==$now_month-1):?>
		<option value="<?php echo $now_month-1;?>"selected><?php echo $now_month-1;?></option>
	<?php endif;?>
<?php endfor;?>
</select>
月分
<br>
<form method="post" name="form1" action="create_monthly_details.php">
	<input type="hidden" name="year" value="">
	<input type="hidden" name="month" value="">
	<input type="submit" value="月次詳細情報ダウンロード">
</form>
<br>
<form method="post" name="form2" action="../create_bill.php">
	<input type="hidden" name="year" value="">
	<input type="hidden" name="month" value="">
	<select name="change" style="font-size:35px" > 
		<option>選択して下さい</option>
<?php foreach($ad_form_data as $row):?>
		<option value="<?php echo $row['req_id'];?>"><?php echo $row['req_id'],"_",$row['req_ad_name'];?></option>
<?php endforeach; ?>		
	</select> <input type="submit"  value="請求書ダウンロード">
<br>
<br>
<!--
<textarea name="mail_template"  cols=40 rows=4 disabled>
</textarea>
-->
</form>

</body>
</html>
