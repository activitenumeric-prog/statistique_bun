# Statistiques BUN

Version: `1.0.0`

Plugin SPIP de tableau de bord Matomo pour afficher une vue globale des statistiques d'un site.

## Fonctionnalites

- page de dashboard publique dediee via `spip.php?page=statistiques_bun_dashboard`
- visibilite configurable : `public`, `prive_connecte`, `webmestre`
- configuration de `site_url`, `matomo_url`, `idsite` et `token_auth`
- vue globale avec KPI principaux :
  - visites
  - visiteurs uniques
  - pages vues / visite
  - taux de rebond
  - temps moyen par visite
- histogramme mensuel des visites

## Configuration

Le plugin initialise les valeurs suivantes par defaut :

- `site_url` : `https://notre-environnement.gouv.fr`
- `matomo_url` : `https://audience-sites.din.developpement-durable.gouv.fr/`
- `idsite` : `1492`
- `visibilite_dashboard` : `prive_connecte`

Le `token_auth` est optionnel si l'API Matomo autorise deja la lecture anonyme.

## Fichiers principaux

- `inc/statistiques_bun.php` : appels API Matomo, controle d'acces et rendu du dashboard
- `formulaires/configurer_statistiques_bun.php` : formulaire de configuration
- `statistiques_bun_dashboard.html` : page publique du dashboard
- `css/statistiques_bun.css` : habillage de la vue globale

## Notes

- les appels Matomo sont effectues cote serveur pour ne pas exposer le `token_auth`
- cette premiere version couvre uniquement la vue globale
- les blocs complementaires (sources, appareils, themes, tops pages) pourront etre ajoutes ensuite
