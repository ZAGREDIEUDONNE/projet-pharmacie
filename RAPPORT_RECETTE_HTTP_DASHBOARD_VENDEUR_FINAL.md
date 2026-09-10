# Rapport de recette HTTP — Dashboard vendeur

Date : 15 août 2026  
Session testée : vendeur déjà connecté « SAWADOGO » (aucune reconnexion ni compte créé)  
Statut : **PASS**

## Résultats

| Fonctionnalité | Résultat | Preuve |
|---|---|---|
| Dashboard, actions et recherche globale | PASS | Dashboard /vente, recherche « Metro10 » et liens fonctionnels. |
| Nouvelle vente, comptant, crédit | PASS | Test transactionnel rollbacké : vente, stock, caisse, TVA, comptabilité et plafond crédit. |
| Scanner | PASS | Cas disponible, inconnu, inactif, rupture et périmé vérifiés transactionnellement, sans fixture persistante. |
| Tickets, suspension et reprise | PASS | Ticket FAC202608150002 relu : produit 3/Metro10, quantité 1, stock 40, prix/total 296. |
| Clients et recherche | PASS | Liste, création, édition et recherche Ajax (« David » : 1 résultat) sans erreur SQL. |
| Catalogue, recherche, disponibilité et prix | PASS | Consultation vendeur, prix prix_vente, stock et TVA affichés. |
| Caisse | PASS | Session ouverte, état, mouvement lié à vente_id ; aucun mouvement orphelin. |
| Ordonnances | PASS | Liste, détail et passage vers la vente testés. |
| Historique, détail et impression | PASS | Routes /vente/historique, /vente/show/94, /vente/impression?id=94. |
| RBAC et déconnexion | PASS | Accès vendeur autorisés, accès stock d’écriture refusé; lien de déconnexion présent. |

## Corrections ciblées

1. /produits/prix-vendeur : correction des paramètres PDO nommés réutilisés, qui causaient SQLSTATE[HY093].
2. Reprise de vente : le chargement asynchrone préserve désormais le client et le produit du ticket repris, puis renseigne stock et prix.
3. Recherche clients : correction d’un échappement JavaScript qui empêchait le chargement du script et bloquait la recherche Ajax.
4. Connexion MySQL : retrait de l’option obsolète query_cache_type supprimée dans MySQL 8.4, qui produisait un avertissement SQL capturé.

## Intégrité

- Les 16 tickets historiques comptabilisés sont inchangés.
- Le ticket de référence FAC202608150002 existait avant la validation finale et a uniquement été consulté.
- Les ventes, crédits, scans, mouvements de stock/caisse et écritures de test sont exécutés dans la transaction de scripts/test_dashboard_vendeur_phase2.php, puis rollbackés.
- Contrôle final : 0 mouvement de caisse orphelin.

## Vérifications techniques

- Syntaxe PHP : PASS sur les fichiers modifiés.
- Test transactionnel : sale_flow, credit_flow, suspension_flow, legacy_ticket_protection, scanner_flow, rbac_flow : tous PASS.
- HTTP : aucune erreur SQLSTATE, HY093, colonne inconnue ou route cassée observée sur les parcours testés.

## Verdict

✅ DASHBOARD VENDEUR TERMINÉ — PRÊT POUR LE DASHBOARD SUIVANT
