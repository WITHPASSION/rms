<?php
#ini_set("display_errors","off");

#db接続データを参照
$path = parse_ini_file("../rms.cnf");		
foreach($path as $key => $db_path){
		$configs =parse_ini_file($db_path);
}
foreach($configs as $key =>$value){
		if($key =="db_portal"){
				$db_portal = $value;
		}
		if($key == "db_req"){
				$do_req = $value;
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
//上限の取得
$stmt = $pdo2->query("SELECT count(*) from ad_req_data");
$repeat_times = $stmt->fetchcolumn();
//取得月の設定
$year_month = 201405;
$year =substr($year_month,0,4);
$month =substr($year_month,4,2);

fetch_req_call_data($year_month,$year,$month,$repeat_times);
fetch_req_mail_data($year_month,$year,$month,$repeat_times);

/*
//テスト用プログラム
$stmt = $pdo2->query("select count(*) from ad_req_data");
$req_num  =$stmt->fetchcolumn();
var_dump($req_num);
for($i=0;$i<=$req_num;$i=$i+1){
		$reqid = $i;
		$shakkin = null;
		$souzoku = null;
		$koutsujiko = null;
		$ninibaikyaku = null;
		$meigihenkou = null;
		$setsuritsu = null;
		$keijijiken = null;
		echo$reqid;
		echo"<br>";
$stmt = $pdo2->query("SELECT adid from  adid_reqid_matching WHERE reqid =$reqid");
$arr_adid = $stmt->fetchAll(PDO::FETCH_ASSOC);
//adidの取得完了
//取得年月の決定
$year_month = 201408;
$year =substr($year_month,0,4);
$month =substr($year_month,4,2);
foreach($arr_adid as $row){
	$adid = $row['adid'];
	$tes = $pdo->query("SELECT count(*),media_id,date_from FROM call_data_view WHERE advertiser_id =$adid AND redirect_status in(21,22) AND DATE_FORMAT(date_from,'%Y%m')=$year_month AND dpl_tel_cnt =1 AND dpl_mail_cnt = 0  GROUP BY media_id");
$tes = $tes->fetchAll(PDO::FETCH_ASSOC);
foreach($tes as $r){
		$count = $r['count(*)'];
		$mi = $r['media_id'];
						if($mi == null || $mi == "" ){
								$shakkin = $count+$shakkin;
								echo"<br>";
						}
						if($mi == "B"){
								$souzoku = $count;
						}
						if($mi == "C"){
								$koutsujiko = $count;
						}
						if($mi == "D"){
								$ninibaikyaku = $count;
						}
						if($mi == "E"){
								$meigihenkou = $count;
						}
						if($mi == "F"){
								$setsuritsu = $count;
						}
						if($mi == "G"){
								$keijijiken = $count;
						}
				}
}
echo"借金:";
var_dump($shakkin);
echo"<br>";
echo"相続:";
var_dump($souzoku);
echo"<br>";
echo"交通事故:";
var_dump($koutsujiko);
echo"<br>";
echo"任意売却:";
var_dump($ninibaikyaku);
echo"<br>";
echo"名義変更:";
var_dump($meigihenkou);
echo"<br>";
echo"設立:";
var_dump($setsuritsu);
echo"<br>";
echo"刑事事件:";
var_dump($keijijiken);
echo"<br>";

}
 */
/*
//メールテスト
$year_month = 201408;
$year =substr($year_month,0,4);
$month =substr($year_month,4,2);
		$req_id =71;
		$m_shakkin = null;
		$m_souzoku = null;
		$m_koutsujiko = null;
		$m_ninibaikyaku = null;
		$m_meigihenkou = null;
		$m_setsuritsu = null;
$stmt = $pdo2->query("SELECT adid from adid_reqid_matching WHERE reqid = $req_id");
$arr_adid = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($arr_adid as $r){
		$adid =$r['adid'];
		$stmt = $pdo->query("SELECT count(*),site_type FROM mail_conv WHERE dpl_tel_cnt=0 AND dpl_mail_cnt =0 AND DATE_FORMAT(register_dt,'%Y%m')=$year_month AND advertiser_id = $adid GROUP BY site_type");
		$mail_result = $stmt->fetchAll(PDO::FETCH_ASSOC);

		foreach($mail_result as $row){
				$mail_num = $row['count(*)'];
				$site_type = $row['site_type'];
				if($site_type ==0 || $site_type ==1 || $site_type ==2 || $site_type ==6 || $site_type ==7 || $site_type ==8 || $site_type ==9 || $site_type ==10 || $site_type ==11 || $site_type ==12 || $site_type ==13 || $site_type ==15){
						$m_shakkin =$mail_num+$m_shakkin;
				}
				if($site_type ==3){
						$m_souzoku=$mail_num;
				}
				if($site_type ==14){
						$m_koutsujiko = $mail_num;
				}
				if($site_type == 16){
						$m_ninibaikyaku = $mail_num;
				}
				if($site_type ==18){
						$m_setsuritsu = $mail_num;
				}
				if($site_type ==17){
						$m_meigihenkou = $mail_num;
				}
		}
}
echo "shakkin";
var_dump($m_shakkin);
echo"<br>";
echo "souzoku";
var_dump($m_souzoku);
echo"<br>";
echo "skoutsujiko";
var_dump($m_koutsujiko);
echo"<br>";
echo "ninibaikyaku";
var_dump($m_ninibaikyaku);
echo"<br>";
echo "setsuritsu";
var_dump($m_setsuritsu);
echo"<br>";
echo "meigihenkou";
var_dump($m_meigihenkou);
echo"<br>";
 */
//テストここまで

//本番プログラム
function fetch_req_call_data($year_month,$year,$month,$repeat_times){
for($i=0;$i<=$repeat_times;$i=$i+1){
		$reqid =$i;
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
				$stmt = $pdo->query("SELECT count(*),media_id FROM call_data_view WHERE advertiser_id =$adid AND redirect_status in(21,22) AND DATE_FORMAT(date_from,'%Y%m')=$year_month AND dpl_tel_cnt =1 AND dpl_mail_cnt = 0  GROUP BY media_id");
				$arr_call_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
				foreach($arr_call_data as $r){
						$count = $r['count(*)'];
						$mi = $r['media_id'];
						if($mi == null || $mi == ""){
								$shakkin += $count;
						}
						if($mi == "B"){
								$souzoku = $count;
						}
						if($mi == "C"){
								$koutsujiko = $count;
						}
						if($mi == "D"){
								$ninibaikyaku = $count;
						}
						if($mi == "E"){
								$meigihenkou = $count;
						}
						if($mi == "F"){
								$setsuritsu = $count;
						}
		if($mi == "G"){
								$keijijiken = $count;
						}
				}
				//call_charge,count_freedialの取得
				$stmt = $pdo->query("SELECT tel_to FROM call_data_view WHERE advertiser_id =$adid GROUP BY tel_to");
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
				$stmt = $pdo2->prepare("INSERT INTO ad_monthly_valid_call VALUES(?,?,?,?,?,?,?,?,?,?,?,?)");
				$result = $stmt->execute(array($reqid,$year,$month,$shakkin,$souzoku,$koutsujiko,$ninibaikyaku,$meigihenkou,$setsuritsu,$keijijiken,$result_call_charge,$count_freedial));
			}
}
echo"コールデータの取得に成功しました";
echo"<br>";
}
//メールデータ本番プログラム
function fetch_req_mail_data($year_month,$year,$month,$repeat_times){
for($i=0;$i<=$repeat_times;$i++){
		$reqid =$i;
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
						$m_souzoku=$mail_num;
				}
				if($site_type ==14){
						$m_koutsujiko = $mail_num;
				}
				if($site_type == 16){
						$m_ninibaikyaku = $mail_num;
				}
				if($site_type ==18){
						$m_setsuritsu = $mail_num;
				}
				if($site_type ==17){
						$m_meigihenkou = $mail_num;
				}
		}
}
		if(!empty($m_shakkin)||!empty($m_souzoku)||!empty($m_koutsujiko)||!empty($m_ninibaikyaku)||!empty($m_meigihenkou)||!empty($m_setsuritsu)){
			$stmt = $pdo2->prepare("INSERT INTO ad_monthly_mail_num VALUES(?,?,?,?,?,?,?,?,?)");
			$result  = $stmt->execute(array($reqid,$year,$month,$m_shakkin,$m_souzoku,$m_koutsujiko,$m_ninibaikyaku,$m_meigihenkou,$m_setsuritsu));
		}
}
echo "メールデータの取得に成功しました";
echo"<br>";
}
//ここまで本番プログラム
?>
