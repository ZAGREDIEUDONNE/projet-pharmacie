# Rapport détaillé — Suivi Client

Date : 14 juillet 2026

## Audit préalable

La base active `medecin` (MySQL 8.4.7, 82 tables) a été interrogée avant les modifications. Les données réelles utilisées sont :

- `clients` : identité, coordonnées, plafond et solde matérialisé ;
- `ventes` : ventes à crédit (`is_credit=1`) non annulées ;
- `client_reglements` : source des saisies de règlement du module ;
- `paiements_factures` + `factures` : paiements de factures liées aux ventes à crédit ;
- `bons` : avoirs client non annulés ;
- `utilisateurs` : auteur des règlements ;
- `audit_logs` : traçabilité ;
- `caisse_sessions` : auditée, sans écriture créée afin de ne pas dupliquer le flux caisse existant.

Les équivalents réellement déployés sont `ventes_items` (et non `vente_details`) et `paiements_factures` (et non `payments_factures`). Aucune table de règlement supplémentaire n'a été créée.

## Fichiers modifiés ou créés

- `public/index.php` — autoloader étendu à `App\Repositories`.
- `app/Controllers/SuiviClientController.php` — actions, contrôles de permission, filtres, exports et relevés.
- `app/Repositories/SuiviClientRepository.php` — requêtes paramétrées et optimisées.
- `app/Services/SuiviClientService.php` — saisie transactionnelle et audit.
- `app/Views/suivi-client/module.php` — vue partagée opérationnelle.
- `app/Views/suivi-client/{saisie-reglement,ouvrir-saisie,recu-reglement,releve-reglements,liste-clients,solde-courant,releve-courant,solde-arrete,releve-arrete}.php` — vues existantes reliées au rendu fonctionnel.
- `database/migrations/020_suivi_client_indexes.sql` — index idempotents.
- `config/routes.php` — export du module.

## Routes et méthodes

Les routes existantes sont conservées : saisie, enregistrement, reçu, ouverture, relevé règlements, clients, solde et relevé courant, arrêtés. L’export est centralisé sur :

- `GET /suivi-client/export?type=reglements&format=pdf|excel`
- `GET /suivi-client/export?type=releve&client_id={id}&format=pdf|excel`

Méthodes principales : `storeReglement`, `ouvrirSaisie`, `releveReglements`, `listeClients`, `soldeCourant`, `soldeArrete`, `releveCourant`, `releveArrete`, `recuReglement`, `export`.

## Règles et requêtes métiers

- Solde courant et arrêté : `SUM(ventes.montant_net)` crédit moins règlements, paiements factures et avoirs.
- Relevé chronologique : ventes débit, règlements et avoirs crédit, ajustements débit, solde progressif.
- Arrêté : les sous-requêtes utilisent une borne exclusive au lendemain de la date de fin ; aucune opération postérieure n'est incluse.
- Saisie : insertion dans `client_reglements`, recalcul du solde `clients.solde_credit`, puis audit `SAISIE_REGLEMENT_CLIENT`, dans une transaction unique.
- Filtres règlements : numéro, client, référence, utilisateur, date début et date fin.

Index appliqués :

- `client_reglements(client_id, date_mouvement)`
- `ventes(client_id, is_credit, statut_vente, date_vente)`
- `bons(client_id, type_bon, statut_bon, date_emission)`
- `clients(solde_credit, nom, prenom)`

## Fonctionnalités livrées

- Saisie complète, utilisateur connecté, date/heure, mode, référence, observations, audit et solde mis à jour.
- Reçu RC-numéroté, rendu thermique et impression navigateur compatible PDF.
- Recherche d'une saisie, détail et réimpression.
- Relevé des règlements filtrable, totaux, impression/PDF et Excel.
- Liste client avec recherche, tri sécurisé et pagination.
- Soldes avec ventes, règlements, avoirs et solde final.
- Relevés courants et arrêtés avec solde progressif / solde d'ouverture, impression/PDF et Excel.

## Tests effectués et corrections

- Audit réel des colonnes des 9 tables concernées.
- Lint PHP de contrôleur, dépôt, service, routes et toutes les vues : succès.
- Exécution réelle des requêtes clients, soldes courants, soldes arrêtés, paiements et relevés : succès (6 clients, 1 règlement et 1 mouvement présents dans la base au test).
- Contrôle des permissions conservé sur chaque action via `requirePermission`.
- Correction de l’autoloader manuel pour les repositories.
- Correction du calcul : les avoirs sont désormais déduits des soldes et du solde synchronisé.
