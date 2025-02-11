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

.PHONY: analyse test sniff fix front