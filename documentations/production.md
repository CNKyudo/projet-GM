# Informations sur la production

## Firewall UFW

Le firewall bloque tous ce qui rentre sur le serveur sauf les ports SSH, HTTP et HTTPS.
Pour vérifier qu'il est actif, il suffit de taper la commande :

```bash
sudo ufw status
```

## Fail2ban

Fail2ban est un outils qui bloque les tentatives de bruteforce.
C'est à dire les tentatives de connection intempestives dans le but de trouver des informations pour entrer dans le serveur.
Pour cela il analyse le traffic et s'il y a beaucoup d'erreur venant d'une seule personne (adresse IP), il la bloque pendant quelques minutes.
Deux règles principales sont actives :

- SSH, il bloque les tentatives de connexions infructueuses trop nombreuses
- nginx, il bloque l'utilisateur s'il rencontre trop de requête en erreur (404)

## Nginx

Avec PHP il permet de rendre le site visible sur internet.
Son dossier est `/var/www/projet-GM/production` qui est un lien symbolique vers le un dossier de releases. (cf. déploiement)

La configuration Nginx pour le projet se situe ici :

```bash
sudo vim /etc/nginx/sites-available/projet-gm.conf
```

Les logs de l'application sont ici :

- `/var/log/nginx/projet-GM_access.log`
- `/var/log/nginx/projet-GM_error.log`

## PHP

Ce projet a un pool PHP dédié, ce qui permet d'avoir à l'avenir d'autre projet hébergés par le serveur avec des configurations différentes.
Le pool se configure ici :

```bash
sudo vim /etc/php/8.4/fpm/pool.d/projet-gm.conf
```

## PostgreSQL

La base de donnée est sur le serveur également et n'est pas accessible de l'extérieur par mesure de sécurité.
Il y a deux utilisateurs pour le moment :

- `postgres` : l'administrateur de la base de donnée
- `gm_user` : le propriétaire et le gestionnaire de la base de donnée du projet

Pour faire des modifications sur la base de donnée pour le projet, il faut utilise `gm_user` qui a tous les droits sur la base du projet.

Pour créer une nouvelle base de donnée ou gérer les droits, on peut utiliser l'utilisateur `postgres`, comme ceci :

```bash
sudo -i -u postgres
psql
```

Pour faire des requêtes sur la base de donnée du projet, il faut utiliser `gm_user` comme ceci :

```bash
psql -U gm_user -d projet_gm -h 127.0.0.1 -W

```

## Déploiement

Le déploiement est lancé automatiquement lorsqu'une Pull Request Github est merge sur la branche `main`.
Il copie le script `deploy.sh` sur le serveur avec l'utilisateur `gm_user` et le lance.

Ce script crée une dossiers avec le hash du commit dans `/var/www/projet-GM/releases/{HASH_COMMIT}`.
Dans ce dossier il git clone le projet, insère le `.env.local` contenant les variables d'environnement.
Ensuite il lance les commandes Symfony pour "préparer" le dossier.
Enfin, lorsque tout s'est terminé avec succès il crée un lien symbolique de `/var/www/projet-GM/releases/{HASH_COMMIT}` vers `/var/www/projet-GM/production`.

Ce script supprimes les anciennes releases pour n'en garder que 5.

Il est donc possible de rollback un déploiement en changeant le lien symbolique vers l'ancien hash (en s'aidant des logs git ou des dates) :

```bash
ln -sfn "/var/www/projet-GM/releases/{HASH_COMMIT}" "/var/www/projet-GM/production"

```

## HTTPS

Le HTTPS certificat TLS est géré par l'autorité de certificat Let's Encrypt qui propose des certificats TLS de 3 mois.
Le certificat est généré sur le serveur et lié à la configuration Nginx pour servir du HTTPS.
Un Timer est lancé régulièrement pour tenter de renouveller le certificat.
Pour avoir des infos sur le Timer, et quelle sera son prochain passage (Trigger), on peut utiliser la commande :

```bash
sudo systemctl status certbot.timer
```

Les logs letsencrypt sont ici : `/var/log/letsencrypt/letsencrypt.log`.

On peut faire un test immédiat "à blanc" (--dry-run simule le renouvellement) :

```bash
sudo sudo certbot renew --dry-run
```