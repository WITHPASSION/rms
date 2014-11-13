<?php
#データベース接続処理
ini_set("display_errors", "off");
#db接続データの参照
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
#グローバル変数
$pdo =null;
$pdo2 =null;
$reviser=null;

$dsn ="mysql:dbname=$db_req;host=$host";
$user = "$name";
$pass = "$pass";
try{	
		$pdo = new PDO($dsn,$user,$pass);
}catch(PDOException $e){
		exit('接続ミス'.$e->getMessage());
}
$stmt=$pdo->query('SET NAMES utf8');
if(!$stmt){
		$info=$pdo->errorinfo();
		exit($info[2]);
}

$dsn2 ="mysql:dbname=$db_portal;host=$host";
$user = "$name";
$pass = "$pass";
try{	
		$pdo2 = new PDO($dsn2,$user,$pass);
}catch(PDOException $e){
		exit('接続ミス'.$e->getMessage());
}
$stmt=$pdo2->query('SET NAMES utf8');
if(!$stmt){
		$info=$pdo->errorinfo();
		exit($info[2]);
}



#reviser呼び出し
require_once('reviser_lite.php');
$reviser = NEW Excel_Reviser;
$reviser->setInternalCharset('utf-8');	

#DB内の行最大数を取得
$max_row = $pdo->query("SELECT COUNT(*) FROM ad_req_data");

#フォームからの事務所IDの受け取り
$id = $_POST['change'];
#フォームからの年月の受け取り
$year = $_POST['year'];
$month = $_POST['month'];
$month = sprintf("%02d",$month);
$year_month = "$year"."$month";
#請求有効件数が0であった場合には出力しない
$check = $pdo->query("SELECT valid_call_shakkin,valid_call_souzoku,valid_call_koutsujiko,valid_call_meigihenkou,valid_call_setsuritsu,valid_call_keijijiken FROM ad_monthly_valid_call WHERE req_id=$id AND year=$year AND month=$month");
$check = $check->fetch(PDO::FETCH_ASSOC);
$check2 = $pdo->query("SELECT mail_shakkin,mail_souzoku,mail_koutsujiko,mail_meigihenkou,mail_setsuritsu FROM ad_monthly_mail_num WHERE req_id=$id AND year=$year AND month=$month");
$check2 = $check2->fetch(PDO::FETCH_ASSOC);
if(!empty($check)|| !empty($check2)){
	get_each_ad_data($id,$year,$month,$year_month);
		}
else{
		print('<a href="../senmonka-RMS.php">戻る</a>');
		print("<br>");
		die("この年月では、この事務所は有効電話数とメール数が０件です");
}

function get_each_ad_data($id,$year,$month,$year_month){
		#####有効コール請求内容データの取得
		global $pdo,$pdo2,$reviser,$month;
		#無効も含めた全てのコール数
		$all_call_shakkin = null;
		$all_call_souzoku = null;
		$all_call_koutsujiko = null;
		$all_call_ninibaikyaku = null;
		$all_call_meigihenkou = null;
		$all_call_setsuritsu = null;
		$all_call_keijijiken = null;
		#無効も含めた全てのメール数
		$all_mail_shakkin = null;
		$all_mail_souzoku = null;
		$all_mail_koutsujiko = null;
		$all_mail_ninibaikyaku = null;
		$all_mail_meigihenkou = null;
		$all_mail_setsuritsu = null;
		#メール日
		$shakkin_mail_dt = null;
		$souzoku_mail_dt = null;
		$koutsujiko_mail_dt = null;
		$ninibaikyaku_mail_dt =null;
		$meigihenkou_mail_dt=null;
		$setsuritsu_mail_dt =null;
		$mail_dt =null;
		#無効詳細内容の取得
		$inv_shakkin =null;
		$inv_souzoku =null;
		$inv_koutsujiko =null;
		$inv_ninibaikyaku =null;
		$inv_meigihenkou =null;
		$inv_setsuritsu =null;
		$inv_keijijiken =null;
		#メディア毎有効請求
		$res_shakkin = null;
		$res_souzoku =null;
		$res_koutsujiko=null;
		$res_ninibaikyaku=null;
		$res_meigihenkou=null;
		$res_setsuritsu=null;
		$res_keijijiken=null;

		#合計詳細
		$va_shakkin= null;
		$va_souzoku = null;
		$va_koutsujiko=null;
		$va_ninibaikyaku =null;
		$va_meigihenkou =null;
		$va_setsuritsu =null;
		$va_keijijiken =null;
		#template文
		$all_tmp=null;
		#空の配列作成
		$arr_shakkin_mail_dt = array();
		$arr_souzoku_mail_dt = array();
		$arr_koutsujiko_mail_dt = array();
		$arr_ninibaikyaku_mail_dt = array();
		$arr_meigihenkou_mail_dt = array();
		$arr_setsuritsu_mail_dt = array();


		$sheet_num =0;
		$stmt = $pdo->query("SELECT * FROM ad_monthly_valid_call WHERE req_id=$id AND year=$year AND month=$month");
		$req_mvc_data = $stmt->fetch(PDO::FETCH_ASSOC);
				#問題毎コール数の取得
		$shakkin_call = $req_mvc_data['valid_call_shakkin'];
		$souzoku_call = $req_mvc_data['valid_call_souzoku'];
		$koutsujiko_call = $req_mvc_data['valid_call_koutsujiko'];
		$ninibaikyaku_call = $req_mvc_data['valid_call_ninibaikyaku'];
		$meigihenkou_call = $req_mvc_data['valid_call_meigihenkou'];
		$setsuritsu_call = $req_mvc_data['valid_call_setsuritsu'];
		$keijijiken_call = $req_mvc_data['valid_call_keijijiken'];
		$call_sum = $req_mvc_data['call_sum'];
		####課金メール数請求内容データの取得
		$stmt2 = $pdo->query("SELECT * FROM ad_monthly_mail_num WHERE req_id=$id AND year=$year AND month=$month");
		$req_mail_data = $stmt2->fetch(PDO::FETCH_ASSOC);
				#問題ごとメール数の取得
		$shakkin_mail = $req_mail_data['mail_shakkin'];
		$souzoku_mail = $req_mail_data['mail_souzoku'];
		$koutsujiko_mail = $req_mail_data['mail_koutsujiko'];
		$ninibaikyaku_mail = $req_mail_data['mail_ninibaikyaku'];
		$meigihenkou_mail = $req_mail_data['mail_meigihenkou'];
		$setsuritsu_mail = $req_mail_data['mail_setsuritsu'];
		$mail_sum = $req_mail_data['mail_sum'];
		#請求合計数の取得
		$all_sum = $call_sum+$mail_sum;
		#######無効も含めた全コール数,メール数の取得
		#無効アリコール数
		$stmt=$pdo->query("SELECT * FROM adid_reqid_matching WHERE reqid =$id");
		$arr_ad_id =$stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach($arr_ad_id as $row){
			$adid = $row['adid'];
			$stmt = $pdo2->query("SELECT media_id FROM call_data_view WHERE advertiser_id = $adid AND DATE_FORMAT(date_from,'%Y%m')=$year_month");
			$arr_all_call_data =$stmt->fetchAll(PDO::FETCH_ASSOC);
			foreach($arr_all_call_data as $row){
					$mi =$row['media_id'];
						if($mi == null || $mi == ""){
								$all_call_shakkin ++;
						}
						if($mi == "B"){
								$all_call_souzoku ++;
						}
						if($mi == "C"){
								$all_call_koutsujiko ++;
						}
						if($mi == "D"){
								$all_call_ninibaikyaku ++;
						}
						if($mi == "E"){
								$all_call_meigihenkou ++;
						}
						if($mi == "F"){
								$all_call_setsuritsu ++;
						}
						if($mi == "G"){
								$all_call_keijijiken ++;
						}	
			}
		#無効アリメール数,メール日取得
			$stmt2 = $pdo2->query("SELECT site_type,DATE_FORMAT(register_dt,'%m%d') FROM  mail_conv WHERE advertiser_id = $adid AND DATE_FORMAT(register_dt,'%Y%m')=$year_month ");
			$arr_all_mail_data = $stmt2->fetchAll(PDO::FETCH_ASSOC);
			foreach($arr_all_mail_data as $row){
				$st=$row['site_type'];
				$register_dt = $row["DATE_FORMAT(register_dt,'%m%d')"];
				$mail_month =substr($register_dt,0,2);
				$mail_day = substr($register_dt,2,2);
				$mail_day = sprintf('%01d',$mail_day);
				if($st == 0 || $st == 1 || $st == 2 || $st == 6 || $st == 7 || $st == 8 || $st == 9 || $st == 10 || $st == 11 || $st == 12 || $st == 13 || $st == 15){
					$all_mail_shakkin++;
					array_push($arr_shakkin_mail_dt,$mail_day);
					asort($arr_shakkin_mail_dt);
				}
				if($st ==3){
					$all_mail_souzoku ++;
					array_push($arr_souzoku_mail_dt,$mail_day);
					asort($arr_souzoku_mail_dt);
				}
				if($st ==14){
					$all_mail_koutsujiko ++;
					array_push($arr_koutsujiko_mail_dt,$mail_day);
					asort($arr_koutsujiko_mail_dt);
				}
				if($st ==16){
					$all_mail_ninibaikyaku ++;
					array_push($arr_ninibaikyaku_mail_dt,$mail_day);
					asort($arr_ninibaikyaku_mail_dt);
				}
				if($st ==18){ 
					$all_mail_setsuritsu ++;
					array_push($arr_setsuritsu_mail_dt,$mail_day);
					asort($arr_setsuritsu_mail_dt);
				}
				if($st ==17){
					$all_mail_meigihenkou ++;
					array_push($arr_meigihenkou_mail_dt,$mail_day);
					asort($arr_meigihenkou_mail_dt);
				}
			}
		}
		#配列のメール日数を変数に入れる処理
		//借金
		foreach ($arr_shakkin_mail_dt as $row) {
			$shakkin_mail_dt .= $row."日・";
		}
		$shakkin_mail_dt = rtrim($shakkin_mail_dt,'・');
		$shakkin_mail_dt = "(".$shakkin_mail_dt.")";
		//相続
		foreach ($arr_souzoku_mail_dt as $row) {
			$souzoku_mail_dt .= $row."日・";
		}
		$souzoku_mail_dt = rtrim($souzoku_mail_dt,'・');
		$souzoku_mail_dt = "(".$souzoku_mail_dt.")";
		//交通事故
		foreach ($arr_koutsujiko_mail_dt as $row) {
			$koutsujiko_mail_dt .= $row."日・";
		}
		$koutsujiko_mail_dt = rtrim($koutsujiko_mail_dt,'・');
		$koutsujiko_mail_dt = "(".$koutsujiko_mail_dt.")";
		//任意売却
		foreach ($arr_ninibaikyaku_mail_dt as $row) {
			$ninibaikyaku_mail_dt .= $row."日・";
		}
		$ninibaikyaku_mail_dt = rtrim($ninibaikyaku_mail_dt,'・');
		$ninibaikyaku_mail_dt = "(".$ninibaikyaku_mail_dt.")";
		//設立
		foreach ($arr_setsuritsu_mail_dt as $row) {
			$setsuritsu_mail_dt .= $row."日・";
		}
		$setsuritsu_mail_dt = rtrim($setsuritsu_mail_dt,'・');
		$setsuritsu_mail_dt = "(".$setsuritsu_mail_dt.")";
		//名義変更
		foreach ($arr_meigihenkou_mail_dt as $row) {
			$meigihenkou_mail_dt .= $row."日・";
		}
		$meigihenkou_mail_dt = rtrim($meigihenkou_mail_dt,'・');
		$meigihenkou_mail_dt = "(".$meigihenkou_mail_dt.")";
		#####事務所情報データの取得
		$stmt3 = $pdo->query("SELECT * FROM ad_req_data WHERE req_id=$id");
		$ad_data = $stmt3->fetch(PDO::FETCH_ASSOC);
		$req_ad_name = $ad_data['req_ad_name'];
		$reviser->setSheetname($sheet_num,$req_ad_name);
		#御担当者名の表示
		$c_name  = $ad_data['person_in_charge'];
		/*問題毎のメールテンプレート文*/
		#借金all_tmp
		if($all_call_shakkin!=null && $all_mail_shakkin!=null){
				$shakkin_all_tmp ="借金問題サイトで".$all_call_shakkin."件の電話と".$all_mail_shakkin."件のメール".$shakkin_mail_dt;
		}elseif($all_call_shakkin!=null && $all_mail_shakkin==null){
				$shakkin_all_tmp ="借金問題サイトで".$all_call_shakkin."件の電話";
		}elseif($all_call_shakkin ==null && $all_mail_shakkin !=null){
				$shakkin_all_tmp ="借金問題サイトで".$all_mail_shakkin."件のメール".$shakkin_mail_dt;
		}
		else{
				$shakkin_all_tmp ="";
		}
		#相続all_tmp
		if($all_call_souzoku!=null && $all_mail_souzoku!=null){
				$souzoku_all_tmp ="相続サイトで".$all_call_souzoku."件の電話と".$all_mail_souzoku."件のメール".$souzoku_mail_dt;
		}elseif($all_call_souzoku!=null && $all_mail_souzoku==null){
				$souzoku_all_tmp ="相続サイトで".$all_call_souzoku."件の電話";
		}elseif($all_call_souzoku ==null && $all_mail_souzoku !=null){
				$souzoku_all_tmp ="相続サイトで".$all_mail_souzoku."件のメール".$souzoku_mail_dt;
		}
		else{
				$souzoku_all_tmp ="";
		}
		#交通事故all_tmp
		if($all_call_koutsujiko!=null && $all_mail_koutsujiko!=null){
				$koutsujiko_all_tmp ="交通事故サイトで".$all_call_koutsujiko."件の電話と".$all_mail_koutsujiko."件のメール".$koutsujiko_mail_dt;
		}elseif($all_call_koutsujiko!=null && $all_mail_koutsujiko==null){
				$koutsujiko_all_tmp ="交通事故サイトで".$all_call_koutsujiko."件の電話";
		}elseif($all_call_koutsujiko ==null && $all_mail_koutsujiko !=null){
				$koutsujiko_all_tmp ="交通事故サイトで".$all_mail_koutsujiko."件のメール".$koutsujiko_mail_dt;
		}
		else{
				$koutsujiko_all_tmp ="";
		}
		#任意売却all_tmp
		if($all_call_ninibaikyaku!=null && $all_mail_ninibaikyaku!=null){
				$ninibaikyaku_all_tmp ="任意売却サイトで".$all_call_ninibaikyaku."件の電話と".$all_mail_ninibaikyaku."件のメール".$ninibaikyaku_mail_dt;
		}elseif($all_call_ninibaikyaku!=null && $all_mail_ninibaikyaku==null){
				$ninibaikyaku_all_tmp ="任意売却サイトで".$all_call_ninibaikyaku."件の電話";
		}elseif($all_call_ninibaikyaku ==null && $all_mail_ninibaikyaku !=null){
				$ninibaikyaku_all_tmp ="任意売却サイトで".$all_mail_ninibaikyaku."件のメール".$ninibaikyaku_mail_dt;
		}
		else{
				$ninibaikyaku_all_tmp ="";
		}
		#名義変更all_tmp
		if($all_call_meigihenkou!=null && $all_mail_meigihenkou!=null){
				$meigihenkou_all_tmp ="名義変更サイトで".$all_call_meigihenkou."件の電話と".$all_mail_meigihenkou."件のメール".$meigihenkou_mail_dt;
		}elseif($all_call_meigihenkou!=null && $all_mail_meigihenkou==null){
				$meigihenkou_all_tmp ="名義変更サイトで".$all_call_meigihenkou."件の電話";
		}elseif($all_call_meigihenkou ==null && $all_mail_meigihenkou !=null){
				$meigihenkou_all_tmp ="名義変更サイトで".$all_mail_meigihenkou."件のメール".$meigihenkou_mail_dt;
		}
		else{
				$meigihenkou_all_tmp ="";
		}
		#会社設立all_tmp
		if($all_call_setsuritsu!=null && $all_mail_setsuritsu!=null){
				$setsuritsu_all_tmp ="会社設立サイトで".$all_call_setsuritsu."件の電話と".$all_mail_setsuritsu."件のメール".$setsuritsu_mail_dt;
		}elseif($all_call_setsuritsu!=null && $all_mail_setsuritsu==null){
				$setsuritsu_all_tmp ="会社設立サイトで".$all_call_setsuritsu."件の電話";
		}elseif($all_call_setsuritsu ==null && $all_mail_setsuritsu !=null){
				$setsuritsu_all_tmp ="会社設立サイトで".$all_mail_setsuritsu."件のメール".$setsuritsu_mail_dt;
		}
		else{
				$setsuritsu_all_tmp ="";
		}
		#刑事事件all_tmp
		if($all_call_keijijiken!=null){
				$keijijiken_all_tmp ="刑事事件サイトで".$all_call_keijijiken."件の電話";
		}
		else{
				$keijijiken_all_tmp ="";
		}
#無効件数の計算
		$res_shakkin = $shakkin_call +$shakkin_mail;
		$res_souzoku = $souzoku_call +$souzoku_mail;
		$res_koutsujiko = $koutsujiko_call +$koutsujiko_mail;
		$res_ninibaikyaku = $ninibaikyaku_call +$ninibaikyaku_mail;
		$res_meigihenkou = $meigihenkou_call +$meigihenkou_mail;
		$res_setsuritsu = $setsuritsu_call +$setsuritsu_mail;
		$res_keijijiken = $keijijiken_call ;
$inv_shakkin = $all_call_shakkin +$all_mail_shakkin-$res_shakkin;
		if($inv_shakkin>0){
			$inv_tmp_shakkin ="借金問題サイトで同一電話番号の電話・メール及び60秒以内電話の".$inv_shakkin."件";
		}
		$inv_souzoku = $all_call_souzoku +$all_mail_souzoku-$res_souzoku;
		if($inv_souzoku>0){
			$inv_tmp_souzoku ="相続問題サイトで同一電話番号の電話・メール及び60秒以内電話の".$inv_souzoku."件";
		}
$inv_koutsujiko = $all_call_koutsujiko +$all_mail_koutsujiko-$res_koutsujiko;
		if($inv_koutsujiko>0){
			$inv_tmp_koutsujiko ="交通事故サイトで同一電話番号の電話・メール及び60秒以内電話の".$inv_koutsujiko."件";
		}
$inv_ninibaikyaku = $all_call_ninibaikyaku +$all_mail_ninibaikyaku-$res_ninibaikyaku;
		if($inv_ninibaikyaku>0){
			$inv_tmp_ninibaikyaku ="任意売却サイトで同一電話番号の電話・メール及び60秒以内電話の".$inv_ninibaikyaku."件";
		}
$inv_meigihenkou = $all_call_meigihenkou +$all_mail_meigihenkou-$res_meigihenkou;
		if($inv_meigihenkou>0){
			$inv_tmp_meigihenkou ="名義変更サイトで同一電話番号の電話・メール及び60秒以内電話の".$inv_meigihenkou."件";
		}
$inv_setsuritsu = $all_call_setsuritsu +$all_mail_setsuritsu-$res_setsuritsu;
		if($inv_setsuritsu>0){
			$inv_tmp_setsuritsu ="会社設立サイトで同一電話番号の電話・メール及び60秒以内電話の".$inv_setsuritsu."件";
		}
$inv_keijijiken = $all_call_keijijiken-$res_keijijiken;
		if($inv_keijijiken>0){
			$inv_tmp_keijijiken ="刑事事件サイトで同一電話番号の電話・メール及び60秒以内電話の".$inv_keijijiken."件";
		}
#有効件数生成
if(!empty($res_shakkin)){
	$va_shakkin ="借金".$res_shakkin."件・";
}
if(!empty($res_souzoku)){
	$va_souzoku ="相続".$res_souzoku."件・";
}
if(!empty($res_koutsujiko)){
	$va_koutsujiko ="交通事故".$res_koutsujiko."件・";
}
if(!empty($res_ninibaikyaku)){
	$va_ninibaikyaku ="任意売却".$res_ninibaikyaku."件・";
}
if(!empty($res_meigihenkou)){
	$va_meigihenkou ="名義変更".$res_meigihenkou."件・";
}
if(!empty($res_setsuritsu)){
	$va_setsuritsu ="会社設立".$res_setsuritsu."件・";
}
if(!empty($res_keijijiken)){
	$va_keijijiken ="刑事事件".$res_keijijiken."件・";
}
//////////////////
/////template文生成
#all_tmp
		if(!empty($shakkin_all_tmp)){
$all_tmp =$all_tmp."
".$shakkin_all_tmp;
		}
		if(!empty($souzoku_all_tmp)){
$all_tmp =$all_tmp."
".$souzoku_all_tmp;
		}
		if(!empty($koutsujiko_all_tmp)){
$all_tmp =$all_tmp."
".$koutsujiko_all_tmp;
		}
		if(!empty($ninibaikyaku_all_tmp)){
$all_tmp =$all_tmp."
".$ninibaikyaku_all_tmp;
		}
		if(!empty($meigihenkou_all_tmp)){
$all_tmp =$all_tmp."
".$meigihenkou_all_tmp;
		}
		if(!empty($setsuritsu_all_tmp)){
$all_tmp =$all_tmp."
".$setsuritsu_all_tmp;
		}
		if(!empty($keijijiken_all_tmp)){
$all_tmp =$all_tmp."
".$keijijiken_all_tmp;
		}
#inv_tmp
		if(!empty($inv_tmp_shakkin)){
$inv_tmp =$inv_tmp."
".$inv_tmp_shakkin;
		}
		if(!empty($inv_tmp_souzoku)){
$inv_tmp =$inv_tmp."
".$inv_tmp_souzoku;
		}
		if(!empty($inv_tmp_koutsujiko)){
$inv_tmp =$inv_tmp."
".$inv_tmp_koutsujiko;
		}
		if(!empty($inv_tmp_ninibaikyaku)){
$inv_tmp =$inv_tmp."
".$inv_tmp_ninibaikyaku;
		}
		if(!empty($inv_tmp_meigihenkou)){
$inv_tmp =$inv_tmp."
".$inv_tmp_meigihenkou;
		}
		if(!empty($inv_tmp_setsuritsu)){
$inv_tmp =$inv_tmp."
".$inv_tmp_setsuritsu;
		}
		if(!empty($inv_tmp_keijijiken)){
$inv_tmp =$inv_tmp."
".$inv_tmp_keijijiken;
		}
if(!empty($inv_tmp)){
$inv_tmp = $inv_tmp."
"."を差し引いて";
}

#valid_tmp
$va_tmp =$va_shakkin.$va_souzoku.$va_koutsujiko.$va_ninibaikyaku.$va_meigihenkou.$va_setsuritsu.$va_keijijiken;
$va_tmp = rtrim($va_tmp,'・');


		###################################
		##ここからがExcelへの記入に関するコード##
		###################################
		#monthを表示用数字に変更
		$month = sprintf('%01d',$month);
		#郵便番号
		$reviser->addString($sheet_num,4,2,"〒".$ad_data['postal_code']);
		#住所
		$reviser->addString($sheet_num,5,2,$ad_data['address_1']);
		$reviser->addString($sheet_num,6,2," ".$ad_data['address_2']);
		#貴社名/御氏名
		$reviser->addString($sheet_num,7,2,$ad_data['req_ad_name']);
		$reviser->addString($sheet_num,8,2,$c_name."　様");
		#行数の定義
		$i=18;
		$reviser->addNumber($sheet_num,$i,0,"1");
		#借金問題
		if($shakkin_call>0 OR $shakkin_mail>0){
				#月
				$reviser->addNumber($sheet_num,$i,1,"$month");	
				#商品名
				$reviser->addString($sheet_num,$i,2,"月成果料金(借金問題)");
				#数量
				$reviser->addNumber($sheet_num,$i,4,$shakkin_call+$shakkin_mail);
				#単価
				$reviser->addNumber($sheet_num,$i,5,10000);
				#合計金額
				$sum = ($shakkin_call+$shakkin_mail)*10000;
				$i=$i+1;
		}
		#相続
		if($souzoku_call>0 OR $souzoku_mail>0){
				#月
				$reviser->addNumber($sheet_num,$i,1,"$month");	
				#商品名
				$reviser->addString($sheet_num,$i,2,"月成果料金(相続)");
				#数量
				$reviser->addNumber($sheet_num,$i,4,$souzoku_call+$souzoku_mail);
				#単価
				$reviser->addNumber($sheet_num,$i,5,5000);
				#合計金額
				$sum = ($souzoku_call + $souzoku_mail)*5000;
				$i=$i+1;
		}
		#交通事故
		if($koutsujiko_call>0 OR $koutsujiko_mail>0){
				#月
				$reviser->addNumber($sheet_num,$i,1,"$month");	
				#商品名
				$reviser->addString($sheet_num,$i,2,"月成果料金(交通事故)");
				#数量
				$reviser->addNumber($sheet_num,$i,4,$koutsujiko_call+$koutsujiko_mail);
				#単価
				$reviser->addNumber($sheet_num,$i,5,10000);
				#合計金額
				$sum = ($koutsujiko_call+$koutsujiko_mail)*10000;
				$i=$i+1;
		}
		#名義変更
		if($meigihenkou_call>0 OR $meigihenkou_mail>0){
				#月
				$reviser->addNumber($sheet_num,$i,1,"$month");	
				#商品名
				$reviser->addString($sheet_num,$i,2,"月成果料金(名義変更)");
				#数量
				$reviser->addNumber($sheet_num,$i,4,$meigihenkou_call+$meigihenkou_mail);
				#単価
				$reviser->addNumber($sheet_num,$i,5,5000);
				#合計金額
				$sum = ($meigihenkou_call+$meigihenkou_mail)*5000;
				$i=$i+1;
		}
		#会社設立
		if($setsuritsu_call>0 OR $setsuritsu_mail>0){
				#月
				$reviser->addNumber($sheet_num,$i,1,"$month");	
				#商品名
				$reviser->addString($sheet_num,$i,2,"月成果料金(会社設立)");
				#数量
				$reviser->addNumber($sheet_num,$i,4,$setsuritsu_call+$setsuritsu_mail);
				#単価
				$reviser->addNumber($sheet_num,$i,5,5000);
				#合計金額
				$sum =($setsuritsu_call + $setsuritsu_mail)*5000;
				$i=$i+1;
		}
		#刑事事件
		if($keijijiken_call>0){
				#月
				$reviser->addNumber($sheet_num,$i,1,"$month");	
				#商品名
				$reviser->addString($sheet_num,$i,2,"月成果料金(刑事事件)");
				#数量
				$reviser->addNumber($sheet_num,$i,4,$keijijiken_call);
				#単価
				$reviser->addNumber($sheet_num,$i,5,10000);
				#合計金額
				$sum =($keijijiken_call)*10000;
				$i=$i+1;
		}
		$i=$i+1;
		$reviser->addNumber($sheet_num,$i,0,"2");
		#月
		$reviser->addNumber($sheet_num,$i,1,"$month");	
		#フリーダイヤル料金記入
		$reviser->addString($sheet_num,$i,2,"月フリーダイヤル通話料金");
		#単価
		$reviser->addNumber($sheet_num,$i,5,$req_mvc_data['call_charge']);
		#合計金額
		$reviser->addNumber($sheet_num,$i,6,$req_mvc_data['call_charge']);
		if($req_mvc_data['count_freedial']!=null){
			$i=$i+1;
			#月
			$reviser->addNumber($sheet_num,$i,1,"$month");
			#発番費用
			$reviser->addString($sheet_num,$i,2,"月フリーダイヤル費用");
			#数量
			$reviser->addNumber($sheet_num,$i,4,$req_mvc_data['count_freedial']);
			#単価
			$reviser->addNumber($sheet_num,$i,5,1000);//変更可能である可能性あり
			#合計金額
			$sum =($req_mvc_data['count_freedial'])*1000;
			$i =$i+1;
		}


		//////////////////////
		//////メールtemplate本文////
		//////////////////////
$sheet_num =1;
$reviser->addString($sheet_num,0,0,"
【専門家検索ドットコム】請求書（".$year."年".$month."月分）

".$c_name."様

いつもお世話になっております。
ウィズパッションの土田です。

".$month."月分の請求書を添付させていただきます。

".$month."月は、".$all_tmp."
が発生致しました。".$inv_tmp."
計".$all_sum."件(".$va_tmp.")を請求させて頂きます。

何かご不明な点があればなんなりとご連絡ください。
今後ともよろしくお願い致します。"

);

#事務所毎でのsheetの名前
$sheet_name = "請求書（".$req_ad_name.$year."年".$month."月分）";
		#テンプレを読み込み、出力する
		$readfile = "./template.xls";	
		$outfile=$sheet_name.".xls";
		$reviser->revisefile($readfile,$outfile);

}#end_of_function/get_each_ad_data

?>
