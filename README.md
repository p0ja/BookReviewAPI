# BookReviewAPI
REST API books and reviews management

# docker
docker compose up -d

# xdebug
Xdebug is installed in the dev image and off by default. Start the stack with step debugging enabled:
XDEBUG_MODE=debug docker compose up -d

In PHPStorm listen on port 9003 and map the project root to /app (Settings -> PHP -> Servers, name: localhost).
Connection problems are logged to var/log/xdebug.log.

Docker Engine inside WSL2 with PHPStorm on Windows: host.docker.internal resolves to the WSL VM, not Windows, so pass the Windows host address (the WSL default gateway):
XDEBUG_MODE=debug XDEBUG_CLIENT_HOST=$(ip route show default | awk '{print $3}') docker compose up -d

# fixtures
symfony console doctrine:fixtures:load

# JWT:
php bin/console lexik:jwt:generate-keypair

# password hasher
bin/console security:hash-password

# logging (default token ttl=1h)
Users are loaded from the users table by email. The fixtures create admin@example.com / admin (ROLE_ADMIN) and user@example.com / user (ROLE_USER).
curl -X POST -H "Content-Type: application/json" https://localhost/login_check -d '{"username":"user@example.com","password":"user"}'

# requests
# list books - pagination and sorting are query parameters (page, size, orderBy)
# size defaults to 20 and is capped at 100; a malformed value answers 400
curl -X GET -H "Authorization: Bearer [jwt token]" 'https://localhost/books?page=1&size=20&orderBy=title'

# single book
curl -X GET -H "Authorization: Bearer [jwt token]" https://localhost/books/{id}

# reviews of a book
curl -X GET -H "Authorization: Bearer [jwt token]" https://localhost/books/{id}/reviews

# list reviews
curl -X GET -H "Authorization: Bearer [jwt token]" 'https://localhost/reviews?page=1&size=20&orderBy=rating'

# create book
curl -v -X POST http://127.0.0.1:8000/books -H 'Authorization: Bearer [jwt token]' -H 'Content-Type: application/json' -d '{"title":"nowy title","isbn":"nowyIsbn0123","description":"book description","price":"123.14","genre":"PHP","publish_date":"2023-12-12","authors":[{"name":"author1 name and surname","info":"information about author"},{"name":"author2 name","info":"information about author"}]}'

# create review
curl -v -X POST http://127.0.0.1:8000/books/{id}/reviews -H 'Authorization: Bearer [jwt token]' -H 'Content-Type: application/json' -d '{"name":"reviewer name","content":"prosty nowy review content","rating":"3"}'

# tests
The API and repository tests use the test database (app_test, created and reset automatically), so the database service must be running.
docker compose exec php bin/phpunit

From the host, point the tests at the database port published by compose.override.yaml:
DATABASE_URL='postgresql://app:postgres@127.0.0.1:5432/app?serverVersion=16&charset=utf8' php bin/phpunit