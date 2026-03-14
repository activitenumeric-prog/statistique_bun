<?php

/**
 * Définit les autorisations du plugin Statistiques BUN
 *
 * @plugin     Statistiques BUN
 * @copyright  2026
 * @author     Mikaël
 * @licence    GNU/GPL
 * @package    SPIP\Statistiques BUN\Autorisations
 */

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}


/**
 * Fonction d'appel pour le pipeline
 * @pipeline autoriser
 */
function statistiques_bun_autoriser() {
}


/**
 * Autoriser la configuration du plugin
 *
 * @return bool
 */
function autoriser_statistiques_bun_configurer_dist($faire, $type, $id, $qui, $opt) {
	return autoriser('webmestre', $type, $id, $qui, $opt);
}
