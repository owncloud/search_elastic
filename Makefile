SHELL := /bin/bash

COMPOSER_BIN := $(shell command -v composer 2> /dev/null)
ifndef COMPOSER_BIN
    $(error composer is not available on your system, please install composer)
endif


# bin file definitions
PHPUNIT=php -d zend.enable_gc=0  "$(PWD)/../../lib/composer/bin/phpunit"
PHPDBG=phpdbg -qrr -d memory_limit=4096M -d zend.enable_gc=0 "$(PWD)/../../lib/composer/bin/phpunit"
PHP_PARALLEL_LINT=php -d zend.enable_gc=0 vendor-bin/php-parallel-lint/vendor/bin/parallel-lint


app_name=$(notdir $(CURDIR))
project_directory=$(CURDIR)/../$(app_name)
build_tools_directory=$(CURDIR)/build/tools
appstore_package_name=$(CURDIR)/build/dist/$(app_name)
composer=$(shell which composer 2> /dev/null)

occ=$(CURDIR)/../../occ
private_key=$(HOME)/.owncloud/certificates/$(app_name).key
certificate=$(HOME)/.owncloud/certificates/$(app_name).crt
sign=php -f $(occ) integrity:sign-app --privateKey="$(private_key)" --certificate="$(certificate)"
sign_skip_msg="Skipping signing, either no key and certificate found in $(private_key) and $(certificate) or occ can not be found at $(occ)"
ifneq (,$(wildcard $(private_key)))
ifneq (,$(wildcard $(certificate)))
ifneq (,$(wildcard $(occ)))
	CAN_SIGN=true
endif
endif
endif

app_doc_files=README.md CHANGELOG.md
app_src_dirs=appinfo command controller css db hooks img jobs js lib search templates vendor
app_all_src=$(app_src_dirs) $(app_doc_files)
build_dir=build
dist_dir=$(build_dir)/dist

# internal aliases
composer_deps=vendor/
composer_dev_deps=lib/composer/phpunit

#
# Catch-all rules
#

.PHONY: all
all: $(composer_dev_deps)

.PHONY: clean
clean: clean-composer-deps clean-dist clean-build

#
# dist
#

$(dist_dir)/$(app_name): $(composer_deps)
	rm -Rf $@; mkdir -p $@
	cp -R $(app_all_src) $@
	find $@/vendor -type d -iname Test? -print | xargs rm -Rf
	find $@/vendor -name travis -print | xargs rm -Rf
	find $@/vendor -name doc -print | xargs rm -Rf
	find $@/vendor -iname \*.sh -delete
	find $@/vendor -iname \*.exe -delete

.PHONY: dist
dist: clean $(dist_dir)/$(app_name)
ifdef CAN_SIGN
	$(sign) --path="$(appstore_package_name)"
else
	@echo $(sign_skip_msg)
endif
	tar -czf $(appstore_package_name).tar.gz -C $(appstore_package_name)/../ $(app_name)

.PHONY: clean-dist
clean-dist:
	rm -Rf $(dist_dir)

.PHONY: clean-build
clean-build:
	rm -Rf $(build_dir)

##
## Dependency management
##--------------------------------------

.PHONY: install-php-deps
install-php-deps:          ## Install PHP dependencies
install-php-deps: $(composer_deps)

$(composer_deps): composer.json composer.lock
	$(COMPOSER_BIN) install --no-dev

$(composer_dev_deps): composer.json composer.lock
	$(COMPOSER_BIN) install --dev

.PHONY: clean-composer-deps
clean-composer-deps:
	rm -Rf $(composer_deps)

vendor/bamarni/composer-bin-plugin: $(composer_deps)

vendor-bin/php-parallel-lint/vendor: vendor/bamarni/composer-bin-plugin  vendor-bin/php-parallel-lint/composer.lock
	$(COMPOSER_BIN) bin php-parallel-lint install --no-progress

vendor-bin/php-parallel-lint/composer.lock: vendor-bin/php-parallel-lint/composer.json
	@echo php-parallel-lint composer.lock is not up to date.


##
## Tests
##--------------------------------------
.PHONY: test-php-lint
test-php-lint:             ## php linting
test-php-lint: vendor-bin/php-parallel-lint/vendor
	$(PHP_PARALLEL_LINT) --exclude vendor --exclude build --exclude vendor-bin .

.PHONY: test-php-unit
test-php-unit:
test-php-unit:             ## Run php unit tests
	$(PHPUNIT) --configuration ./tests/unit/phpunit.xml

.PHONY: test-php-unit-dbg
test-php-unit-dbg:
test-php-unit-dbg:         ## Run php unit tests with phpdbg
	$(PHPDBG) --configuration ./tests/unit/phpunit.xml
