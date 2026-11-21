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
