<?php

/**
 * Fonctions utiles au plugin Statistiques BUN
 *
 * @plugin     Statistiques BUN
 * @copyright  2026
 * @author     Mikael
 * @licence    GNU/GPL
 * @package    SPIP\StatistiquesBUN\Fonctions
 */

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

include_spip('inc/statistiques_bun');

function filtre_statistiques_bun_exemple($texte) {
	return strtoupper($texte);
}

function filtre_statistiques_bun_render_dashboard($dummy = '', $range_key = 'last_month') {
	return statistiques_bun_render_dashboard((string) $range_key);
}
