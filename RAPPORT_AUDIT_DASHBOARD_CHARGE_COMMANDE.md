# Audit initial — Dashboard Chargé de commande

Date : 14 août 2026

## Périmètre identifié

- Route principale : `GET /commande/dashboard` → `ChargeCommandeController@dashboard`.
- Données : `GET /commande/api/dashboard` → `ChargeCommandeController@apiDashboard` → `ChargeCommandeService@getDashboardData()`.
- Vue : `app/Views/commande/dashboard.php`.
- Contrôles : `ChargeCommandePolicy` autorise notamment `stock.view`, `stock.view_expiry`, `stock.inventory`, `view_stock_movements`, `receive_products`, `create_supplier_orders`, `view_supplier_orders`, `edit_supplier_orders` et `send_supplier_orders`.

## Fonctionnel et relié aux données

- Les KPI, commandes en attente, réceptions récentes, mouvements récents et graphiques sont fournis par `ChargeCommandeService`.
- Les commandes réceptionnables incluent les statuts `EN_ATTENTE`, `ENVOYEE`, `VALIDEE` et `RECEPTION_PARTIELLE`.
- La réception est transactionnelle : réception, lignes reçues, stock, mouvement et statut de commande sont traités dans `receiveOrder()`.
- Les alertes de rupture, stock faible, péremption et retard s'appuient sur les données de stock, lots et commandes fournisseurs.
- Les tables nécessaires au flux sont présentes : `produits`, `stock`, `stock_entries`, `supplier_orders`, `supplier_order_items`, `receptions`, `reception_items`, `mouvements_stock`, `inventaires`, `inventaire_articles`, `lots` et `fournisseurs`.

## Écarts relevés avant correction

1. Les cartes KPI « Ruptures » et « Stock sous seuil » pointent vers `/stock`, le dashboard général, plutôt que vers leur liste filtrée.
2. Le calcul des produits à commander somme toutes les lignes `supplier_order_items`, y compris celles de commandes réceptionnées ou annulées. La quantité déjà commandée peut donc être surévaluée.
3. Le bouton « Commander » d'un produit à commander ouvre le formulaire sans pré-sélectionner le produit.
4. Le lien « Sorties » et le lien « Mouvements » du menu dirigent tous deux vers le même historique de mouvements.
5. La page d'inventaire utilise la table existante `inventaire_articles` ; il n'existe pas de table `inventaire_items`. Aucune table supplémentaire n'est nécessaire.
6. La vue de détail produit manquait ; elle a été ajoutée lors de la correction précédente afin que le bouton « Voir » des alertes n'échoue plus.

## Compatibilité base de données

- Aucun ajout de table ou colonne n'est requis pour les écarts ci-dessus.
- `stock_entries` possède les colonnes attendues par le flux de réception.
- La date de péremption est portée par `lots.date_peremption`, et non par `stock`.

## Corrections prévues

- Spécialiser les destinations des KPI sans retirer de route.
- Limiter les quantités commandées aux commandes ouvertes.
- Préremplir la commande depuis la recommandation produit lorsque le formulaire le permet.
- Distinguer le lien de sorties de stock du lien de consultation des mouvements.
- Vérifier par exécution des requêtes de dashboard et par rendu contrôlé avec le rôle `CHARGE_COMMANDE`.
