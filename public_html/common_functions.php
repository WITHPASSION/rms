<?php
define('CALL_TYPE_ALL', 0);
define('CALL_TYPE_SAMPLE', 1);
define('CALL_TYPE_VALID', 2);
define('CALL_TYPE_EXCLUSION', 3);
define('MAIL_TYPE_ALL', 0);
define('MAIL_TYPE_SAMPLE', 1);
define('MAIL_TYPE_VALID', 2);
define('MAIL_TYPE_EXCLUSION', 3);

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

function get_monthly_total_calls(
	$year_month,
	$call_type = CALL_TYPE_ALL,
	$ad_group_id = null
) {
	global $pdo_request;
	$where = "";
	if ($call_type == CALL_TYPE_VALID)
	{
		$where = " AND pm.id <> 2 AND v.dpl_tel_cnt_for_billing = 0 AND v.call_minutes >= v.charge_seconds AND v.dpl_mail_cnt = 0 AND v.is_exclusion = 0";
	}
	else if ($call_type == CALL_TYPE_SAMPLE)
	{
		$where = " AND v.dpl_tel_cnt = 0 AND v.call_minutes >= 60 AND v.dpl_mail_cnt = 0 AND is_exclusion = 0";
	}
	if ($ad_group_id != null)
	{
		$where .= " AND v.ad_group_id = $ad_group_id";
	}
	$stmt = $pdo_request->query("
		SELECT
			m.bill_payer_id as bill_payer_id,
			v.ad_group_id,
			v.advertiser_id as advertiser_id,
			v.site_group as site_group,
			group_concat(distinct pm.method) as payment_method,
			count(v.id) as tel_count
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
					dv.media_type,
					dv.dpl_tel_cnt,
					dv.dpl_mail_cnt,
					dv.dpl_tel_cnt_for_billing,
					dv.date_from,
					dv.call_minutes,
					dv.is_exclusion,
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
			gpm.site_group = v.site_group
			$where
		GROUP BY
			m.bill_payer_id,
			v.ad_group_id,
			v.advertiser_id,
			v.site_group
		WITH ROLLUP
	");
	$res_arr = $stmt->fetchAll(PDO::FETCH_ASSOC);
	return $res_arr;
}

function get_monthly_total_mails(
	$year_month,
	$mail_type = MAIL_TYPE_ALL,
	$ad_group_id = null
) {
	global $pdo_request;
	$where = "";
	if ($mail_type == MAIL_TYPE_VALID)
	{
		$where = " AND v.dpl_tel_cnt = 0 AND v.dpl_mail_cnt = 0 AND pm.id <> 2 AND is_exclusion = 0";
	}
	else if ($mail_type == MAIL_TYPE_SAMPLE)
	{
		$where = " AND v.dpl_tel_cnt = 0 AND v.dpl_mail_cnt = 0 AND is_exclusion = 0";
	}
	if ($ad_group_id != null)
	{
		$where .= " AND adg.ad_group_id = $ad_group_id";
	}
	$stmt = $pdo_request->query("
		SELECT
			m.bill_payer_id as bill_payer_id,
			adg.ad_group_id as ad_group_id,
			v.advertiser_id as advertiser_id,
			s.site_group as site_group,
			group_concat(distinct pm.method) as payment_method,
			count(v.ID) as mail_count
		FROM
			cdr.mail_conv_view v,
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
			gpm.site_group = s.site_group AND
			gpm.payment_method_id = pm.id AND
			CAST(v.register_dt AS DATE) BETWEEN gpm.from_date AND gpm.to_date AND
			DATE_FORMAT(v.register_dt, '%Y%m') = '$year_month'
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

function append_call_counts($ad_group_id, $call_data_arr, &$bp_arr, &$adg_arr, &$sgp_arr, $count_type)
{
	foreach ($call_data_arr as $call_data) {
		if ($call_data['ad_group_id'] == null)
		{
			continue;
		}
		$ad_id = $call_data['advertiser_id'];
		$site_group = $call_data['site_group'];
		$tel_count = $call_data['tel_count'];
		if ($ad_id != null && $site_group != null)
		{
			if (!isset($adg_arr[$ad_group_id]['total'][$count_type])) {
				$adg_arr[$ad_group_id]['total'][$count_type] = 0;
			}
			$adg_arr[$ad_group_id]['total'][$count_type] += intval($tel_count);
			if (!isset($adg_arr[$ad_group_id][$ad_id]['total'][$count_type])) {
				$adg_arr[$ad_group_id][$ad_id]['total'][$count_type] = 0;
			}
			$adg_arr[$ad_group_id][$ad_id]['total'][$count_type] += intval($tel_count);
			if (!isset($adg_arr[$ad_group_id][$ad_id][$site_group][$count_type])) {
				$adg_arr[$ad_group_id][$ad_id][$site_group][$count_type] = 0;
			}
			$adg_arr[$ad_group_id][$ad_id][$site_group][$count_type] = intval($tel_count);

			if (!isset($sgp_arr[$ad_group_id][$site_group][$count_type])) {
				$sgp_arr[$ad_group_id][$site_group][$count_type] = 0;
			}
			$sgp_arr[$ad_group_id][$site_group][$count_type] += intval($tel_count);

			if (!isset($bp_arr['total'][$count_type])) {
				$bp_arr['total'][$count_type] = 0;
			}
			$bp_arr['total'][$count_type] += intval($tel_count);
			if (!isset($bp_arr[$site_group][$count_type])) {
				$bp_arr[$site_group][$count_type] = 0;
			}
			$bp_arr[$site_group][$count_type] += intval($tel_count);
		}
	}
}

function append_mail_counts($ad_group_id, $mail_data_arr, &$bp_arr, &$adg_arr, &$sgp_arr, $count_type)
{
	foreach ($mail_data_arr as $mail_data) {
		if ($mail_data['ad_group_id'] == null)
		{
			continue;
		}
		$ad_id = $mail_data['advertiser_id'];
		$site_group = $mail_data['site_group'];
		$mail_count = $mail_data['mail_count'];
		if ($ad_id != null && $site_group != null)
		{
			if (!isset($adg_arr[$ad_group_id]['total'][$count_type])) {
				$adg_arr[$ad_group_id]['total'][$count_type] = 0;
			}
			$adg_arr[$ad_group_id]['total'][$count_type] += intval($mail_count);
			if (!isset($adg_arr[$ad_group_id][$ad_id]['total'][$count_type])) {
				$adg_arr[$ad_group_id][$ad_id]['total'][$count_type] = 0;
			}
			$adg_arr[$ad_group_id][$ad_id]['total'][$count_type] += intval($mail_count);
			if (!isset($adg_arr[$ad_group_id][$ad_id][$site_group][$count_type])) {
				$adg_arr[$ad_group_id][$ad_id][$site_group][$count_type] = 0;
			}
			$adg_arr[$ad_group_id][$ad_id][$site_group][$count_type] = intval($mail_count);

			if (!isset($sgp_arr[$ad_group_id][$site_group][$count_type])) {
				$sgp_arr[$ad_group_id][$site_group][$count_type] = 0;
			}
			$sgp_arr[$ad_group_id][$site_group][$count_type] += intval($mail_count);

			if (!isset($bp_arr['total'][$count_type])) {
				$bp_arr['total'][$count_type] = 0;
			}
			$bp_arr['total'][$count_type] += intval($mail_count);
			if (!isset($bp_arr[$site_group][$count_type])) {
				$bp_arr[$site_group][$count_type] = 0;
			}
			$bp_arr[$site_group][$count_type] += intval($mail_count);
		}
	}
}