# Rapport final — Dashboard vendeur

Date : 15 août 2026  
Profil : vendeur connecté « SAWADOGO »  
Méthode : recette HTTP avec la session fournie et tests métier transactionnels rollbackés.

## Fonctionnalités testées

| Domaine | Résultat |
|---|---|
| Dashboard, actions, recherche globale et déconnexion | PASS |
| Nouvelle vente, vente comptant et vente à crédit | PASS |
| Scanner (disponible, inconnu, inactif, rupture, périmé) | PASS |
| Tickets, suspension et reprise | PASS |
| Clients : liste, recherche, création et édition | PASS |
| Catalogue, recherche produit, prix et disponibilité | PASS |
| Caisse, stock et comptabilité | PASS |
| Ordonnances : liste, détail et préparation de vente | PASS |
| Historique, détail et impression | PASS |
| RBAC vendeur et contrôle en lecture des autres rôles | PASS |

## Corrections effectuées

- Correction de HY093 dans le catalogue des prix vendeur : paramètres PDO distincts pour chaque prédicat de recherche.
- Correction du préremplissage d’un ticket repris après chargement asynchrone des clients et produits.
- Correction d’une erreur JavaScript de recherche client due à un apostrophe sur-échappé.
- Suppression de l’instruction MySQL 8 obsolète query_cache_type.

## Tables et colonnes validées

- ventes : identifiant, numéro, statut, montants, utilisateur, session caisse, écriture.
- ventes_items : vente, produit, quantité, prix unitaire.
- stock, mouvements_stock : quantité disponible, quantités avant/après, référence vente.
- mouvements_caisse, caisse_sessions : vente liée, montant, session ouverte.
- ecritures_comptables, lignes_ecritures : écriture, débit, crédit, comptes 571/701/44571 et 411 pour le crédit.
- clients, ventes_credit : plafond, solde crédit, client débiteur.
- produits, tva_taux, ordonnances, utilisateurs, roles, permissions, utilisateur_permissions.

## Résultats d’intégrité

- Vente comptant : stock diminué, mouvement de stock et mouvement de caisse avec vente_id, écriture équilibrée ; HT + TVA = TTC.
- Vente crédit : plafond et nouveau solde contrôlés, ligne ventes_credit, compte 411 et absence de mouvement de caisse.
- Scanner : prix de vente catalogue et disponibilité réelle utilisés ; aucun recours à prix_achat.
- Ticket FAC202608150002 : Metro10, quantité 1, stock 40, prix/total 296 FCFA correctement rechargés.
- 16 tickets historiques comptabilisés : inchangés.
- Mouvements de caisse orphelins : 0.
- Écritures de test : transactionnelles et rollbackées, sans nouvelle donnée de test créée lors de la validation finale.

## Permissions vérifiées

Le vendeur dispose de vente.create, client.create, stock.view, caisse.open; il ne dispose pas de user.manage ni stock.update_product. L’accès à /produits/entree-stock retourne bien « Acces refuse ». Les contrôles en lecture des rôles assistant, chargé de commande, comptable et administrateur passent.

## Risques et limites

Le navigateur intégré bloque directement l’alias /clients/search avec ERR_BLOCKED_BY_CLIENT; l’interface réelle utilise /clients/rechercher, testée avec succès. Aucun défaut applicatif n’a été constaté sur la recherche client.

## Conclusion

DASHBOARD VENDEUR :  
✅ TERMINÉ — PRÊT À PASSER AU DASHBOARD SUIVANT
