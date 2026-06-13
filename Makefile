.PHONY: help install test lint cs cs-fix phpstan analyse

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-15s\033[0m %s\n", $$1, $$2}'

install: ## Download tools (phpstan, phpunit, phpcs, php-cs-fixer)
	@mkdir -p tools
	@test -f tools/phpstan.phar || curl -sSL https://phar.phpunit.de/phpstan.phar -o tools/phpstan.phar && chmod +x tools/phpstan.phar
	@test -f tools/phpunit.phar || curl -sSL https://phar.phpunit.de/phpunit-10.phar -o tools/phpunit.phar && chmod +x tools/phpunit.phar
	@test -f tools/phpcs || curl -sSL https://squizlabs.github.io/PHP_CodeSniffer/phpcs.phar -o tools/phpcs && chmod +x tools/phpcs
	@test -f tools/phpcbf || curl -sSL https://squizlabs.github.io/PHP_CodeSniffer/phpcbf.phar -o tools/phpcbf && chmod +x tools/phpcbf
	@test -f tools/php-cs-fixer.phar || curl -sSL https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/releases/latest/download/php-cs-fixer.phar -o tools/php-cs-fixer.phar && chmod +x tools/php-cs-fixer.phar
	@echo "Tools installed in tools/"

test: ## Run PHPUnit tests
	php tools/phpunit.phar tests/unit/

test-all: ## Run all tests (unit + integration)
	php tools/phpunit.phar

lint: ## Check PHP syntax on all files
	@find include/ plugins/ setup/ html/ -name '*.php' -o -name '*.inc' | xargs -I{} php -l {} 2>&1 | grep -v "No syntax errors" || echo "All files OK"

cs: ## Check coding style (dry-run)
	php tools/php-cs-fixer.phar fix --dry-run --diff

cs-fix: ## Auto-fix coding style
	php tools/php-cs-fixer.phar fix

phpstan: ## Run PHPStan static analysis
	php tools/phpstan.phar analyse

analyse: lint cs phpstan test ## Run all checks (lint + cs + phpstan + tests)
