# Audit — Scanner produit vendeur

Date : 15 août 2026. Audit effectué avant la modification.

- L'action « Scanner produit » du dashboard vendeur pointait vers `/vente/create`; aucune page scanner n'existait.
- La table réelle est `produits`. La colonne dédiée est `code_barre VARCHAR(50)`, avec une contrainte unique et l'index `code_barre`; aucune migration SQL n'est requise.
- `ProduitController` contient une recherche vendeur générique, mais pas de recherche exacte par douchette ni de réponse JSON dédiée.
- `VenteController@apiProduits` et `VenteService@getProduitsDisponibles` alimentent déjà le formulaire de vente; le formulaire ne possède pas de panier indépendant persistant.
- `StockService` protège la déduction au moment de la vente. La date de péremption actuellement disponible au niveau du stock est `stock.date_peremption`; les lots sont signalés comme désactivés dans les routes de vente.
- Les contrôles d'accès existants utilisables sont l'authentification et `vente.create`, déjà accordée au rôle Vendeur dans l'architecture actuelle.
- La base locale ne contient actuellement aucun `code_barre` renseigné. La douchette sera fonctionnelle dès qu'un code est saisi pour un produit dans la gestion du stock.
