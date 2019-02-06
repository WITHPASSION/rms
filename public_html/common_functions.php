<?php
define('CALL_TYPE_ALL', 0);
define('CALL_TYPE_SAMPLE', 1);
define('CALL_TYPE_VALID', 2);
define('CALL_TYPE_EXCLUSION', 3);
define('MAIL_TYPE_ALL', 0);
define('MAIL_TYPE_SAMPLE', 1);
define('MAIL_TYPE_VALID', 2);
define('MAIL_TYPE_EXCLUSION', 3);
define('FLATRATE_PRICE', 120000);

const SITE_GROUP_NAMES = array(
	"借金",
	"相続",
	"交通",
	"任売",
	"名変",
	"設立",
	"刑事",
	"離婚",
	"Ｂ型",
	"誹謗",
	"時効",
	"労働"
);


function get_ad_group($ad_group_id) {
	global $pdo_request;
	$stmt = $pdo_request->query("
		SELECT
			*
		FROM
			wordpress.ss_ad_groups
		WHERE
			ID = '$ad_group_id'
	");
	$res_arr = $stmt->fetchAll(PDO::FETCH_ASSOC);
	if (count($res_arr) > 0) {
		return $res_arr[0];
	}
	return null;

}
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
		$where = " AND pm.id <> 2 AND v.redirect_status IN (21,22) AND v.dpl_tel_cnt_for_billing = 0 AND v.call_minutes >= v.charge_seconds AND v.dpl_mail_cnt = 0 AND v.is_exclusion = 0";
	}
	else if ($call_type == CALL_TYPE_SAMPLE)
	{
		$where = " AND v.dpl_tel_cnt = 0 AND v.redirect_status IN (21,22) AND v.call_minutes >= 60 AND v.dpl_mail_cnt = 0 AND is_exclusion = 0";
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
					dv.media_type,
					dv.dpl_tel_cnt,
					dv.dpl_mail_cnt,
					dv.dpl_tel_cnt_for_billing,
					dv.date_from,
					dv.call_minutes,
					dv.redirect_status,
					dv.is_exclusion,
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
					dv.date_from BETWEEN pm.from_date AND pm.to_date
				ORDER BY dv.ad_group_id, dv.advertiser_id, dv.id
			) v,
			smk_request_data.ad_group_bill_payer m,
			cdr.office_group_payment_method gpm,
			cdr.payment_method pm
		WHERE
			m.ad_group_id = v.ad_group_id AND
			v.ad_group_id = gpm.ad_group_id AND
			gpm.payment_method_id = pm.id AND
			v.date_from BETWEEN gpm.from_date AND gpm.to_date AND
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
			v.register_dt BETWEEN gpm.from_date AND gpm.to_date AND
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

function get_monthly_total_earnings(
	$year_month,
	$ad_group_id = null
) {
	global $pdo_request;
	$where = "";
	if ($ad_group_id != null)
	{
		$where .= " AND v.ad_group_id = $ad_group_id";
	}
	$stmt = $pdo_request->query("
		SELECT
			m.bill_payer_id,
			v.ad_group_id,
			v.advertiser_id,
			v.site_group,
			group_concat(distinct v.payment_method_id) as payment_method_ids,
			SUM(v.unit_price) price
		FROM
			smk_request_data.ad_group_bill_payer m,
			cdr.charged_actions_view v
		WHERE
			m.ad_group_id = v.ad_group_id AND
			DATE_FORMAT(v.action_date, '%Y%m') = '$year_month' AND
			v.payment_method_id <> 2
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

function get_monthly_group_calls_and_price($year_month, $ad_group_id) {
	global $pdo_request;
	$stmt = $pdo_request->query("
		SELECT DISTINCT
			m.bill_payer_id as bill_payer_id,
			v.ad_group_id,
			v.site_group as site_group,
			v.site_group_name as site_group_name,
			group_concat(distinct gpm.payment_method_id) as payment_method_id,
			group_concat(distinct gpm.unit_price) as unit_price,
			count(v.id) as tel_count
		FROM
			(
				SELECT
					dv.id,
					dv.ad_group_id,
					CAST(dv.advertiser_id AS UNSIGNED) as advertiser_id,
					dv.media_type,
					dv.dpl_tel_cnt,
					dv.dpl_mail_cnt,
					dv.dpl_tel_cnt_for_billing,
					dv.date_from,
					dv.call_minutes,
					dv.redirect_status,
					dv.is_exclusion,
					pm.unit_price,
					pm.charge_seconds,
					sg.site_group,
					sg.site_group_name
				FROM
					cdr.call_data_view dv,
					cdr.office_group_payment_method pm,
					wordpress.ss_site_group sg
				WHERE
					dv.ad_group_id = pm.ad_group_id AND
					sg.media_type = dv.media_type AND
					sg.site_group = pm.site_group AND
					dv.date_from BETWEEN pm.from_date AND pm.to_date
				ORDER BY dv.ad_group_id, dv.advertiser_id, dv.id
			) v,
			smk_request_data.ad_group_bill_payer m,
			cdr.office_group_payment_method gpm
		WHERE
			m.ad_group_id = v.ad_group_id AND
			v.ad_group_id = gpm.ad_group_id AND
			v.date_from BETWEEN gpm.from_date AND gpm.to_date AND
			DATE_FORMAT(v.date_from, '%Y%m') = $year_month AND
			gpm.site_group = v.site_group AND
			gpm.payment_method_id = 0 AND
			v.redirect_status IN (21,22) AND
			v.dpl_tel_cnt_for_billing = 0 AND
			v.call_minutes >= v.charge_seconds AND
			v.dpl_mail_cnt = 0 AND
			v.is_exclusion = 0 AND
			v.ad_group_id = $ad_group_id
		GROUP BY
			m.bill_payer_id,
			v.ad_group_id,
			v.site_group,
			v.unit_price
		WITH ROLLUP
	");
	$res_arr = $stmt->fetchAll(PDO::FETCH_ASSOC);
	return $res_arr;
}

function get_monthly_group_mails_and_price($year_month, $ad_group_id) {
	global $pdo_request;
	$stmt = $pdo_request->query("
		SELECT DISTINCT
			m.bill_payer_id as bill_payer_id,
			adg.ad_group_id as ad_group_id,
			s.site_group as site_group,
			sg.site_group_name as site_group_name,
			group_concat(distinct gpm.payment_method_id) as payment_method_id,
			group_concat(distinct gpm.unit_price) as unit_price,
			count(v.ID) as mail_count
		FROM
			cdr.mail_conv_view v,
			smk_request_data.ad_group_bill_payer m,
			wordpress.ss_site_type s,
			wordpress.ss_site_group sg,
			wordpress.ss_advertiser_ad_group adg,
			cdr.office_group_payment_method gpm
		WHERE
			s.site_type = v.site_type AND
			m.ad_group_id = adg.ad_group_id AND
			adg.advertiser_id = v.advertiser_id AND
			adg.ad_group_id = gpm.ad_group_id AND
			gpm.site_group = s.site_group AND
			sg.site_group = s.site_group AND
			v.register_dt BETWEEN gpm.from_date AND gpm.to_date AND
			DATE_FORMAT(v.register_dt, '%Y%m') = $year_month AND
			gpm.payment_method_id = 0 AND
			v.dpl_tel_cnt = 0 AND
			v.dpl_mail_cnt = 0 AND
			v.is_exclusion = 0 AND
			adg.ad_group_id = $ad_group_id
		GROUP BY
			m.bill_payer_id,
			adg.ad_group_id,
			s.site_group,
			gpm.unit_price
		WITH ROLLUP
	");
	$res_arr = $stmt->fetchAll(PDO::FETCH_ASSOC);
	return $res_arr;
}

function get_monthly_fixedcost_charged_actions($ad_group_id, $datetime) {
	global $pdo_request;
	$stmt = $pdo_request->query("
		SELECT
			DATE_FORMAT(action_date, '%Y%m') ym,
			site_group,
			unit_price,
			COUNT(action_date) count,
			SUM(unit_price) total
		FROM
			cdr.charged_actions_view
		WHERE
			ad_group_id = $ad_group_id AND
			payment_method_id IN (3,4) AND
			action_date <= '$datetime'
		GROUP BY
			DATE_FORMAT(action_date, '%Y%m'),
			site_group,
			unit_price
		ORDER BY
			DATE_FORMAT(action_date, '%Y%m'),
			site_group
	");
	$res_arr = $stmt->fetchAll(PDO::FETCH_ASSOC);
	return $res_arr;
}

function get_monthly_flatrate_costs($ad_group_id, $date) {
	global $pdo_request;
	$stmt = $pdo_request->query("
		SELECT
			ft.*,
			IF (ft.site_groups <> '',
				(
				SELECT
					GROUP_CONCAT(site_group_short_name SEPARATOR ',')
				FROM
					wordpress.ss_site_group
				WHERE
					FIND_IN_SET(site_group, ft.site_groups)
				),
				(
				SELECT
					GROUP_CONCAT(DISTINCT sg.site_group_short_name order by sg.site_group SEPARATOR ',')
				FROM
					cdr.office_group_payment_method opm,
					wordpress.ss_site_group sg
				WHERE
					opm.ad_group_id = ft.ad_group_id AND
					opm.site_group = sg.site_group AND
					ft.dt BETWEEN opm.from_date AND opm.to_date
				GROUP BY
					opm.ad_group_id
				)
			) as site_group_names
		FROM
			cdr.flatrate_cost_view ft
		WHERE
			ft.ad_group_id = $ad_group_id AND
			ft.dt <= '$date'
		ORDER BY
			dt, CAST(site_groups as signed)
	");
	$res_arr = $stmt->fetchAll(PDO::FETCH_ASSOC);
	return $res_arr;
}

function get_last_datetime_of_month($ym) {
	return date('Y-m-d 23:59:59', strtotime('last day of '. $ym));
}

function get_payment_methods($ad_group_id, $date) {
	global $pdo_request;
	$stmt = $pdo_request->query("
		SELECT
			ogpm.ad_group_id,
			ogpm.site_group,
			ogpm.from_date,
			ogpm.to_date,
			ogpm.payment_method_id,
			pm.method,
			ogpm.charge_seconds,
			ogpm.unit_price
		FROM
			cdr.payment_method pm,
			cdr.office_group_payment_method ogpm
		WHERE
			pm.id = ogpm.payment_method_id AND
			ogpm.ad_group_id = $ad_group_id AND
			'$date' BETWEEN from_date AND to_date;
	");
	$res_arr = $stmt->fetchAll(PDO::FETCH_ASSOC);
	return $res_arr;
}

function get_flatrate_costs_for_bill($ad_group_id, $date) {
	global $pdo_request;
	$stmt = $pdo_request->query("
		SELECT DISTINCT
			adg.ID,
			adg.group_name,
			ft.site_groups,
			IF (ft.site_groups <> '',
				(
					SELECT
						GROUP_CONCAT(site_group_name SEPARATOR ',')
					FROM
						wordpress.ss_site_group
					WHERE
						FIND_IN_SET(site_group, ft.site_groups)
				),
				(
					SELECT
						GROUP_CONCAT(DISTINCT sg.site_group_name order by sg.site_group SEPARATOR ',')
					FROM
						cdr.office_group_payment_method opm,
						wordpress.ss_site_group sg
					WHERE
						opm.ad_group_id = adg.ID AND
						opm.site_group = sg.site_group AND
						'$date' BETWEEN opm.from_date AND opm.to_date
					GROUP BY
						opm.ad_group_id
				)
			) as site_group_names,
			ft.dt as flatrate_dt,
			ft.flatrate_count,
			ft.flatrate_count * ".FLATRATE_PRICE." as flatrate_price
		FROM
			wordpress.ss_ad_groups adg,
			cdr.office_group_payment_method gpm
		LEFT OUTER JOIN
			(
				SELECT
					c.*
				FROM
					(
						SELECT
							*
						FROM
							cdr.flatrate_cost
						WHERE
							dt <= '$date'
						ORDER BY
							dt DESC
					) c
				GROUP BY
					c.ad_group_id, c.site_groups
			) ft
		ON
			gpm.ad_group_id = ft.ad_group_id AND
			(ft.site_groups IS NULL OR ft.site_groups = '' OR FIND_IN_SET(gpm.site_group, ft.site_groups))
		WHERE
			adg.ID = $ad_group_id AND
			adg.ID = gpm.ad_group_id AND
			gpm.payment_method_id = 4 AND
			'$date' BETWEEN gpm.from_date AND gpm.to_date
		ORDER BY
			CAST(ft.site_groups as SIGNED);
	");
	$res_arr = $stmt->fetchAll(PDO::FETCH_ASSOC);
	return $res_arr;
}

function get_tofixed_costs_for_bill($ad_group_id, $date) {
	global $pdo_request;
	$stmt = $pdo_request->query("
		SELECT DISTINCT
			adg.ID,
			adg.group_name,
			ft.site_groups,
			IF (ft.site_groups <> '',
				(
					SELECT
						GROUP_CONCAT(site_group_name SEPARATOR ',')
					FROM
						wordpress.ss_site_group
					WHERE
						FIND_IN_SET(site_group, ft.site_groups)
				),
				(
					SELECT
						GROUP_CONCAT(DISTINCT sg.site_group_name order by sg.site_group SEPARATOR ',')
					FROM
						cdr.office_group_payment_method opm,
						wordpress.ss_site_group sg
					WHERE
						opm.ad_group_id = adg.ID AND
						opm.site_group = sg.site_group AND
						'$date' BETWEEN opm.from_date AND opm.to_date
					GROUP BY
						opm.ad_group_id
				)
			) as site_group_names,
			ft.dt as flatrate_dt,
			ft.flatrate_price
		FROM
			wordpress.ss_ad_groups adg,
			cdr.office_group_payment_method gpm
		LEFT OUTER JOIN
			(
				SELECT
					c.*
				FROM
					(
						SELECT
							*
						FROM
							cdr.tofixed_cost
						WHERE
							dt <= '$date'
						ORDER BY
							dt DESC
					) c
				GROUP BY
					c.ad_group_id, c.site_groups
			) ft
		ON
			gpm.ad_group_id = ft.ad_group_id AND
			(ft.site_groups IS NULL OR ft.site_groups = '' OR FIND_IN_SET(gpm.site_group, ft.site_groups))
		WHERE
			adg.ID = $ad_group_id AND
			adg.ID = gpm.ad_group_id AND
			gpm.payment_method_id = 3 AND
			'$date' BETWEEN gpm.from_date AND gpm.to_date
		ORDER BY
			CAST(ft.site_groups as SIGNED)
	");
	$res_arr = $stmt->fetchAll(PDO::FETCH_ASSOC);
	return $res_arr;
}