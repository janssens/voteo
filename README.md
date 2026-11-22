# 🗳️ Voteo

**Recueillez l'avis de vos membres directement dans leur boîte mail.**

Voteo est une application web **libre, gratuite et open-source** qui permet aux associations, groupes, institutions et entreprises de consulter leurs membres par email. Chaque membre reçoit une question personnelle et y répond **d'un simple clic, depuis l'email**, sans créer de compte ni installer d'application.

## Pour qui ?

- **Associations** : choisir la date d'une assemblée, valider une activité, recueillir un avis sur un projet.
- **Groupes** : clubs, collectifs, amicales, groupes de parents ou de voisins.
- **Institutions** : consulter simplement des adhérents, des élus ou des usagers.
- **Entreprises** : sonder des équipes ou des clients sans outil compliqué.

## Comment ça marche ?

1. **Rédigez votre question** : un sujet, une introduction, une conclusion et les réponses possibles (avec une couleur pour chacune). Choix unique ou multiple.
2. **Envoyez-la à vos listes** : ajoutez vos contacts, regroupez-les en listes, puis envoyez la question. Chaque contact reçoit un email avec un lien de réponse unique.
3. **Suivez les réponses** : le membre clique sur sa réponse dans l'email, elle est enregistrée immédiatement. Il voit alors un récapitulatif : nombre de réponses, taux de participation et répartition par option.

## Fonctionnalités

- Réponse en un clic depuis l'email, sans compte pour les membres
- Questions à choix unique ou multiple
- Gestion des contacts et des listes de diffusion, import CSV (Excel, LibreOffice, Google Sheets)
- Résultats en direct : nombre de réponses, taux de participation, détail par option
- Espace d'administration pour les organisateurs (EasyAdmin)
- Interface en français, adaptée au mobile

## Stack technique

- [Symfony 7.4](https://symfony.com/) (PHP ≥ 8.3), Twig, Doctrine ORM
- [EasyAdmin](https://github.com/EasyCorp/EasyAdminBundle) pour l'espace organisateur
- MariaDB 10.11
- [FrankenPHP](https://frankenphp.dev/) et Docker
- [Mailpit](https://mailpit.axllent.org/) pour intercepter les emails en développement

## Démarrage rapide

Prérequis : [Docker Compose](https://docs.docker.com/compose/install/) v2.10 ou plus.

```bash
git clone https://github.com/janssens/voteo.git
cd voteo

# Construire et lancer les conteneurs
docker compose build --pull --no-cache
docker compose up --wait

# Créer le schéma de base de données
docker compose exec php bin/console doctrine:migrations:migrate
```

Ensuite :

- Application : <https://localhost> (acceptez le certificat TLS auto-signé)
- Espace organisateur : <https://localhost/admin>
- Emails envoyés (Mailpit) : <http://localhost:8025>

Créez un compte via **Créer un compte**, puis ajoutez vos contacts, vos listes et votre première question depuis l'espace organisateur.

Pour arrêter : `docker compose down --remove-orphans`.

### Tests

Tests unitaires (sans base de données) : droits d'accès, contexte personnel / organisation, expéditeur des emails, règles des organisations et invitations.

```bash
docker compose exec php bin/phpunit
```

### Envoi automatique des résultats (cron)

Quand l'option **Envoyer les résultats à la clôture** est cochée sur une question, les résultats sont envoyés par email à tous les destinataires une fois la date de clôture passée. L'envoi est fait par la commande `app:send-results`, à lancer régulièrement, par exemple toutes les 5 minutes depuis la crontab de l'hôte :

```cron
*/5 * * * * cd /chemin/vers/voteo && docker compose exec -T php bin/console app:send-results --no-interaction
```

Chaque question n'est envoyée qu'une seule fois. `--dry-run` liste les questions concernées sans rien envoyer.

### Purge des invitations expirées (cron)

Les invitations à rejoindre une organisation contiennent l'adresse email de la personne invitée. Une fois le lien expiré (7 jours), elles restent visibles et renvoyables pendant 30 jours, puis la commande `app:purge-invitations` les supprime. À lancer une fois par jour :

```cron
30 3 * * * cd /chemin/vers/voteo && docker compose exec -T php bin/console app:purge-invitations --no-interaction
```

## Mise en ligne (o2switch)

La production est hébergée sur un mutualisé o2switch (Apache + PHP). Le workflow [deploy.yml](.github/workflows/deploy.yml) envoie le code par FTPS avec `lftp`.

Le déploiement automatique à chaque push sur `main` est **suspendu** : lancez le workflow à la main depuis l'onglet **Actions** → *Deploy o2switch (FTP)* → **Run workflow**. Pour le réactiver, décommentez le bloc `push` en tête du workflow.

### Ce que fait le workflow

1. Génère `.env.local.php` à partir du secret `ENV_PROD` (`composer dump-env prod`).
2. Restaure la date de chaque fichier à celle de son dernier commit, pour n'envoyer que les fichiers modifiés.
3. Synchronise le projet vers le serveur (`lftp mirror --reverse --delete`). Les fichiers supprimés du dépôt sont supprimés sur le serveur.
4. Vide le cache Symfony distant (`var/cache/prod`).

Ne sont **pas** envoyés : `vendor/`, `public/bundles/`, `var/`, `docs/`, les fichiers Docker et les `.env` locaux. Ces dossiers ne sont jamais supprimés sur le serveur.

Pour renvoyer tous les fichiers (si un fichier semble ne pas avoir été mis à jour) : onglet **Actions** → *Deploy o2switch (FTP)* → **Run workflow**, en cochant `full`.

### Configuration initiale

**Secrets GitHub** (*Settings → Environments → `production`*) :

| Secret | Contenu |
|---|---|
| `FTP_SERVER` | hôte FTP, par ex. `ftp.mondomaine.fr` |
| `FTP_USERNAME` | utilisateur FTP cPanel |
| `FTP_PASSWORD` | mot de passe FTP |
| `FTP_SERVER_DIR` | dossier du projet, relatif à la racine FTP (par ex. `voteo/`). Vide = racine du compte FTP |
| `ENV_PROD` | variables de production, au format `.env` (voir ci-dessous) |

```dotenv
APP_SECRET=une_chaine_aleatoire_longue
DATABASE_URL="mysql://user:pass@localhost:3306/base?serverVersion=10.11.2-MariaDB&charset=utf8mb4"
MAILER_DSN=smtp://user:pass@mail.mondomaine.fr:465
DEFAULT_URI=https://voteo.mondomaine.fr
TRANSACTIONAL_MAIL_SENDER=no-reply@mondomaine.fr
LEGAL_PUBLISHER_NAME="Association Exemple"
LEGAL_PUBLISHER_ADDRESS="1 rue de l'Exemple, 75000 Paris"
LEGAL_PUBLISHER_EMAIL=contact@mondomaine.fr
```

Les variables `LEGAL_*` alimentent les mentions légales et la politique de confidentialité (voir [Informations légales et RGPD](#informations-légales-et-rgpd)).

> [!WARNING]
> Le dossier cible ne doit contenir que Voteo : `--delete` y supprime tout ce qui n'est pas dans le dépôt. Si `FTP_SERVER_DIR` est vide, utilisez un compte FTP dédié dont le dossier est celui du projet. Les valeurs `/`, `~` et `public_html` sont refusées.

**Dans cPanel :**

- *Domaines* : faites pointer le domaine (ou sous-domaine) vers `voteo/public`.
- *Sélectionner une version de PHP* : PHP 8.3 ou plus, avec les extensions `intl` et `pdo_mysql`.
- *Bases de données MySQL* : créez la base et l'utilisateur (avec tous les privilèges sur la base) correspondant à `DATABASE_URL`. Ajustez `serverVersion` à la version de MariaDB affichée dans phpMyAdmin.
- *Tâches cron* : ajoutez l'envoi des résultats, par exemple toutes les 5 minutes, et la purge quotidienne des invitations expirées :

  ```cron
  */5 * * * * cd ~/voteo && php bin/console app:send-results --no-interaction
  30 3 * * * cd ~/voteo && php bin/console app:purge-invitations --no-interaction
  ```

Après le premier déploiement, depuis le *Terminal* cPanel :

```bash
cd ~/voteo
composer install --no-dev --optimize-autoloader
php bin/console doctrine:migrations:migrate --no-interaction
```

### À chaque mise en ligne

Lancer le workflow suffit dans la plupart des cas. Deux actions restent manuelles, depuis le terminal cPanel :

- **`composer.json` ou `composer.lock` a changé** (lors d'un déploiement automatique, le workflow affiche alors un avertissement) :

  ```bash
  cd ~/voteo && composer install --no-dev --optimize-autoloader
  ```

  N'utilisez pas `--classmap-authoritative` : les nouvelles classes de `src/` arrivent sans que composer soit relancé, et ne seraient pas trouvées.

- **Une nouvelle migration a été ajoutée** dans `migrations/` :

  ```bash
  cd ~/voteo && php bin/console doctrine:migrations:migrate --no-interaction
  ```

Si le FTP coupe en cours d'envoi (`ECONNRESET`, timeout), l'IP du runner a peut-être été bloquée par le pare-feu o2switch : vérifiez dans *Outil de déblocage d'IP* (cPanel), puis relancez le workflow.

## Informations légales et RGPD

Les pages [/mentions-legales](templates/legal/notice.html.twig) et [/confidentialite](templates/legal/privacy.html.twig) affichent des informations tirées de [config/packages/legal.yaml](config/packages/legal.yaml), elles-mêmes lues dans des variables d'environnement. Les valeurs par défaut sont dans `.env`. Modifiez-les dans `.env.local` en développement, et dans le secret `ENV_PROD` en production.

| Variable | Contenu |
|---|---|
| `LEGAL_SITE_NAME` | nom affiché du service |
| `LEGAL_PUBLISHER_NAME` | éditeur : nom ou raison sociale (**obligatoire**) |
| `LEGAL_PUBLISHER_STATUS` | forme juridique (association loi 1901, SAS…) |
| `LEGAL_PUBLISHER_ADDRESS` | adresse de l'éditeur (**obligatoire**) |
| `LEGAL_PUBLISHER_REGISTRATION` | SIRET, RCS, RNA… |
| `LEGAL_PUBLISHER_VAT` | n° de TVA intracommunautaire |
| `LEGAL_PUBLISHER_EMAIL` | email de contact (**obligatoire**) |
| `LEGAL_PUBLISHER_PHONE` | téléphone |
| `LEGAL_DIRECTOR` | directeur·rice de la publication (par défaut : l'éditeur) |
| `LEGAL_HOST_NAME`, `LEGAL_HOST_ADDRESS`, `LEGAL_HOST_PHONE`, `LEGAL_HOST_URL` | hébergeur (o2switch par défaut) |
| `LEGAL_PRIVACY_EMAIL` | contact pour les demandes RGPD (par défaut : email de l'éditeur) |
| `LEGAL_DPO_NAME` | délégué·e à la protection des données, s'il y en a un |
| `LEGAL_LOGS_RETENTION` | durée de conservation des journaux du serveur |
| `LEGAL_UPDATED_AT` | date de mise à jour de la politique (`AAAA-MM-JJ`) |

Une valeur vide masque la ligne. Si elle est obligatoire, la page affiche « à renseigner » à la place.

Fonctionnalités liées :

- **Inscription** : la case de consentement renvoie vers la politique, et sa date d'acceptation est enregistrée.
- **Mon compte** (`/compte`) : l'organisateur modifie ses informations, télécharge toutes ses données (JSON) et supprime son compte, avec tout son contenu.
- **Mes données** (`/mes-donnees/{lien}`) : chaque membre voit les données le concernant et peut les supprimer. Le lien figure au bas de chaque email, avec l'organisateur et la liste à l'origine de l'envoi.

## Structure du front

| Élément | Emplacement |
|---|---|
| Layout général (header, footer, messages) | [templates/base.html.twig](templates/base.html.twig) |
| Feuille de style | [public/css/app.css](public/css/app.css) |
| Thème des formulaires | [templates/form/voteo_theme.html.twig](templates/form/voteo_theme.html.twig) |
| Icônes SVG | [templates/partials/_icons.html.twig](templates/partials/_icons.html.twig) |
| Page d'accueil | [templates/default/index.html.twig](templates/default/index.html.twig) |

Les couleurs sont définies comme variables CSS en tête de `app.css` : modifiez-les pour adapter la charte.

## Documentation

1. [Options de build Docker](docs/options.md)

## Contribuer

Les contributions sont les bienvenues : signalez un bug ou proposez une amélioration via les [issues](https://github.com/janssens/voteo/issues), ou ouvrez directement une pull request.

## Licence

Voteo est distribué sous [licence MIT](LICENSE).
