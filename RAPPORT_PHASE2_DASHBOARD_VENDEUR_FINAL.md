# Rapport final — Phase 2, dashboard vendeur

Date : 15 août 2026  
Méthode : audit statique, validation SQL en lecture et test d'intégration dans une transaction systématiquement annulée (`ROLLBACK`). La migration 031 n'a pas été exécutée.

## Synthèse

| # | Problème | État | Correction | Test |
|---|---|---|---|---|
| 1 | Tickets suspendus | CORRIGÉ | Les nouveaux brouillons n'ont aucun effet stock, caisse ou comptabilité. La reprise finalise la même vente. Les tickets historiques déjà comptabilisés sont désormais bloqués en lecture seule. | Vente suspendue → reprise : PASS ; protection d'un historique : PASS |
| 2 | Prix vente | CORRIGÉ | Catalogue, panier, scanner et écriture de ligne utilisent `produits.prix_vente`; `prix_achat` reste limité au coût/marge/valorisation. | Prix de ligne = prix catalogue : PASS |
| 3 | Clients | CORRIGÉ | Les requêtes liste/recherche emploient les colonnes réelles et conservent les alias attendus par les vues. | Requêtes `/clients` et `/clients/search` : PASS SQL |
| 4 | HT/TVA/TTC | CORRIGÉ | Le taux actif `tva_taux` est appliqué avant comptabilisation et les champs HT, TVA, TTC sont enregistrés. | 432,20 + 77,80 = 510,00 ; débit = crédit : PASS |
| 5 | Voir vente | CORRIGÉ | Route et méthode `GET /vente/show/{id}` ajoutées, avec affichage 404 pour une vente absente. | Routes et vue : PASS statique |
| 6 | Impression | CORRIGÉ | Les liens utilisent uniformément `/vente/impression?id={id}`. | Dashboard, historique, recherche, détail : PASS statique |
| 7 | Liaison caisse | CORRIGÉ | L'encaissement transmet désormais `vente_id` au mouvement de caisse. | Mouvement de la vente de test relié : PASS |
| 8 | TVA catalogue | CORRIGÉ | Aucune lecture de `produits.tva_taux`; le catalogue consulte `tva_taux`. | Requête catalogue : PASS SQL |
| 9 | RBAC vendeur | CORRIGÉ | Le fallback codé pour `VENDEUR` est vide : les permissions sont lues depuis `role_permissions`. | Vendeur : `vente.*`, `client.*`, `stock.view`, `caisse.*` présents en DB |
| 10 | Permissions caisse | CORRIGÉ | Consultation, ouverture, fermeture et mouvements manuels sont protégés par permissions métier. Les décaissements/mouvements manuels exigent `close_cash_register`; le vendeur n'y a pas droit. | Contrôleurs/routes : PASS statique |

## Fichiers contrôlés et modifications de reprise

Les 14 fichiers de la reprise ont été audités. Les correctifs complémentaires ont été limités à :

- `app/Services/VenteService.php` : refus explicite de reprise d'un ticket historique déjà comptabilisé, sans modifier son entête ni ses lignes.
- `app/Controllers/VenteController.php` : même protection avant rendu du formulaire de reprise.
- `app/Controllers/CaisseController.php` : permissions d'ouverture et de mouvements manuels/décaissements renforcées.
- `config/routes.php` : routes d'ouverture de caisse déclarées.
- `scripts/test_dashboard_vendeur_phase2.php` : contrôle de l'équilibre débit/crédit et de l'intangibilité d'un ticket historique.

Les autres fichiers déjà modifiés sont syntaxiquement valides et ont été conservés : `ClientController`, `ProduitController`, `BaseController`, `EcritureComptableService`, `ProduitScannerService`, les vues vente et la migration 031.

## Migration 031

Contenu lu et vérifié, sans exécution. Elle est idempotente :

- `permissions.nom` est unique et l'insertion utilise `INSERT IGNORE` ;
- `role_permissions` possède la clé unique `(role_id, permission_id)` et la migration utilise également `NOT EXISTS` ;
- aucune suppression ni révocation de permission n'est présente ;
- les permissions attendues (`caisse.view`, `caisse.open`, vente/client/stock) existent déjà pour `vendeur` dans la base locale.

Conclusion : ne pas l'exécuter sur cette base, car elle est déjà dans l'état cible et n'apporterait aucune permission nécessaire.

## Tests réalisés

- PHP : `php -l` sur les 13 fichiers PHP modifiés et la vue PHP — PASS, aucune erreur de syntaxe.
- SQL clients : colonnes réelles validées (`code`, `plafond_credit`, `notes`) — PASS.
- SQL TVA : `tva_taux` est la structure utilisée, `produits.tva_taux` n'est pas lu — PASS.
- Flux inter-modules sous `ROLLBACK` — PASS : produit → vente → stock → mouvement caisse lié → comptabilité.
- Montants sous `ROLLBACK` — PASS : HT 432,20 + TVA 77,80 = TTC 510,00 ; débit 510,00 = crédit 510,00.
- Ticket suspendu sous `ROLLBACK` — PASS : stock inchangé, aucune écriture, puis une seule déduction à validation.
- Historique sous `ROLLBACK` — PASS : les 16 tickets `EN_COURS` déjà comptabilisés sont bloqués de la reprise et leurs lignes restent inchangées.
- Intégrité caisse : 0 mouvement avec `vente_id` non nul pointant vers une vente inexistante — PASS.
- Routes : détail, impression et ouverture de caisse déclarés — PASS statique.

## Points non testés dynamiquement

- Scanner : aucun produit avec code-barres exploitable n'a été trouvé dans la base locale ; le test est donc `NON_TESTE` sans créer de donnée.
- Création/modification réelle d'un client, et contrôle HTTP complet avec les rôles `VENDEUR`, `ASSISTANT`, `CHARGE_COMMANDE` et `ADMIN` : non joués dans le navigateur pour éviter toute écriture/altération de données utilisateur.
- Les routes sont validées statiquement, mais aucun test HTTP serveur n'a été exécuté.

## Avertissements non bloquants

PHP signale l'impossibilité d'ouvrir le journal Xdebug et la configuration essaie de régler `query_cache_type`, option retirée des versions MySQL récentes. Ces avertissements n'ont pas affecté les requêtes ni les transactions de test.

La Phase 2 est validée pour les flux vérifiés ci-dessus, mais le statut « 100 % fonctionnel » n'est pas déclaré tant que le scanner et les contrôles HTTP/RBAC multi-rôles n'ont pas été joués sur une base de recette.
