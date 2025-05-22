# BookReviewAPI
REST API books and reviews management

# docker
docker compose up -d

# fixtures
symfony console doctrine:fixtures:load

# JWT:
php bin/console lexik:jwt:generate-keypair

# password hasher
bin/console security:hash-password

# logging (default token ttl=1h)
curl -X POST -H "Content-Type: application/json" https://localhost/login_check -d '{"username":"username","password":"password"}'

# requests
curl -X GET -H "Content-Type: application/json" -H "Authorization: Bearer [jwt token]" https://localhost/books

# create book
curl -v -X POST http://127.0.0.1:8000/books -H 'Authorization: Bearer [jwt token]' -H 'Content-Type: application/json' -d '{"title":"nowy title","isbn":"nowyIsbn0123","description":"book description","price":"123.14","genre":"PHP","publish_date":"2023-12-12","authors":[{"name":"author1 name and surname","info":"information about author"},{"name":"author2 name","info":"information about author"}]}'

# create review
curl -v -X POST http://127.0.0.1:8000/books/{id}/reviews -H 'Authorization: Bearer [jwt token]' -H 'Content-Type: application/json' -d '{"name":"reviewer name","content":"prosty nowy review content","rating":"3"}'

# todo:
- xdebug
- unit tests
- replace of in_memory_users