<?php
#ini_set("display_errors","off");

#db接続データを参照
$path = parse_ini_file("../rms.cnf");		
foreach ($path as $key => $db_path) {
	$configs =parse_ini_file($db_path);
}
foreach($configs as $key => $value) {
	if ($key == "db_cdr") {
		$db_cdr = $value;
	}
	if ($key == "db_request") {
		$db_request = $value;
	}
	if ($key == "host") {
		$host = $value;
	}	
	if ($key == "name") {
		$name = $value;
	}
	if ($key == "pass") {
		$pass = $value;
	}
}
#smk_portal_dataへの接続
$dsn = "mysql:dbname=$db_cdr;host=$host";
$user = "$name";
$pass = "$pass";
try {
	$pdo = new PDO($dsn,$user,$pass);
} catch(PDOException $e) {
	exit('miss'.$e->getMessage());
}
$stmt = $pdo->query('set NAMES utf8');
if (!$stmt) {
	$info = $pdo->errorinfo();
	exit($info[2]);
}
#smk_request_dataへの接続

$dsn2 = "mysql:dbname=$db_request;host=$host";
$user2 = "$name";
$pass2 = "$pass";
try {
	$pdo2 = new PDO($dsn2,$user2,$pass2);
} catch(PDOException $e) {
	exit ('接続ミス'.$e->getMessage());
}
$stmt = $pdo2->query('SET NAMES utf8');
if (!$stmt) {
	$info = $pdo2->errorinfo();
	exit($info[2]);
}
//取得月の設定
$year_month = $_POST['ym'];
if (empty($year_month)) {
	print('<!DOCTYPE html>');
	print('<html lang="ja">');
	print('<head>');
	print('<meta charset="UTF-8">');
	print('<title>作成できません</title>');
	print('</head>');
	print('<body>');
	print('<a href="/">戻る</a>');
	print("<br>");
	print("年月が未指定です。");
	print('</body>');
	print('</html>');
	die();
}
$year = substr($year_month, 0, 4);
$month = substr($year_month, 4, 6);

//請求対象の取得
$stmt = $pdo2->query("
	SELECT
		bill_payer_id
	FROM
		bill_payers
");
$arr_bill_payer_id = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($arr_bill_payer_id as $row) {
	$bill_payer_id = $row['bill_payer_id'];
	fetch_req_call_data($year_month, $year, $month, $bill_payer_id);
	fetch_req_mail_data($year_month, $year, $month, $bill_payer_id);
}

//本番プログラム
function fetch_req_call_data($year_month, $year, $month, $bill_payer_id) {
	global $pdo, $pdo2;
	$shakkin = null;
	$souzoku = null;
	$koutsujiko = null;
	$ninibaikyaku = null;
	$meigihenkou = null;
	$setsuritsu = null;
	$keijijiken = null;
	$rikon = null;
	$bgatakanen = null;
	$hibouchuushou = null;
	$jikouenyou = null;
	$roudou = null;
	$youikuhi = null;
	$result_call_charge = null;
	$count_freedial = null;
	$stmt = $pdo2->query("
		SELECT
			ad_group_id
		FROM
			ad_group_bill_payer
		WHERE
			bill_payer_id = $bill_payer_id
	");
	$arr_ad_group_id = $stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach($arr_ad_group_id as $row) {
		$ad_group_id = $row['ad_group_id'];
		//call_dataの取得
		$stmt = $pdo->query("
			SELECT
				media_id
			FROM
				call_data_view
			WHERE
				ad_group_id = $ad_group_id AND
				tel_from <> 'anonymous' AND
				redirect_status in(21,22) AND
				DATE_FORMAT(date_from,'%Y%m') = $year_month AND
				dpl_tel_cnt = 0 AND
				dpl_mail_cnt = 0 AND
				call_minutes >= 60
		");
		$arr_call_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach ($arr_call_data as $r) {
			$mi = $r['media_id'];
			if (substr($mi, 0, 1) == "B"){
				$souzoku++;
			}
			else if (substr($mi, 0, 1) == "C"){
				$koutsujiko++;
			}
			else if (substr($mi, 0, 1) == "D"){
				$ninibaikyaku++;
			}
			else if (substr($mi, 0, 1) == "E"){
				$meigihenkou++;
			}
			else if (substr($mi, 0, 1) == "F"){
				$setsuritsu++;
			}
			else if (substr($mi, 0, 1) == "G"){
				$keijijiken++;
			}
			else if (substr($mi, 0, 1) == "H"){
				$rikon++;
			}
			else if (substr($mi, 0, 1) == "I"){
				$bgatakanen++;
			}
			else if (substr($mi, 0, 1) == "J"){
				$hibouchuushou++;
			}
			else if (substr($mi, 0, 1) == "K"){
				$jikouenyou++;
			}
			else if (substr($mi, 0, 1) == "L"){
				$roudou++;
			}
			else if (substr($mi, 0, 1) == "M"){
				$youikuhi++;
			}
			else {
				$shakkin++;
			}
		}
		//anonymous取得
		$stmt2 = $pdo->query("
			SELECT
				media_id
			FROM
				call_data_view
			WHERE
				ad_group_id = $ad_group_id AND
				tel_from = 'anonymous' AND
				redirect_status in(21,22) AND
				DATE_FORMAT(date_from,'%Y%m') = $year_month AND
				call_minutes >= 60
		");
		$arr_anonymous_call_data = $stmt2->fetchAll(PDO::FETCH_ASSOC);
		foreach ($arr_anonymous_call_data as $r) {
			$mi = $r['media_id'];
			if (substr($mi, 0, 1) == "B") {
				$souzoku++;
			}
			else if (substr($mi, 0, 1) == "C") {
				$koutsujiko++;
			}
			else if (substr($mi, 0, 1) == "D") {
				$ninibaikyaku++;
			}
			else if (substr($mi, 0, 1) == "E") {
				$meigihenkou++;
			}
			else if (substr($mi, 0, 1) == "F") {
				$setsuritsu++;
			}
			else if (substr($mi, 0, 1) == "G") {
				$keijijiken++;
			}
			else if (substr($mi, 0, 1) == "H") {
				$rikon++;
			}
			else if (substr($mi, 0, 1) == "I") {
				$bgatakanen++;
			}
			else if (substr($mi, 0, 1) == "J") {
				$hibouchuushou++;
			}
			else if (substr($mi, 0, 1) == "K") {
				$jikouenyou++;
			}
			else if (substr($mi, 0, 1) == "L") {
				$roudou++;
			}
			else if (substr($mi, 0, 1) == "M") {
				$youikuhi++;
			}
			else {
				$shakkin++;
			}
		}
		//call_chargeの取得
		$stmt = $pdo->query("
			SELECT
				tel_to
			FROM
				call_data_view
			WHERE
				ad_group_id = $ad_group_id AND
				DATE_FORMAT(date_from,'%Y%m') = $year_month
			GROUP BY
				tel_to
		");
		$arr_call_num =$stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach ($arr_call_num as $r) {
			$call_num = $r['tel_to'];
			$stmt = $pdo->query("
				SELECT
					call_charge
				FROM
					bill
				WHERE
					tel_to = $call_num AND
					year = $year AND
					month = $month
			");
			$arr_call_charge  = $stmt->fetchAll(PDO::FETCH_ASSOC);
			foreach ($arr_call_charge as $row) {
				$call_charge = $row['call_charge'];
				$result_call_charge += $call_charge;
			}
		}
		//count_freedialの取得
		$stmt = $pdo->query("
			SELECT
				ac.tel
			FROM
				cdr.adsip_conf ac,
				wordpress.ss_advertiser_ad_group aadg
			WHERE
				aadg.advertiser_id = ac.office_id AND
				aadg.ad_group_id = $ad_group_id
		");
		$arr_adsip_conf = $stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach ($arr_adsip_conf as $row) {
			$tel_num = $row['tel'];
			$freedial = substr($tel_num, 0, 4);
			if($freedial == "0120") {
				$count_freedial++;
			}
		}
	}
	if (!empty($shakkin) ||
			!empty($souzoku) ||
			!empty($koutsujiko) ||
			!empty($ninibaikyaku) ||
			!empty($meigihenkou) ||
			!empty($setsuritsu) ||
			!empty($keijijiken) ||
			!empty($rikon) ||
			!empty($bgatakanen) ||
			!empty($hibouchuushou) ||
			!empty($jikouenyou) ||
			!empty($roudou) ||
			!empty($youikuhi) ||
			!empty($result_call_charge) ||
			!empty($count_freedial)
	) {
		$call_sum = $shakkin+$souzoku+$koutsujiko+$ninibaikyaku+$meigihenkou+$setsuritsu+$keijijiken+$rikon+$bgatakanen+$hibouchuushou+$jikouenyou+$roudou+$youikuhi;
		$stmt = $pdo2->prepare("
			REPLACE INTO
				monthly_valid_call
			VALUES(
				?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?
			)
		");
		$result = $stmt->execute(
			array(
				$bill_payer_id,
				$year,
				$month,
				$shakkin,
				$souzoku,
				$koutsujiko,
				$ninibaikyaku,
				$meigihenkou,
				$setsuritsu,
				$keijijiken,
				$rikon,
				$bgatakanen,
				$hibouchuushou,
				$jikouenyou,
				$roudou,
				$youikuhi,
				$result_call_charge,
				$count_freedial,
				$call_sum
			)
		);
	}
}
//end_of_function

//メールデータ本番プログラム
function fetch_req_mail_data($year_month, $year, $month, $bill_payer_id) {
	global $pdo,$pdo2;
	$m_shakkin = null;
	$m_souzoku = null;
	$m_koutsujiko = null;
	$m_ninibaikyaku = null;
	$m_meigihenkou = null;
	$m_setsuritsu = null;
	$m_rikon = null;
	$m_bgatakanen = null;
	$m_hibouchuushou = null;
	$m_jikouenyou = null;
	$m_roudou = null;
	$m_youikuhi = null;
	$stmt = $pdo2->query("
		SELECT
			ad_group_id
		FROM
			ad_group_bill_payer
		WHERE
			bill_payer_id = $bill_payer_id
	");
	$arr_ad_group_id = $stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach ($arr_ad_group_id as $r) {
		$ad_group_id = $r['ad_group_id'];
		$stmt = $pdo->query("
			SELECT
				mc.site_group
			FROM
				cdr.mail_conv_view mc
			WHERE
				mc.dpl_tel_cnt = 0 AND
				mc.dpl_mail_cnt = 0 AND
				DATE_FORMAT(mc.register_dt,'%Y%m') = $year_month AND
				mc.ad_group_id = $ad_group_id
		");
		$mail_result = $stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach ($mail_result as $row) {
			$site_group = $row['site_group'];
			if ($site_group == 0) {
				$m_shakkin++;
			}
			else if ($site_group == 1) {
				$m_souzoku++;
			}
			else if ($site_group == 2) {
				$m_koutsujiko++;
			}
			else if ($site_group == 3) {
				$m_ninibaikyaku++;
			}
			else if ($site_group == 4) {
				$m_meigihenkou++;
			}
			else if ($site_group == 5) {
				$m_setsuritsu++;
			}
			else if ($site_group == 7) {
				$m_rikon++;
			}
			else if ($site_group == 8) {
				$m_bgatakanen++;
			}
			else if ($site_group == 9) {
				$m_hibouchuushou++;
			}
			else if ($site_group == 10) {
				$m_jikouenyou++;
			}
			else if ($site_group == 11) {
				$m_roudou++;
			}
			else if ($site_group == 12) {
				$m_youikuhi++;
			}
		}
	}
	if (!empty($m_shakkin) ||
			!empty($m_souzoku) ||
			!empty($m_koutsujiko) ||
			!empty($m_ninibaikyaku) ||
			!empty($m_meigihenkou) ||
			!empty($m_setsuritsu) ||
			!empty($m_rikon) ||
			!empty($m_bgatakanen) ||
			!empty($m_hibouchuushou) ||
			!empty($m_jikouenyou) ||
			!empty($m_roudou) ||
			!empty($m_youikuhi)
	) {
		$mail_sum =	$m_shakkin+$m_souzoku+$m_koutsujiko+$m_ninibaikyaku+$m_meigihenkou+$m_setsuritsu+$m_rikon+$m_bgatakanen+$m_hibouchuushou+$m_jikouenyou+$m_roudou+$m_youikuhi;
		$stmt = $pdo2->prepare("
			REPLACE INTO
				monthly_mail_num
			VALUES(
				?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?
			)
		");
		$result = $stmt->execute(
			array(
				$bill_payer_id,
				$year,
				$month,
				$m_shakkin,
				$m_souzoku,
				$m_koutsujiko,
				$m_ninibaikyaku,
				$m_meigihenkou,
				$m_setsuritsu,
				$m_rikon,
				$m_bgatakanen,
				$m_hibouchuushou,
				$m_jikouenyou,
				$m_roudou,
				$m_youikuhi,
				$mail_sum
			)
		);
	}
}
//ここまで本番プログラム
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>作成完了</title>
</head>
<body>
作成完了
<br>
<a href="../senmonka-RMS.php">請求書作成ページはこちら</a>
</body>
</html>