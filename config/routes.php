<?php

/**
 * Configuration des routes de l'application MVC
 */

// Routes publiques (authentification)
$routes['GET']['/login'] = 'AuthController@login';
$routes['POST']['/login/auth'] = 'AuthController@authenticate';
$routes['GET']['/logout'] = 'AuthController@logout';

// Routes protégées - Administration
$routes['GET']['/admin/dashboard'] = 'AdminController@dashboard';

// Routes protégées - Vente
$routes['GET']['/vente'] = 'VenteController@index';
$routes['GET']['/vente/dashboard'] = 'VenteController@index';
$routes['GET']['/vente/create'] = 'VenteController@create';
$routes['POST']['/vente/store'] = 'VenteController@store';
$routes['GET']['/vente/show/{id}'] = 'VenteController@show';
$routes['POST']['/vente/cancel-ticket'] = 'VenteController@cancelTicket';
$routes['GET']['/vente/apiClients'] = 'VenteController@apiClients';
$routes['GET']['/vente/apiProduits'] = 'VenteController@apiProduits';
$routes['GET']['/vente/impression'] = 'VenteController@impression';
$routes['GET']['/vente/historique'] = 'VenteController@historique';
$routes['GET']['/vente/tickets-en-attente'] = 'VenteController@ticketsEnAttente';
$routes['POST']['/vente/annuler-ticket-en-attente'] = 'VenteController@annulerTicketEnAttente';
$routes['GET']['/vente/reprendre'] = 'VenteController@reprendreVente';
$routes['GET']['/recherche-globale'] = 'VenteController@rechercheGlobale';

// Ordonnances
$routes['GET']['/ordonnances'] = 'OrdonnanceController@index';
$routes['GET']['/ordonnances/liste'] = 'OrdonnanceController@liste';
$routes['GET']['/ordonnances/voir/{id}'] = 'OrdonnanceController@voir';
$routes['GET']['/ordonnances/traiter/{id}'] = 'OrdonnanceController@traiter';

$routes['GET']['/caisse/session'] = 'CaisseController@sessionForm';
$routes['GET']['/caisse/etat'] = 'CaisseController@etat';
$routes['GET']['/caisse/apiEtat'] = 'CaisseController@statutSession';
$routes['GET']['/caisse/historique'] = 'CaisseController@historique';
$routes['GET']['/date/change'] = 'SystemController@dateForm';
$routes['POST']['/date/change'] = 'SystemController@changeDate';
$routes['GET']['/caisse/fermer'] = 'CaisseController@fermeture';
$routes['GET']['/caisse/fermeture'] = 'CaisseController@fermeture';
$routes['POST']['/caisse/traiter-fermeture'] = 'CaisseController@traiterFermeture';

// Routes d'authentification module Vente
$routes['GET']['/vente/login'] = 'VenteAuthController@login';
$routes['POST']['/vente/login/auth'] = 'VenteAuthController@authenticate';
$routes['GET']['/vente/logout'] = 'VenteAuthController@logout';

// Routes protégées - Clients (RESTful)
$routes['GET']['/clients'] = 'ClientController@index';
$routes['GET']['/clients/create'] = 'ClientController@create';
$routes['POST']['/clients'] = 'ClientController@store';
$routes['POST']['/clients/store'] = 'ClientController@store';
$routes['GET']['/clients/{id}'] = 'ClientController@show';
$routes['GET']['/clients/{id}/edit'] = 'ClientController@edit';
$routes['POST']['/clients/{id}'] = 'ClientController@update';
$routes['GET']['/clients/debiteurs'] = 'ClientController@debiteurs';
$routes['GET']['/clients/statistiques'] = 'ClientController@statistiques';
$routes['GET']['/clients/search'] = 'ClientController@rechercher';
$routes['GET']['/clients/{id}/check-credit'] = 'ClientController@verifierPlafond';
$routes['GET']['/clients/export'] = 'ClientController@export';
$routes['POST']['/clients/{id}/deactivate'] = 'ClientController@desactiver';
// Routes legacy (compatibilité)
$routes['GET']['/clients/dashboard'] = 'ClientController@index';
$routes['GET']['/clients/liste'] = 'ClientController@index';
$routes['GET']['/clients/creer'] = 'ClientController@create';
$routes['GET']['/clients/fiche'] = 'ClientController@show';
$routes['GET']['/clients/modifier'] = 'ClientController@edit';
$routes['POST']['/clients/modifier'] = 'ClientController@update';
$routes['GET']['/clients/rechercher'] = 'ClientController@rechercher';
$routes['GET']['/clients/verifier-plafond'] = 'ClientController@verifierPlafond';
$routes['GET']['/clients/exporter'] = 'ClientController@export';
$routes['POST']['/clients/desactiver'] = 'ClientController@desactiver';

// Routes protégées - Produits (Gestion des prix)
$routes['GET']['/produits/modify-price'] = 'PriceController@modifyForm';
$routes['POST']['/produits/modify-price'] = 'PriceController@modify';
$routes['GET']['/produits/price-history'] = 'PriceController@history';
$routes['GET']['/produits/price-history/export'] = 'PriceController@exportCSV';
$routes['GET']['/api/price/can-modify'] = 'PriceController@canModifyPrice';

// Routes protégées - Caisse
$routes['GET']['/caisse'] = 'CaisseController@dashboard';
$routes['GET']['/caisse/session'] = 'CaisseController@sessionForm';
$routes['POST']['/caisse/session'] = 'CaisseController@changeSession';
$routes['GET']['/caisse/ouverture'] = 'CaisseController@ouverture';
$routes['POST']['/caisse/traiter-ouverture'] = 'CaisseController@traiterOuverture';
$routes['GET']['/caisse/etat'] = 'CaisseController@etat';
$routes['GET']['/caisse/apiEtat'] = 'CaisseController@statutSession';
$routes['GET']['/caisse/historique'] = 'CaisseController@historique';
$routes['GET']['/caisse/fermer'] = 'CaisseController@fermeture';
$routes['GET']['/caisse/fermeture'] = 'CaisseController@fermeture';
$routes['POST']['/caisse/traiter-fermeture'] = 'CaisseController@traiterFermeture';
$routes['GET']['/caisse/encaissements'] = 'CaisseController@encaissements';
$routes['GET']['/caisse/decaissements'] = 'CaisseController@decaissements';
$routes['POST']['/caisse/decaissements'] = 'CaisseController@storeDecaissement';
$routes['GET']['/caisse/annulations'] = 'CaisseController@annulations';
$routes['GET']['/caisse/rapports'] = 'CaisseController@rapports';
$routes['GET']['/caisse/rapports/export'] = 'CaisseController@exportRapport';

// Routes Journal de Caisse
$routes['GET']['/caisse/journal'] = 'JournalCaisseController@index';
$routes['GET']['/caisse/journal/detail'] = 'JournalCaisseController@detail';
$routes['GET']['/caisse/journal/export-excel'] = 'JournalCaisseController@exportExcel';
$routes['GET']['/caisse/journal/export-pdf'] = 'JournalCaisseController@exportPdf';
$routes['GET']['/caisse/journal/search'] = 'JournalCaisseController@search';

// Routes protégées - Administration
$routes['GET']['/admin'] = 'AdminController@adminDashboard';
$routes['GET']['/admin/dashboard'] = 'AdminController@dashboard';
$routes['GET']['/admin/statistiques'] = 'AdminController@statistiques';
$routes['GET']['/admin/statistiques/live'] = 'AdminController@statistiquesLive';
$routes['GET']['/admin/annulation-tickets'] = 'AdminController@annulationTickets';
$routes['GET']['/admin/users'] = 'AdminController@users';
$routes['GET']['/admin/users/create'] = 'AdminController@createUser';
$routes['POST']['/admin/users/store'] = 'AdminController@storeUser';
$routes['GET']['/admin/users/{id}/edit'] = 'AdminController@editUser';
$routes['POST']['/admin/users/{id}/update'] = 'AdminController@updateUser';
$routes['POST']['/admin/users/{id}/delete'] = 'AdminController@deleteUser';
$routes['GET']['/admin/roles'] = 'AdminController@roles';
$routes['GET']['/admin/audit'] = 'AdminController@audit';
$routes['GET']['/admin/audit/live'] = 'AdminController@auditLive';
$routes['GET']['/admin/system'] = 'AdminController@system';

// Routes protégées - Système
$routes['GET']['/date/change'] = 'SystemController@dateForm';
$routes['POST']['/date/change'] = 'SystemController@changeDate';
$routes['GET']['/system/info'] = 'SystemController@info';
$routes['GET']['/assistant/dashboard'] = 'AssistantController@dashboard';
$routes['GET']['/assistant/api/dashboard'] = 'AssistantController@apiDashboard';
$routes['GET']['/assistant/vente-session'] = 'AssistantController@venteSession';
$routes['GET']['/assistant/commandes'] = 'AssistantController@commandes';
$routes['GET']['/assistant/remise'] = 'AssistantController@remise';
$routes['GET']['/assistant/annulation'] = 'AssistantController@annulationTickets';
$routes['GET']['/assistant/annulation-ticket'] = 'AssistantController@annulationTickets';
$routes['GET']['/assistant/arret-caisse'] = 'AssistantController@arretCaisse';
$routes['GET']['/assistant/facturation'] = 'AssistantController@facturation';
$routes['GET']['/assistant/impression'] = 'AssistantController@facturation';
$routes['GET']['/assistant/preparation-commandes'] = 'AssistantController@preparationCommandes';
$routes['GET']['/assistant/preparer-commande'] = 'AssistantController@preparationCommandes';
$routes['GET']['/assistant/statistiques'] = 'AssistantController@statistiques';
$routes['GET']['/assistant/mouvements-stock'] = 'AssistantController@mouvementsProduits';
$routes['GET']['/assistant/mouvements-produits'] = 'AssistantController@mouvementsProduits';
$routes['GET']['/assistant/codes-acces'] = 'AssistantController@codesAcces';
$routes['GET']['/assistant/stock'] = 'StockController@index';
$routes['GET']['/commande/dashboard'] = 'ChargeCommandeController@dashboard';
$routes['GET']['/commande/api/dashboard'] = 'ChargeCommandeController@apiDashboard';
$routes['GET']['/commande/reception'] = 'ChargeCommandeController@receptionForm';
$routes['POST']['/commande/reception'] = 'ChargeCommandeController@storeReception';
$routes['GET']['/commande/order-items'] = 'ChargeCommandeController@orderItems';
$routes['GET']['/commande/saisie'] = 'ChargeCommandeController@orderForm';
$routes['POST']['/commande/saisie'] = 'ChargeCommandeController@storeOrder';
$routes['GET']['/commande/edit'] = 'ChargeCommandeController@editOrder';
$routes['POST']['/commande/update'] = 'ChargeCommandeController@updateOrder';
$routes['GET']['/commande/show'] = 'ChargeCommandeController@showOrder';
$routes['GET']['/commande/print'] = 'ChargeCommandeController@printOrder';
$routes['GET']['/commande/export-pdf'] = 'ChargeCommandeController@exportPdf';
$routes['GET']['/commande/export-excel'] = 'ChargeCommandeController@exportExcel';
$routes['POST']['/commande/cancel'] = 'ChargeCommandeController@cancelOrder';
$routes['POST']['/commande/duplicate'] = 'ChargeCommandeController@duplicateOrder';
$routes['GET']['/commande/mouvements'] = 'ChargeCommandeController@mouvements';
$routes['GET']['/commande/historique'] = 'ChargeCommandeController@orderHistory';
$routes['POST']['/commande/update-status'] = 'ChargeCommandeController@updateOrderStatus';
$routes['POST']['/commande/send'] = 'ChargeCommandeController@sendOrder';

// Routes protégées - Comptabilité SYSCOHADA
$routes['GET']['/comptabilite'] = 'ComptabiliteController@index';
$routes['GET']['/comptabilite/plan-comptable'] = 'ComptabiliteController@planComptable';
$routes['GET']['/comptabilite/journaux'] = 'ComptabiliteController@journaux';
$routes['GET']['/comptabilite/journaux/ventes'] = 'ComptabiliteController@journalVentes';
$routes['GET']['/comptabilite/journaux/achats'] = 'ComptabiliteController@journalAchats';
$routes['GET']['/comptabilite/journaux/caisse'] = 'ComptabiliteController@journalCaisse';
$routes['GET']['/comptabilite/grand-livre'] = 'ComptabiliteController@grandLivre';
$routes['GET']['/comptabilite/balance'] = 'ComptabiliteController@balance';
$routes['GET']['/comptabilite/etats-financiers'] = 'ComptabiliteController@etatsFinanciers';
$routes['GET']['/comptabilite/suivi-tiers'] = 'ComptabiliteController@suiviTiers';
$routes['GET']['/comptabilite/tva'] = 'ComptabiliteController@tva';
$routes['GET']['/comptabilite/integration'] = 'ComptabiliteController@integration';
$routes['POST']['/comptabilite/integration'] = 'ComptabiliteController@integration';
$routes['GET']['/comptabilite/exporter'] = 'ComptabiliteController@exporter';
$routes['GET']['/comptabilite/api'] = 'ComptabiliteController@api';
$routes['POST']['/comptabilite/api'] = 'ComptabiliteController@api';

// Routes protégées - Inventaire
$routes['GET']['/inventaire'] = 'InventaireController@index';
$routes['GET']['/inventaire/create'] = 'InventaireController@create';
$routes['POST']['/inventaire'] = 'InventaireController@store';
$routes['GET']['/inventaire/saisie'] = 'InventaireController@saisie';
$routes['POST']['/inventaire/article'] = 'InventaireController@storeArticle';
$routes['GET']['/inventaire/{id}'] = 'InventaireController@detail';
$routes['POST']['/inventaire/{id}/valider'] = 'InventaireController@valider';
$routes['POST']['/inventaire/{id}/annuler'] = 'InventaireController@annuler';

// Routes RBAC Test (Admin uniquement)
$routes['GET']['/rbac/test'] = 'RBACTestController@index';
$routes['POST']['/rbac/test/run'] = 'RBACTestController@runTests';

// Routes API
$routes['GET']['/api/clients'] = 'ApiController@getClients';
$routes['POST']['/api/clients'] = 'ApiController@createClient';
$routes['PUT']['/api/clients/{id}'] = 'ApiController@updateClient';
$routes['DELETE']['/api/clients/{id}'] = 'ApiController@deleteClient';

// Routes protégées - Stock
$routes['GET']['/stock'] = 'StockController@dashboard';
$routes['GET']['/stock/index'] = 'StockController@index';
$routes['GET']['/stock/disponibilite'] = 'StockController@index';
$routes['GET']['/stock/alerts'] = 'StockAlertController@index';
$routes['GET']['/stock/ajouter'] = 'StockController@ajouter';
$routes['POST']['/stock/ajouter'] = 'StockController@storeAjout';
$routes['POST']['/stock/entree-directe'] = 'StockController@entreeDirecte';
$routes['GET']['/stock/ajouter-produit'] = 'StockController@ajouterProduit';
$routes['POST']['/stock/ajouter-produit'] = 'StockController@storeAjoutProduit';
$routes['GET']['/stock/historique-prix'] = 'StockController@historiquePrix';
$routes['GET']['/fournisseurs/ajouter'] = 'StockController@ajouterFournisseur';
$routes['POST']['/fournisseurs/store'] = 'StockController@storeFournisseur';
$routes['GET']['/stock/fournisseurs/ajouter'] = 'StockController@ajouterFournisseur';
$routes['POST']['/stock/fournisseurs/store'] = 'StockController@storeFournisseur';
$routes['GET']['/stock/details'] = 'StockController@details';
// Routes liées aux lots - Désactivées car le système fonctionne maintenant sans lots
// $routes['GET']['/stock/lots'] = 'StockController@lots';
// $routes['GET']['/stock/ajouter-lot'] = 'StockController@ajouterLot';
// $routes['POST']['/stock/ajouter-lot'] = 'StockController@ajouterLot';
$routes['GET']['/stock/ajustement'] = 'StockController@ajustement';
$routes['POST']['/stock/ajustement'] = 'StockController@ajustement';
$routes['GET']['/stock/modifier/{id}'] = 'StockController@modifier';
$routes['POST']['/stock/modifier/{id}'] = 'StockController@update';
$routes['GET']['/stock/mouvements'] = 'StockController@mouvements';
$routes['GET']['/stock/peremptions'] = 'StockController@peremptions';
$routes['POST']['/stock/traiter-perimes'] = 'StockController@traiterPerimes';
$routes['GET']['/stock/commandes-automatiques'] = 'StockController@commandesAutomatiques';
$routes['POST']['/stock/commandes-automatiques'] = 'StockController@commandesAutomatiques';
$routes['GET']['/stock/rapports'] = 'StockController@rapports';
$routes['GET']['/stock/rechercher-produits'] = 'StockController@rechercherProduits';
$routes['POST']['/stock/synchroniser-stocks'] = 'StockController@synchroniserStocks';
$routes['POST']['/stock/entree'] = 'StockController@entree';
$routes['POST']['/stock/sortie'] = 'StockController@sortie';
$routes['GET']['/stock/flux'] = 'StockController@flux';
$routes['GET']['/stock/valeur'] = 'StockController@valeurStock';
$routes['GET']['/fournisseurs'] = 'StockController@listFournisseurs';
$routes['GET']['/stock/fournisseurs'] = 'StockController@listFournisseurs';
// Routes d'export
$routes['GET']['/stock/export/csv'] = 'StockController@exportStockCSV';
$routes['GET']['/stock/mouvements/export/csv'] = 'StockController@exportMouvementsCSV';
$routes['GET']['/stock/peremptions/export/csv'] = 'StockController@exportPeremptionsCSV';
$routes['GET']['/stock/print'] = 'StockController@printStock';

// Finance — règlements clients et fournisseurs
$routes['GET']['/finance/clients'] = 'FinanceController@clients';
$routes['GET']['/finance/clients/detail'] = 'FinanceController@clientDetail';
$routes['POST']['/finance/clients/reglement'] = 'FinanceController@storeReglementClient';
$routes['GET']['/finance/fournisseurs'] = 'FinanceController@fournisseurs';
$routes['GET']['/finance/fournisseurs/detail'] = 'FinanceController@fournisseurDetail';
$routes['POST']['/finance/fournisseurs/reglement'] = 'FinanceController@storeReglementFournisseur';
$routes['GET']['/finance/remises-limites'] = 'FinanceController@remisesLimites';
$routes['POST']['/finance/remises-limites'] = 'FinanceController@updateRemiseLimite';

// Suivi Client — Module de suivi des clients et règlements
$routes['GET']['/suivi-client'] = 'SuiviClientController@index';
$routes['GET']['/suivi-client/create'] = 'SuiviClientController@create';
$routes['POST']['/suivi-client/store'] = 'SuiviClientController@store';
$routes['GET']['/suivi-client/edit'] = 'SuiviClientController@edit';
$routes['POST']['/suivi-client/update'] = 'SuiviClientController@update';
$routes['POST']['/suivi-client/destroy'] = 'SuiviClientController@destroy';
$routes['GET']['/suivi-client/saisie-reglement'] = 'SuiviClientController@saisieReglement';
$routes['POST']['/suivi-client/store-reglement'] = 'SuiviClientController@storeReglement';
$routes['POST']['/suivi-client/brouillons'] = 'SuiviClientController@saveDraft';
$routes['POST']['/suivi-client/brouillons/{id}'] = 'SuiviClientController@saveDraft';
$routes['GET']['/suivi-client/brouillons/{id}/consulter'] = 'SuiviClientController@consulterBrouillon';
$routes['POST']['/suivi-client/brouillons/{id}/supprimer'] = 'SuiviClientController@deleteDraft';
$routes['GET']['/suivi-client/recu-reglement'] = 'SuiviClientController@recuReglement';
$routes['GET']['/suivi-client/ouvrir-saisie'] = 'SuiviClientController@ouvrirSaisie';
$routes['GET']['/suivi-client/releve-reglements'] = 'SuiviClientController@releveReglements';
$routes['GET']['/suivi-client/liste-clients'] = 'SuiviClientController@listeClients';
$routes['GET']['/suivi-client/export-clients'] = 'SuiviClientController@exportClients';

// Produits — Module de gestion des produits
$routes['GET']['/produits'] = 'ProduitController@index';
$routes['GET']['/produits/catalogue-vendeur'] = 'ProduitController@catalogueVendeur';
$routes['GET']['/produits/inventaire'] = 'ProduitController@inventaire';
$routes['GET']['/produits/etat-stocks'] = 'ProduitController@etatStocks';
$routes['GET']['/produits/liste-prix'] = 'ProduitController@listePrix';
$routes['GET']['/produits/gestion-mini-maxi'] = 'ProduitController@gestionMiniMaxi';
$routes['GET']['/produits/produits-specifiques'] = 'ProduitController@produitsSpecifiques';
$routes['GET']['/produits/coefficients-vente'] = 'ProduitController@coefficientsVente';
$routes['GET']['/produits/sortie-stock'] = 'ProduitController@sortieStock';
$routes['POST']['/produits/sortie-stock/store'] = 'ProduitController@storeSortieStock';
$routes['GET']['/produits/historique-sorties'] = 'ProduitController@historiqueSorties';
// Routes liées aux lots - Désactivées car le système fonctionne maintenant sans lots
// $routes['GET']['/produits/api/lots'] = 'ProduitController@apiProduitLots';
$routes['GET']['/produits/entree-stock'] = 'ProduitController@entreeStock';
$routes['POST']['/produits/entree-stock/store'] = 'ProduitController@storeEntreeStock';
$routes['GET']['/produits/ajustement-stock'] = 'ProduitController@ajustementStock';
$routes['POST']['/produits/ajustement-stock/store'] = 'ProduitController@storeAjustementStock';
$routes['GET']['/produits/produits-expires'] = 'ProduitController@produitsExpires';
$routes['GET']['/produits/rapports'] = 'ProduitController@rapports';
// Produits — Consultation vendeur (lecture seule)
$routes['GET']['/produits/scanner'] = 'ProduitController@scanner';
$routes['GET']['/produits/recherche-vendeur'] = 'ProduitController@rechercheVendeur';
$routes['GET']['/produits/disponibilite-vendeur'] = 'ProduitController@disponibiliteVendeur';
$routes['GET']['/produits/prix-vendeur'] = 'ProduitController@prixVendeur';
$routes['GET']['/suivi-client/solde-courant'] = 'SuiviClientController@soldeCourant';
$routes['GET']['/suivi-client/releve-courant'] = 'SuiviClientController@releveCourant';
$routes['GET']['/suivi-client/solde-arrete'] = 'SuiviClientController@soldeArrete';
$routes['GET']['/suivi-client/releve-arrete'] = 'SuiviClientController@releveArrete';
$routes['GET']['/suivi-client/solde-client'] = 'SuiviClientController@soldeClient';
$routes['GET']['/suivi-client/releve-client'] = 'SuiviClientController@releveClient';
$routes['GET']['/suivi-client/export'] = 'SuiviClientController@export';

// Inventaire
$routes['GET']['/inventaire'] = 'InventaireController@index';
$routes['GET']['/inventaire/create'] = 'InventaireController@create';
$routes['POST']['/inventaire/store'] = 'InventaireController@store';
$routes['GET']['/inventaire/saisie'] = 'InventaireController@saisie';
$routes['POST']['/inventaire/article'] = 'InventaireController@storeArticle';
$routes['POST']['/inventaire/cloturer'] = 'InventaireController@cloturer';
$routes['GET']['/inventaire/detail'] = 'InventaireController@detail';

// Routes dashboard par défaut
$routes['GET']['/dashboard'] = 'DashboardController@index';
$routes['GET']['/'] = 'HomeController@index';

return $routes;
