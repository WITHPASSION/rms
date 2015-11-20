<?php
#共通関数のインクルード
include 'common_functions.php';

#エラーを画面に表示させない処理
//ini_set("display_errors", "off");
#データベース接続処理
#db接続データの参照
$path = parse_ini_file("../rms.cnf");		
foreach($path as $key => $db_path) {
	$configs = parse_ini_file($db_path);
}
foreach($configs as $key => $value) {
	switch ($key) {
		case 'db_cdr':
			$db_cdr = $value;
			break;
		case 'db_request':
			$db_request = $value;
			break;
		case 'db_wordpress':
			$db_wordpress = $value;
			break;
		case 'host':
			$host = $value;
			break;
		case 'name':
			$name = $value;
			break;
		case 'pass':
			$pass = $value;
			break;
	}
}

#グローバル変数
$pdo_request = null;
$pdo_cdr = null;
$pdo_wordpress = null;

$types = array(
	"shakkin",
	"souzoku",
	"koutsujiko",
	"ninibaikyaku",
	"meigihenkou",
	"setsuritsu",
	"keijijiken",
	"rikon"
);

#cdrへの接続
$dsn_cdr ="mysql:dbname=$db_cdr;host=$host";
try {	
	$pdo_cdr = new PDO($dsn_cdr, $name, $pass);
} catch (PDOException $e) {
	exit('接続ミス'.$e->getMessage());
}
$stmt = $pdo_cdr->query('SET NAMES utf8');
if (!$stmt) {
	$info=$pdo_cdr->errorinfo();
	exit($info[2]);
}

#smk_request_dataへの接続
$dsn_request ="mysql:dbname=$db_request;host=$host";
try {	
	$pdo_request = new PDO($dsn_request,$name,$pass);
} catch (PDOException $e) {
	exit('接続ミス'.$e->getMessage());
}
$stmt = $pdo_request->query('SET NAMES utf8');
if(!$stmt) {
	$info=$pdo_request->errorinfo();
	exit($info[2]);
}

#wordpressへの接続
$dsn_wordpress = "mysql:dbname=$db_wordpress;host=$host";
try {	
	$pdo_wordpress = new PDO($dsn_wordpress,$name,$pass);
} catch(PDOException $e) {
	exit('接続ミス'.$e->getMessage());
}
$stmt=$pdo_wordpress->query('SET NAMES utf8');
if(!$stmt) {
	$info = $pdo_wordpress->errorinfo();
	exit($info[2]);
}

#フォームからの年月の受け取り
$year = $_GET['year'];
$month = $_GET['month'];
#一桁の月に"0"を付加
$month = sprintf("%02d",$month);
$year_month = "$year"."$month";

$billing_offices = get_billing_office_list();
$call_data = get_monthly_total_calls($year_month, CALL_TYPE_VALID);
$sample_call_data = get_monthly_total_calls($year_month, CALL_TYPE_SAMPLE);
$mail_data = get_monthly_total_mails($year_month, MAIL_TYPE_VALID);
$sample_mail_data = get_monthly_total_mails($year_month, MAIL_TYPE_SAMPLE);

$ret = array();
foreach ($billing_offices as $office) {
	$bill_payer_id = $office["bill_payer_id"];
	if (!array_key_exists($bill_payer_id, $ret)) {
		$o = array();
		$o["bill_payer_name"] = $office["bill_payer_name"];
		$o["call_count"] = 0;
		$o["sample_call_count"] = 0;
		$o["mail_count"] = 0;
		$o["sample_mail_count"] = 0;
		$o["ad_groups"] = array();
		$ret += array($bill_payer_id => $o);
	}
	if (!array_key_exists($office["ad_group_id"], $ret[$bill_payer_id]["ad_groups"])) {
		$g = array();
		$g["group_name"] = $office["group_name"];
		$g["call_count"] = 0;
		$g["sample_call_count"] = 0;
		$g["mail_count"] = 0;
		$g["sample_mail_count"] = 0;
		$g["advertisers"] = array();
		$ret[$bill_payer_id]["ad_groups"] += array($office["ad_group_id"] => $g);
	}
	$a = array();
	$a["office_name"] = $office["office_name"];
	$a["call_count"] = 0;
	$a["sample_call_count"] = 0;
	$a["mail_count"] = 0;
	$a["sample_mail_count"] = 0;
	$a["medias"] = array();
	foreach($types as $type) {
		$mc = array();
		$mc["call_count"] = 0;
		$mc["sample_call_count"] = 0;
		$mc["mail_count"] = 0;
		$mc["sample_mail_count"] = 0;
		$a["medias"] += array($type => $mc);
	}
	$mc = array();
	$mc["call_count"] = 0;
	$mc["sample_call_count"] = 0;
	$mc["mail_count"] = 0;
	$mc["sample_mail_count"] = 0;
	$a["medias"] += array("LP" => $mc);
	$ret[$bill_payer_id]["ad_groups"][$office["ad_group_id"]]["advertisers"] += array($office["advertiser_id"] => $a);
}

foreach ($call_data as $call) {
	$bill_payer_id = $call["bill_payer_id"];
	if ($bill_payer_id == null) {
		continue;
	}
	$ad_group_id = $call["ad_group_id"];
	$ad_id = $call["advertiser_id"];
	$site_group = $call["site_group"];
	$count = $call["tel_count"];
	$payment_method = $call["payment_method"];
	if ($ad_group_id == null) {
		$ret[$bill_payer_id]["call_count"] = $count;
	}
	else if ($ad_id == null) {
		$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["call_count"] = $count;
	}
	else if ($site_group == null) {
		$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["call_count"] = $count;
	}
	else {
		if ($site_group == 1){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["souzoku"]["call_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["souzoku"]["payment_method"] = $payment_method;
		}
		else if ($site_group == 2){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["koutsujiko"]["call_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["koutsujiko"]["payment_method"] = $payment_method;
			}
		else if ($site_group == 3){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["ninibaikyaku"]["call_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["ninibaikyaku"]["payment_method"] = $payment_method;
		}
		else if ($site_group == 4){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["meigihenkou"]["call_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["meigihenkou"]["payment_method"] = $payment_method;
		}
		else if ($site_group == 5){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["setsuritsu"]["call_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["setsuritsu"]["payment_method"] = $payment_method;
		}
		else if ($site_group == 6){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["keijijiken"]["call_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["keijijiken"]["payment_method"] = $payment_method;
		}
		else if ($site_group == 7){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["rikon"]["call_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["rikon"]["payment_method"] = $payment_method;
		}
		else {
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["shakkin"]["call_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["shakkin"]["payment_method"] = $payment_method;
		}
	}
}

foreach ($sample_call_data as $call) {
	$bill_payer_id = $call["bill_payer_id"];
	if ($bill_payer_id == null) {
		continue;
	}
	$ad_group_id = $call["ad_group_id"];
	$ad_id = $call["advertiser_id"];
	$site_group = $call["site_group"];
	$count = $call["tel_count"];
	$payment_method = $call["payment_method"];
	if ($ad_group_id == null) {
		$ret[$bill_payer_id]["sample_call_count"] = $count;
	}
	else if ($ad_id == null) {
		$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["sample_call_count"] = $count;
	}
	else if ($site_group == null) {
		$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["sample_call_count"] = $count;
	}
	else {
		if ($site_group == 1){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["souzoku"]["sample_call_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["souzoku"]["payment_method"] = $payment_method;
		}
		else if ($site_group == 2){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["koutsujiko"]["sample_call_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["koutsujiko"]["payment_method"] = $payment_method;
			}
		else if ($site_group == 3){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["ninibaikyaku"]["sample_call_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["ninibaikyaku"]["payment_method"] = $payment_method;
		}
		else if ($site_group == 4){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["meigihenkou"]["sample_call_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["meigihenkou"]["payment_method"] = $payment_method;
		}
		else if ($site_group == 5){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["setsuritsu"]["sample_call_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["setsuritsu"]["payment_method"] = $payment_method;
		}
		else if ($site_group == 6){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["keijijiken"]["sample_call_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["keijijiken"]["payment_method"] = $payment_method;
		}
		else if ($site_group == 7){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["rikon"]["sample_call_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["rikon"]["payment_method"] = $payment_method;
		}
		else {
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["shakkin"]["sample_call_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["shakkin"]["payment_method"] = $payment_method;
		}
	}
}

foreach ($mail_data as $mail) {
	$bill_payer_id = $mail["bill_payer_id"];
	if ($bill_payer_id == null) {
		continue;
	}
	$ad_group_id = $mail["ad_group_id"];
	$ad_id = $mail["advertiser_id"];
	$group = $mail["site_group"];
	$count = $mail["mail_count"];
	$payment_method = $mail["payment_method"];
	if ($ad_group_id == null) {
		$ret[$bill_payer_id]["mail_count"] = $count;
	}
	else if ($ad_id == null) {
		$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["mail_count"] = $count;
	}
	else if ($group == null) {
		$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["mail_count"] = $count;
	}
	else {
		if ($group == 0){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["shakkin"]["mail_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["shakkin"]["payment_method"] = $payment_method;
		}
		else if ($group == 1){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["souzoku"]["mail_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["souzoku"]["payment_method"] = $payment_method;
		}
		else if ($group == 2){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["koutsujiko"]["mail_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["koutsujiko"]["payment_method"] = $payment_method;
			}
		else if ($group == 3){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["ninibaikyaku"]["mail_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["ninibaikyaku"]["payment_method"] = $payment_method;
		}
		else if ($group == 4){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["meigihenkou"]["mail_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["meigihenkou"]["payment_method"] = $payment_method;
		}
		else if ($group == 5){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["setsuritsu"]["mail_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["setsuritsu"]["payment_method"] = $payment_method;
		}
		else if ($group == 6){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["keijijiken"]["mail_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["keijijiken"]["payment_method"] = $payment_method;
		}
		else if ($group == 7){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["rikon"]["mail_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["rikon"]["payment_method"] = $payment_method;
		}
	}
}

foreach ($sample_mail_data as $mail) {
	$bill_payer_id = $mail["bill_payer_id"];
	if ($bill_payer_id == null) {
		continue;
	}
	$ad_group_id = $mail["ad_group_id"];
	$ad_id = $mail["advertiser_id"];
	$group = $mail["site_group"];
	$count = $mail["mail_count"];
	$payment_method = $mail["payment_method"];
	if ($ad_group_id == null) {
		$ret[$bill_payer_id]["sample_mail_count"] = $count;
	}
	else if ($ad_id == null) {
		$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["sample_mail_count"] = $count;
	}
	else if ($group == null) {
		$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["sample_mail_count"] = $count;
	}
	else {
		if ($group == 0){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["shakkin"]["sample_mail_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["shakkin"]["payment_method"] = $payment_method;
		}
		else if ($group == 1){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["souzoku"]["sample_mail_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["souzoku"]["payment_method"] = $payment_method;
		}
		else if ($group == 2){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["koutsujiko"]["sample_mail_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["koutsujiko"]["payment_method"] = $payment_method;
			}
		else if ($group == 3){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["ninibaikyaku"]["sample_mail_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["ninibaikyaku"]["payment_method"] = $payment_method;
		}
		else if ($group == 4){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["meigihenkou"]["sample_mail_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["meigihenkou"]["payment_method"] = $payment_method;
		}
		else if ($group == 5){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["setsuritsu"]["sample_mail_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["setsuritsu"]["payment_method"] = $payment_method;
		}
		else if ($group == 6){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["keijijiken"]["sample_mail_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["keijijiken"]["payment_method"] = $payment_method;
		}
		else if ($group == 7){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["rikon"]["sample_mail_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["rikon"]["payment_method"] = $payment_method;
		}
	}
}

echo json_encode($ret);

?>