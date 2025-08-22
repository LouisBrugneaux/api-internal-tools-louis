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
5. Documentation : http://localhost:8000/api/docs

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




