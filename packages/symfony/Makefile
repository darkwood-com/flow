EXEC_PHP        = php -d memory_limit=-1
COMPOSER        = composer

##
##Dev
##-------------

cs-fix: ## Check and fix coding styles using PHP CS Fixer
	composer cs-fix

phpqa: ## Execute PHQA toolsuite analysis
	composer phpqa

phpstan: ## Execute PHPStan analysis
	composer phpstan

psalm: ## Execute Psalm analysis
	composer psalm

test: ## Launch PHPUnit test suite
	composer test

# DEFAULT
.DEFAULT_GOAL := help
help:
	@grep -E '(^[a-zA-Z_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'
.PHONY: help

##
