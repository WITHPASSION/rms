<!DOCTYPE html>
<html lang="ja">
<head> 
<meta charset="UTF-8">
<title>rms_index</title>
</head>
<body>
<form method="post" name="form" action="../fetch_rms_data.php">
<select name="ym">
<?php 
$now_ym = date("Ym01");
$default_ym = date("Ym01", strtotime($now_ym.' -1 month'));
$startdt = '20140901';
$i = 0;
while (true) {
	$d = date('Ym01', strtotime($startdt.' +'.$i.' month'));
	$d1 = date('Ym', strtotime($d));
	$d2 = date('Y年m月', strtotime($d));
	if ($d == $default_ym) {
?>
<option value="<?php echo $d1;?>" selected><?php echo $d2;?></option>
<?php
	} else {
?>
<option value="<?php echo $d1;?>"><?php echo $d2;?></option>
<?php
	}
	if ($d == $now_ym) {
		break;
	}
	$i++;
}
?>
</select>分
<br>
<input type="submit"  value="請求書データ生成">
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
