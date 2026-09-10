# Rapport d'audit — Dashboard Vendeur

Date : 15 août 2026  
Périmètre : code PHP MVC, routes, vues, permissions et base MySQL `medecin` (lecture seule).  
Méthode : inspection statique et requêtes `SELECT` uniquement. Aucun fichier métier, schéma ou enregistrement de production n'a été modifié.

## 1. Résumé général

**Score de fonctionnement : 42 % (audit statique / base réelle).**

Le dashboard est rendu et son flux central a une architecture cohérente (contrôleur `VenteController`, `VenteService`, transaction SQL, stock, caisse et écriture comptable). Toutefois, il ne satisfait pas encore le critère de fonctionnement de bout en bout : des liens visibles sont cassés, le menu Clients échoue sur le schéma réel, la suspension déclenche des effets définitifs, et la gestion des prix n'utilise pas le prix de vente stocké.

Les tests qui créeraient une vente, un règlement, une session, un mouvement ou une ordonnance n'ont volontairement pas été lancés afin de ne pas écrire en production. Leur statut est donc **NON TESTÉ dynamiquement**, même lorsque la chaîne statique est analysable.

## 2. Inventaire visible

| Zone | Éléments visibles | Chaîne principale |
|---|---|---|
| Menu | Dashboard, Nouvelle vente, Clients, Suivi client, Produits, Caisse, Retour (assistant seulement), Déconnexion | `vente`, `clients`, `suivi-client`, `produits`, `caisse`, `logout` |
| Header | recherche globale, état/fond de caisse, Ouvrir session, déconnexion | `VenteController@rechercheGlobale`, `CaisseController@sessionForm` |
| KPIs | CA, tickets, encaissé, clients servis, ventes crédit, panier moyen, articles, reste à encaisser | `VenteController@index` → `VenteService` → `ventes`, `ventes_items`, `clients` |
| Actions | Nouvelle vente, Tickets en attente, Scanner produit, Clients, Impression | `VenteController`, `ProduitController`, `ClientController` |
| Caisse | détail de session, session ouverte/fermée, fond, mouvements | `CaisseController` → `CaisseService` → `caisse_sessions`, `mouvements_caisse` |
| Produits | recherche, disponibilité, prix | `ProduitController` → `produits`, `stock`, `categories` |
| Ordonnances | recherche, historique, nouvelle vente avec ordonnance | `OrdonnanceController` / `OrdonnanceService` → `ordonnances`, liens de vente |
| Historique | dernières ventes, voir, imprimer | `VenteController@historique` / `impression` |

**Écart d'inventaire :** « Ordonnances » n'est pas dans le menu latéral, alors qu'il est demandé et présent comme section du contenu. « Tickets en attente » et l'historique ne sont pas non plus dans ce menu.

## 3. Routes du dashboard

| Route | HTTP | Contrôleur / méthode | Vue / données principales | Permission | Statut |
|---|---|---|---|---|---|
| `/vente`, `/vente/dashboard` | GET | `VenteController@index` | `vente/dashboard`; `ventes`, `ventes_items`, `clients`, caisse | `vente.view` | PARTIEL |
| `/vente/create` | GET | `VenteController@create` | `vente/create` | `vente.create` | PARTIEL |
| `/vente/store` | POST | `VenteController@store` → `VenteService@creerVente` | redirection/impression | `vente.create` | PARTIEL |
| `/vente/tickets-en-attente` | GET | `VenteController@ticketsEnAttente` | `vente/tickets-en-attente`; `ventes`, `ventes_items`, `clients` | `vente.view` | PARTIEL |
| `/vente/reprendre?id=` | GET | `VenteController@reprendreVente` | `vente/create` | `vente.create` | PARTIEL |
| `/vente/annuler-ticket-en-attente` | POST | `VenteController@annulerTicketEnAttente` | redirection | `vente.delete` | FAIL vendeur |
| `/vente/historique` | GET | `VenteController@historique` | `vente/historique` | `vente.view` | PARTIEL |
| `/vente/impression?id=` | GET | `VenteController@impression` | `vente/impression` | `vente.view` | PARTIEL |
| `/vente/show/{id}` | GET | — | — | — | **FAIL : route absente** |
| `/vente/impression/{id}` | GET | — | — | — | **FAIL : route absente; la route attend `?id=`** |
| `/recherche-globale` | GET | `VenteController@rechercheGlobale` | JSON; produits, clients, ventes | authentification seulement | PARTIEL |
| `/clients` | GET | `ClientController@index` | `clients/index`; `clients` | rôle opérationnel / `client.view` | **FAIL SQL** |
| `/clients/creer` | GET | `ClientController@create` | `clients/create` | rôle opérationnel / `client.create` | PARTIEL |
| `/clients/modifier?id=` | GET/POST | `ClientController@edit` / `update` | `clients/edit` | rôle opérationnel / `client.update` | PARTIEL |
| `/suivi-client` | GET | `SuiviClientController@index` | `suivi-client/index` | `suivi_client.view` | PARTIEL |
| `/produits/catalogue-vendeur` | GET | `ProduitController@catalogueVendeur` | `produits/catalogue-vendeur` | `stock.view` (vendeur admis par fallback) | PARTIEL |
| `/produits/recherche-vendeur` | GET | `ProduitController@rechercheVendeur` | `produits/recherche-vendeur` | accès produit lecture | PARTIEL |
| `/produits/disponibilite-vendeur` | GET | `ProduitController@disponibiliteVendeur` | `produits/disponibilite-vendeur` | authentification seule | PARTIEL / RBAC |
| `/produits/prix-vendeur` | GET | `ProduitController@prixVendeur` | `produits/prix-vendeur` | authentification seule | **FAIL SQL** |
| `/produits/scanner` | GET | `ProduitController@scanner` → `ProduitScannerService` | `produits/scanner` / JSON | `vente.create` | PARTIEL |
| `/ordonnances`, `/ordonnances/liste` | GET | `OrdonnanceController@index/liste` | `ordonnances/index` | `ordonnance.view` | PARTIEL |
| `/ordonnances/voir/{id}` | GET | `OrdonnanceController@voir` | `ordonnances/detail` | `ordonnance.view` | PARTIEL |
| `/ordonnances/traiter/{id}` | GET | `OrdonnanceController@traiter` | redirection vente | `ordonnance.process` | PARTIEL |
| `/caisse/etat` | GET | `CaisseController@etat` | `caisse/etat` | authentifié, hors chargé commande | PARTIEL / RBAC |
| `/caisse/session` | GET/POST | `CaisseController@sessionForm/changeSession` | `caisse/session` | authentifié seulement | PARTIEL / RBAC |
| `/logout` | GET | `AuthController@logout` | redirection | authentifié | PASS statique |

### Liens réellement cassés depuis le dashboard

1. Les icônes **Voir** utilisent `/vente/show/{id}` ; aucune route ni méthode `show` n'existe.
2. Les icônes **Imprimer** utilisent `/vente/impression/{id}` ; seule `/vente/impression?id={id}` existe.
3. La recherche globale retourne aussi `/vente/show/{id}` pour une facture : même panne.

## 4. Fonctionnalités fonctionnelles ou cohérentes statiquement

- Le dashboard appelle une vue existante et protège l'accès par `vente.view`.
- Les routes de création, enregistrement, historique, impression, attente, scanner, caisse et ordonnances sont déclarées et leurs méthodes existent.
- Le scanner utilise bien `produits.code_barre`, colonne présente dans MySQL. Il contrôle produit actif, quantité disponible, péremption et la vue accepte l'entrée suivie de la touche Entrée.
- Les tables centrales existent : `ventes`, `ventes_items`, `produits`, `stock`, `mouvements_stock`, `caisse_sessions`, `mouvements_caisse`, `clients`, `ordonnances`, `paiements_factures`, `paiement_details`, `ecritures_comptables`, `lignes_ecritures`, `journaux_comptables`, `plan_comptable`.
- Les contrôles d'intégrité observés donnent 0 vente pointant vers une écriture inexistante, 0 écriture dont `total_debit` diffère de `total_credit`, et 0 mouvement de caisse pointant vers une vente inexistante.
- `VenteService@creerVente` démarre une transaction, puis crée l'entête, les lignes, déduit le stock, encaisse, génère l'écriture et valide/annule la transaction.
- L'écriture de vente est conçue selon le flux demandé : débit 571 au comptant ou 411 au crédit, crédit 701, et crédit 44571 si TVA positive.

## 5. Fonctionnalités partiellement fonctionnelles

| Fonction | Problème / cause | Fichiers principaux | Impact |
|---|---|---|---|
| Vente | `getPrixVenteProduit()` lit `produits.prix_achat` et applique un coefficient, au lieu de lire `prix_vente`. | `app/Services/VenteService.php` | Les tickets et l'encaissement peuvent utiliser un prix différent du catalogue. |
| Vente / TVA | La vente renseigne surtout `montant_total`, `montant_net` et remise; `montant_ht`, `montant_tva`, `montant_ttc` ne sont pas calculés dans ce flux. L'écriture lit ces montants. | `VenteService.php`, `EcritureComptableService.php` | TVA et ventilation comptable ne sont pas démontrées correctement. |
| Ticket suspendu | Une vente `EN_COURS` est créée avec articles, stock déduit, et écriture générée avant la suspension. La reprise réaffiche les lignes mais n'actualise pas la vente existante. | `VenteService.php`, `VenteController.php` | 16 tickets `EN_COURS` constatés, les 16 ayant déjà `ecriture_id`; risque de double vente/stock/comptabilité à la reprise. |
| Caisse | L'encaissement passe une référence `VENTE_{id}` mais pas `vente_id` à `CaisseService@enregistrerMouvement`, alors que la colonne existe. | `VenteService.php` | Traçabilité relationnelle vente → mouvement caisse incomplète (recherche par chaîne de référence). |
| Ordonnances | Les consultations, recherche, liens ordonnance–vente et contrôle de disponibilité existent, mais il n'y a pas de route de création autonome; le dashboard l'annonce « en cours de développement ». | `OrdonnanceController.php`, `OrdonnanceService.php`, `VenteService.php` | Module consultation/traitement, pas un cycle autonome complet. |
| Suivi client | `SuiviClientRepository` calcule les soldes/règlements; en parallèle `clients.solde_credit` et `clients.solde` existent. | `SuiviClientRepository.php`, `SuiviClientService.php` | Double source de vérité à réconcilier. |
| Caisse | Les points d'accès `sessionForm` et `changeSession` vérifient seulement l'identité, tandis que `etat` exclut seulement le rôle commande. | `CaisseController.php` | Vendeur/assistant obtiennent plus d'accès que le RBAC explicite ne le décrit. |

## 6. Fonctionnalités non fonctionnelles

| Fonction | Erreur exacte / route | Cause | Correction recommandée |
|---|---|---|---|
| Liste Clients | `ERROR 1054: Champ 'code_client' inconnu dans field list` sur `/clients` | La requête sélectionne `code_client`, `plafond`, `observations`; la table réelle contient `code`, `plafond_credit`, `notes` et pas ces trois colonnes. | Aligner les requêtes sur le schéma réel ou établir une migration validée, après décision. |
| Recherche Clients | Même ensemble de colonnes absentes sur `/clients/search` | Même divergence code/base. | Même correction centralisée. |
| Prix vendeur | `ERROR 1054: Champ 'p.tva_taux' inconnu dans field list` sur `/produits/prix-vendeur` | `produits.tva_taux` n'existe pas dans MySQL. | Utiliser le modèle TVA réellement retenu (`tva_taux` est une table) ou ajouter la colonne par migration approuvée. |
| Voir dernière vente | `/vente/show/{id}` | Absence de route et de méthode. | Choisir une vue détail ou pointer vers impression avec le paramètre `id`. |
| Imprimer dernière vente | `/vente/impression/{id}` | Mauvais format de route. | Utiliser `/vente/impression?id={id}` ou déclarer une route paramétrée. |
| Annuler attente (vendeur) | `/vente/annuler-ticket-en-attente` | Exige `vente.delete`, absente des permissions DB du vendeur et du fallback. | Définir explicitement la politique d'annulation/retrait du vendeur. |

## 7. Base de données et cohérence code/schéma

| Fonction | Tables / colonnes réelles utilisées | Existe | Statut |
|---|---|---:|---|
| Vente | `ventes` (`caisse_session_id`, `ecriture_id`, montants, statut), `ventes_items`, `produits`, `stock`, `mouvements_stock` | Oui | PARTIEL : prix et TVA |
| Caisse | `caisse_sessions`, `mouvements_caisse` (`caisse_session_id`, `vente_id`, `ecriture_id`, `supprime`) | Oui | PARTIEL : `vente_id` non renseigné dans flux vente |
| Paiement | `paiements_factures`, `paiement_details` | Oui | PARTIEL : deux modèles de paiement, usage à normaliser |
| Clients | `clients` (`matricule`, téléphones, adresse, IFU, RCCM, `plafond_credit`, `solde_credit`, assurance) | Oui | FAIL dans index/search : noms de colonnes divergents |
| Produits / scanner | `produits` (`code_cip`, `code_barre`, DCI, forme, prix), `stock` | Oui | PARTIEL : prix service != prix catalogue |
| Ordonnances | `ordonnances`, `vente_ordonnances`, `vente_ordonnance_items` | Oui | PARTIEL |
| Comptabilité | `ecritures_comptables`, `lignes_ecritures`, `journaux_comptables`, `plan_comptable` | Oui | PARTIEL : TVA non fiabilisée par le flux vente |

Le schéma de référence est incomplet : `database/schema.sql` décrit 25 tables, tandis que la base locale en contient 84. Des tables actives essentielles (`client_reglements`, `journaux_comptables`, `lignes_ecritures`, `paiement_details`, `ordonnances`, etc.) sont absentes de ce schéma de départ ou uniquement apportées par migrations. Deux migrations annoncent `reglements_clients` et `rapports_comptables` / `soldes_comptables`, mais ces tables sont absentes de la base observée.

## 8. Permissions

| Fonction | Permission demandée en code | Vendeur en table RBAC | Assistant en table RBAC | Statut |
|---|---|---:|---:|---|
| Voir/créer vente | `vente.view`, `vente.create` | non listées | non listées | Contourné par fallback de rôle dans `BaseController` |
| Lire/créer client | `client.view`, `client.create` | non listées | non listées | Contourné par fallback / rôle opérationnel |
| Voir stock/catalogue | `stock.view` | non listée | non listée | Contourné par fallback |
| Ordonnances | `ordonnance.view`, `ordonnance.process` | oui | non | Vendeur cohérent, assistant bloqué |
| Suivi client | `suivi_client.*` | view/solde/releve/reglement | view/solde/releve/reglement/create/update/delete | Cohérent à l'échelle DB |
| Annuler ticket | `vente.delete` | non | non | Action inaccessible depuis la page d'attente |
| Caisse | aucune permission spécifique sur plusieurs méthodes | — | — | Contrôle insuffisant |

Le système possède deux sources de décision : les associations `roles`/`permissions` en base et une liste codée en dur dans `BaseController::hasDefaultRolePermission`. Le fallback accorde vente, clients et stock au vendeur même quand ces permissions ne figurent pas en base. C'est un contournement RBAC et rend les audits d'habilitation non déterministes.

## 9. Flux inter-modules et transactions

```text
Vente → ventes / ventes_items → StockService::deduireStock → mouvements_stock
      → CaisseService::enregistrerMouvement → mouvements_caisse
      → EcritureComptableService → ecritures_comptables / lignes_ecritures
      → ticket / impression
```

La transaction enveloppe l'essentiel du flux de vente et rollback est prévu. Les faiblesses sont fonctionnelles, non syntaxiques : une vente suspendue produit déjà les effets du flux final, les montants TVA ne sont pas alimentés de manière démontrée, et la liaison `mouvements_caisse.vente_id` est laissée à `NULL` par l'appel de vente.

Les services de caisse et de suivi client utilisent aussi des transactions. Les règles de clôture, décaissement et règlement demandent cependant des tests d'intégration sur une base de recette pour conclure au PASS.

## 10. Matrice de tests fonctionnels

| Test minimal | Statut | Justification |
|---|---|---|
| Dashboard / KPIs | PARTIEL | Chaîne statique existante; valeurs non validées par scénario réel |
| Nouvelle vente | PARTIEL | Transaction présente, mais prix et TVA incohérents |
| Recherche produit | PARTIEL | Route et requêtes présentes; test de résultat réel non exécuté |
| Scanner | PARTIEL | Code-barres, actif, péremption, stock et Entrée analysés; ajout direct non confirmé |
| Ajout / quantité / suppression article | NON TESTÉ | Nécessiterait une vente d'essai |
| Remise | PARTIEL | Validation de limite présente; calcul réel non testé |
| Clients | FAIL | Requête de liste invalide sur MySQL réel |
| Crédit / suivi client | PARTIEL | Services/permissions présents, double solde |
| Paiement / ticket | PARTIEL | Flux présent, paiement et TVA à valider |
| Impression | PARTIEL | Liste fonctionne statiquement, liens de ligne cassés |
| Suspendre / reprendre | FAIL | Effets définitifs avant reprise et risque de duplication |
| Caisse / encaissement / décaissement | PARTIEL | Services et tables, RBAC et lien vente insuffisants |
| Historique | PARTIEL | Route/service présents; lien détail cassé |
| Stock | PARTIEL | Déduction transactionnelle présente; flux réel non joué |
| Ordonnances | PARTIEL | Consultation/traitement, pas création autonome |
| Comptabilité | PARTIEL | Équilibre observé, TVA vente non fiabilisée |
| Permissions | FAIL | fallback codé en dur et contrôles caisse insuffisants |
| Déconnexion | PASS statique | Route et contrôleur déclarés |

## 11. Problèmes critiques classés

| Gravité | Problème |
|---|---|
| CRITIQUE | Tickets suspendus : stock, caisse potentielle et comptabilité sont engagés; 16 tickets `EN_COURS` portent une écriture comptable. |
| CRITIQUE | Prix des ventes calculé depuis `prix_achat`, au lieu du prix de vente enregistré. |
| CRITIQUE | `/clients` échoue avec des colonnes inexistantes, bloquant une action principale. |
| IMPORTANT | TVA et champs HT/TTC non alimentés de façon cohérente avant l'écriture. |
| IMPORTANT | Liens de détail et impression des dernières ventes / résultats de recherche cassés. |
| IMPORTANT | RBAC à deux sources, et accès caisse sans permission métier explicite. |
| IMPORTANT | `mouvements_caisse.vente_id` n'est pas fourni lors de l'encaissement de vente. |
| MOYEN | `/produits/prix-vendeur` échoue sur `p.tva_taux` absent. |
| MOYEN | Module ordonnance affiché comme incomplet; création autonome absente. |
| MOYEN | Schéma de référence incomplet par rapport à la base réelle. |

## 12. Corrections recommandées (à ne pas appliquer sans validation)

### Phase 1 — Critique

1. Redéfinir le vrai cycle de suspension : brouillon sans stock/caisse/comptabilité, ou annulation/compensation atomique; traiter les 16 cas existants avec une procédure validée.
2. Lire et figer `produits.prix_vente` lors de la vente; ne pas recalculer le prix depuis l'achat.
3. Aligner les requêtes Clients sur les colonnes réellement présentes.
4. Définir le calcul HT/TVA/TTC avant l'écriture comptable et couvrir TVA zéro / TVA positive / crédit.

### Phase 2 — Fonctionnel

1. Corriger/déclarer les routes de détail et d'impression des ventes.
2. Renseigner `vente_id` dans le mouvement caisse de la vente.
3. Corriger le catalogue prix (`tva_taux`) selon le modèle TVA choisi.
4. Finaliser la reprise de ticket sans créer de deuxième vente ou, si elle est conçue comme duplication, le rendre explicite et compensé.

### Phase 3 — Cohérence base

1. Désigner un schéma canonique reproductible et réconcilier schema, migrations et base.
2. Choisir une source de vérité pour le solde client et normaliser `paiements_factures` / `paiement_details` / règlements.
3. Ajouter les contrôles de clés et index après validation de la structure retenue.

### Phase 4 — UX

1. Ajouter Ordonnances au menu vendeur si le rôle doit y accéder.
2. Rendre explicite l'état des actions incomplètes au lieu de les présenter comme disponibles.
3. Conserver le `return_to` sur Suivi client et Caisse, puis tester tous les retours.

### Phase 5 — Optimisation

1. Écrire des tests d'intégration sur une base de recette clonée et transaction rollback.
2. Centraliser les noms de colonnes et les politiques RBAC.
3. Ajouter des contrôles de non-régression de routes et de requêtes sur le schéma réel.

## À CORRIGER EN PREMIER

1. Suspension/reprise de ticket avec effets stock-caisse-comptabilité déjà finalisés.
2. Prix de vente pris sur `prix_achat` et recalculé.
3. Erreur SQL `/clients` (`code_client`, `plafond`, `observations`).
4. Calcul/écriture TVA HT-TTC non fiable.
5. Routes `/vente/show/{id}` absentes.
6. Liens `/vente/impression/{id}` incompatibles avec la route réelle.
7. Liaison caisse–vente (`mouvements_caisse.vente_id`) non alimentée.
8. RBAC du vendeur/assistant contourné par permissions codées en dur.
9. Accès caisse sans permission métier explicite.
10. Erreur SQL du catalogue prix (`produits.tva_taux` absent).

