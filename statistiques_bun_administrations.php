<?php

/**
 * Fichier gerant l'installation et desinstallation du plugin Statistiques BUN
 *
 * @plugin     Statistiques BUN
 * @copyright  2026
 * @author     Mikael
 * @licence    GNU/GPL
 * @package    SPIP\StatistiquesBUN\Installation
 */

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

include_spip('inc/config');
include_spip('inc/statistiques_bun');

function statistiques_bun_upgrade($nom_meta_base_version, $version_cible) {
	$maj = [];

	$maj['create'] = [
		['statistiques_bun_initialiser_config'],
	];

	$maj['1.1.0'] = [
		['statistiques_bun_initialiser_config'],
	];

	include_spip('base/upgrade');
	maj_plugin($nom_meta_base_version, $version_cible, $maj);
}

function statistiques_bun_vider_tables($nom_meta_base_version) {
	effacer_config('statistiques_bun');
	effacer_meta($nom_meta_base_version);
}

function statistiques_bun_initialiser_config() {
	$defaults = statistiques_bun_config_defaults();
	foreach ($defaults as $cle => $valeur) {
		if (lire_config('statistiques_bun/' . $cle, null) === null) {
			ecrire_config('statistiques_bun/' . $cle, $valeur);
		}
	}
}
