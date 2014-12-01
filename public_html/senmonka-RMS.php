<!DOCTYPE html>
<html lang="ja">
<head> 
<meta charset="UTF-8">
<title>request_manager</title>
<?php 
$pdo=null;
connect_db();
$ad_form_data = get_adformdata();
$arr_call_req_id = get_when_selected_time_call_data(2014,9);
#$arr_mail_req_id = get_when_selected_time_mail_data(2014,9);
foreach($arr_call_req_id as $r):?>
<script>
function getvalidreqdata(){
		var selectyear = document.forms.form.year;
		var selectmonth = document.forms.form.month;
		var selectreqdata = document.forms.form.change;
		month.options.length = 0;
		if(selectyear.options.[selectyear.selectedIndex].value ==<?php $r['year']?>)
		{
			document.write("成功");
		}
}
</script>
</head>
<?php
	$call_req_id = $r['req_id'];
endforeach;
######funciton#########################################################
function connect_db(){
		$path = parse_ini_file("../rms.cnf");		
		foreach($path as $key => $db_path){
				$configs =parse_ini_file($db_path);
		}
		foreach($configs as $key =>$value){
				if($key =="db_request"){
				$db_request = $value;
				}
				if($key =="db_cdr"){
				$db_portal = $value;
				}
				if($key =="host"){
				$host = $value;
				}	
				if($key =="name"){
				$name = $value;
				}
				if($key =="pass"){
				$pass = $value;
				}	
		}
		global $pdo;
		$dsn = "mysql:dbname=$db_request;host=$host";
		$user= "$name";
		$pass= "$pass";
		try{
				$pdo = new PDO($dsn,$user,$pass);
		}catch(PDOException $e){
				exit ('接続ミス'.$e->getMessage());
		}
		$stmt = $pdo->query('SET NAMES utf8');
		if(!$stmt){
				$info = $pdo->errorinfo();
				exit($info[2]);
		}
}
function get_adformdata(){
		global $pdo;
		$stmt = $pdo->query("SELECT req_id,req_ad_name FROM ad_req_data");
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_when_selected_time_call_data($year,$month){
		global $pdo;
		$stmt = $pdo->query("SELECT req_id,year,month FROM ad_monthly_valid_call WHERE year=$year AND month=$month");
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function get_when_selected_time_mail_data($year,$month){
	global $pdo;
	$stmt = $pdo->query("SELECT req_id FROM ad_monthly_mail_num WHERE year=$year AND month=$month");
	return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
#######endofdunction########################################################
?>
<body>
<form method="post" name="form" action="../create_bill.php">
<select name="year" onChange="getvalidreqdata()">
<?php 
$now_year =date("Y");
for($y=2004;$y<=($now_year-1);$y++):
?>
<option value="<?php echo $y;?>"><?php echo $y;?></option>
<?php endfor;?>
		<option value="<?php echo $now_year;?>"selected><?php echo $now_year;?></option>
</select>
年
<select name="month">
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
月分<br>
	<select name="change" style="font-size:35px" > 
		<option>選択して下さい</option>
<?php foreach($ad_form_data as $row):?>
		<option value="<?php echo $row['req_id'];?>"><?php echo $row['req_id'];  echo $row['req_ad_name'];?></option>
<?php endforeach; ?>		
	</select>
<br>
<br>
<input type="submit"  value="請求書作成">
<br>
<br>
<!--
<textarea name="mail_template"  cols=40 rows=4 disabled>
</textarea>
-->
</form>

</body>
</html>
