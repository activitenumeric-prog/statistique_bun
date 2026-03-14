<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

include_spip('inc/config');
include_spip('inc/statistiques_bun');

function formulaires_configurer_statistiques_bun_charger_dist() {
	return statistiques_bun_config();
}

function formulaires_configurer_statistiques_bun_verifier_dist() {
	$erreurs = [];

	$site_url = trim((string) _request('site_url'));
	$matomo_url = trim((string) _request('matomo_url'));
	$idsite = trim((string) _request('idsite'));
	$visibilite = trim((string) _request('visibilite_dashboard'));

	if ($site_url !== '' && !filter_var($site_url, FILTER_VALIDATE_URL)) {
		$erreurs['site_url'] = _T('statistiques_bun:erreur_url');
	}

	if ($matomo_url === '' || !filter_var($matomo_url, FILTER_VALIDATE_URL)) {
		$erreurs['matomo_url'] = _T('statistiques_bun:erreur_url');
	}

	if ($idsite === '' || !ctype_digit($idsite) || (int) $idsite <= 0) {
		$erreurs['idsite'] = _T('statistiques_bun:erreur_entier_positif');
	}

	if (!in_array($visibilite, ['public', 'prive_connecte', 'webmestre'], true)) {
		$erreurs['visibilite_dashboard'] = _T('statistiques_bun:erreur_valeur_invalide');
	}

	return $erreurs;
}

function formulaires_configurer_statistiques_bun_traiter_dist() {
	$config = [
		'site_url' => trim((string) _request('site_url')),
		'matomo_url' => trim((string) _request('matomo_url')),
		'idsite' => (int) _request('idsite'),
		'token_auth' => trim((string) _request('token_auth')),
		'visibilite_dashboard' => trim((string) _request('visibilite_dashboard')),
	];

	foreach ($config as $cle => $valeur) {
		ecrire_config('statistiques_bun/' . $cle, $valeur);
	}

	return [
		'message_ok' => _T('statistiques_bun:config_enregistree'),
	];
}
