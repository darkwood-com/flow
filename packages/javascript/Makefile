##
##usage :
##-------

MAKE = make
NODE_ENV ?= local
NPM_BIN = ./node_modules/.bin

dev: ## dev
	NODE_ENV=development $(NPM_BIN)/nodemon

test: ## test
	NODE_ENV=test $(NPM_BIN)/jest --maxWorkers=1

pack: ## pack for publish
	npm pack

publish: ## publish to npm
	npm publish

# DEFAULT
.DEFAULT_GOAL := help
help:
	@grep -E '(^[a-zA-Z_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'

##
