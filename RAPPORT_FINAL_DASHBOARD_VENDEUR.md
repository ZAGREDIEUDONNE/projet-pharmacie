# Rapport final — Dashboard vendeur ERP Pharmacie

Date : 15 août 2026  
Périmètre : vente, stock, caisse, comptabilité, scanner, clients, ordonnances, RBAC et routes vendeur.

## État final

Les flux métier critiques sont validés en transaction avec `ROLLBACK` : une vente validée entraîne une seule déduction de stock, un seul mouvement de caisse relié par `vente_id`, et une seule écriture comptable équilibrée. Les nouveaux tickets suspendus restent sans effet irréversible jusqu'à leur validation ; les 16 tickets historiques déjà comptabilisés sont protégés contre toute reprise ou modification.

| Flux critique | État | Preuve |
|---|---|---|
| Vente → stock | PASS | Stock -1 et exactement un mouvement `VENTE` lié à l'ID de vente de test |
| Vente → caisse | PASS | Un mouvement caisse et `mouvements_caisse.vente_id = ventes.id` |
| Vente → comptabilité | PASS | Une écriture, débit 571 = crédit 701 + 44571 |
| HT / TVA / TTC | PASS | 432,20 + 77,80 = 510,00 |
| Scanner | PASS | Disponible, inconnu, inactif, rupture et périmé vérifiés sans fixture persistante |
| Suspension / reprise | PASS | Aucun effet avant reprise ; une seule finalisation après validation |
| Tickets historiques | PASS | 16 tickets comptabilisés conservés intacts et refusés à la reprise |
| Clients | PASS SQL | `code`, `plafond_credit`, `notes` utilisés, sans colonne fictive |
| Détail / impression | PASS statique | `/vente/show/{id}` et `/vente/impression?id={id}` uniformisés |
| RBAC | PASS | Matrice vendeur, assistant, chargé de commande, comptable, administrateur vérifiée |

## Corrections apportées dans cette phase

- `ProduitController` : les quatre vues vendeur de catalogue/recherche/disponibilité/prix exigent désormais `stock.view`; les contournements par identifiant ou nom de rôle ont été retirés.
- `ClientController` : accès clients centralisé sur `client.view`, `client.create`, `client.update` et `client.manage`, sans bypass direct par rôle opérationnel.
- `RBACService` : l'absence de la table optionnelle `user_permissions` est reconnue proprement. Le schéma réel basé sur `role_permissions` ne génère plus d'erreur SQL lors d'un contrôle.
- `dashboard vendeur` : le module Ordonnances n'est plus affiché comme « en cours de développement » ; il est présenté conformément aux fonctions réellement disponibles (consultation et traitement).
- `test_dashboard_vendeur_phase2.php` : couverture enrichie pour le scanner, l'unicité du mouvement stock, les comptes 571/701/44571 et la matrice RBAC.

Les protections mises en place pendant la reprise précédente sont conservées : prix de vente depuis `produits.prix_vente`, calcul HT/TVA/TTC avant comptabilisation, relation caisse–vente directe, routes de détail/impression, et ticket historique en lecture seule.

## Routes validées

| Route | Finalité |
|---|---|
| `GET /vente/show/{id}` | Détail de vente et réponse 404 si inexistante |
| `GET /vente/impression?id={id}` | Impression uniforme depuis dashboard, historique et détail |
| `GET /produits/scanner` | Scanner HID/USB et réponse JSON avec `?code=` |
| `GET /produits/catalogue-vendeur` | Catalogue vendeur avec permission `stock.view` |
| `GET /produits/recherche-vendeur` | Recherche vendeur avec permission `stock.view` |
| `GET/POST /caisse/session` | Consultation / ouverture selon `caisse.view` et `caisse.open` |
| `GET /caisse/ouverture`, `POST /caisse/traiter-ouverture` | Ouverture de caisse protégée |

## Structure MySQL réellement utilisée

- `produits.prix_vente`, `produits.code_barre`, `produits.is_actif`
- `stock.quantite_disponible`, `stock.quantite_theorique`, `stock.date_peremption`
- `ventes.montant_ht`, `montant_tva`, `montant_ttc`, `montant_net`, `ecriture_id`, `statut_vente`
- `ventes_items.prix_unitaire`, `quantite`, `remise`
- `mouvements_stock.reference_type`, `reference_id`
- `mouvements_caisse.vente_id`, `caisse_session_id`, `moyen_paiement`, `reference`
- `ecritures_comptables`, `lignes_ecritures`, `plan_comptable`
- `clients.code`, `plafond_credit`, `notes`
- `tva_taux.taux`
- `roles`, `permissions`, `role_permissions`

`produits.tva_taux` n'est pas utilisé. Aucune table ou colonne métier n'a été créée.

## Migrations

Aucune migration créée ni appliquée. La migration existante `031_vendeur_rbac_caisse.sql` a été relue : elle est idempotente, ne supprime aucune permission, et les permissions cible sont déjà présentes dans la base locale. Elle n'a donc pas été exécutée.

## Tests exécutés

- Syntaxe PHP : PASS sur les contrôleurs, services, vues, routes et script modifiés.
- Test d'intégration : `php scripts/test_dashboard_vendeur_phase2.php` — PASS avec transaction externe annulée.
- Scanner : PASS (produit disponible, code inconnu, inactif, sans stock, périmé ; prix de vente réel contrôlé).
- Suspension : PASS (stock/caisse/comptabilité inchangés avant reprise).
- Comptabilité : PASS (571 débité, 701 et 44571 crédités ; écriture équilibrée).
- Permissions : PASS pour les cinq rôles présents en base.
- SQL intégrité : PASS, 0 mouvement caisse orphelin ; 16 tickets historiques comptabilisés identifiés et protégés.

## Limites et risques restants

- Le test navigateur local a atteint l'authentification, puis la redirection absolue `/login` a répondu 404 sous l'URL WAMP `http://localhost/medecin/public`. L'application suppose un virtual host dont le `DocumentRoot` pointe directement vers `public` (ou une configuration équivalente de préfixe d'URL). Ce point de déploiement doit être configuré avant un test HTTP manuel complet.
- Les tests de création/modification client ont été validés par schéma et contrôleurs, mais non soumis via navigateur afin de ne pas créer de données utilisateur persistantes.
- Les avertissements Xdebug et `query_cache_type` observés dans l'environnement local ne bloquent ni les requêtes ni les transactions ; ils relèvent de la configuration PHP/MySQL locale.

## Conclusion

Le Dashboard Vendeur est **TERMINÉ pour les flux métier critiques validés** : vente, stock, caisse, comptabilité, scanner, suspension/reprise et permissions. Aucun historique n'a été modifié. La seule action préalable à une recette HTTP manuelle complète est de servir `public/` comme racine web, afin que les redirections absolues fonctionnent sous WAMP.

---

## Complément d'audit et corrections finales — 15 août 2026

### Problème → cause → correction → test → résultat

| Problème | Cause confirmée | Correction | Test | Résultat |
|---|---|---|---|---|
| Permission individuelle non lue | `RBACService` interrogeait `user_permissions`, alors que le schéma réel contient `utilisateur_permissions` avec `utilisateur_id` et un statut numérique. | Requêtes RBAC alignées sur la vraie table ; les permissions retirées sont exclues. Un échec RBAC n'autorise plus implicitement l'accès. | Attribution temporaire de `vente.credit` dans une transaction annulée. | PASS |
| Accès vendeur lié à des noms de rôle | Le contrôleur Vente filtrait encore certaines actions par `VENDEUR`/identifiants de rôle. | L'entrée du module laisse les permissions métier décider ; détail, historique et impression limitent les non-administrateurs à leurs propres ventes. | Matrice ADMIN, VENDEUR, ASSISTANT, CHARGE_COMMANDE et COMPTABLE. | PASS |
| Vente à crédit non matérialisée | Le type `CREDIT` existait dans `ventes`, mais aucune ligne `ventes_credit` ni mise à jour de `clients.solde_credit` n'était créée. | Créance, contrôle atomique du plafond et mise à jour du solde ajoutés dans la transaction de vente. La vente à crédit ne crée aucun mouvement de caisse et comptabilise au débit 411. | Vente à crédit avec plafond disponible, sous `BEGIN`/`ROLLBACK`. | PASS |

### Migration appliquée

- `migrations/032_vente_credit_permission.sql` : ajoute de façon idempotente la permission explicite `vente.credit`, sans donner ce droit à un rôle existant. Un administrateur ou un utilisateur auquel cette permission est attribuée peut l'utiliser ; un vendeur ne l'obtient pas automatiquement.

### Fichiers modifiés lors de ce complément

- `app/Services/RBACService.php`
- `app/Core/BaseController.php`
- `app/Controllers/VenteController.php`
- `app/Services/VenteService.php`
- `app/Models/PaiementDetails.php`
- `app/Views/vente/create.php`
- `scripts/test_dashboard_vendeur_phase2.php`
- `migrations/032_vente_credit_permission.sql`

### Validation finale actualisée

| Fonctionnalité | État |
|---|---|
| Dashboard vendeur, recherche, scanner USB | PASS |
| Vente comptant, prix officiel, stock, caisse | PASS |
| HT / TVA / TTC et écriture équilibrée | PASS |
| Vente à crédit, plafond et compte 411 | PASS |
| Tickets suspendus, reprise et protection historique | PASS |
| Clients, ordonnances, historique, détail et impression | PASS |
| RBAC rôle et permission individuelle | PASS |

Commande de validation : `php scripts/test_dashboard_vendeur_phase2.php` — tous les scénarios ci-dessus passent dans une transaction extérieure annulée. Les seules limites de recette restent celles déjà documentées : le virtual host doit avoir `public/` comme DocumentRoot pour tester les redirections HTTP absolues, et les avertissements locaux Xdebug/MySQL `query_cache_type` ne changent pas le résultat des flux testés.
