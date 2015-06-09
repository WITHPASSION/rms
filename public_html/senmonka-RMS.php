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
<style>
.offices{
	font-size: 12px;
	border-collapse: separate;
	border-spacing: 0px;
	border-top: 1px solid #ccc;
	border-left: 1px solid #ccc;
}
.offices th{
	padding: 4px;
	text-align: left;
	vertical-align: top;
	color: #444;
	background-color: #ccc;
	border-top: 1px solid #fff;
	border-left: 1px solid #fff;
	border-right: 1px solid #ccc;
	border-bottom: 1px solid #ccc;
}
.offices td{
	padding: 4px;
	background-color: #fafafa;
	border-right: 1px solid #ccc;
	border-bottom: 1px solid #ccc;
}
.right_txt {
	text-align: right;
}
.bold {
	font-weight: bold;
}
.bold_blue {
	font-weight: bold;
	color: blue;
}
.gray_down {
	color: #AAAAAA;
}
</style>
<script src="//code.jquery.com/jquery-2.1.3.min.js"></script>
<script type="text/javascript">
<!--
function changeYM() {
	var ym = $("#id_ym").val();
	var year = ym.substring(0, 4);
	var month = ym.substring(4, 6);
	form1.year.value = year;
	form1.month.value = month;
	form2.year.value = year;
	form2.month.value = month;
	form3.year.value = year;
	form3.month.value = month;

	$.getJSON(
		"/get_monthly_total.php",
		{'year' : year, 'month' : month},
		function(json) {
			var html = "<body>";
			var keys = Object.keys(json);
			for (i = 0; i < keys.length; i++) {
				var req_id = keys[i];
				var office = json[req_id];
				var ad_keys = Object.keys(office.advertisers);
				var office_row_count = 1;
				for (n = 0; n < ad_keys.length; n++) {
					var adv = office.advertisers[ad_keys[n]];
					var med_keys = Object.keys(adv.medias);
					for (z = 0; z < med_keys.length; z++) {
						var med = adv.medias[med_keys[z]];
						if (med.call_count > 0 || med.sample_call_count > 0 || med.mail_count > 0 || med.sample_mail_count > 0) {
							office_row_count++;
						}
					}
					office_row_count++;
				}
				var has_bill = false;
				if (office.call_count > 0 || office.mail_count > 0) {
					has_bill = true;
				}
				var office_named = false;
				for (n = 0; n < ad_keys.length; n++) {
					var ad_id = ad_keys[n];
					var adv = office.advertisers[ad_id];
					var med_keys = Object.keys(adv.medias);
					var adv_named = false;
					var adv_row_count = 1;
					for (z = 0; z < med_keys.length; z++) {
						var med = adv.medias[med_keys[z]];
						if (med.call_count > 0 || med.sample_call_count > 0 || med.mail_count > 0 || med.sample_mail_count > 0) {
							adv_row_count++;
						}
					}
					for (z = 0; z < med_keys.length; z++) {
						var med = adv.medias[med_keys[z]];
						var med_name = "";
						if (med.call_count == 0 && med.sample_call_count == 0 && med.mail_count == 0 && med.sample_mail_count == 0) {
							continue;
						}
						if (med_keys[z] == "shakkin") {
							med_name = "借金問題";
						}
						else if (med_keys[z] == "souzoku") {
							med_name = "相続問題";
						}
						else if (med_keys[z] == "koutsujiko") {
							med_name = "交通事故";
						}
						else if (med_keys[z] == "ninibaikyaku") {
							med_name = "任意売却";
						}
						else if (med_keys[z] == "meigihenkou") {
							med_name = "名義変更";
						}
						else if (med_keys[z] == "setsuritsu") {
							med_name = "会社設立";
						}
						else if (med_keys[z] == "keijijiken") {
							med_name = "刑事事件";
						}
						else if (med_keys[z] == "LP") {
							med_name = "Ｌ　Ｐ　";
						}
						if (med.payment_method.lastIndexOf('月額固定', 0) === 0) {
							//月額固定費の案件が有ればダウンロードボタン表示
							has_bill = true;
						}
						html += "<tr>";
						if (!office_named) {
							html += "<td rowspan='" + office_row_count + "'>" + req_id + "." + office.req_ad_name + "</td>";
							office_named = true;
						}
						if (!adv_named) {
							html += "<td rowspan='" + adv_row_count + "'>" + ad_id + "." + adv.office_name + "</td>";
							adv_named = true;
						}
						html += "<td>" + med_name + "<small>（" + med.payment_method + "）</small></td>";
						html += "<td class='right_txt'>" + med.call_count + "</td>";
						html += "<td class=''><small>(" + med.sample_call_count + ")</small></td>";
						html += "<td class='right_txt'>" + med.mail_count + "</td>";
						html += "<td class=''><small>(" + med.sample_mail_count + ")</small></td>";
						html += "</tr>";
					}
					html += "<tr>";
					if (!office_named) {
						html += "<td rowspan='" + office_row_count + "'>" + req_id + "." + office.req_ad_name + "</td>";
						office_named = true;
					}
					if (!adv_named) {
						html += "<td rowspan='" + adv_row_count + "'>" + ad_id + "." + adv.office_name + "</td>";
						adv_named = true;
					}
					html += "<td class='bold'>事務所計</td>";
					html += "<td class='right_txt bold'>" + adv.call_count + "</td>";
					html += "<td class=''><small>(" + adv.sample_call_count + ")</small></td>";
					html += "<td class='right_txt bold'>" + adv.mail_count + "</td>";
					html += "<td class=''><small>(" + adv.sample_mail_count + ")</small></td>";
					html += "</tr>";
				}
				html += "<tr>";
				if (has_bill) {
					html += "<td class='right_txt'><input type='button' value='請求書ダウンロード' onclick='download_bill(" + req_id + ")' style='font-size: 1.2em; font-weight: bold;'></td>";
					html += "<td class='bold_blue'>請求計</td>";
					html += "<td class='right_txt bold_blue'>" + office.call_count + "</td>";
					html += "<td class=''><small>(" + office.sample_call_count + ")</small></td>";
					html += "<td class='right_txt bold_blue'>" + office.mail_count + "</td>";
					html += "<td class=''><small>(" + office.sample_mail_count + ")</small></td>";
				}
				else {
					html += "<td></td>";
					html += "<td class='gray_down'>請求計</td>";
					html += "<td class='right_txt gray_down'>" + office.call_count + "</td>";
					html += "<td class='gray_down'><small>(" + office.sample_call_count + ")</small></td>";
					html += "<td class='right_txt gray_down'>" + office.mail_count + "</td>";
					html += "<td class='gray_down'><small>(" + office.sample_mail_count + ")</small></td>";
				}
				html += "</tr>";
			}
			html += "</body>";
			$("#office_table").html(html);
		}
	);
}

function download_bill(req_id) {
	document.form
	form2.change.value = req_id;
	return form2.submit();
}

$(function(){
	changeYM();
});
-->
</script>
</head>
<body>
<select id="id_ym" name="ym" onChange="changeYM()">
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
<br>
<form method="post" name="form1" action="create_monthly_details.php">
	<input type="hidden" name="year" value="">
	<input type="hidden" name="month" value="">
	<input type="submit" value="月次詳細情報ダウンロード" style="font-size: 1.2em; width: 300px;">
</form>
<form method="post" name="form3" action="create_bill.php">
	<input type="hidden" name="year" value="">
	<input type="hidden" name="month" value="">
	<input type="hidden" name="pack" value="true">
	<input type="submit" value="請求書一括ダウンロード" style="font-size: 1.2em; width: 300px;">
</form>
<form method="post" name="form2" action="create_bill.php">
	<input type="hidden" name="year" value="">
	<input type="hidden" name="month" value="">
	<input type="hidden" name="change" value="">
</form>
<br>
<table class="offices">
	<thead>
		<th>請求先</th>
		<th>事務所</th>
		<th>メディア</th>
		<th colspan="2">請求コール数 <small>(参考値)</small></th>
		<th colspan="2">請求メール数 <small>(参考値)</small></th>
	</thead>
	<tbody id="office_table">
	</tbody>
</table>
<br>
<br>
<br>
</body>
</html>
