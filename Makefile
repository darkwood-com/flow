##
##Dev
##------------

dev: ## Start dev server
	composer dev

docs-serve: ## Start documentation server locally
	composer docs-serve

editorconfig-fixer: ## Fixes text files based on given .editorconfig declarations
	composer editorconfig-fixer

infection: ## Run Infection
	composer infection

php-cs-fixer: ## Check and fix coding styles using PHP CS Fixer
	composer php-cs-fixer

phan: ## Run Phan
	composer phan

phpcbf: ## Clean code with PHP Code Beautifier and Fixer
	composer phpcbf

phpcs: ## Run PHP Code Sniffer
	composer phpcs

phpmd: ## Run PHP Mess Detector
	composer phpmd

phpstan: ## Execute PHPStan analysis
	composer phpstan

phpunit: ## Launch PHPUnit test suite
	composer phpunit

psalm: ## Run Psalm
	composer psalm

# DEFAULT
.DEFAULT_GOAL := help
help:
	@grep -E '(^[a-zA-Z_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'
.PHONY: help

##
