<?php
#エラーを画面に表示させない処理
ini_set("display_errors", "off");
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
$reviser = null;

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

#reviser呼び出し
require_once('reviser_lite.php');
$reviser = NEW Excel_Reviser;
$reviser->setInternalCharset('utf-8');	

#フォームからの事務所IDの受け取り
$id = $_POST['change'];
#フォームからの年月の受け取り
$year = $_POST['year'];
$month = $_POST['month'];
#一桁の月に"0"を付加
$month = sprintf("%02d",$month);
$year_month = "$year"."$month";

create_monthly_details($year,$month,$year_month);

#月次詳細情報の出力
function create_monthly_details($year, $month, $year_month) {
	global $pdo_cdr,$pdo_request,$pdo_wordpress,$reviser;
	$count_mail = null;
	#出力時の行を定義
	$i = 4;
	$stmt = $pdo_request->query("
		SELECT
			bill_payer_id,
			bill_payer_name
		FROM
			bill_payers
	");
	$arr_bill_payers = $stmt->fetchAll(PDO::FETCH_ASSOC);
	#media_typesへの接続
	$stmt = $pdo_wordpress->query("
		SELECT
			*
		FROM
			ss_site_group
	");
	$arr_site_group_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach ($arr_bill_payers as $row) {
		$bill_payer_id = $row['bill_payer_id'];
		$bill_payer_name = $row['bill_payer_name'];
		$reviser->addString(0, $i, 0, $bill_payer_id.$bill_payer_name);
		$i++;
		$reviser->addString(0, $i, 0, "事務所ID");
		$reviser->addString(0, $i, 1, "事務所名");
		$reviser->addString(0, $i, 3, "サイト種別");
		$reviser->addString(0, $i, 4, "電話番号");
		$reviser->addString(0, $i, 5, "転送先番号");
		$reviser->addString(0, $i, 6, "通話開始日");
		$reviser->addString(0, $i, 7, "通話終了日");
		$reviser->addString(0, $i, 8, "通話秒数");
		$reviser->addString(0, $i, 9, "発信元番号");
		$reviser->addString(0, $i, 10, "通話状態");
		$reviser->addString(0, $i, 11, "有効無効(60秒)");
		$reviser->addString(0, $i, 12, "有効秒数");
		$reviser->addString(0, $i, 13, "有効無効");
		$reviser->addString(0, $i, 14, "除外理由");
		$i++;
		$stmt = $pdo_request->query("
			SELECT
				*
			FROM
				ad_group_bill_payer
			WHERE
				bill_payer_id = $bill_payer_id
		");
		$arr_bill_payer_id = $stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach ($arr_bill_payer_id as $row) {
			$ad_group_id = $row['ad_group_id'];
			//登録事務所の取得
			$stmt = $pdo_wordpress->query("
				SELECT
					ad.ID,
					ad.office_name
				FROM
					wordpress.ss_advertisers ad,
					wordpress.ss_advertiser_ad_group aadg
				WHERE
					ad.ID = aadg.advertiser_id AND
					aadg.ad_group_id = $ad_group_id
			");
			$arr_ad_name = $stmt->fetchAll(PDO::FETCH_ASSOC);
			foreach ($arr_ad_name as $row) {
				$ad_id = $row['ID'];
				$ad_name = $row['office_name'];
				$stmt = $pdo_cdr->query("
					SELECT
						dv.*,
						pm.payment_method_id,
						pm.charge_seconds
					FROM
						cdr.call_data_view dv,
						cdr.office_group_payment_method pm
					WHERE
						dv.ad_group_id = pm.ad_group_id AND
						dv.site_group = pm.site_group AND
						CAST(dv.date_from AS DATE) BETWEEN pm.from_date AND pm.to_date AND
						DATE_FORMAT(dv.date_from,'%Y%m') = $year_month AND
						dv.advertiser_id = $ad_id
					ORDER BY
						dv.date_from
				");
				$arr_detail_call_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
				foreach ($arr_detail_call_data as $row) {
					$count_all_call++;
					#配列の値から変数へと代入
					$media_id = $row['media_id'];
					$media_type = $row['media_type'];
					$tel_to = $row['tel_to'];
					$tel_send = $row['tel_send'];
					$date_from = $row['date_from'];
					$date_to = $row['date_to'];
					$call_minutes = $row['call_minutes'];
					$tel_from = $row['tel_from'];
					$dpl_tel_cnt = $row['dpl_tel_cnt'];
					$dpl_mail_cnt =$row['dpl_mail_cnt'];
					$redirect_status = $row['redirect_status'];
					$payment_method_id = $row['payment_method_id'];
					$dpl_tel_cnt_for_billing = $row['dpl_tel_cnt_for_billing'];
					$is_exclusion = $row['is_exclusion'];
					$exclusion_reason = $row['exclusion_reason'];
					$exclusion_is_request = $row['exclusion_is_request'];
					$charge_seconds = $row['charge_seconds'];
					#media_nameの取得
					foreach ($arr_site_group_data as $r) {
						if($r['media_type'] == $media_type) {
							$media_name = $r['site_group_name'];
						}
					}
					if($media_name == "借金問題"){
						$media_name = "";
					}
					#電話statusの取得
					switch ($redirect_status) {
						case '21':
						case '22':
							$call_status = "正常終了";
							break;
						case '41':
						case '42':
							$call_status = "転送先応答なし";
							break;
						case '11':
						case '12':
							$call_status = "転送先呼び出し中切断";
							break;
						case '51':
						case '52':
							$call_status = "転送設定なし";
							break;
						case '61':
						case '62':
							$call_status = "番号応答なし";
							break;
						case '31':
						case '32':
							$call_status = "転送先話中";
							break;
					}
					#電話重複の確認
					if ($call_minutes >= 60 && $dpl_tel_cnt > 0 && $dpl_mail_cnt > 0) {
						$check_call_dpl = "同一電話・メール";
						$count_invalid_call++;
					}
					else if ($call_minutes >= 60 && $dpl_tel_cnt > 0) {
						$check_call_dpl = "同一電話";
						$count_invalid_call++;
					}
					else if ($call_minutes >= 60 && $dpl_mail_cnt > 0) {
						$check_call_dpl = "同一メール";
						$count_invalid_call++;
					}
					else if ($call_minutes >= 60) {
						if ($tel_from == "anonymous" OR ($dpl_tel_cnt == 0 && $dpl_mail_cnt == 0)) {
							if ($redirect_status == 21 || $redirect_status == 22) {
								$check_call_dpl = "○";
								if ($is_exclusion) {
									$count_invalid_call++;
								}
								else {
									$count_valid_call++;
								}
							}
							else {
								$count_invalid_call++;
							}
						}
						else {
							$count_invalid_call++;
						}
					}
					else {
						$check_call_dpl = null;
						$count_invalid_call++;
					}

					#課金対象の確認
					$check_call_dpl_for_billing = null;
					if ($is_exclusion) {
						if ($exclusion_is_request) {
							$check_call_dpl_for_billing = "除外依頼";
						}
						else {
							$check_call_dpl_for_billing = "弊社除外";
						}
						$count_invalid_call_for_billing++;
					}
					else if ($payment_method_id < 2) {
						if ($call_minutes >= $charge_seconds && $dpl_tel_cnt_for_billing > 0 && $dpl_mail_cnt > 0) {
							$check_call_dpl_for_billing = "同一電話・メール";
							$count_invalid_call_for_billing++;
						}
						else if ($call_minutes >= $charge_seconds && $dpl_tel_cnt_for_billing > 0) {
							$check_call_dpl_for_billing = "同一電話";
							$count_invalid_call_for_billing++;
						}
						else if ($call_minutes >= $charge_seconds && $dpl_mail_cnt > 0) {
							$check_call_dpl_for_billing = "同一メール";
							$count_invalid_call_for_billing++;
						}
						else if ($call_minutes >= $charge_seconds) {
							if ($tel_from == "anonymous" OR ($dpl_tel_cnt_for_billing == 0 && $dpl_mail_cnt == 0)) {
								if ($redirect_status == 21 || $redirect_status == 22) {
									$check_call_dpl_for_billing = "○";
									$count_valid_call_for_billing++;
								}
							}
						}
						else {
							$check_call_dpl_for_billing = null;
							$count_invalid_call_for_billing++;
						}
					}
					else {
						$check_call_dpl_for_billing = null;
						$count_invalid_call_for_billing++;
					}

					$reviser->addString(0, $i, 0, $ad_id);
					$reviser->addString(0, $i, 1, $ad_name);
					$reviser->addString(0, $i, 3, $media_name);
					$reviser->addString(0, $i, 4, $tel_to);
					$reviser->addString(0, $i, 5, $tel_send);
					$reviser->addString(0, $i, 6, $date_from);
					$reviser->addString(0, $i, 7, $date_to);
					$reviser->addNumber(0, $i, 8, $call_minutes);
					$reviser->addString(0, $i, 9, $tel_from);
					$reviser->addString(0, $i, 10, $call_status);
					$reviser->addString(0, $i, 11, $check_call_dpl);
					$reviser->addString(0, $i, 12, $charge_seconds);
					$reviser->addString(0, $i, 13, $check_call_dpl_for_billing);
					$reviser->addString(0, $i, 14, $exclusion_reason);
					$i++;
				}
				#end_of_arr_detail_call_data
			}
			#end_of_arr_ad_name
		}
		#end_of_arr_ad_id
		$i++;
	}
	#全体コール数
	$reviser->addString(0, 0, 0, "全体コール数");
	$reviser->addString(0, 0, 1, $count_all_call);
	#有効コール数
	$reviser->addString(0, 1, 0, "有効コール数(60秒)");
	$reviser->addString(0, 1, 1, $count_valid_call);
	$reviser->addString(0, 1, 2, "有効コール数");
	$reviser->addString(0, 1, 3, $count_valid_call_for_billing);
	#無効コール数
	$reviser->addString(0, 2, 0, "無効コール数(60秒)");
	$reviser->addString(0, 2, 1, $count_invalid_call);
	$reviser->addString(0, 2, 2, "無効コール数");
	$reviser->addString(0, 2, 3, $count_invalid_call_for_billing);


	##メール詳細情報の取得

	#出力時の行を定義
	$i = 4;
	$reviser->addString(1, $i, 0, "事務所ID");
	$reviser->addString(1, $i, 1, "事務所名");
	$reviser->addString(1, $i, 2, "サイト名");
	$reviser->addString(1, $i, 3, "配信日時");
	$reviser->addString(1, $i, 4, "電話番号");
	$reviser->addString(1, $i, 5, "有効無効");
	$reviser->addString(1, $i, 6, "除外理由");
	$i++;

	$stmt = $pdo_cdr->query("
		SELECT
			c.*,
			ad.office_name,
			st.site_type_name
		FROM
			wordpress.ss_advertisers ad,
			wordpress.ss_site_type st,
			cdr.mail_conv_view c
		WHERE
			c.advertiser_id = ad.ID AND
			c.site_type = st.site_type AND
			DATE_FORMAT(c.register_dt,'%Y%m') = $year_month
		ORDER BY
			c.register_dt
	");
	$arr_detail_mail_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
	#全てのメール情報の入る配列
	$sum_detail_mail_data = array();
	foreach ($arr_detail_mail_data as $row) {
		#全てのメール数をカウント
		$count_all_mail++;
		$advertiser_id = $row['advertiser_id'];
		$office_name = $row['office_name'];
		$st = $row['site_type'];
		$st_name = $row['site_type_name'];
		$sender_tel = $row['sender_tel'];
		$date = $row['register_dt'];
		$dpl_tel_cnt = $row['dpl_tel_cnt'];
		$dpl_mail_cnt = $row['dpl_mail_cnt'];
		$is_exclusion = $row['is_exclusion'];
		$exclusion_is_request = $row['exclusion_is_request'];
		$exclusion_reason = $row['exclusion_reason'];
		if ($is_exclusion) {
			if ($exclusion_is_request) {
				$check_mail_dpl = "除外依頼";
			}
			else {
				$check_mail_dpl = "弊社除外";
			}
			$count_invalid_mail++;
		}
		else if ($dpl_tel_cnt == 0 && $dpl_mail_cnt == 0) {
			$check_mail_dpl = "○";
			$count_valid_mail++;
		}
		else if ($dpl_tel_cnt > 0 && $dpt_mail_cnt > 0) {
			$check_mail_dpl = "同一電話・メール";
			$count_invalid_mail++;
		}
		else if ($dpl_tel_cnt > 0) {
			$check_mail_dpl = "同一電話";
			$count_invalid_mail++;
		}
		else if ($dpl_mail_cnt > 0) {
			$check_mail_dpl = "同一メール";
			$count_invalid_mail++;
		}
		#事務所毎かつ、発生メール毎の情報が入る配列
		$new_detail_mail_array_data = array();
		array_push(
			$new_detail_mail_array_data,
			$advertiser_id,
			$office_name,
			$st_name,
			$sender_tel,
			$date,
			$check_mail_dpl,
			$exclusion_reason
		);
		array_push($sum_detail_mail_data, $new_detail_mail_array_data);
	}

	#配列に代入したメールデータを出力
	foreach ($sum_detail_mail_data as $row) {
		$ad_id = $row['0'];
		$office_name = $row['1'];
		$st_name = $row['2'];
		$sender_tel = $row['3'];
		$mail_date = $row['4'];
		$check_mail_dpl = $row['5'];
		$exclusion_reason = $row['6'];
		$reviser->addString(1, $i, 0, $ad_id);
		$reviser->addString(1, $i, 1, $office_name);
		$reviser->addString(1, $i, 2, $st_name);
		$reviser->addString(1, $i, 3, $mail_date);
		$reviser->addString(1, $i, 4, $sender_tel);
		$reviser->addString(1, $i, 5, $check_mail_dpl);
		$reviser->addString(1, $i, 6, $exclusion_reason);
		$i++;
	}
	#全体メール数
	$reviser->addString(1, 0, 0, "全体メール数");
	$reviser->addString(1, 0, 1, $count_all_mail);
	#有効メール数
	$reviser->addString(1, 1, 0, "有効メール数");
	$reviser->addString(1, 1, 1, $count_valid_mail);
	#無効メール数
	$reviser->addString(1, 2, 0, "無効メール数");
	$reviser->addString(1, 2, 1, $count_invalid_mail);

	$readfile = "./monthly_details_template.xls";
	$outfile = $year."年".$month."月詳細情報.xls";
	$reviser->revisefile($readfile, $outfile);
}
#end_of_function/create_monthly_details
?>