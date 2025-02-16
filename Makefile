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
	@cp ~/.envs/.sahma_habibi_env /var/www/sahma-habibi/.env

	@echo "Installing ubuntu system packages..."
	@sudo apt-get update
	@sudo apt-get install ffmpeg zip poppler-utils libreoffice unoconv

	@echo "Setup completed!"

converter:
	@echo "Creating systemd service file: $(SERVICE_FILE)"
	@sudo mkdir -p $(SYSTEMD_DIR)
	@sudo echo "[Unit]" > $(SERVICE_FILE)
	@sudo echo "Description=Unoconv listener for document conversions" >> $(SERVICE_FILE)
	@sudo echo "Documentation=https://github.com/dagwieers/unoconv" >> $(SERVICE_FILE)
	@sudo echo "After=network.target remote-fs.target nss-lookup.target" >> $(SERVICE_FILE)
	@sudo echo "" >> $(SERVICE_FILE)
	@sudo echo "[Service]" >> $(SERVICE_FILE)
	@sudo echo "Type=simple" >> $(SERVICE_FILE)
	@sudo echo "ExecStart=$(UNOCONV_PATH) --listener" >> $(SERVICE_FILE)
	@sudo echo "Restart=always" >> $(SERVICE_FILE)
	@sudo echo "User=www-data" >> $(SERVICE_FILE)
	@sudo echo "Group=www-data" >> $(SERVICE_FILE)
	@sudo echo "" >> $(SERVICE_FILE)
	@sudo echo "[Install]" >> $(SERVICE_FILE)
	@sudo echo "WantedBy=multi-user.target" >> $(SERVICE_FILE)

	@echo "Reloading systemd daemon..."
	@sudo systemctl daemon-reload

	@echo "Enabling unoconv service..."
	@sudo systemctl enable unoconv.service

	@echo "Starting unoconv service..."
	@sudo systemctl start unoconv.service

	@echo "Checking unoconv service status..."
	@sudo systemctl status unoconv.service --no-pager


update:
	@echo "Updating with the latest git changes"
	@git pull

stop:
	@sudo supervisorctl stop sahma-habibi-renderer sahma-habibi-web sahma-habibi-worker
start:
	@sudo supervisorctl start sahma-habibi-renderer sahma-habibi-web sahma-habibi-worker

.PHONY: analyse test sniff fix front lint-front ready converter update stop start