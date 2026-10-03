# Changelog

## Unreleased

### Breaking changes for API clients

- **Lists are pages.** `GET /books`, `/reviews`, `/books/{id}/reviews`, `/authors` and
  `/authors/{id}/books` answer `{"items": [...], "total": 42, "page": 1, "size": 20}` instead
  of a plain array, at most 100 rows per page. Read the rows from `items`. A book's reviews
  take the same `page`, `size`, `orderBy` and `rating` parameters as `/reviews`; an unknown
  book still gives an empty result.
- **Delete paths.** `DELETE /books/{id}` and `DELETE /reviews/{id}` replace
  `/book/delete/{id}` and `/review/delete/{id}`, which now answer 404.
- **Creating a book.** `POST /books` answers **201** (was 200) with a `Location` header.
  A book with an ISBN that already exists is **no longer updated**: the answer is 409.
  Change a book with `PUT` or `PATCH /books/{id}` instead.
- **Permissions.** Changing or deleting a book needs `ROLE_ADMIN` (403 otherwise).
  A review can be changed or deleted only by the user who posted it, or by an admin;
  reviews posted before this release have no owner, so only admins can change them.
- **Errors.** Every error is `{"error": "..."}`, including 401s from login and JWT checks,
  which were `{"code": 401, "message": "..."}`.
- **Book authors.** In a book's `authors`, `id` is now the author's id (usable with
  `/authors/{id}`); it was the id of the internal book-author link.
- **Validation.** `publish_date` must be a real date (`YYYY-MM-DD`; `""` clears it).
  `price` must be at most 99999999.99. `description` may be up to 10000 characters (was 255).
  Lengths and "not blank" are checked on the trimmed value, which is what is stored: a title
  of only spaces, an ISBN that is under 10 characters once trimmed, or a review of under 3
  characters once trimmed now answer 422.
- **Authors.** Authors are matched by name trimmed and in any letter case, so `"kent beck "`
  is the existing `Kent Beck`. `POST /books` links an existing author as it is: its `info`
  is no longer overwritten (or wiped when left out). Admins can still change an author's
  `info` by giving it in `PUT`/`PATCH /books/{id}`; leaving it out keeps it. Two requests
  creating the same new author at once: one answers 409, and a retry succeeds.

### Added

- `PUT`/`PATCH` for books and reviews, `GET /reviews/{id}`, `GET /authors`,
  `GET /authors/{id}`, `GET /authors/{id}/books`.
- `POST /register` (public): creates a `ROLE_USER` account.
- Filters: `title`, `genre`, `author`, `minRating` on `/books`; `rating` on `/reviews`;
  `name` on `/authors`.
- Books show `average_rating` and `review_count`; reviews show `user_id`.
- Swagger UI at `/api/doc` (public).
- CORS for `localhost`/`127.0.0.1` on any port (`CORS_ALLOW_ORIGIN`).
- Login throttling: 5 failed attempts per email and address per minute, then 429.
- Registration limit: 10 attempts per address per hour, then 429 with `Retry-After`.
- Conditional GET: successful `GET`s carry an `ETag` and `Cache-Control: private, no-cache`;
  send it back in `If-None-Match` and an unchanged resource answers `304` with no body.

### Changed

- Emails are case-insensitive: login matches any letter case, registration stores them
  in lower case and refuses an address already registered in another case.

### Upgrading a deployment

1. Put secrets in `.env.local` (or real environment variables): `.env` no longer holds
   `APP_SECRET` or `JWT_PASSPHRASE`, and `.env.dev` is gone. See the README.
2. Regenerate the JWT key pair with the new `JWT_PASSPHRASE`; tokens issued before stop working.
3. Rebuild the Docker image (`docker compose up -d --build`): the Mercure hub was removed, and
   the application now runs on Symfony 7.4 LTS (from 7.2), which fixes the security advisories
   open against 7.2; run `composer install` on deployments that do not build the image.
4. Run the migrations. One of them converts `price`, `publish_date` and `description`, and
   stops without changing anything if a price is above 99999999.99 or a publish date is
   not `YYYY-MM-DD`; fix those rows first. It also removes duplicate book-author links.
   Another renames the `book_id_id`/`author_id_id` columns of `review` and `book_author` to
   `book_id`/`author_id` in place; raw SQL against those tables must use the new names.
   A third adds `book.updated_at`, filled from `created_at` for existing books. A fourth
   trims author names and merges authors whose names differ only in case or spacing (the
   oldest is kept, with the first `info` found; book links move to it), then makes names
   unique in any letter case. The merge cannot be undone by migrating down.
5. The prod image no longer contains `.env.local`, the JWT keys, dev packages or tests
   (a `.dockerignore` was missing, so `COPY . ./` took the whole checkout). Pass `APP_SECRET`,
   `JWT_PASSPHRASE` and `DATABASE_URL` as environment variables and mount `config/jwt/`;
   see "production image" in the README.
   Behind a load balancer or reverse proxy, also set `SYMFONY_TRUSTED_PROXIES`, or the
   per-address limits on `/register` and `/login_check` apply to all clients together.
