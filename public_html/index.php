<!DOCTYPE html>
<html lang="ja">
<head> 
<meta charset="UTF-8">
<title>rms_index</title>
</head>
<body>
<form method="post" name="form" action="../fetch_rms_data.php">
<select name="year">
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
for($m=1;$m<=12;$m++):?>
	<?php if($m!=$now_month-1):?>
		<option value="<?php echo $m;?>"><?php echo $m;?></option>
	<?php elseif($m==$now_month-1):?>
		<option value="<?php echo $now_month-1;?>"selected><?php echo $now_month-1;?></option>
	<?php endif;?>
<?php endfor;?>
</select>
月分<br>
<input type="submit"  value="請求書データ取得">
<br>
<br>
<a href="../senmonka-RMS.php">請求書作成ページはこちら</a>
<br>

<!--
<textarea name="mail_template"  cols=40 rows=4 disabled>
</textarea>
-->
</form>

</body>
</html>
