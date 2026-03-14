<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

include_spip('inc/config');
include_spip('inc/distant');

function statistiques_bun_config_defaults() {
	return [
		'site_url' => 'https://notre-environnement.gouv.fr',
		'matomo_url' => 'https://audience-sites.din.developpement-durable.gouv.fr/',
		'idsite' => 1492,
		'token_auth' => '',
		'visibilite_dashboard' => 'prive_connecte',
	];
}

function statistiques_bun_config() {
	$config = lire_config('statistiques_bun', []);
	if (!is_array($config)) {
		$config = [];
	}

	return array_merge(statistiques_bun_config_defaults(), $config);
}

function statistiques_bun_dashboard_accessible($config = null) {
	$config = is_array($config) ? $config : statistiques_bun_config();
	$visibilite = (string) ($config['visibilite_dashboard'] ?? 'prive_connecte');

	if ($visibilite === 'public') {
		return true;
	}

	include_spip('inc/session');
	$id_auteur = (int) session_get('id_auteur');
	if ($visibilite === 'prive_connecte') {
		return $id_auteur > 0;
	}

	return autoriser('configurer', '_statistiques_bun');
}

function statistiques_bun_render_dashboard($range_key = 'last_month') {
	$config = statistiques_bun_config();
	if (!statistiques_bun_dashboard_accessible($config)) {
		include_spip('inc/headers');
		if (function_exists('http_status')) {
			http_status(403);
		}

		return statistiques_bun_render_state('denied', [
			'title' => _T('statistiques_bun:dashboard_titre'),
			'message' => _T('statistiques_bun:dashboard_acces_refuse'),
			'config_url' => statistiques_bun_dashboard_config_url(),
		]);
	}

	if (empty($config['matomo_url']) || empty($config['idsite'])) {
		return statistiques_bun_render_state('warning', [
			'title' => _T('statistiques_bun:dashboard_titre'),
			'message' => _T('statistiques_bun:dashboard_config_incomplete'),
			'config_url' => statistiques_bun_dashboard_config_url(),
		]);
	}

	$range = statistiques_bun_dashboard_range($range_key);
	$summary = statistiques_bun_matomo_request([
		'method' => 'VisitsSummary.get',
		'period' => 'range',
		'date' => $range['current_api_date'],
	], $config);
	$previous = statistiques_bun_matomo_request([
		'method' => 'VisitsSummary.get',
		'period' => 'range',
		'date' => $range['previous_api_date'],
	], $config);
	$history = statistiques_bun_matomo_request([
		'method' => 'VisitsSummary.get',
		'period' => 'month',
		'date' => statistiques_bun_history_api_range(),
	], $config);

	$messages = [];
	if (!$summary['ok']) {
		$messages[] = $summary['message'];
	}
	if (!$previous['ok']) {
		$messages[] = $previous['message'];
	}
	if (!$history['ok']) {
		$messages[] = $history['message'];
	}

	if (!$summary['ok']) {
		return statistiques_bun_render_state('error', [
			'title' => _T('statistiques_bun:dashboard_titre'),
			'message' => _T('statistiques_bun:dashboard_erreur_api'),
			'details' => $messages,
			'config_url' => statistiques_bun_dashboard_config_url(),
		]);
	}

	$summary_data = is_array($summary['data']) ? $summary['data'] : [];
	$previous_data = ($previous['ok'] && is_array($previous['data'])) ? $previous['data'] : [];
	$history_data = statistiques_bun_extract_history($history['ok'] ? $history['data'] : []);

	return statistiques_bun_render_layout([
		'config' => $config,
		'range' => $range,
		'summary' => $summary_data,
		'previous' => $previous_data,
		'history' => $history_data,
		'messages' => array_values(array_unique(array_filter($messages))),
	]);
}

function statistiques_bun_dashboard_range($range_key = 'last_month') {
	$range_key = (string) $range_key;
	$tz = new DateTimeZone(date_default_timezone_get() ?: 'Europe/Paris');
	$today = new DateTimeImmutable('today', $tz);

	$ranges = [
		'last_month' => function () use ($today) {
			$current_start = $today->modify('first day of last month');
			$current_end = $today->modify('last day of last month');
			$previous_start = $today->modify('first day of -2 month');
			$previous_end = $today->modify('last day of -2 month');

			return [
				'key' => 'last_month',
				'label' => _T('statistiques_bun:dashboard_filtre_last_month'),
				'current_api_date' => statistiques_bun_api_date_range($current_start, $current_end),
				'previous_api_date' => statistiques_bun_api_date_range($previous_start, $previous_end),
			];
		},
		'last_30_days' => function () use ($today) {
			$current_start = $today->modify('-29 days');
			$current_end = $today;
			$previous_start = $today->modify('-59 days');
			$previous_end = $today->modify('-30 days');

			return [
				'key' => 'last_30_days',
				'label' => _T('statistiques_bun:dashboard_filtre_last_30_days'),
				'current_api_date' => statistiques_bun_api_date_range($current_start, $current_end),
				'previous_api_date' => statistiques_bun_api_date_range($previous_start, $previous_end),
			];
		},
		'last_12_months' => function () use ($today) {
			$current_start = $today->modify('first day of -12 month');
			$current_end = $today->modify('last day of last month');
			$previous_start = $today->modify('first day of -24 month');
			$previous_end = $today->modify('last day of -13 month');

			return [
				'key' => 'last_12_months',
				'label' => _T('statistiques_bun:dashboard_filtre_last_12_months'),
				'current_api_date' => statistiques_bun_api_date_range($current_start, $current_end),
				'previous_api_date' => statistiques_bun_api_date_range($previous_start, $previous_end),
			];
		},
	];

	if (!isset($ranges[$range_key])) {
		$range_key = 'last_month';
	}

	return $ranges[$range_key]();
}

function statistiques_bun_history_api_range() {
	$tz = new DateTimeZone(date_default_timezone_get() ?: 'Europe/Paris');
	$today = new DateTimeImmutable('today', $tz);
	$start = $today->modify('first day of -24 month');
	$end = $today->modify('last day of last month');

	return statistiques_bun_api_date_range($start, $end);
}

function statistiques_bun_api_date_range(DateTimeImmutable $start, DateTimeImmutable $end) {
	return $start->format('Y-m-d') . ',' . $end->format('Y-m-d');
}

function statistiques_bun_matomo_request($params, $config = null) {
	$config = is_array($config) ? $config : statistiques_bun_config();
	$base = rtrim((string) ($config['matomo_url'] ?? ''), '/');
	$idsite = (int) ($config['idsite'] ?? 0);
	if ($base === '' || $idsite <= 0) {
		return [
			'ok' => false,
			'message' => _T('statistiques_bun:dashboard_config_incomplete'),
		];
	}

	$query = array_merge([
		'module' => 'API',
		'format' => 'JSON',
		'idSite' => $idsite,
		'filter_limit' => -1,
	], $params);

	$token_auth = trim((string) ($config['token_auth'] ?? ''));
	if ($token_auth !== '') {
		$query['token_auth'] = $token_auth;
	}

	$url = $base . '/index.php?' . http_build_query($query);
	$response = recuperer_url($url, ['timeout' => 20]);
	if (!is_array($response) || empty($response['page'])) {
		return [
			'ok' => false,
			'message' => _T('statistiques_bun:dashboard_erreur_api'),
		];
	}

	$data = json_decode((string) $response['page'], true);
	if (!is_array($data)) {
		return [
			'ok' => false,
			'message' => _T('statistiques_bun:dashboard_erreur_api'),
		];
	}

	if (($data['result'] ?? '') === 'error') {
		return [
			'ok' => false,
			'message' => trim((string) ($data['message'] ?? _T('statistiques_bun:dashboard_erreur_api'))),
		];
	}

	return [
		'ok' => true,
		'data' => $data,
		'message' => '',
	];
}

function statistiques_bun_extract_history($data) {
	$rows = [];
	if (!is_array($data)) {
		return $rows;
	}

	foreach ($data as $key => $row) {
		if (is_array($row) && array_key_exists('nb_visits', $row)) {
			$date_key = is_string($key) ? $key : (string) ($row['label'] ?? '');
			$rows[] = [
				'date_key' => $date_key,
				'label' => statistiques_bun_month_label($date_key),
				'value' => (int) round((float) $row['nb_visits']),
			];
			continue;
		}

		if (is_numeric($row)) {
			$date_key = is_string($key) ? $key : '';
			$rows[] = [
				'date_key' => $date_key,
				'label' => statistiques_bun_month_label($date_key),
				'value' => (int) round((float) $row),
			];
		}
	}

	usort($rows, static function ($a, $b) {
		return strcmp((string) ($a['date_key'] ?? ''), (string) ($b['date_key'] ?? ''));
	});

	$max = 0;
	foreach ($rows as $row) {
		$max = max($max, (int) ($row['value'] ?? 0));
	}

	foreach ($rows as &$row) {
		$value = (int) ($row['value'] ?? 0);
		$row['height'] = $max > 0 ? max(6, (int) round(($value / $max) * 100)) : 0;
		$row['value_label'] = statistiques_bun_format_compact($value);
	}
	unset($row);

	return $rows;
}

function statistiques_bun_render_layout($view) {
	$config = $view['config'];
	$range = $view['range'];
	$summary = $view['summary'];
	$previous = $view['previous'];
	$history = $view['history'];
	$messages = $view['messages'];

	$site_label = statistiques_bun_site_label((string) ($config['site_url'] ?? ''));
	$title = _T('statistiques_bun:dashboard_titre');
	$subtitle = _T('statistiques_bun:dashboard_sous_titre', ['periode' => $range['label']]);

	$cards = [
		statistiques_bun_metric_card(
			_T('statistiques_bun:kpi_visits'),
			(float) ($summary['nb_visits'] ?? 0),
			(float) ($previous['nb_visits'] ?? 0),
			'compact',
			false
		),
		statistiques_bun_metric_card(
			_T('statistiques_bun:kpi_unique_visitors'),
			(float) ($summary['nb_uniq_visitors'] ?? 0),
			(float) ($previous['nb_uniq_visitors'] ?? 0),
			'compact',
			false
		),
		statistiques_bun_metric_card(
			_T('statistiques_bun:kpi_pages_per_visit'),
			(float) ($summary['nb_actions_per_visit'] ?? 0),
			(float) ($previous['nb_actions_per_visit'] ?? 0),
			'decimal',
			false
		),
		statistiques_bun_metric_card(
			_T('statistiques_bun:kpi_bounce_rate'),
			statistiques_bun_numeric((string) ($summary['bounce_rate'] ?? 0)),
			statistiques_bun_numeric((string) ($previous['bounce_rate'] ?? 0)),
			'percent',
			true
		),
		statistiques_bun_metric_card(
			_T('statistiques_bun:kpi_avg_time'),
			(float) ($summary['avg_time_on_site'] ?? 0),
			(float) ($previous['avg_time_on_site'] ?? 0),
			'duration',
			false
		),
	];

	$html = [];
	$html[] = '<div class="sb-page">';
	$html[] = '<div class="sb-shell">';
	$html[] = '<header class="sb-header">';
	$html[] = '<div class="sb-brand">';
	$html[] = '<div class="sb-brand__flag">Republique francaise</div>';
	$html[] = '<div class="sb-brand__site">' . statistiques_bun_escape($site_label) . '</div>';
	$html[] = '</div>';
	$html[] = statistiques_bun_render_filter_form($range['key']);
	$html[] = '</header>';
	$html[] = '<section class="sb-hero">';
	$html[] = '<p class="sb-hero__eyebrow">Matomo dashboard</p>';
	$html[] = '<h1 class="sb-hero__title">' . statistiques_bun_escape($title) . '</h1>';
	$html[] = '<p class="sb-hero__subtitle">' . statistiques_bun_escape(_T('statistiques_bun:dashboard_mesure')) . '<br />' . statistiques_bun_escape($subtitle) . '</p>';
	$html[] = '</section>';

	if ($messages) {
		$html[] = '<div class="sb-notice sb-notice--soft">';
		foreach ($messages as $message) {
			$html[] = '<p>' . statistiques_bun_escape($message) . '</p>';
		}
		$html[] = '</div>';
	}

	$html[] = '<section class="sb-cards">';
	foreach ($cards as $card) {
		$html[] = $card;
	}
	$html[] = '</section>';

	$html[] = '<section class="sb-panel">';
	$html[] = '<div class="sb-panel__header">';
	$html[] = '<span class="sb-legend"><span class="sb-legend__dot"></span>' . statistiques_bun_escape(_T('statistiques_bun:dashboard_legende_visites')) . '</span>';
	$html[] = '<span class="sb-panel__meta">' . statistiques_bun_escape(_T('statistiques_bun:dashboard_mise_a_jour')) . ' : ' . statistiques_bun_escape(date('d/m/Y H:i')) . '</span>';
	$html[] = '</div>';

	if ($history) {
		$html[] = '<div class="sb-chart">';
		foreach ($history as $row) {
			$html[] = '<div class="sb-chart__item">';
			$html[] = '<div class="sb-chart__bar-wrap">';
			$html[] = '<div class="sb-chart__value">' . statistiques_bun_escape($row['value_label']) . '</div>';
			$html[] = '<div class="sb-chart__bar" style="height:' . (int) $row['height'] . '%" title="' . statistiques_bun_escape($row['label'] . ' : ' . $row['value_label']) . '"></div>';
			$html[] = '</div>';
			$html[] = '<div class="sb-chart__label">' . statistiques_bun_escape($row['label']) . '</div>';
			$html[] = '</div>';
		}
		$html[] = '</div>';
	} else {
		$html[] = '<div class="sb-empty">' . statistiques_bun_escape(_T('statistiques_bun:dashboard_aucune_donnee')) . '</div>';
	}

	$html[] = '</section>';

	if (autoriser('configurer', '_statistiques_bun')) {
		$html[] = '<footer class="sb-footer"><a class="sb-footer__link" href="' . statistiques_bun_escape(statistiques_bun_dashboard_config_url()) . '">' . statistiques_bun_escape(_T('statistiques_bun:dashboard_lien_configurer')) . '</a></footer>';
	}

	$html[] = '</div>';
	$html[] = '</div>';

	return implode("\n", $html);
}

function statistiques_bun_metric_card($label, $current, $previous, $format = 'compact', $inverse = false) {
	$current = (float) $current;
	$previous = (float) $previous;
	$change = statistiques_bun_change($current, $previous, $inverse);

	return '<article class="sb-card">'
		. '<div class="sb-card__label">' . statistiques_bun_escape($label) . '</div>'
		. '<div class="sb-card__value">' . statistiques_bun_escape(statistiques_bun_format_metric($current, $format)) . '</div>'
		. '<div class="sb-card__delta sb-card__delta--' . statistiques_bun_escape($change['tone']) . '">' . statistiques_bun_escape($change['label']) . '</div>'
		. '</article>';
}

function statistiques_bun_change($current, $previous, $inverse = false) {
	if ((float) $previous === 0.0) {
		return ['tone' => 'neutral', 'label' => 'vs n-1'];
	}

	$delta = ((float) $current - (float) $previous) / abs((float) $previous) * 100;
	$good = $inverse ? ($delta <= 0) : ($delta >= 0);
	$tone = abs($delta) < 0.05 ? 'neutral' : ($good ? 'up' : 'down');
	$sign = $delta > 0 ? '+' : '';

	return [
		'tone' => $tone,
		'label' => $sign . statistiques_bun_format_decimal($delta, 1) . ' %',
	];
}

function statistiques_bun_format_metric($value, $format) {
	switch ($format) {
		case 'decimal':
			return statistiques_bun_format_decimal($value, 1);
		case 'percent':
			return statistiques_bun_format_decimal($value, 1) . ' %';
		case 'duration':
			return statistiques_bun_format_duration($value);
		case 'compact':
		default:
			return statistiques_bun_format_compact($value);
	}
}

function statistiques_bun_format_compact($value) {
	$value = (float) $value;
	$abs = abs($value);
	if ($abs >= 1000000) {
		return statistiques_bun_format_decimal($value / 1000000, 1) . ' M';
	}
	if ($abs >= 1000) {
		return statistiques_bun_format_decimal($value / 1000, 1) . ' k';
	}
	return statistiques_bun_format_decimal($value, $abs >= 100 ? 0 : 1);
}

function statistiques_bun_format_decimal($value, $precision = 1) {
	return number_format((float) $value, (int) $precision, ',', ' ');
}

function statistiques_bun_format_duration($seconds) {
	$seconds = max(0, (int) round((float) $seconds));
	$hours = (int) floor($seconds / 3600);
	$minutes = (int) floor(($seconds % 3600) / 60);
	$secs = $seconds % 60;

	if ($hours > 0) {
		return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
	}

	return sprintf('%02d:%02d', $minutes, $secs);
}

function statistiques_bun_numeric($value) {
	if (is_numeric($value)) {
		return (float) $value;
	}

	$value = str_replace(',', '.', (string) $value);
	$value = preg_replace('/[^0-9\.\-]/', '', $value);

	return is_numeric($value) ? (float) $value : 0.0;
}

function statistiques_bun_site_label($site_url) {
	$host = parse_url((string) $site_url, PHP_URL_HOST);
	return $host ?: (string) $site_url;
}

function statistiques_bun_month_label($date_key) {
	$date_key = (string) $date_key;
	if ($date_key === '') {
		return '';
	}

	try {
		$date = new DateTimeImmutable($date_key);
	} catch (Exception $e) {
		return $date_key;
	}

	$mois = [
		1 => 'janv.',
		2 => 'fevr.',
		3 => 'mars',
		4 => 'avr.',
		5 => 'mai',
		6 => 'juin',
		7 => 'juil.',
		8 => 'aout',
		9 => 'sept.',
		10 => 'oct.',
		11 => 'nov.',
		12 => 'dec.',
	];

	$m = (int) $date->format('n');
	return ($mois[$m] ?? $date->format('m')) . ' ' . $date->format('Y');
}

function statistiques_bun_render_filter_form($selected_key) {
	$options = [
		'last_month' => _T('statistiques_bun:dashboard_filtre_last_month'),
		'last_30_days' => _T('statistiques_bun:dashboard_filtre_last_30_days'),
		'last_12_months' => _T('statistiques_bun:dashboard_filtre_last_12_months'),
	];

	$html = [];
	$html[] = '<form method="get" class="sb-filter">';
	$html[] = '<input type="hidden" name="page" value="statistiques_bun_dashboard" />';
	$html[] = '<label class="sb-filter__label" for="sb-range-key">' . statistiques_bun_escape(_T('statistiques_bun:dashboard_filtre_label')) . '</label>';
	$html[] = '<select name="range_key" id="sb-range-key" class="sb-filter__select" onchange="this.form.submit()">';
	foreach ($options as $key => $label) {
		$selected = ($selected_key === $key) ? ' selected="selected"' : '';
		$html[] = '<option value="' . statistiques_bun_escape($key) . '"' . $selected . '>' . statistiques_bun_escape($label) . '</option>';
	}
	$html[] = '</select>';
	$html[] = '</form>';

	return implode("\n", $html);
}

function statistiques_bun_render_state($type, $state) {
	$title = $state['title'] ?? _T('statistiques_bun:dashboard_titre');
	$message = $state['message'] ?? '';
	$details = $state['details'] ?? [];
	$config_url = $state['config_url'] ?? '';

	$html = [];
	$html[] = '<div class="sb-page">';
	$html[] = '<div class="sb-shell">';
	$html[] = '<section class="sb-hero sb-hero--state">';
	$html[] = '<p class="sb-hero__eyebrow">Matomo dashboard</p>';
	$html[] = '<h1 class="sb-hero__title">' . statistiques_bun_escape($title) . '</h1>';
	$html[] = '</section>';
	$html[] = '<div class="sb-notice sb-notice--' . statistiques_bun_escape($type) . '">';
	$html[] = '<p>' . statistiques_bun_escape($message) . '</p>';
	foreach ((array) $details as $detail) {
		$html[] = '<p>' . statistiques_bun_escape($detail) . '</p>';
	}
	if ($config_url && autoriser('configurer', '_statistiques_bun')) {
		$html[] = '<p><a href="' . statistiques_bun_escape($config_url) . '">' . statistiques_bun_escape(_T('statistiques_bun:dashboard_lien_configurer')) . '</a></p>';
	}
	$html[] = '</div>';
	$html[] = '</div>';
	$html[] = '</div>';

	return implode("\n", $html);
}

function statistiques_bun_dashboard_config_url() {
	return generer_url_ecrire('configurer_statistiques_bun');
}

function statistiques_bun_escape($value) {
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
