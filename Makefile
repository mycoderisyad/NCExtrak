APP_ID := ncextrak
VERSION ?= 0.1.4
PACKAGE_DIR := build
PACKAGE_NAME := $(APP_ID)-$(VERSION)
PACKAGE_FILE := $(PACKAGE_NAME).tar.gz

.PHONY: build clean lint format package

build:
	npm ci
	npm run build
	composer install --no-dev --optimize-autoloader

lint:
	npm run lint
	composer run lint

format:
	npm run format
	composer run format

clean:
	rm -rf $(PACKAGE_DIR)
	rm -rf js

package: clean build
	mkdir -p $(PACKAGE_DIR)/$(APP_ID)
	rsync -a --delete \
		--exclude '.git' \
		--exclude '.github' \
		--exclude '.agents' \
		--exclude '.gitignore' \
		--exclude '.php-cs-fixer.cache' \
		--exclude '.php-cs-fixer.dist.php' \
		--exclude '.prettierrc.json' \
		--exclude 'eslint.config.mjs' \
		--exclude 'Makefile' \
		--exclude 'node_modules' \
		--exclude 'package-lock.json' \
		--exclude 'package.json' \
		--exclude 'phpstan.neon' \
		--exclude 'RELEASE.md' \
		--exclude 'scripts' \
		--exclude 'skills-lock.json' \
		--exclude 'src' \
		--exclude 'build' \
		--exclude 'tests' \
		--exclude 'tsconfig.json' \
		--exclude 'vendor' \
		--exclude 'vite.config.js' \
		./ $(PACKAGE_DIR)/$(APP_ID)/
	cd $(PACKAGE_DIR) && tar -czf "$(PACKAGE_FILE)" "$(APP_ID)"
