<?php
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

$types = [
	"shakkin",
	"souzoku",
	"koutsujiko",
	"ninibaikyaku",
	"meigihenkou",
	"setsuritsu",
	"keijijiken"
];

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
$call_data = get_monthly_total_calls($year_month);
$mail_data = get_monthly_total_mails($year_month);

$ret = array();
foreach ($billing_offices as $office) {
	if (!array_key_exists($office["req_id"], $ret)) {
		$o = array();
		$o["req_ad_name"] = $office["req_ad_name"];
		$o["call_count"] = 0;
		$o["mail_count"] = 0;
		$o["advertisers"] = array();
		$ret += array($office["req_id"] => $o);
	}
	$a = array();
	$a["office_name"] = $office["office_name"];
	$a["call_count"] = 0;
	$a["mail_count"] = 0;
	$a["medias"] = array();
	foreach($types as $type) {
		$mc = array();
		$mc["call_count"] = 0;
		$mc["mail_count"] = 0;
		$a["medias"] += array($type => $mc);
	}
	$mc = array();
	$mc["call_count"] = 0;
	$mc["mail_count"] = 0;
	$a["medias"] += array("LP" => $mc);
	$ret[$office["req_id"]]["advertisers"] += array($office["advertiser_id"] => $a);
}

foreach ($call_data as $call) {
	$req_id = $call["req_id"];
	if ($req_id == null) {
		continue;
	}
	$ad_id = $call["advertiser_id"];
	$media_id = $call["media_id"];
	$count = $call["valid_tel_count"];
	if ($ad_id == null) {
		$ret[$req_id]["call_count"] = $count;
	}
	else if ($media_id == null) {
		$ret[$req_id]["advertisers"][$ad_id]["call_count"] = $count;
	}
	else {
		if ($media_id == "B"){
			$ret[$req_id]["advertisers"][$ad_id]["medias"]["souzoku"]["call_count"] += $count;
		}
		else if ($media_id == "C"){
			$ret[$req_id]["advertisers"][$ad_id]["medias"]["koutsujiko"]["call_count"] += $count;
			}
		else if ($media_id == "D"){
			$ret[$req_id]["advertisers"][$ad_id]["medias"]["ninibaikyaku"]["call_count"] += $count;
		}
		else if ($media_id == "E"){
			$ret[$req_id]["advertisers"][$ad_id]["medias"]["meigihenkou"]["call_count"] += $count;
		}
		else if ($media_id == "F"){
			$ret[$req_id]["advertisers"][$ad_id]["medias"]["setsuritsu"]["call_count"] += $count;
		}
		else if ($media_id == "G"){
			$ret[$req_id]["advertisers"][$ad_id]["medias"]["keijijiken"]["call_count"] += $count;
		}
		else if ($media_id == "A-LPPC" || $media_id == "A-LPSmart"){
			$ret[$req_id]["advertisers"][$ad_id]["medias"]["LP"]["call_count"] += $count;
		}
		else {
			$ret[$req_id]["advertisers"][$ad_id]["medias"]["shakkin"]["call_count"] += $count;
		}
	}
}

foreach ($mail_data as $mail) {
	$req_id = $mail["req_id"];
	if ($req_id == null) {
		continue;
	}
	$ad_id = $mail["advertiser_id"];
	$group = $mail["site_group"];
	$count = $mail["valid_mail_count"];
	if ($ad_id == null) {
		$ret[$req_id]["mail_count"] = $count;
	}
	else if ($group == null) {
		$ret[$req_id]["advertisers"][$ad_id]["mail_count"] = $count;
	}
	else {
		if ($group == 0){
			$ret[$req_id]["advertisers"][$ad_id]["medias"]["shakkin"]["mail_count"] += $count;
		}
		else if ($group == 1){
			$ret[$req_id]["advertisers"][$ad_id]["medias"]["souzoku"]["mail_count"] += $count;
		}
		else if ($group == 2){
			$ret[$req_id]["advertisers"][$ad_id]["medias"]["koutsujiko"]["mail_count"] += $count;
			}
		else if ($group == 3){
			$ret[$req_id]["advertisers"][$ad_id]["medias"]["ninibaikyaku"]["mail_count"] += $count;
		}
		else if ($group == 4){
			$ret[$req_id]["advertisers"][$ad_id]["medias"]["meigihenkou"]["mail_count"] += $count;
		}
		else if ($group == 5){
			$ret[$req_id]["advertisers"][$ad_id]["medias"]["setsuritsu"]["mail_count"] += $count;
		}
		else if ($group == 6){
			$ret[$req_id]["advertisers"][$ad_id]["medias"]["keijijiken"]["mail_count"] += $count;
		}
		else if ($group == 999){
			$ret[$req_id]["advertisers"][$ad_id]["medias"]["LP"]["mail_count"] += $count;
		}
	}
}

echo json_encode($ret);

function get_billing_office_list() {
	global $pdo_request;
	$stmt = $pdo_request->query("
		SELECT
			r.req_id as req_id,
			r.req_ad_name as req_ad_name,
			a.ID as advertiser_id,
			a.office_name as office_name
		FROM
			smk_request_data.adid_reqid_matching as m,
			smk_request_data.ad_req_data as r,
			wordpress.ss_advertisers as a
		WHERE
			r.req_id = m.reqid AND
			a.ID = m.adid
		ORDER BY r.req_id, a.ID
	");
	$res_arr = $stmt->fetchAll(PDO::FETCH_ASSOC);
	return $res_arr;
}

function get_monthly_total_calls($year_month) {
	global $pdo_request;
	$stmt = $pdo_request->query("
		SELECT
			m.reqid as req_id,
			v.advertiser_id as advertiser_id,
			v.media_id as media_id,
			count(v.id) as valid_tel_count
		FROM
			cdr.call_data_view v,
			smk_request_data.adid_reqid_matching m
		WHERE
			m.adid = v.advertiser_id AND
			DATE_FORMAT(v.date_from, '%Y%m') = '$year_month' AND
			v.dpl_tel_cnt <= 1 AND
			v.dpl_mail_cnt = 0 AND
			v.call_minutes >= 60
		GROUP BY
			m.reqid,
			v.advertiser_id,
			v.media_id
		WITH ROLLUP
	");
	$res_arr = $stmt->fetchAll(PDO::FETCH_ASSOC);
	return $res_arr;
}

function get_monthly_total_mails($year_month) {
	global $pdo_request;
	$stmt = $pdo_request->query("
		SELECT
			m.reqid as req_id,
			v.advertiser_id as advertiser_id,
			s.site_group as site_group,
			count(v.ID) as valid_mail_count
		FROM
			cdr.mail_conv v,
			smk_request_data.adid_reqid_matching m,
			wordpress.ss_site_type s
		WHERE
			s.site_type = v.site_type AND
			m.adid = v.advertiser_id AND
			DATE_FORMAT(v.register_dt, '%Y%m') = '$year_month' AND
			v.dpl_tel_cnt <= 0 AND
			v.dpl_mail_cnt <= 0
		GROUP BY
			m.reqid,
			v.advertiser_id,
			s.site_group
		WITH ROLLUP
	");
	$res_arr = $stmt->fetchAll(PDO::FETCH_ASSOC);
	return $res_arr;
}
?>