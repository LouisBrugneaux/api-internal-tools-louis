# Internal Tools API

## Technologies
- Langage: PHP 8.2
- Framework: Symfony 6
- Base de données: PostgreSQL 16
- Port API: 8000 (configurable dans `.env`)

## Quick Start

1. docker-compose --profile postgres up -d

2. Installer les dépendances PHP :
   composer install
3. Démarrer le serveur Symfony :
   symfony serve -d
4. API disponible sur http://localhost:8000
5. Documentation : http://localhost:8000/api/docs (je n'ai finalement pas réussi à créer de Swagger et je n'ai pas eu le temps de le faire)

## Configuration
- Variables d'environnement: voir .env
- Configuration DB: DATABASE_URL="postgresql://postgres:postgres_mdp@127.0.0.1:5432/postgres?serverVersion=17&charset=utf8"

## Tests
php bin/phpunit - Tests unitaires + intégration

## Architecture
- Symfony + Doctrine : Pour faciliter la gestion des entités et les relations entre entités.
- PostgreSQL : base relationnelle puissante et fiable.
- Structure projet :
  - src/
    - Controller/   - Endpoints
    - Entity/       - Entités Doctrine
    - Repository/   - Requêtes de base de données
  - config/
    - packages/     - Config Symfony (Doctrine, API Docs, etc.)

## Analytics

Le but de cette partie est d'ajouter des endpoints analytics permettant d'avoir une vue globale des coûts de l'entreprise :

- **Par département :**  obtenir le total des coûts, le nombre d'utilisateurs et le nombre d'outils par département.
- **Les outils les plus coûteux :** identifier les outils qui génèrent le plus de dépenses avec un indicateur d'efficacité en comparant à la moyenne de l'entreprise.
- **Par catégorie :** connaître la répartition des outils, des coûts et des utilisateurs dans les différentes catégories.
- **Les outils peu utilisés :** lister les outils qui ont peu ou pas d'utilisateurs et faire une estimation des économies si l'outil est supprimé.
- **Par fournisseur :** savoir combien d'outils viennent de chaque fournisseur, combien ils coûtent et calculer leur efficacité moyenne. 