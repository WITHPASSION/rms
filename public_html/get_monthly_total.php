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
$call_data = get_monthly_total_calls($year_month, false);
$sample_call_data = get_monthly_total_calls($year_month, true);
$mail_data = get_monthly_total_mails($year_month, false);
$sample_mail_data = get_monthly_total_mails($year_month, true);

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
	$media_id = $call["media_id"];
	$count = $call["valid_tel_count"];
	$payment_method = $call["payment_method"];
	if ($ad_group_id == null) {
		$ret[$bill_payer_id]["call_count"] = $count;
	}
	else if ($ad_id == null) {
		$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["call_count"] = $count;
	}
	else if ($media_id == null) {
		$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["call_count"] = $count;
	}
	else {
		if ($media_id == "B"){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["souzoku"]["call_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["souzoku"]["payment_method"] = $payment_method;
		}
		else if ($media_id == "C"){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["koutsujiko"]["call_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["koutsujiko"]["payment_method"] = $payment_method;
			}
		else if ($media_id == "D"){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["ninibaikyaku"]["call_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["ninibaykyaku"]["payment_method"] = $payment_method;
		}
		else if ($media_id == "E"){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["meigihenkou"]["call_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["meigihenkou"]["payment_method"] = $payment_method;
		}
		else if ($media_id == "F"){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["setsuritsu"]["call_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["setsuritsu"]["payment_method"] = $payment_method;
		}
		else if ($media_id == "G"){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["keijijiken"]["call_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["keijijiken"]["payment_method"] = $payment_method;
		}
		else if ($media_id == "H"){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["rikon"]["call_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["rikon"]["payment_method"] = $payment_method;
		}
		else if ($media_id == "A-LPPC" || $media_id == "A-LPSmart"){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["LP"]["call_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["LP"]["payment_method"] = $payment_method;
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
	$media_id = $call["media_id"];
	$count = $call["valid_tel_count"];
	$payment_method = $call["payment_method"];
	if ($ad_group_id == null) {
		$ret[$bill_payer_id]["sample_call_count"] = $count;
	}
	else if ($ad_id == null) {
		$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["sample_call_count"] = $count;
	}
	else if ($media_id == null) {
		$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["sample_call_count"] = $count;
	}
	else {
		if ($media_id == "B"){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["souzoku"]["sample_call_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["souzoku"]["payment_method"] = $payment_method;
		}
		else if ($media_id == "C"){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["koutsujiko"]["sample_call_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["koutsujiko"]["payment_method"] = $payment_method;
			}
		else if ($media_id == "D"){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["ninibaikyaku"]["sample_call_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["ninibaykyaku"]["payment_method"] = $payment_method;
		}
		else if ($media_id == "E"){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["meigihenkou"]["sample_call_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["meigihenkou"]["payment_method"] = $payment_method;
		}
		else if ($media_id == "F"){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["setsuritsu"]["sample_call_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["setsuritsu"]["payment_method"] = $payment_method;
		}
		else if ($media_id == "G"){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["keijijiken"]["sample_call_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["keijijiken"]["payment_method"] = $payment_method;
		}
		else if ($media_id == "A-LPPC" || $media_id == "A-LPSmart"){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["LP"]["sample_call_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["LP"]["payment_method"] = $payment_method;
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
	$count = $mail["valid_mail_count"];
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
		else if ($group == 999){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["LP"]["mail_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["LP"]["payment_method"] = $payment_method;
		}
	}
}

foreach ($sample_mail_data as $mail) {
	$bill_payer_id = $mail["bill_payer_id"];
	if ($bill_payer_id == null) {
		continue;
	}
	$ad_id = $mail["advertiser_id"];
	$group = $mail["site_group"];
	$count = $mail["valid_mail_count"];
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
		else if ($group == 999){
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["LP"]["sample_mail_count"] += $count;
			$ret[$bill_payer_id]["ad_groups"][$ad_group_id]["advertisers"][$ad_id]["medias"]["LP"]["payment_method"] = $payment_method;
		}
	}
}

echo json_encode($ret);

function get_billing_office_list() {
	global $pdo_request;
	$stmt = $pdo_request->query("
		SELECT
			r.bill_payer_id as bill_payer_id,
			r.bill_payer_name as bill_payer_name,
			ag.ID as ad_group_id,
			ag.group_name,
			a.ID as advertiser_id,
			a.office_name as office_name
		FROM
			smk_request_data.ad_group_bill_payer as m,
			smk_request_data.bill_payers as r,
			wordpress.ss_advertisers as a,
			wordpress.ss_advertiser_ad_group aag,
			wordpress.ss_ad_groups ag
		WHERE
			r.bill_payer_id = m.bill_payer_id AND
			ag.ID = m.ad_group_id AND
			aag.advertiser_id = a.ID AND
			aag.ad_group_id = ag.ID
		ORDER BY r.bill_payer_id, a.ID
	");
	$res_arr = $stmt->fetchAll(PDO::FETCH_ASSOC);
	return $res_arr;
}

function get_monthly_total_calls($year_month, $is_sample = false) {
	global $pdo_request;
	$where = "v.dpl_tel_cnt_for_billing = 0 AND v.call_minutes >= v.charge_seconds AND";
	if ($is_sample)
	{
		$where = "v.dpl_tel_cnt = 0 AND v.call_minutes >= 60 AND";
	}
	$stmt = $pdo_request->query("
		SELECT
			m.bill_payer_id as bill_payer_id,
			v.ad_group_id,
			v.advertiser_id as advertiser_id,
			v.media_id as media_id,
			group_concat(distinct pm.method) as payment_method,
			count(v.id) as valid_tel_count
		FROM
			(
				SELECT
					dv.id,
					dv.ad_group_id,
					CAST(dv.advertiser_id AS UNSIGNED) as advertiser_id,
					(case
						when (dv.media_id like 'A-Portal%') then 'A'
						when (dv.media_id like '') then 'A'
						else dv.media_id
					end) AS media_id,
					dv.dpl_tel_cnt,
					dv.dpl_mail_cnt,
					dv.dpl_tel_cnt_for_billing,
					dv.date_from,
					dv.call_minutes,
					pm.payment_method_id,
					pm.charge_seconds,
					sg.site_group
				FROM
					cdr.call_data_view dv,
					cdr.office_group_payment_method pm,
					wordpress.ss_site_group sg
				WHERE
					dv.ad_group_id = pm.ad_group_id AND
					sg.media_type = dv.media_type AND
					sg.site_group = pm.site_group AND
					CAST(dv.date_from AS DATE) BETWEEN pm.from_date AND pm.to_date
				ORDER BY dv.ad_group_id, dv.advertiser_id, dv.id
			) v,
			smk_request_data.ad_group_bill_payer m,
			cdr.office_group_payment_method gpm,
			cdr.payment_method pm
		WHERE
			m.ad_group_id = v.ad_group_id AND
			v.ad_group_id = gpm.ad_group_id AND
			gpm.payment_method_id = pm.id AND
			CAST(v.date_from AS DATE) BETWEEN gpm.from_date AND gpm.to_date AND
			DATE_FORMAT(v.date_from, '%Y%m') = '$year_month' AND
			gpm.site_group = v.site_group AND
			$where
			v.dpl_mail_cnt = 0
		GROUP BY
			m.bill_payer_id,
			v.ad_group_id,
			v.advertiser_id,
			v.media_id
		WITH ROLLUP
	");
	$res_arr = $stmt->fetchAll(PDO::FETCH_ASSOC);
	return $res_arr;
}

function get_monthly_total_mails($year_month, $is_sample = false) {
	global $pdo_request;
	$where = "AND pm.id <> 2";
	if ($is_sample)
	{
		$where = "";
	}
	$stmt = $pdo_request->query("
		SELECT
			m.bill_payer_id as bill_payer_id,
			adg.ad_group_id as ad_group_id,
			v.advertiser_id as advertiser_id,
			s.site_group as site_group,
			group_concat(distinct pm.method) as payment_method,
			count(v.ID) as valid_mail_count
		FROM
			cdr.mail_conv v,
			smk_request_data.ad_group_bill_payer m,
			wordpress.ss_site_type s,
			wordpress.ss_advertiser_ad_group adg,
			cdr.office_group_payment_method gpm,
			cdr.payment_method pm
		WHERE
			s.site_type = v.site_type AND
			m.ad_group_id = adg.ad_group_id AND
			adg.advertiser_id = v.advertiser_id AND
			adg.ad_group_id = gpm.ad_group_id AND
			(gpm.site_group = s.site_group OR (s.site_group = 999 AND gpm.site_group = 0)) AND
			gpm.payment_method_id = pm.id AND
			CAST(v.register_dt AS DATE) BETWEEN gpm.from_date AND gpm.to_date AND
			DATE_FORMAT(v.register_dt, '%Y%m') = '$year_month' AND
			v.dpl_tel_cnt = 0 AND
			v.dpl_mail_cnt = 0
			$where
		GROUP BY
			m.bill_payer_id,
			adg.ad_group_id,
			v.advertiser_id,
			s.site_group
		WITH ROLLUP
	");
	$res_arr = $stmt->fetchAll(PDO::FETCH_ASSOC);
	return $res_arr;
}
?>