# Rapport d'audit — Dashboard Assistant

Date : 15 août 2026  
Périmètre : `app/Views/assistant/dashboard.php`, `AssistantController`, services et routes reliés.

## 1. Architecture actuelle

- Vue : `app/Views/assistant/dashboard.php` (rendu initial, puis données AJAX).
- Page : `GET /assistant/dashboard` → `AssistantController::dashboard()`.
- API : `GET /assistant/api/dashboard` → `AssistantController::apiDashboard()`.
- Service principal : `AssistantDashboardService::getOverview()`.
- Caisse : `BaseController::getDashboardCaisseData()` et `partials/caisse-status.php`.
- Cibles : `VenteController`, `ClientController`, `CaisseController`, `StockController`, `SuiviClientController`.
- Accès : rôle Assistant (`role_id = 3`), puis contrôles de `BaseController` et des contrôleurs cibles.

## 2. Fonctionnalités existantes

- Quatre KPI principaux : CA, ventes, alertes stock et état de caisse.
- Quatre KPI secondaires : encaissements, tickets annulés, clients actifs, commandes en attente.
- Actions filtrées par `BaseController::can()` : client, vente, annulation, remise, caisse, statistiques, stock et commandes.
- Tableaux d’alertes stock, ventes, commandes fournisseur et mouvements de stock.
- Sous-menu Suivi Client de neuf liens, affiché avec `suivi_client.view`.
- Zone caisse détaillée : session active, solde théorique, ouverture/fermeture et sessions.
- Recherche et tri JavaScript dans les tableaux.

## 3. KPI, données et SQL

Le service lit les données réelles dans `ventes`, `clients`, `produits`, `stock`, `supplier_orders` (avec repli sur `commandes`), `caisse_sessions`, `mouvements_caisse`, `mouvements_stock`, `audit_logs`, `utilisateurs` et `fournisseurs`.

- CA, ventes et encaissements : ventes du jour non annulées.
- Stock faible : stock disponible comparé à `stock_alerte` / `stock_securite`.
- Commandes : commandes fournisseur ouvertes, puis ancien modèle en repli.
- Caisse : dernière session globalement ouverte et montant théorique.
- Ventes : la requête fournit ticket, date, montant, statut, client et paiement ; la vue n’affiche ni paiement ni action.

Les tables ont été vérifiées présentes dans MySQL par le script d’audit existant. Le contrôleur, le service et la vue passent également `php -l`.

## 4. Routes vérifiées

| Action | Route | Contrôleur | Constat |
| --- | --- | --- | --- |
| Dashboard | `GET /assistant/dashboard` | `AssistantController::dashboard` | Conforme. |
| API | `GET /assistant/api/dashboard` | `AssistantController::apiDashboard` | Conforme. |
| Nouvelle vente | `GET /vente/create` | `VenteController::create` | Route existante ; exige `vente.create`. |
| Créer client | `GET /clients/creer` | `ClientController::create` | Alias legacy existant ; route canonique : `/clients/create`. |
| Clients | `GET /clients` | `ClientController::index` | Liste, pas l’édition d’un client précis. |
| Annuler ticket | `GET /assistant/annulation-ticket`, puis `POST /vente/cancel-ticket` | `AssistantController`, `VenteController` | Routes existantes ; `cancel_ticket` appliquée à l’écriture. |
| Caisse | `GET /caisse/etat`, `GET/POST /caisse/session`, `GET /caisse/fermeture` | `CaisseController` | Routes existantes. |
| Encaissement / décaissement | `GET /caisse/encaissements`, `GET/POST /caisse/decaissements` | `CaisseController` | Routes existantes, absentes du dashboard. |
| Alertes / péremptions | `GET /stock/alerts`, `GET /stock/peremptions` | `StockAlertController`, `StockController` | Routes existantes ; autorisations à confirmer avant exposition. |
| Historique / impression | `GET /vente/historique`, `GET /vente/impression?id=…` | `VenteController` | Routes existantes ; demandent `vente.view`. |

## 5. Permissions

La base associe 16 permissions au rôle Assistant : les neuf permissions historiques du dashboard (`create_client`, `edit_client`, `make_sale`, `cancel_ticket`, `apply_discount`, `close_cash_register`, `view_statistics`, `view_stock_movements`, `prepare_orders`) et `suivi_client.*`.

`AssistantController` utilise bien `can()` pour filtrer les actions et `requirePermission()` pour ses écrans. L’affirmation des anciens rapports selon laquelle tous les assistants étaient automatiquement autorisés n’est plus conforme au code actuel.

Incohérences à résoudre :

- La carte `make_sale` peut apparaître, mais la page Vente exige `vente.create`.
- Les permissions de cartes client ne correspondent pas exactement aux permissions métier `client.*`, bien que le contrôleur Client comporte aussi des règles de rôle opérationnel.
- `close_cash_register` protège l’écran intermédiaire Assistant, mais plusieurs routes de `CaisseController` ne font qu’exiger l’authentification et l’exclusion du Chargé de commande.

## 6. Redondances et éléments hors cible

- L’écran présente les KPI, un résumé, les actions, quatre tableaux et un détail extensible : au-delà des cinq zones demandées.
- Les neuf liens Suivi Client et les actions secondaires doublonnent le menu.
- L’API calcule activité et notifications, mais elles ne sont pas affichées.
- Les cartes, couleurs et actions sont plus nombreuses que nécessaire.

## 7. Problèmes constatés

1. **Priorité haute — boutons et autorisations non alignés.** Les permissions de visibilité ne sont pas toujours celles des contrôleurs cibles : un bouton peut mener à un refus d’accès.
2. **Priorité haute — donnée caisse globale.** La dernière session ouverte et son solde théorique sont récupérés sans restriction au caissier connecté. La partielle les affiche lorsque la zone est visible. Toute conservation de ce solde doit être soumise à une permission fine.
3. **Priorité haute — protections caisse larges.** Ouverture, fermeture, encaissements et décaissements ne font pas tous l’objet d’une permission fine dans `CaisseController`. Le dashboard ne doit pas ajouter de raccourci avant une correction côté serveur.
4. **Priorité moyenne — ventes récentes incomplètes.** Il manque heure, paiement, Voir, Réimprimer et Annuler ; il faut aussi exposer l’identifiant de vente pour créer des liens sûrs.
5. **Priorité moyenne — alertes non centralisées.** Stock est présent ; péremptions, tickets à intervenir, liens d’action et lien de traitement de commande ne le sont pas.
6. **Priorité moyenne — liens à normaliser.** La création client utilise l’alias `/clients/creer` plutôt que `/clients/create`. `/assistant/stock` ouvre le module Stock et doit rester absent du nouveau dashboard pour ne pas diriger vers un autre dashboard.
7. **Priorité basse — requêtes répétées.** `getNotifications()` relance les lectures de stock, commandes et caisse déjà exécutées par `getOverview()`.

## 8. Risques et limites de test

- La syntaxe PHP et la présence des tables sont confirmées.
- Aucun parcours HTTP authentifié n’a été exécuté pendant l’audit : il faut des comptes Assistant et non autorisé pour valider l’ensemble route → contrôleur → service → permission.
- Les migrations `008` et `028` se recouvrent sur neuf permissions. Ne pas réexécuter `028` sans vérifier son hypothèse de colonne `created_at` dans `role_permissions`.

## 9. Phase suivante proposée

1. Aligner les permissions des quatre actions rapides et leurs contrôleurs, sans privilège supplémentaire.
2. Restreindre et protéger les données/actions de caisse.
3. Réduire la vue aux cinq zones demandées.
4. Ajouter les liens d’alerte et les actions de vente uniquement après vérification route, identifiant et permission.
5. Exécuter les tests fonctionnels Assistant et non autorisé, puis rédiger le rapport final.

## Conclusion

Le socle MVC, les requêtes préparées et les données réelles sont présents. Le besoin porte surtout sur la cohérence interface → route → permission, avec une vigilance particulière sur la caisse. Aucune modification de code applicatif ou de schéma n’a été effectuée pendant cet audit.
