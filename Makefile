UNOCONV_PATH=/usr/bin/unoconv
SYSTEMD_DIR=/etc/systemd/system
SERVICE_FILE=$(SYSTEMD_DIR)/unoconv.service

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

	@echo "Building frontend assets..."
	@npm run build

	@echo "Optimization..."
	@php artisan optimize

	@echo "Configuring .env variables..."
	@cp ~/.envs/.sahma_habibi_env ./.env

	@echo "Installing ubuntu system packages..."
	@sudo apt-get update
	@sudo apt-get install ffmpeg zip poppler-utils libreoffice unoconv

	@echo "Setup completed!"

update:
	@echo "Updating with the latest git changes"
	@git pull

stop:
	@sudo supervisorctl stop sahma-habibi-renderer sahma-habibi-web sahma-habibi-worker
start:
	@sudo supervisorctl start sahma-habibi-renderer sahma-habibi-web sahma-habibi-worker

.PHONY: analyse test sniff fix front lint-front ready converter update stop start

Database name: default

base-user:mwxkyijq9y8ebng%23z4cxt0y-@45e8917db84e4cd09a7e71142dbcc89d.db.arvandbaas.ir
