analyse:
	@./vendor/bin/phpstan analyse -c phpstan.neon --memory-limit 1G

test:
	@./vendor/bin/phpunit --configuration ./phpunit.xml

sniff:
	@./vendor/bin/phpcs --standard=./phpcs.xml

fix:
	@./vendor/bin/phpcbf --standard=./phpcs.xml

front:
	@npm run watch

lint-front:
	@npm run lint

ready:
	@echo "Installing composer dependencies..."
	@composer install --prefer-dist --no-ansi --no-interaction --no-progress --ignore-platform-reqs

	@echo "Installing npm dependencies..."
	@npm i

	@echo "Optimization..."
	@php artisan optimize

	@echo "Configuring .env variables..."
	@cp ~/.envs/.sahma_habibi_env /var/www/sahma-habibi/.env

	@echo "Setup completed!"

update:
	@echo "Updating with the latest git changes"
	@git pull

stop:
	@sudo supervisorctl stop sahma-habibi-renderer sahma-habibi-web sahma-habibi-worker
start:
	@sudo supervisorctl start sahma-habibi-renderer sahma-habibi-web sahma-habibi-worker

.PHONY: analyse test sniff fix front lint-front deps update