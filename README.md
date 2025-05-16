# BookReviewAPI
REST API books and reviews management

# database
docker compose up -d

# http server
symfony serve:start -d

# JWT:
php bin/console lexik:jwt:generate-keypair

# password hasher
bin/console security:hash-password

# logging (todo: configure https, default token ttl=1h)
curl -X POST -H "Content-Type: application/json" http://localhost/login_check -d '{"username":"admin","password":"password"}'

# requests
curl -X GET -H "Content-Type: application/json" -H "Authorization: Bearer [jwt token]" http://localhost:8000/users
