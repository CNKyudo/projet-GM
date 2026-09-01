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
- `nginx-http-auth` : bannit les échecs d'authentification basique (utilisé sur
  les vhosts internes `monitoring.kyudo.fr` et `netdata.kyudo.fr`, cf.
  Observabilité), `logpath = /var/log/nginx/*_error.log`

Statut détaillé d'une jail :

```bash
sudo fail2ban-client status sshd
sudo fail2ban-client status nginx-404
sudo fail2ban-client status nginx-botsearch
sudo fail2ban-client status nginx-http-auth
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

`pg_hba.conf` ne contient volontairement aucune règle spécifique par base/rôle
(une ligne `host projet_gm gm_user ...` avait été ajoutée puis supprimée car
placée après la règle générique `host all all 127.0.0.1/32 scram-sha-256` —
donc jamais évaluée, et de toute façon redondante avec le `REVOKE` ci-dessus) :
c'est le `GRANT`/`REVOKE CONNECT` au niveau de chaque base qui fait l'isolation
réelle, pas `pg_hba.conf`.

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
les envoie vers OVH Object Storage (`s3cmd`), avec une purge locale à 30 jours.
À la fin, il envoie un heartbeat à Uptime Kuma (`status=up`/`status=down` selon
qu'une erreur a eu lieu ou non pendant le dump/upload, cf. Observabilité).

Logs : `/var/log/pg_backup.log`

Le script et le fichier `~backup_pg/.s3cfg` contiennent des identifiants en
clair (mot de passe PostgreSQL, clés S3) : leurs permissions doivent rester
strictes (`700`/`600`, propriétaire `backup_pg` uniquement) :

```bash
ls -la /usr/local/bin/pg_backup_s3.sh ~backup_pg/.s3cfg
```

**Droits de `backup_pg` sur une nouvelle base** : ne pas se contenter de
`GRANT SELECT ON ALL TABLES` + `ALTER DEFAULT PRIVILEGES` exécutés par
`postgres` juste après la création de la base — ça ne suffit pas, pour deux
raisons rencontrées avec `pikaichu` :

1. `ALTER DEFAULT PRIVILEGES` ne s'applique qu'aux futurs objets créés par le
   rôle qui exécute la commande. Les tables sont en réalité créées par le rôle
   propriétaire du projet (ex. `pikaichu_user`, via les migrations Doctrine),
   pas par `postgres` — il faut donc `ALTER DEFAULT PRIVILEGES FOR ROLE
   pikaichu_user IN SCHEMA public GRANT SELECT ON TABLES TO backup_pg;`
2. Le `GRANT SELECT ON ALL TABLES`/`ALL SEQUENCES` fait avant que les tables
   existent (avant le premier déploiement) n'a aucun effet rétroactif — à
   refaire une fois l'appli déployée et les migrations passées
3. Les **séquences** (clés auto-incrémentées) ne sont pas couvertes par
   `GRANT ... ON ALL TABLES` : il faut un `GRANT SELECT ON ALL SEQUENCES IN
   SCHEMA public TO backup_pg` + son équivalent `ALTER DEFAULT PRIVILEGES ...
   ON SEQUENCES` séparé

**Purge côté bucket S3** : gérée par des règles de cycle de vie (lifecycle),
pas par le script. Une règle par projet/préfixe, 30 jours :

```bash
sudo -u backup_pg s3cmd getlifecycle s3://sauvegarde-bdd
```

Pense à ajouter une règle pour tout nouveau projet — `s3cmd setlifecycle`
remplace la politique entière, il faut donc réinclure les règles existantes en
plus de la nouvelle.

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

## Observabilité

Deux outils tournent en conteneurs Docker, accessibles uniquement via un vhost
Nginx interne (HTTPS + authentification basique + fail2ban), jamais exposés
directement :

```bash
sudo docker ps
```

Les deux ont `--restart=unless-stopped` : ils redémarrent automatiquement avec
le démon Docker (donc au reboot du serveur), pas besoin de service systemd
séparé.

### Uptime Kuma

`https://monitoring.kyudo.fr` — conteneur `uptime-kuma`, port publié en
`127.0.0.1:3001` uniquement.

- 3 monitors HTTPS (un par projet) avec alerte d'expiration de certificat
- 4 monitors Push (heartbeat) : backup PostgreSQL, renouvellement certbot,
  mises à jour système en attente, scan `rkhunter` — chacun alimenté par un
  script/hook qui appelle l'URL de push à la fin de son exécution
- Notifications par email (SMTP Brevo)
- Auth basique Nginx (`/etc/nginx/.htpasswd_monitoring`) en plus du login
  natif de l'outil — **sauf sur `/api/push/`**, qui doit rester accessible
  sans identifiants Nginx (le token dans l'URL sert déjà de secret, et cet
  endpoint doit pouvoir être appelé par des scripts/cron/services systemd) :
  ```nginx
  location /api/push/ {
      auth_basic off;
      proxy_pass http://127.0.0.1:3001;
      ...
  }
  ```
- **Piège sur les monitors Push** : c'est le champ "Heartbeat Interval" (pas
  le "Retry Interval") qui définit la fenêtre d'attente avant de considérer un
  heartbeat manquant. Pour un heartbeat quotidien (backup, rkhunter, updates),
  le mettre à ~90000s (25h) — sinon le monitor repasse "down" (et notifie) à
  chaque minute entre deux exécutions réelles.

### Netdata

`https://netdata.kyudo.fr` — conteneur `netdata`, en mode `--network=host
--pid=host` (nécessaire pour qu'il voie les services de l'hôte comme
PostgreSQL/Nginx, qui n'écoutent que sur le `127.0.0.1` de la machine — un
conteneur en réseau bridge par défaut ne peut pas les atteindre). En mode
host, le mapping de ports Docker (`-p`) ne s'applique plus : la restriction à
`127.0.0.1` se fait dans la config Netdata elle-même (`[web] bind to =
127.0.0.1` dans `netdata.conf`).

Collecteurs actifs (`go.d/*.conf`, à éditer via
`docker exec -it netdata bash` puis `cd /etc/netdata && ./edit-config
go.d/<module>.conf`) :

| Collecteur | Fichier | Nécessite |
|---|---|---|
| Système (CPU/RAM/disque) | natif | montages `/proc`, `/sys` (`:ro`) |
| PostgreSQL | `postgres.conf` | rôle dédié `netdata` (`pg_monitor`), `GRANT CONNECT ON DATABASE postgres` |
| Nginx | `nginx.conf` | `stub_status` sur un vhost interne `127.0.0.1:8091` |
| PHP-FPM ×3 | `phpfpm.conf` | `pm.status_path = /status` par pool, exposé via le même vhost `8091` |
| Logs web ×3 | `web_log.conf` | lecture de `/var/log/nginx/*_access.log`, groupe `adm` de l'hôte ajouté au conteneur (`--group-add <GID adm>`) |
| fail2ban | `fail2ban.conf` | paquet installé au démarrage (`-e NETDATA_EXTRA_DEB_PACKAGES=fail2ban`) + socket `/var/run/fail2ban/fail2ban.sock` monté |
| systemd (unités) | `systemdunits.conf` | socket `/run/systemd/private` monté |
| Sessions actives | `logind.conf` | socket `/var/run/dbus/system_bus_socket` monté |
| Certificats TLS | `x509check.conf` | format `source: "tcp://domaine:443"` (pas `https://`, pas de port implicite) |

**À savoir en cas de nouveau collecteur** : le nom du module peut différer de
la documentation générale selon la version de l'image (`weblog` s'appelle en
réalité `web_log` sur la version installée, avec underscore). Pour
diagnostiquer un collecteur qui ne remonte rien :

```bash
sudo docker exec -it netdata bash
ls /usr/lib/netdata/conf.d/go.d/ | grep -i <mot-clé>   # trouve le vrai nom du module
exit
sudo docker exec -it netdata /usr/libexec/netdata/plugins.d/go.d.plugin -d -m <module>   # mode debug, montre l'erreur exacte
```

Les erreurs les plus fréquentes rencontrées : socket hôte manquant dans le
conteneur (`dial unix /chemin: no such file or directory` → ajouter le
montage), ou permissions insuffisantes sur un fichier de log (`--group-add`
avec le bon GID). Après un ajout de collecteur nécessitant un nouveau
montage/variable d'env, il faut recréer le conteneur (`docker stop && docker
rm && docker run ...`), pas juste le redémarrer.

Après un ajout de collecteur, si la section n'apparaît pas dans le dashboard
alors que l'API la confirme (`curl -s http://127.0.0.1:19999/api/v1/charts |
grep <module>`), faire un rechargement complet du navigateur (vider le cache)
— le menu ne se met pas toujours à jour tout seul.

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
  3 bases PostgreSQL qui cassait l'isolation entre projets.
- **2026-08-31 / 2026-09-01** : mise en place de l'observabilité (Docker,
  Uptime Kuma, Netdata — cf. section dédiée). Corrections associées : droits
  `backup_pg` sur `pikaichu` incomplets (tables + séquences + portée du rôle
  pour `ALTER DEFAULT PRIVILEGES`), jail `nginx-http-auth` ajoutée pour
  couvrir les nouveaux vhosts avec auth basique, règle de purge S3 manquante
  pour `pikaichu/`, nettoyage d'une ligne `pg_hba.conf` obsolète. Le point de
  vigilance sur le compte `ubuntu` (sudo `NOPASSWD:ALL`) a été volontairement
  abandonné (jugé non prioritaire).
- **Point ouvert, non traité** : Ubuntu 25.04 (« Plucky Puffin ») est une
  version intermédiaire dont le support standard (9 mois) est terminé depuis
  début 2026 — mise à niveau vers 26.04 LTS à prévoir (chemin obligatoire :
  25.04 → 25.10 → 26.04, deux montées de version successives), avec un
  snapshot OVH avant de commencer et une vigilance particulière sur SSH,
  Docker (dépôt tiers pointant sur un nom de code figé) et PHP-FPM (chemins
  versionnés).
