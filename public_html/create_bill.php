<?php
#データベース接続処理
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
$year_month = "$year"."0$month";
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
		#無効も含めた全コール数,メール数の取得
		$all_call_shakkin = null;
		$all_call_souzoku = null;
		$all_call_koutsujiko = null;
		$all_call_ninibaikyaku = null;
		$all_call_meigihenkou = null;
		$all_call_setsuritsu = null;
		$all_call_keijijiken = null;
		$all_mail_shakkin = null;
		$all_mail_souzoku = null;
		$all_mail_koutsujiko = null;
		$all_mail_ninibaikyaku = null;
		$all_mail_meigihenkou = null;
		$all_mail_setsuritsu = null;
		$shakkin_mail_dt = null;
		$souzoku_mail_dt = null;
		$koutsujiko_mail_dt = null;
		$ninibaikyaku_mail_dt =null;
		$meigihenkou_mail_dt=null;
		$setsuritsu_mail_dt =null;
		$mail_dt =null;
		#無効アリコール数
		$stmt=$pdo->query("SELECT * FROM adid_reqid_matching WHERE reqid =$id");
		$arr_ad_id =$stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach($arr_ad_id as $row){
			$adid = $row['adid'];
			$stmt = $pdo2->query("SELECT count(*),media_id FROM call_data_view WHERE advertiser_id = $adid AND DATE_FORMAT(date_from,'%Y%m')=$year_month GROUP BY media_id");
			$arr_all_call_data =$stmt->fetchAll(PDO::FETCH_ASSOC);
			foreach($arr_all_call_data as $row){
					$all_call_count = $row['count(*)'];
					$mi =$row['media_id'];
						if($mi == null || $mi == ""){
								$all_call_shakkin += $all_call_count;
						}
						if($mi == "B"){
								$all_call_souzoku += $all_call_count;
						}
						if($mi == "C"){
								$all_call_koutsujiko += $all_call_count;
						}
						if($mi == "D"){
								$all_call_ninibaikyaku += $all_call_count;
						}
						if($mi == "E"){
								$all_call_meigihenkou += $all_call_count;
						}
						if($mi == "F"){
								$all_call_setsuritsu += $all_call_count;
						}
						if($mi == "G"){
								$all_call_keijijiken += $all_call_count;
						}	
			}
				#無効アリメール数,メール日取得
			$stmt2 = $pdo2->query("SELECT count(*),site_type,DATE_FORMAT(register_dt,'%m%d') FROM mail_conv WHERE advertiser_id = $adid AND DATE_FORMAT(register_dt,'%Y%m')=$year_month GROUP BY site_type");
			$arr_all_mail_data = $stmt2->fetchAll(PDO::FETCH_ASSOC);
			foreach($arr_all_mail_data as $row){
				$all_mail_count =$row['count(*)'];
				$st=$row['site_type'];
				$register_dt = $row["DATE_FORMAT(register_dt,'%m%d')"];
				$mail_month =substr($register_dt,0,2);
				$mail_day = substr($register_dt,2,2);
				$mail_dt = "(".$mail_month."月".$mail_day."日)";
					 if($st ==0 || $st ==1 || $st ==2 || $st ==6 ||$st ==7 ||$st ==8 ||$st ==9|| $st==10||$st ==11||$st==12||$st==13||$st==15){
					 		$all_mail_shakkin += $all_mail_count;
				$shakkin_mail_dt .=$mail_dt;
					 }if($st ==3){
					 		$all_mail_souzoku +=$all_mail_count;
				$souzokumail_mail_dt .=$mail_dt;
					 }if($st ==14){
					 		$all_mail_koutsujiko +=$all_mail_count;
				$koutsujiko_mail_dt .=$mail_dt;
					 }if($st ==16){
					 		$all_mail_ninibaikyaku +=$all_mail_count;
				$ninibaikyaku_mail_dt .=$mail_dt;
					 }if($st ==18){ 
					 		$all_mail_setsuritsu +=$all_mail_count;
				$setsuritsu_mail_dt .=$mail_dt;
					 }if($st ==17){
					 		$all_mail_meigihenkou +=$all_mail_count;
				$meigihenkou_mail_dt .=$mail_dt;
					 }
			}
		}
		#####事務所情報データの取得
		$stmt3 = $pdo->query("SELECT * FROM ad_req_data WHERE req_id=$id");
		$ad_data = $stmt3->fetch(PDO::FETCH_ASSOC);
		$req_ad_name = $ad_data['req_ad_name'];
		$reviser->setSheetname($sheet_num,$req_ad_name);
		#御担当者名の表示
		$c_name  = $ad_data['person_in_charge'];
		$reviser->addString($sheet_num,8,2,$c_name."　様");
		#####データベースからの取得内容表示
		#郵便番号
		$reviser->addString($sheet_num,4,2,"〒".$ad_data['postal_code']);
		#住所
		$reviser->addString($sheet_num,5,2,$ad_data['address_1']);
		$reviser->addString($sheet_num,6,2," ".$ad_data['address_2']);
		#貴社名/御氏名
		$reviser->addString($sheet_num,7,2,$ad_data['req_ad_name']);
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
				$reviser->addString($sheet_num,$i-1,6,"=E$i*F$i");
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
				$reviser->addString($sheet_num,$i-1,6,"=E$i*F$i");
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
				$reviser->addString($sheet_num,$i-1,6,"=E$i*F$i");
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
				$reviser->addString($sheet_num,$i-1,6,"=E$i*F$i");
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
				$reviser->addString($sheet_num,$i-1,6,"=E$i*F$i");
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
				$reviser->addString($sheet_num,$i-1,6,"=E$i*F$i");
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
			$reviser->addString($sheet_num,$i-1,6,"=E$i*F$i");
		}
		#メール内容の書き込み
		
		$sheet_num =1;
		#問題毎のメールテンプレート文
		#借金tmp
		if($all_call_shakkin!=null && $all_mail_shakkin!=null){
				$shakkin_tmp ="借金問題サイトで".$all_call_shakkin."件の電話と".$all_mail_shakkin."件のメール".$shakkin_mail_dt;
		}elseif($all_call_shakkin!=null && $all_mail_shakkin==null){
				$shakkin_tmp ="借金問題サイトで".$all_call_shakkin."件の電話";
		}elseif($all_call_shakkin ==null && $all_mail_shakkin !=null){
				$shakkin_tmp ="借金問題サイトで".$all_mail_shakkin."件のメール".$shakkin_mail_dt;
		}
		else{
				$shakkin_tmp ="";
		}
		#相続tmp
		if($all_call_souzoku!=null && $all_mail_souzoku!=null){
				$souzoku_tmp ="相続サイトで".$all_call_souzoku."件の電話と".$all_mail_souzoku."件のメール".$souzoku_mail_dt;
		}elseif($all_call_souzoku!=null && $all_mail_souzoku==null){
				$souzoku_tmp ="相続サイトで".$all_call_souzoku."件の電話";
		}elseif($all_call_souzoku ==null && $all_mail_souzoku !=null){
				$souzoku_tmp ="相続サイトで".$all_mail_souzoku."件のメール".$souzoku_mail_dt;
		}
		else{
				$souzoku_tmp ="";
		}
		#交通事故tmp
		if($all_call_koutsujiko!=null && $all_mail_koutsujiko!=null){
				$koutsujiko_tmp ="交通事故サイトで".$all_call_koutsujiko."件の電話と".$all_mail_koutsujiko."件のメール".$koutsujiko_mail_dt;
		}elseif($all_call_koutsujiko!=null && $all_mail_koutsujiko==null){
				$koutsujiko_tmp ="交通事故サイトで".$all_call_koutsujiko."件の電話";
		}elseif($all_call_koutsujiko ==null && $all_mail_koutsujiko !=null){
				$koutsujiko_tmp ="交通事故サイトで".$all_mail_koutsujiko."件のメール".$koutsujiko_mail_dt;
		}
		else{
				$koutsujiko_tmp ="";
		}
		#任意売却tmp
		if($all_call_ninibaikyaku!=null && $all_mail_ninibaikyaku!=null){
				$ninibaikyaku_tmp ="任意売却サイトで".$all_call_ninibaikyaku."件の電話と".$all_mail_ninibaikyaku."件のメール".$ninibaikyaku_mail_dt;
		}elseif($all_call_ninibaikyaku!=null && $all_mail_ninibaikyaku==null){
				$ninibaikyaku_tmp ="任意売却サイトで".$all_call_ninibaikyaku."件の電話";
		}elseif($all_call_ninibaikyaku ==null && $all_mail_ninibaikyaku !=null){
				$ninibaikyaku_tmp ="任意売却サイトで".$all_mail_ninibaikyaku."件のメール".$ninibaikyaku_mail_dt;
		}
		else{
				$ninibaikyaku_tmp ="";
		}
		#名義変更tmp
		if($all_call_meigihenkou!=null && $all_mail_meigihenkou!=null){
				$meigihenkou_tmp ="名義変更サイトで".$all_call_meigihenkou."件の電話と".$all_mail_meigihenkou."件のメール".$meigihenkou_mail_dt;
		}elseif($all_call_meigihenkou!=null && $all_mail_meigihenkou==null){
				$meigihenkou_tmp ="名義変更サイトで".$all_call_meigihenkou."件の電話";
		}elseif($all_call_meigihenkou ==null && $all_mail_meigihenkou !=null){
				$meigihenkou_tmp ="名義変更サイトで".$all_mail_meigihenkou."件のメール".$megihenkou_mail_dt;
		}
		else{
				$meigihenkou_tmp ="";
		}
		#会社設立tmp
		if($all_call_setsuritsu!=null && $all_mail_setsuritsu!=null){
				$setsuritsu_tmp ="会社設立サイトで".$all_call_setsuritsu."件の電話と".$all_mail_setsuritsu."件のメール".$setsuritsu_mail_dt;
		}elseif($all_call_setsuritsu!=null && $all_mail_setsuritsu==null){
				$setsuritsu_tmp ="会社設立サイトで".$all_call_setsuritsu."件の電話";
		}elseif($all_call_setsuritsu ==null && $all_mail_setsuritsu !=null){
				$setsuritsu_tmp ="会社設立サイトで".$all_mail_setsuritsu."件のメール".$setsuritsu_mail_dt;
		}
		else{
				$setsuritsu_tmp ="";
		}
		#刑事事件tmp
		if($all_call_keijijiken!=null){
				$keijijiken_tmp ="刑事事件サイトで".$all_call_keijijiken."件の電話";
		}
		else{
				$keijijiken_tmp ="";
		}
		#無効内容の取得
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
		$res_shakkin = $shakkin_call +$shakkin_mail;
		$res_souzoku = $souzoku_call +$souzoku_mail;
		$res_koutsujiko = $koutsujiko_call +$koutsujiko_mail;
		$res_ninibaikyaku = $ninibaikyaku_call +$ninibaikyaku_mail;
		$res_meigihenkou = $meigihenkou_call +$meigihenkou_mail;
		$res_setsuritsu = $setsuritsu_call +$setsuritsu_mail;
		$res_keijijiken = $keijijiken_call ;

		$inv_shakkin = $all_call_shakkin +$all_mail_shakkin-$res_shakkin;
		if($inv_shakkin>0){
			$inv_tmp_shakkin ="借金問題サイトの同一電話番号とメール、60秒以内電話の".$inv_shakkin."件";
		}
		$inv_souzoku = $all_call_souzoku +$all_mail_souzoku-$res_souzoku;
		if($inv_souzoku>0){
			$inv_tmp_souzoku ="相続問題サイトの同一電話番号とメール、60秒以内電話の".$inv_souzoku."件";
		}
$inv_koutsujiko = $all_call_koutsujiko +$all_mail_koutsujiko-$res_koutsujiko;
		if($inv_koutsujiko>0){
			$inv_tmp_koutsujiko ="交通事故サイトの同一電話番号とメール、60秒以内電話の".$inv_koutsujiko."件";
		}
$inv_ninibaikyaku = $all_call_ninibaikyaku +$all_mail_ninibaikyaku-$res_ninibaikyaku;
		if($inv_ninibaikyaku>0){
			$inv_tmp_ninibaikyaku ="任意売却サイトの同一電話番号とメール、60秒以内電話の".$inv_ninibaikyaku."件";
		}
$inv_meigihenkou = $all_call_meigihenkou +$all_mail_meigihenkou-$res_meigihenkou;
		if($inv_meigihenkou>0){
			$inv_tmp_meigihenkou ="名義変更サイトの同一電話番号とメール、60秒以内電話の".$inv_meigihenkou."件";
		}
$inv_setsuritsu = $all_call_setsuritsu +$all_mail_setsuritsu-$res_setsuritsu;
		if($inv_setsuritsu>0){
			$inv_tmp_setsuritsu ="会社設立サイトの同一電話番号とメール、60秒以内電話の".$inv_setsuritsu."件";
		}
$inv_keijijiken = $all_call_keijijiken-$res_keijijiken;
		if($inv_keijijiken>0){
			$inv_tmp_keijijiken ="刑事事件サイトの同一電話番号とメール、60秒以内電話の".$inv_keijijiken."件";
		}
		#合計詳細
		$va_shakkin= null;
		$va_souzoku = null;
		$va_koutsujiko=null;
		$va_ninibaikyaku =null;
		$va_meigihenkou =null;
		$va_setsuritsu =null;
		$va_keijijiken =null;
		if(!empty($res_shakkin)){
			$va_shakkin ="(借金".$res_shakkin."件)";
		}
		if(!empty($res_souzoku)){
			$va_souzoku ="(相続".$res_souzoku."件)";
		}
		if(!empty($res_koutsujiko)){
			$va_koutsujiko ="(交通事故".$res_koutsujiko."件)";
		}
		if(!empty($res_ninibaikyaku)){
			$va_ninibaikyaku ="(任意売却".$res_ninibaikyaku."件)";
		}
		if(!empty($res_meigihenkou)){
			$va_meigihenkou ="(名義変更".$res_meigihenkou."件)";
		}
		if(!empty($res_setsuritsu)){
			$va_setsuritsu ="(会社設立".$res_setsuritsu."件)";
		}
		if(!empty($res_keijijiken)){
			$va_keijijiken ="(刑事事件".$res_keijijiken."件)";
		}
		#template本文
		$reviser->addString($sheet_num,0,0,$c_name."様");
		$reviser->addString($sheet_num,1,0,"いつもお世話になっております。");
		$reviser->addString($sheet_num,2,0,"ウィズパッションの土田です。");
		$reviser->addString($sheet_num,3,0,$month."月分の請求書を添付させていただきます。");
		$r =4;
		if(!empty($shakkin_tmp)){
				$reviser->addString($sheet_num,$r,0,$shakkin_tmp);
				$r+=1;
		}
		if(!empty($souzoku_tmp)){
				$reviser->addString($sheet_num,$r,0,$souzoku_tmp);
				$r+=1;
		}
		if(!empty($koutsujiko_tmp)){
				$reviser->addString($sheet_num,$r,0,$koutsujiko_tmp);
				$r+=1;
		}
		if(!empty($ninibaikyaku_tmp)){
				$reviser->addString($sheet_num,$r,0,$ninibaikyaku_tmp);
				$r+=1;
		}
		if(!empty($meigihenkou_tmp)){
				$reviser->addString($sheet_num,$r,0,$meigihenkou_tmp);
				$r+=1;
		}
		if(!empty($setsuritsu_tmp)){
				$reviser->addString($sheet_num,$r,0,$setsuritsu_tmp);
				$r+=1;
		}
		if(!empty($keijijiken_tmp)){
				$reviser->addString($sheet_num,$r,0,$keijijiken_tmp);
				$r+=1;
		}
$reviser->addString($sheet_num,$r,0,"が発生致しました");
$r +=1;
if(!empty($inv_shakkin)){
		$reviser->addString($sheet_num,$r,0,$inv_tmp_shakkin);
		$r +=1;
}

if(!empty($inv_souzoku)){
$reviser->addString($sheet_num,$r,0,$inv_tmp_souzoku);
		$r +=1;
}
if(!empty($inv_koutsujiko)){
$reviser->addString($sheet_num,$r,0,$inv_tmp_koutsujiko);
		$r +=1;
}
if(!empty($inv_ninibaikyaku)){
$reviser->addString($sheet_num,$r,0,$inv_tmp_ninibaikyaku);
		$r +=1;
}
if(!empty($inv_meigihenkou)){
$reviser->addString($sheet_num,$r,0,$inv_tmp_meigihenkou);
		$r +=1;
}
if(!empty($inv_setsuritsu)){
$reviser->addString($sheet_num,$r,0,$inv_tmp_setsuritsu);
		$r +=1;
}
if(!empty($inv_keijijiken)){
$reviser->addString($sheet_num,$r,0,$inv_tmp_keijijiken);
		$r +=1;
}
$reviser->addString($sheet_num,$r,0,"を差し引いて");
		$r +=1;
$reviser->addString($sheet_num,$r,0,"計".$all_sum."件分($va_shakkin$va_souzoku$va_koutsujiko$va_ninibaikyaku$va_meigihenkou$va_setsuritsu$va_keijijiken)を請求させて頂きます。");


		#テンプレを読み込み、出力する
		$readfile = "./template.xls";	
		$outfile="$year$month$req_ad_name.xls";
		$reviser->revisefile($readfile,$outfile);

}#end_of_function/get_each_ad_data

?>
