# projet-GM
## Prérequis

Pour mettre en place l'environnement de développement, vous devez disposer des outils suivants :

- [Docker](https://www.docker.com/get-started)
- [Docker Compose](https://docs.docker.com/compose/install/) (généralement inclus avec Docker Desktop)

## Installation

### 1. Lancer l'environnement Docker

Si vous avez déjà lancé le projet avant le 09/2025, vous aviez une base MySQL. 
Nous avons changé pour une base PostgreSQL. Il faut donc supprimer le volume :

```bash
docker remove database_data
```

Utilisez la commande Make pour construire et démarrer les conteneurs :

```bash
make up
```

Cette commande va :
1. Construire les images Docker
2. Démarrer les conteneurs
3. Installer les dépendances avec Composer
4. Exécuter les migrations de base de données

### 2. Accéder à l'application

Une fois l'installation terminée, vous pouvez accéder à :
- Application web : http://localhost:8000
- Interface Adminer (gestion BDD) : http://localhost:8080
    - Système : PostgreSQL
    - Serveur : database
    - Utilisateur : root
    - Mot de passe : password
    - Base de données : app

## Commandes utiles

Le Makefile contient plusieurs commandes pratiques :

- `make up` : Démarrer l'environnement de développement
- `make down` : Arrêter tous les conteneurs
- `make php` : Ouvrir un terminal bash dans le conteneur PHP
- `make migrate` : Générer et exécuter les migrations de base de données

## Structure du projet

L'environnement de développement du projet utilise une architecture Docker avec les services suivants :
- **database** : PostgreSQL 16
- **adminer** : Interface de gestion de base de données
- **php-nginx** : Serveur web Nginx
- **php-fpm** : PHP 8.4 avec les extensions nécessaires

## Développement

Les fichiers du projet sont montés dans les conteneurs, ce qui signifie que toute modification locale est immédiatement disponible dans l'environnement Docker.