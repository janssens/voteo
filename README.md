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
- Gestion des contacts et des listes de diffusion
- Résultats en direct : nombre de réponses, taux de participation, détail par option
- Espace d'administration pour les organisateurs (EasyAdmin)
- Interface en français, adaptée au mobile

## Stack technique

- [Symfony 7.4](https://symfony.com/) (PHP ≥ 8.3), Twig, Doctrine ORM
- [EasyAdmin](https://github.com/EasyCorp/EasyAdminBundle) pour l'espace organisateur
- PostgreSQL 16
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

### Envoi automatique des résultats (cron)

Quand l'option **Envoyer les résultats à la clôture** est cochée sur une question, les résultats sont envoyés par email à tous les destinataires une fois la date de clôture passée. L'envoi est fait par la commande `app:send-results`, à lancer régulièrement, par exemple toutes les 5 minutes depuis la crontab de l'hôte :

```cron
*/5 * * * * cd /chemin/vers/voteo && docker compose exec -T php bin/console app:send-results --no-interaction
```

Chaque question n'est envoyée qu'une seule fois. `--dry-run` liste les questions concernées sans rien envoyer.

## Mise en ligne (o2switch)

La production est hébergée sur un mutualisé o2switch (Apache + PHP). Chaque push sur `main` déclenche le workflow [deploy.yml](.github/workflows/deploy.yml), qui envoie le code par FTPS avec `lftp`.

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
DATABASE_URL="postgresql://user:pass@localhost:5432/base?serverVersion=16&charset=utf8"
MAILER_DSN=smtp://user:pass@mail.mondomaine.fr:465
DEFAULT_URI=https://voteo.mondomaine.fr
TRANSACTIONAL_MAIL_SENDER=no-reply@mondomaine.fr
```

> [!WARNING]
> Le dossier cible ne doit contenir que Voteo : `--delete` y supprime tout ce qui n'est pas dans le dépôt. Si `FTP_SERVER_DIR` est vide, utilisez un compte FTP dédié dont le dossier est celui du projet. Les valeurs `/`, `~` et `public_html` sont refusées.

**Dans cPanel :**

- *Domaines* : faites pointer le domaine (ou sous-domaine) vers `voteo/public`.
- *Sélectionner une version de PHP* : PHP 8.3 ou plus, avec les extensions `intl` et `pdo_pgsql`.
- *PostgreSQL* : créez la base et l'utilisateur correspondant à `DATABASE_URL`.
- *Tâches cron* : ajoutez l'envoi des résultats, par exemple toutes les 5 minutes :

  ```cron
  */5 * * * * cd ~/voteo && php bin/console app:send-results --no-interaction
  ```

Après le premier déploiement, depuis le *Terminal* cPanel :

```bash
cd ~/voteo
composer install --no-dev --optimize-autoloader
php bin/console doctrine:migrations:migrate --no-interaction
```

### À chaque mise en ligne

Le push sur `main` suffit dans la plupart des cas. Deux actions restent manuelles, depuis le terminal cPanel :

- **`composer.json` ou `composer.lock` a changé** (le workflow affiche alors un avertissement) :

  ```bash
  cd ~/voteo && composer install --no-dev --optimize-autoloader
  ```

  N'utilisez pas `--classmap-authoritative` : les nouvelles classes de `src/` arrivent sans que composer soit relancé, et ne seraient pas trouvées.

- **Une nouvelle migration a été ajoutée** dans `migrations/` :

  ```bash
  cd ~/voteo && php bin/console doctrine:migrations:migrate --no-interaction
  ```

Si le FTP coupe en cours d'envoi (`ECONNRESET`, timeout), l'IP du runner a peut-être été bloquée par le pare-feu o2switch : vérifiez dans *Outil de déblocage d'IP* (cPanel), puis relancez le workflow.

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
