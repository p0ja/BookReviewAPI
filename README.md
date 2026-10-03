# BookReviewAPI
REST API books and reviews management

See CHANGELOG.md for API changes and upgrade steps.

# local secrets (first run)
The committed .env holds defaults only; secrets go in .env.local, which git ignores. Create it once:
printf 'APP_SECRET=%s\nJWT_PASSPHRASE=%s\n' "$(openssl rand -hex 16)" "$(openssl rand -hex 32)" > .env.local

Then generate the JWT key pair (see JWT below), which is encrypted with that JWT_PASSPHRASE.

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
The keys (config/jwt/*.pem, not committed) must be encrypted with the JWT_PASSPHRASE from .env.local; after changing the passphrase, regenerate them (this invalidates every issued token) and restart the php container, which reads .env.local when it starts:
docker compose exec php bin/console lexik:jwt:generate-keypair --overwrite
docker compose restart php

# password hasher
bin/console security:hash-password

# register and log in (default token ttl=1h)
Anyone can create a ROLE_USER account (password at least 8 characters); it can log in straight away.
Each address may try 10 registrations per hour (config/packages/rate_limiter.yaml); after that /register answers 429 with Retry-After.
curl -X POST -H "Content-Type: application/json" https://localhost/register -d '{"email":"reader@example.com","password":"long enough"}'

Users are loaded from the users table by email. The fixtures create admin@example.com / admin (ROLE_ADMIN) and user@example.com / user (ROLE_USER).
After 5 failed logins for an email from one address within a minute, /login_check answers 429 until the minute is over.
curl -X POST -H "Content-Type: application/json" https://localhost/login_check -d '{"username":"user@example.com","password":"user"}'

# requests
# lists (/books, /reviews, /books/{id}/reviews, /authors, /authors/{id}/books) answer one page: {"items": [...], "total": 42, "page": 1, "size": 20}
# pagination and sorting are query parameters (page, size, orderBy); size defaults to 20 and is capped at 100; a malformed value answers 400
# books have average_rating (null without reviews) and review_count
curl -X GET -H "Authorization: Bearer [jwt token]" 'https://localhost/books?page=1&size=20&orderBy=title'

# filter books: title and author match part of the text, genre the whole genre (all case-insensitive), minRating the average rating
curl -X GET -H "Authorization: Bearer [jwt token]" 'https://localhost/books?title=clean&genre=software&author=martin&minRating=4'

# single book
curl -X GET -H "Authorization: Bearer [jwt token]" https://localhost/books/{id}

# reviews of a book (one page, same parameters as /reviews)
curl -X GET -H "Authorization: Bearer [jwt token]" 'https://localhost/books/{id}/reviews?page=1&size=20&orderBy=rating'

# list reviews (rating keeps only reviews with that rating), single review
curl -X GET -H "Authorization: Bearer [jwt token]" 'https://localhost/reviews?page=1&size=20&orderBy=rating&rating=5'
curl -X GET -H "Authorization: Bearer [jwt token]" https://localhost/reviews/{id}

# authors (name matches part of the name), single author, books of an author
curl -X GET -H "Authorization: Bearer [jwt token]" 'https://localhost/authors?name=martin&orderBy=name'
curl -X GET -H "Authorization: Bearer [jwt token]" https://localhost/authors/{id}
curl -X GET -H "Authorization: Bearer [jwt token]" https://localhost/authors/{id}/books

# create book (authors are matched by name, trimmed and in any letter case; an existing author keeps its info, which only admins change, through PUT/PATCH)
curl -v -X POST http://127.0.0.1:8000/books -H 'Authorization: Bearer [jwt token]' -H 'Content-Type: application/json' -d '{"title":"nowy title","isbn":"nowyIsbn0123","description":"book description","price":"123.14","genre":"PHP","publish_date":"2023-12-12","authors":[{"name":"author1 name and surname","info":"information about author"},{"name":"author2 name","info":"information about author"}]}'

# create review
curl -v -X POST http://127.0.0.1:8000/books/{id}/reviews -H 'Authorization: Bearer [jwt token]' -H 'Content-Type: application/json' -d '{"name":"reviewer name","content":"prosty nowy review content","rating":"3"}'

# update a book (admins only): PUT sets every field and replaces the authors, PATCH only the given fields (authors, when given, replace all of them)
curl -X PATCH https://localhost/books/{id} -H 'Authorization: Bearer [jwt token]' -H 'Content-Type: application/json' -d '{"price":"25.00"}'

# update a review (its author or an admin), same PUT/PATCH rules
curl -X PATCH https://localhost/reviews/{id} -H 'Authorization: Bearer [jwt token]' -H 'Content-Type: application/json' -d '{"rating":"4"}'

# update an author (admins only): missing fields keep their value, "info": "" clears it; a name another author has (any letter case) answers 409
curl -X PATCH https://localhost/authors/{id} -H 'Authorization: Bearer [jwt token]' -H 'Content-Type: application/json' -d '{"info":"Author of Clean Code"}'

# delete a book with its reviews (admins only) or a review (its author or an admin)
curl -X DELETE -H "Authorization: Bearer [jwt token]" https://localhost/books/{id}
curl -X DELETE -H "Authorization: Bearer [jwt token]" https://localhost/reviews/{id}

# permissions
Reviews belong to the user who posted them (user_id). Reviews from before that have no owner, so only admins can change them.
Browsers may call the API from localhost or 127.0.0.1 on any port (CORS); set CORS_ALLOW_ORIGIN (a regex) in .env.local for other origins.

# errors
Every error answers {"error": "..."} with the matching status: 401 (missing, invalid or expired token, wrong password), 403 (not allowed to change it), 404, 409 (an ISBN or email that is already taken), 422 (validation), 429 (too many failed logins or registrations).
POST /books answers 201 with a Location header pointing at the new book.
An invalid payload (422) also lists each problem: {"error": "isbn: This field is required.\nprice: ...", "violations": [{"field": "isbn", "message": "This field is required."}, ...]}.
A throttled login (429) carries Retry-After, like /register.

# caching
Every successful GET carries an ETag; send it back as If-None-Match and an unchanged resource answers 304 with no body. Responses are private (no-cache: revalidate each time), so a change is visible on the next request.
curl -i -H "Authorization: Bearer [jwt token]" -H 'If-None-Match: "[etag]"' https://localhost/books/{id}

# production image
The prod stage holds no secrets (.dockerignore keeps .env.local and config/jwt/*.pem out), so pass them at runtime and mount the keys. On start it waits for the database and runs the migrations.
docker build --target frankenphp_prod -t bookreviewapi:prod .
(The Dockerfile needs BuildKit: install the docker buildx plugin, or build through compose with a file that sets build.target: frankenphp_prod.)
docker run -d -p 443:443 -e SERVER_NAME=your.domain -e APP_SECRET=... -e JWT_PASSPHRASE=... -e DATABASE_URL=... -v /path/to/jwt:/app/config/jwt:ro bookreviewapi:prod
Behind a load balancer or reverse proxy, also pass -e SYMFONY_TRUSTED_PROXIES=<its addresses, or private_ranges>: otherwise every client has the proxy's address, and the per-address limits on /register and /login_check apply to all of them together.

# static analysis
PHPStan (level 6, config in phpstan.dist.neon) checks src and tests; CI runs it too:
composer phpstan

# tests
The API and repository tests use the test database (app_test, created and reset automatically), so the database service must be running.
docker compose exec php composer test

Any deprecation notice fails the run (failOnDeprecation in phpunit.dist.xml).

From the host, point the tests at the database port published by compose.override.yaml:
DATABASE_URL='postgresql://app:postgres@127.0.0.1:5432/app?serverVersion=16&charset=utf8' php bin/phpunit
