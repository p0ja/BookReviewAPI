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

# api documentation
Swagger UI: https://localhost/api/doc (OpenAPI JSON: https://localhost/api/doc.json), public, no token needed to read it.
To try the endpoints, run POST /login_check from the page, press "Authorize" and paste the token.
Offline copy: php bin/console nelmio:apidoc:dump > openapi.json

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

# delete a book (with its reviews) or a review
curl -X DELETE -H "Authorization: Bearer [jwt token]" https://localhost/books/{id}
curl -X DELETE -H "Authorization: Bearer [jwt token]" https://localhost/reviews/{id}

# errors
Every error answers {"error": "..."} with the matching status: 401 (missing, invalid or expired token, wrong password), 404, 409 (POST /books with an ISBN that already exists), 422 (validation).
POST /books answers 201 with a Location header pointing at the new book.

# tests
The API and repository tests use the test database (app_test, created and reset automatically), so the database service must be running.
docker compose exec php bin/phpunit

From the host, point the tests at the database port published by compose.override.yaml:
DATABASE_URL='postgresql://app:postgres@127.0.0.1:5432/app?serverVersion=16&charset=utf8' php bin/phpunit

# todo:
## missing functionality
- update endpoints: PUT/PATCH /books/{id} and /reviews/{id} (only create and delete exist; POST /books with a known ISBN answers 409, so there is no way to change a book yet)
- single review endpoint: GET /reviews/{id}
- authors resource: GET /authors, GET /authors/{id} and the books of an author
- user registration (symfonycasts/verify-email-bundle is installed but unused)
- authorization: any logged-in user can delete any book or review; deletes should require ROLE_ADMIN, and reviews should belong to a user so only the owner can edit them
- pagination metadata (total, page, size) in /books and /reviews responses
- filtering and search: by title, genre, author and rating
- average rating and review count per book
- CORS, rate limiting on /login_check

## technical debt
- N+1 queries on /books and /reviews (authors and books are lazy-loaded per row)
- data model: price as decimal(10,2), publish_date as date, description as text, unique (book_id, author_id) in book_author
- CI: run the tests against PostgreSQL instead of SQLite, enable the disabled steps in .github/workflows/ci.yml, add PHPStan
- remove the stale phpunit.xml.dist (phpunit.dist.xml is the one in use)
- remove the unused #[Timestampable] attribute on Book::$createdAt (the Stof extensions bundle is not registered)
- move JWT_PASSPHRASE and APP_SECRET out of the committed .env files
