<?php
#ini_set("display_errors","off");

#db接続データを参照
$path = parse_ini_file("../rms.cnf");		
foreach($path as $key => $db_path){
		$configs =parse_ini_file($db_path);
}
foreach($configs as $key =>$value){
		if($key =="db_cdr"){
				$db_portal = $value;
		}
		if($key == "db_req"){
				$db_req = $value;
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
#smk_portal_dataへの接続
$dsn = "mysql:dbname=$db_portal;host=$host";
$user = "$name";
$pass = "$pass";
try{
		$pdo = new PDO($dsn,$user,$pass);
}catch(PDOException $e){
		exit('miss'.$e->getMessage());
}
$stmt = $pdo->query('set NAMES utf8');
if(!$stmt){
		$info = $pdo->errorinfo();
		exit($info[2]);
}
#smk_request_dataへの接続

$dsn2 = "mysql:dbname=$db_req;host=$host";
$user2= "$name";
$pass2= "$pass";
try{
		$pdo2 = new PDO($dsn2,$user2,$pass2);
}catch(PDOException $e){
		exit ('接続ミス'.$e->getMessage());
}
$stmt = $pdo2->query('SET NAMES utf8');
if(!$stmt){
		$info = $pdo2->errorinfo();
		exit($info[2]);
}
//取得月の設定
$year = $_POST['year'];
$month = $_POST['month'];
$month =sprintf("%02d",$month);
$year_month = "$year"."$month";
#req_idの時に処理
$stmt = $pdo2->query("SELECT req_id FROM ad_req_data");
$arr_req_id = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($arr_req_id as $row) {
	$reqid = $row['req_id'];
	fetch_req_call_data($year_month,$year,$month,$reqid);
	fetch_req_mail_data($year_month,$year,$month,$reqid);
}
echo"コール・メールデータの取得完了";
echo "<br>";

//本番プログラム
function fetch_req_call_data($year_month,$year,$month,$reqid){
		global $pdo,$pdo2;
		$shakkin = null;
		$souzoku = null;
		$koutsujiko = null;
		$ninibaikyaku = null;
		$meigihenkou = null;
		$setsuritsu = null;
		$keijijiken = null;
		$result_call_charge=null;
		$count_freedial=null;
		$stmt = $pdo2->query("SELECT adid from  adid_reqid_matching WHERE reqid =$reqid");
		$arr_adid = $stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach($arr_adid as $row){
				$adid = $row['adid'];
				//call_dataの取得
				$stmt = $pdo->query("SELECT count(*),media_id FROM call_data_view WHERE advertiser_id =$adid AND redirect_status in(21,22) AND DATE_FORMAT(date_from,'%Y%m')=$year_month AND dpl_tel_cnt =1 AND dpl_mail_cnt = 0 AND call_minutes>=60 GROUP BY media_id");
				$arr_call_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
				foreach($arr_call_data as $r){
						$count = $r['count(*)'];
						$mi = $r['media_id'];
						if($mi == null || $mi == ""){
								$shakkin += $count;
						}
						if($mi == "B"){
								$souzoku += $count;
						}
						if($mi == "C"){
								$koutsujiko += $count;
						}
						if($mi == "D"){
								$ninibaikyaku += $count;
						}
						if($mi == "E"){
								$meigihenkou += $count;
						}
						if($mi == "F"){
								$setsuritsu += $count;
						}
						if($mi == "G"){
								$keijijiken += $count;
						}
				}
				//anonymousデータの取得
				$stmt2 =$pdo->query("SELECT count(*),media_id FROM call_data_view WHERE advertiser_id =$adid AND tel_from ='anonymous' AND DATE_FORMAT(date_from,'%Y%m')=$year_month AND call_minutes>=60 GROUP BY media_id");
				$arr_anonymous_data = $stmt2->fetchAll(PDO::FETCH_ASSOC);
				foreach($arr_anonymous_data as $r){
						$count2 = $r['count(*)'];
						$mi = $r['media_id'];
						if($mi == null || $mi == ""){
								$shakkin += $count2;
						}
						if($mi == "B"){
								$souzoku += $count2;
						}
						if($mi == "C"){
								$koutsujiko += $count2;
						}
						if($mi == "D"){
								$ninibaikyaku += $count2;
						}
						if($mi == "E"){
								$meigihenkou += $count2;
						}
						if($mi == "F"){
								$setsuritsu += $count2;
						}
						if($mi == "G"){
								$keijijiken += $count;
						}
				}
//call_charge,count_freedialの取得
				$stmt = $pdo->query("SELECT tel_to FROM call_data_view WHERE advertiser_id =$adid AND DATE_FORMAT(date_from,'%Y%m')=$year_month GROUP BY tel_to");
				$arr_call_num =$stmt->fetchAll(PDO::FETCH_ASSOC);
				foreach($arr_call_num as $r){
					$call_num = $r['tel_to'];
					$freedial = substr($call_num,0,4);
					if($freedial =="0120"){
						$count_freedial +=1;
					}
					$stmt = $pdo->query("SELECT call_charge FROM bill WHERE tel_to=$call_num AND year=$year AND month =$month");
					$arr_call_charge  = $stmt->fetchAll(PDO::FETCH_ASSOC);
					foreach($arr_call_charge as $row){
							$call_charge = $row['call_charge'];
								$result_call_charge +=$call_charge;
							}
				}
		}
		if(!empty($shakkin)||!empty($souzoku)||!empty($koutsujiko)||!empty($ninibaikyaku)||!empty($meigihenkou)||!empty($setsuritsu)||!empty($keijijiken)){
				$call_sum = $shakkin+$souzoku+$koutsujiko+$ninibaikyaku+$meigihenkou+$setsuritsu+$keijijiken;
				$stmt = $pdo2->prepare("INSERT INTO ad_monthly_valid_call VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)");
				$result = $stmt->execute(array($reqid,$year,$month,$shakkin,$souzoku,$koutsujiko,$ninibaikyaku,$meigihenkou,$setsuritsu,$keijijiken,$result_call_charge,$count_freedial,$call_sum));
			}
}
//メールデータ本番プログラム
function fetch_req_mail_data($year_month,$year,$month,$reqid){
		global $pdo,$pdo2;
		$m_shakkin = null;
		$m_souzoku = null;
		$m_koutsujiko = null;
		$m_ninibaikyaku = null;
		$m_meigihenkou = null;
		$m_setsuritsu = null;
$stmt = $pdo2->query("SELECT adid from adid_reqid_matching WHERE reqid = $reqid");
$arr_adid = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($arr_adid as $r){
		$adid =$r['adid'];
		$stmt = $pdo->query("SELECT count(*),site_type FROM mail_conv WHERE dpl_tel_cnt=0 AND dpl_mail_cnt =0 AND DATE_FORMAT(register_dt,'%Y%m')=$year_month AND advertiser_id = $adid GROUP BY site_type");
		$mail_result = $stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach($mail_result as $row){
				$mail_num = $row['count(*)'];
				$site_type = $row['site_type'];
				if($site_type ==0 || $site_type ==1 || $site_type ==2 || $site_type ==6 || $site_type ==7 || $site_type ==8 || $site_type ==9 || $site_type ==10 || $site_type ==11 || $site_type ==12 || $site_type ==13 || $site_type ==15){
						$m_shakkin += $mail_num;
				}
				if($site_type ==3){
						$m_souzoku += $mail_num;
				}
				if($site_type ==14){
						$m_koutsujiko += $mail_num;
				}
				if($site_type == 16){
						$m_ninibaikyaku += $mail_num;
				}
				if($site_type ==18){
						$m_setsuritsu += $mail_num;
				}
				if($site_type ==17){
						$m_meigihenkou += $mail_num;
				}
		}
}		
		if(!empty($m_shakkin)||!empty($m_souzoku)||!empty($m_koutsujiko)||!empty($m_ninibaikyaku)||!empty($m_meigihenkou)||!empty($m_setsuritsu)){
			$mail_sum =	$m_shakkin+$m_souzoku+$m_koutsujiko+$m_ninibaikyaku+$m_meigihenkou+$m_setsuritsu;
			$stmt = $pdo2->prepare("INSERT INTO ad_monthly_mail_num VALUES(?,?,?,?,?,?,?,?,?,?)");
			$result  = $stmt->execute(array($reqid,$year,$month,$m_shakkin,$m_souzoku,$m_koutsujiko,$m_ninibaikyaku,$m_meigihenkou,$m_setsuritsu,$mail_sum));
		}
}
//ここまで本番プログラム
?>
<a href="../senmonka-RMS.php">請求書作成ページはこちら</a>
