# Informations sur la production

Ce serveur VPS OVH héberge 3 projets, chacun avec son utilisateur système, sa base
PostgreSQL et son pool PHP-FPM dédiés :

| Projet | Domaine | User système | Base PostgreSQL |
|---|---|---|---|
| projet-GM | `gm.kyudo.fr` | `gm_user` | `projet_gm` |
| pikaichu-symfony | `pikaichu-symfony.kyudo.fr` | `pikaichu_user` | `pikaichu` |
| Kyudopedia (MediaWiki) | `kyudopedia.kyudo.fr` | `mediawiki` | `mediawiki_db` |

Un compte `backup_pg` (lecture seule) sauvegarde les 3 bases quotidiennement (cf.
section Sauvegardes). L'administration système se fait via le compte `sgandossi`.

## Firewall UFW

Le firewall bloque tout ce qui rentre sur le serveur sauf les ports SSH, HTTP et
HTTPS (en IPv4 et IPv6). Le trafic sortant est autorisé par défaut.

```bash
sudo ufw status verbose
```

## Fail2ban

Fail2ban bloque les tentatives de bruteforce : il analyse le trafic et bannit
temporairement une IP après trop d'échecs.

```bash
sudo fail2ban-client status
```

Jails actives :

- `sshd` : bannit les IP après trop de tentatives de connexion SSH infructueuses
- `nginx-404` : bannit les IP qui génèrent trop d'erreurs 404 (scans de
  vulnérabilités). **Important** : le `logpath` doit couvrir les logs des 3
  projets, pas seulement le fichier par défaut de Nginx :
  `logpath = /var/log/nginx/*_access.log`
- `nginx-botsearch` : bannit les IP qui scannent des chemins typiques de
  webshells/exploits (`R57.php`, `bolt.php`, etc.), indépendamment du code HTTP
  retourné — complète `nginx-404` puisque ces scans tombent souvent sur le vhost
  par défaut avec un code `301`/`444`, pas `404`

Statut détaillé d'une jail :

```bash
sudo fail2ban-client status sshd
sudo fail2ban-client status nginx-404
sudo fail2ban-client status nginx-botsearch
```

## SSH

Configuration durcie dans `/etc/ssh/sshd_config` :

- `PermitRootLogin no` — connexion root directe interdite
- `PasswordAuthentication no` — **authentification par clé uniquement** (le
  volume de bruteforce observé sur `sshd`, plusieurs dizaines de milliers de
  tentatives, rend l'auth par mot de passe risquée)
- `PubkeyAuthentication yes`
- `AllowTcpForwarding no`, `AllowAgentForwarding no`, `X11Forwarding no`,
  `TCPKeepAlive no` — surface d'attaque réduite, ces fonctionnalités ne sont pas
  utilisées sur ce serveur
- `MaxAuthTries 3`, `MaxSessions 2`, `ClientAliveCountMax 2`
- `LogLevel VERBOSE`
- `Banner /etc/issue.net` — bannière légale affichée avant connexion (propriété
  CNKyudo/FFJDA, accès réservé à la commission Outils numériques)

Vérifier la configuration effective :

```bash
sudo sshd -T | grep -Ei 'permitrootlogin|passwordauthentication|pubkeyauthentication'
```

**Point de vigilance non résolu** : le compte `ubuntu` (créé par défaut par
cloud-init OVH) a un sudo `NOPASSWD:ALL` et un mot de passe encore actif (pas de
clé SSH associée, donc pas d'accès distant possible tant que
`PasswordAuthentication` reste désactivé). À verrouiller quand on aura le temps :
`sudo passwd -l ubuntu`, `sudo usermod -s /usr/sbin/nologin ubuntu`, et retrouver
puis neutraliser la règle sudoers correspondante (pas dans
`/etc/sudoers.d/90-cloud-init-users`, à identifier via
`sudo grep -rl "ubuntu" /etc/sudoers /etc/sudoers.d/`).

## Nginx

Chaque projet a son propre vhost. Nginx sert le contenu de `production/` qui est
un lien symbolique vers un dossier de release (cf. Déploiement).

```bash
sudo vim /etc/nginx/sites-available/<projet>.conf
```

Logs par projet :

- `/var/log/nginx/projet-GM_access.log` / `_error.log`
- `/var/log/nginx/pikaichu-symfony_access.log` / `_error.log`
- `/var/log/nginx/mediawiki_access.log` / `_error.log`

**Vhost catch-all** (`/etc/nginx/sites-available/000-default-catchall.conf`) :
toute requête avec un `Host` qui ne correspond à aucun des 3 domaines ci-dessus
tombe sur ce vhost, qui coupe la connexion sans réponse (`return 444;` en HTTP,
`ssl_reject_handshake on;` en HTTPS). Sans ce vhost explicite, Nginx retombe
silencieusement sur le premier vhost chargé par ordre alphabétique — comportement
fragile qu'on a corrigé après l'avoir identifié pendant l'audit de sécurité.

## PHP

Chaque projet a un pool PHP-FPM dédié, avec son propre socket Unix, ce qui
permet d'isoler la configuration et les processus d'un projet à l'autre.

```bash
sudo vim /etc/php/8.4/fpm/pool.d/<projet>.conf
```

`allow_url_fopen = Off` dans `php.ini` (désactive les inclusions de fichiers
distants).

## PostgreSQL

La base de données est sur le serveur, non accessible depuis l'extérieur
(authentification restreinte à `localhost`/socket Unix dans `pg_hba.conf`).

Rôles :

- `postgres` : superutilisateur, administration du cluster
- `gm_user`, `pikaichu_user`, `mediawiki_user` : un rôle par projet, propriétaire
  de sa base, sans attribut spécial (pas de superuser/createdb/createrole)
- `backup_pg` : lecture seule sur les 3 bases, utilisé par le script de sauvegarde

**Isolation entre projets** : chaque base a eu son droit `CONNECT` retiré à
`PUBLIC` (comportement par défaut de PostgreSQL à la création d'une base,
souvent oublié — sans ce retrait, n'importe quel rôle du cluster peut se
connecter à n'importe quelle base avec son propre mot de passe). Seuls le
propriétaire et `backup_pg` ont `CONNECT` explicite :

```sql
REVOKE CONNECT ON DATABASE <db> FROM PUBLIC;
GRANT CONNECT ON DATABASE <db> TO backup_pg;
```

Vérifier l'état des droits :

```bash
sudo -u postgres psql -c "SELECT datname, datacl FROM pg_database;"
```

Administration (créer une base, gérer les rôles) :

```bash
sudo -i -u postgres
psql
```

Requêtes sur la base d'un projet :

```bash
psql -U gm_user -d projet_gm -h 127.0.0.1 -W
```

## Sauvegardes

Script `/usr/local/bin/pg_backup_s3.sh`, exécuté quotidiennement à 3h du matin
via le crontab de `backup_pg` :

```bash
sudo crontab -l -u backup_pg
```

Il dump les 3 bases (`projet_gm`, `pikaichu`, `mediawiki_db`), les compresse et
les envoie vers OVH Object Storage (`s3cmd`), avec une purge locale à 30 jours
(la purge côté bucket S3 n'est pas automatisée — à surveiller si le volume de
stockage grossit trop).

Logs : `/var/log/pg_backup.log`

Le script et le fichier `~backup_pg/.s3cfg` contiennent des identifiants en
clair (mot de passe PostgreSQL, clés S3) : leurs permissions doivent rester
strictes (`700`/`600`, propriétaire `backup_pg` uniquement) :

```bash
ls -la /usr/local/bin/pg_backup_s3.sh ~backup_pg/.s3cfg
```

## Malware / intégrité

`rkhunter` est installé pour scanner périodiquement le système à la recherche de
rootkits/malwares connus :

```bash
sudo rkhunter --check --sk
```

Rapport dans `/var/log/rkhunter.log`. Les bases secondaires
(`programs_bad.dat`, `backdoorports.dat`, `suspscan.dat`, `i18n.ver`) ne se
mettent pas à jour via `--update` à cause d'un `mirrors.dat` vide par défaut
dans le paquet Debian/Ubuntu — sans impact sur le fonctionnement de `--check`,
qui utilise la base principale de signatures fournie par le paquet.

## Déploiement

Le déploiement est lancé automatiquement lorsqu'un commit est poussé sur la
branche `main` de chaque projet (via GitHub Actions + SSH).

Pour projet-GM, le script `deploy.sh` est copié sur le serveur puis exécuté avec
l'utilisateur `gm_user`. Il crée un dossier avec le hash du commit dans
`/var/www/projet-GM/releases/{HASH_COMMIT}`, y clone le projet, insère le
`.env.local` (depuis un dossier `shared/` persistant pour pikaichu-symfony ;
projet-GM utilise encore l'ancien schéma avec `.env.local` dans `production/`),
lance les commandes Symfony de préparation, puis met à jour le lien symbolique
`/var/www/projet-GM/releases/{HASH_COMMIT}` → `/var/www/projet-GM/production`.

Le script garde les 5 dernières releases et supprime les plus anciennes.

Rollback manuel (en s'aidant des logs git ou des dates) :

```bash
ln -sfn "/var/www/projet-GM/releases/{HASH_COMMIT}" "/var/www/projet-GM/production"
```

pikaichu-symfony suit le même principe (voir le repo pikaichu-symfony pour le
détail de son `deploy.sh` et sa procédure de premier déploiement).

## HTTPS

Les certificats TLS sont gérés par Let's Encrypt (durée de vie 3 mois),
générés sur le serveur et liés à la configuration Nginx de chaque projet.

```bash
sudo certbot certificates
```

Un timer systemd tente le renouvellement automatique deux fois par jour :

```bash
sudo systemctl status certbot.timer
```

Logs : `/var/log/letsencrypt/letsencrypt.log`

Test de renouvellement à blanc (ne modifie rien) :

```bash
sudo certbot renew --dry-run
```

## Historique des audits de sécurité

- **2026-08-30** : audit complet (Lynis, UFW, fail2ban, SSH, séparation des
  utilisateurs, PostgreSQL, mises à jour, sauvegardes, certificats). Hardening
  index Lynis passé de 70 à 76/100. Failles corrigées : permissions fichiers
  PostgreSQL, durcissement SSH (dont désactivation de l'auth par mot de passe),
  bannière SMTP Postfix, vhost Nginx catch-all manquant, jails fail2ban mal
  configurées, et surtout l'absence de `REVOKE CONNECT ... FROM PUBLIC` sur les
  3 bases PostgreSQL qui cassait l'isolation entre projets. Voir le point de
  vigilance non résolu sur le compte `ubuntu` ci-dessus.
