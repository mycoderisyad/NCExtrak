APP_ID := ncextrak
VERSION ?= 0.1.3
PACKAGE_DIR := build
PACKAGE_NAME := $(APP_ID)-$(VERSION)

.PHONY: build clean lint format package

build:
	npm ci
	npm run build

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
		--exclude 'node_modules' \
		--exclude 'build' \
		--exclude 'tests' \
		./ $(PACKAGE_DIR)/$(APP_ID)/
	cd $(PACKAGE_DIR) && zip -r "$(PACKAGE_NAME).zip" "$(APP_ID)"
