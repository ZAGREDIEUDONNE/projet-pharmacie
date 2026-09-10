# Implémentation des Types de Clients - Pharmacie Burkina Faso

## Vue d'ensemble

Ce document décrit l'implémentation des nouveaux types de clients conformément aux pratiques des pharmacies au Burkina Faso. Le système remplace les anciens types de clients par une nouvelle structure plus adaptée au contexte pharmaceutique burkinabè.

## Nouveaux Types de Clients

Les types de clients suivants sont maintenant disponibles:

1. **Ordinaire** - Clients particuliers standard
2. **Courant** - Clients avec accès à tous les modes de paiement
3. **Courant - Dépôt** - Clients payant exclusivement par dépôt
4. **Courant - Bon** - Clients payant exclusivement par bon
5. **Courant - Carnet** - Clients payant exclusivement par carnet
6. **Autres clients** - Entreprises et autres entités spéciales

## Modes de Paiement par Type de Client

| Type de Client | Modes de Paiement Autorisés | Mode par Défaut |
|----------------|----------------------------|-----------------|
| Ordinaire | Espèces, Carte Visa, Mobile Money, Chèque | Espèces |
| Courant | Tous les modes (Espèces, Carnet, Dépôt, Carte Visa, Mobile Money, Chèque, Bon) | Espèces |
| Courant - Dépôt | Dépôt uniquement | Dépôt |
| Courant - Bon | Bon uniquement | Bon |
| Courant - Carnet | Carnet uniquement | Carnet |
| Autres clients | Tous les modes | Espèces |

## Fichiers Modifiés

### 1. Base de Données
- **`database/migrations/017_update_client_types.sql`**
  - Met à jour l'ENUM du champ `type_client` dans la table `clients`
  - Migre les données existantes vers les nouveaux types
  - Crée une vue pour les statistiques par type de client

### 2. Modèle
- **`app/Models/Client.php`**
  - Ajout de la méthode `getStatistiquesGlobales()` mise à jour avec les nouveaux types
  - Ajout de `getPaymentModesByClientType()` - Récupère les modes de paiement autorisés
  - Ajout de `getDefaultPaymentMode()` - Récupère le mode de paiement par défaut
  - Ajout de `normalizeClientType()` - Normalise et valide le type de client
  - Ajout de `getClientTypeLabel()` - Récupère le label affichable
  - Ajout de `getClientTypeBadgeClass()` - Récupère la classe CSS pour les badges

### 3. Contrôleur
- **`app/Controllers/ClientController.php`**
  - Import du modèle `Client`
  - Mise à jour de `normalizeClientType()` pour utiliser la méthode du modèle
  - Mise à jour de la requête SQL dans `statistiques()` pour les nouveaux types

### 4. Service
- **`app/Services/ClientService.php`**
  - Mise à jour de `validerDonneesClient()` avec les nouveaux types autorisés

### 5. Vues - Création/Modification de Client
- **`app/Views/clients/create.php`**
  - Mise à jour de la liste des types de clients avec labels français
  - Ajout de la validation côté client pour le type de client

- **`app/Views/clients/edit.php`**
  - Mise à jour de la liste des types de clients avec labels français
  - Ajout de la validation côté client pour le type de client

### 6. Vues - Liste des Clients
- **`app/Views/clients/index.php`**
  - Mise à jour des classes CSS pour les badges des nouveaux types
  - Mise à jour du filtre par type de client
  - Utilisation de `Client::getClientTypeLabel()` pour l'affichage des labels

### 7. Vues - Statistiques
- **`app/Views/clients/statistiques.php`**
  - Mise à jour de la répartition par type avec les nouveaux types
  - Utilisation de `Client::getClientTypeLabel()` pour l'affichage

### 8. Vues - Ventes
- **`app/Views/vente/create.php`**
  - Ajout de la logique JavaScript pour adapter les modes de paiement selon le type de client
  - Ajout de `adaptPaymentModesByClientType()` - Fonction principale d'adaptation
  - Ajout de `prefillDepotDetails()` - Préremplissage des détails de dépôt pour Courant - Dépôt
  - Ajout d'un écouteur d'événement sur le changement de client
  - Stockage des données des clients dans `clientsData`

## Installation

### Étape 1: Exécuter la Migration

Exécutez le fichier de migration SQL manuellement via votre interface MySQL ou en ligne de commande:

```bash
mysql -u votre_utilisateur -p medecin < database/migrations/017_update_client_types.sql
```

Ou via phpMyAdmin:
1. Ouvrez phpMyAdmin
2. Sélectionnez la base de données `medecin`
3. Cliquez sur l'onglet "SQL"
4. Copiez et collez le contenu de `database/migrations/017_update_client_types.sql`
5. Cliquez sur "Exécuter"

### Étape 2: Vérifier la Migration

Vérifiez que la migration s'est bien exécutée:

```sql
-- Vérifier la structure de la table
DESCRIBE clients;

-- Vérifier les données migrées
SELECT type_client, COUNT(*) as count 
FROM clients 
WHERE deleted_at IS NULL 
GROUP BY type_client;
```

## Fonctionnalités Implémentées

### 1. Adaptation Dynamique des Modes de Paiement
Lors de la création d'une vente, le système adapte automatiquement les modes de paiement disponibles en fonction du type de client sélectionné:
- **Ordinaire**: Seuls les modes de paiement standards (Espèces, Carte Visa, Mobile Money, Chèque)
- **Courant**: Tous les modes de paiement disponibles
- **Courant - Dépôt**: Uniquement le mode Dépôt (sélectionné automatiquement)
- **Courant - Bon**: Uniquement le mode Bon (sélectionné automatiquement)
- **Courant - Carnet**: Uniquement le mode Carnet (sélectionné automatiquement)
- **Autres clients**: Tous les modes de paiement disponibles

### 2. Préremplissage des Détails de Dépôt
Pour les clients de type "Courant - Dépôt", le système préremplit automatiquement les champs de dépôt avec les informations du client (nom, adresse, téléphone).

### 3. Badges Colorés par Type de Client
Chaque type de client a un badge avec une couleur distincte pour une identification visuelle rapide:
- **Ordinaire**: Gris
- **Courant**: Bleu
- **Courant - Dépôt**: Violet
- **Courant - Bon**: Jaune
- **Courant - Carnet**: Vert
- **Autres clients**: Orange

### 4. Filtre par Type de Client
La liste des clients inclut un filtre permettant de filtrer par type de client.

### 5. Validation Côté Client et Serveur
- **Côté client**: Validation JavaScript dans les formulaires de création et modification
- **Côté serveur**: Validation dans `ClientService::validerDonneesClient()`

### 6. Statistiques Mises à Jour
Les statistiques globales des clients reflètent maintenant les nouveaux types de clients.

## Migration des Données

La migration SQL effectue les transformations suivantes:

| Ancien Type | Nouveau Type |
|-------------|--------------|
| ORDINAIRE, PARTICULIER, SOUS_CLIENT, BENEFICIAIRE, PRESCRIPTEUR | ORDINAIRE |
| ASSURE | COURANT |
| ENTREPRISE | AUTRES_CLIENTS |

## Compatibilité

### Modules Affectés
- **Gestion des clients**: Mise à jour complète des types
- **Ventes**: Adaptation dynamique des modes de paiement
- **Statistiques**: Mise à jour des rapports
- **Comptabilité**: Les types de clients sont utilisés dans les rapports financiers
- **Caisse**: Les modes de paiement sont adaptés selon le type de client

### Modules Non Affectés
- **Gestion des stocks**: Aucune dépendance directe
- **Commandes fournisseurs**: Aucune dépendance directe
- **Inventaires**: Aucune dépendance directe

## Tests Recommandés

1. **Création de client**: Tester la création avec chaque nouveau type de client
2. **Modification de client**: Vérifier que le type peut être modifié correctement
3. **Liste des clients**: Vérifier l'affichage des badges et le filtre
4. **Ventes**: Tester l'adaptation des modes de paiement pour chaque type de client
5. **Statistiques**: Vérifier que les statistiques reflètent correctement les nouveaux types
6. **Préremplissage**: Tester le préremplissage des détails de dépôt pour Courant - Dépôt

## Notes Importantes

1. **Sauvegarde**: Avant d'exécuter la migration, assurez-vous d'avoir une sauvegarde de la base de données.
2. **Données existantes**: La migration conserve toutes les données existantes en les mappant aux nouveaux types.
3. **Validation**: Les anciens types de clients ne sont plus acceptés par le système.
4. **Rétrocompatibilité**: Le système normalise automatiquement les anciens types vers les nouveaux si nécessaire via la méthode `normalizeClientType()`.

## Support

En cas de problème ou de question, consultez:
- Le fichier de migration SQL pour les détails techniques
- Le modèle `Client.php` pour les méthodes utilitaires
- Les vues modifiées pour les exemples d'utilisation
