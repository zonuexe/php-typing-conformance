.PHONY: init-submodules pull-submodules render-report-html run-lsp-probes serve update-tools install-intelephense install-phpy install-devsense-php-ls install-php-lsp install-phpantom install-laravel-lsp install-laravel-corpus test-harness rescore

REFERENCE_SUBMODULES := \
	references/python-typing \
	references/fig-standards \
	references/mago \
	references/mir \
	references/intelephense.wiki \
	references/phpstan \
	references/psalm \
	references/phpDocumentor \
	references/noverify \
	references/phan.wiki \
	references/laravel-gate-image-board

init-submodules:
	git submodule update --init --filter=blob:none references/python-typing
	git submodule update --init --filter=blob:none --no-checkout references/fig-standards
	git -C references/fig-standards sparse-checkout init --cone
	git -C references/fig-standards sparse-checkout set accepted bylaws proposed
	git -C references/fig-standards checkout
	git submodule update --init --filter=blob:none --no-checkout references/mago
	git -C references/mago sparse-checkout init --cone
	git -C references/mago sparse-checkout set docs
	git -C references/mago checkout
	git submodule update --init --filter=blob:none references/mir
	git submodule update --init --filter=blob:none references/intelephense.wiki
	git submodule update --init --filter=blob:none --no-checkout references/phpstan
	git -C references/phpstan sparse-checkout init --cone
	git -C references/phpstan sparse-checkout set website/src
	git -C references/phpstan checkout
	git submodule update --init --filter=blob:none --no-checkout references/psalm
	git -C references/psalm sparse-checkout init --cone
	git -C references/psalm sparse-checkout set docs
	git -C references/psalm checkout
	git submodule update --init --filter=blob:none --no-checkout references/phpDocumentor
	git -C references/phpDocumentor sparse-checkout init --cone
	git -C references/phpDocumentor sparse-checkout set docs
	git -C references/phpDocumentor checkout
	git submodule update --init --filter=blob:none --no-checkout references/noverify
	git -C references/noverify sparse-checkout init --cone
	git -C references/noverify sparse-checkout set docs
	git -C references/noverify checkout
	git submodule update --init --filter=blob:none --no-checkout references/phan.wiki
	git -C references/phan.wiki sparse-checkout init --cone
	git -C references/phan.wiki sparse-checkout set scripts
	git -C references/phan.wiki checkout
	git submodule update --init --filter=blob:none references/laravel-gate-image-board

pull-submodules: init-submodules
	git submodule update --remote --merge $(REFERENCE_SUBMODULES)

install-intelephense:
	cd vendor-bin/intelephense && npm install

install-phpy:
	cd vendor-bin/phpy && npm install
	find vendor-bin/phpy/node_modules -name 'devsense.php.ls' -exec chmod +x {} \;

# The standalone DEVSENSE language server, versioned separately from the copy
# phpy bundles; probed over LSP by run-lsp-probes.
install-devsense-php-ls:
	cd vendor-bin/devsense-php-ls && npm install
	find vendor-bin/devsense-php-ls/node_modules -name 'devsense.php.ls' -exec chmod +x {} \;

# php-lsp and PHPantom ship only as per-platform binaries on GitHub releases
# (no Packagist/npm package), so they are fetched by tag. Keep the versions in
# step with conformance/data/releases.toml when bumping.
PHP_LSP_VERSION := 0.24.1
PHPANTOM_VERSION := 0.9.0
LSP_BIN_PLATFORM := aarch64-apple-darwin

install-php-lsp:
	mkdir -p vendor-bin/php-lsp/bin
	gh release download v$(PHP_LSP_VERSION) --repo jorgsowa/php-lsp \
		-p 'php-lsp-$(LSP_BIN_PLATFORM).tar.gz' -O - | tar xz -C vendor-bin/php-lsp/bin
	chmod +x vendor-bin/php-lsp/bin/php-lsp

install-phpantom:
	mkdir -p vendor-bin/phpantom/bin
	gh release download $(PHPANTOM_VERSION) --repo phpantom-dev/phpantom_lsp \
		-p 'phpantom_lsp-$(LSP_BIN_PLATFORM).tar.gz' -O - | tar xz -C vendor-bin/phpantom/bin
	chmod +x vendor-bin/phpantom/bin/phpantom_lsp

install-laravel-lsp:
	composer bin laravel-lsp install --no-interaction --no-progress

# Gate imageboard checkout: composer install so Laravel LSP can run
# `artisan tinker` for route/view/config probes. vendor/ stays gitignored
# inside the submodule.
install-laravel-corpus:
	cd references/laravel-gate-image-board && composer install --no-interaction --no-progress --prefer-dist

test-harness:
	php conformance/src/Expectation/self-test.php

# Re-derive Pass/Fail and recognition/enforcement from stored analyzer output
# without re-running the tools. Use after evaluator or marker changes.
rescore:
	php conformance/src/main.php --rescore

render-report-html:
	php conformance/src/render-report-html.php

# Launch every runnable language server headless, record what its initialize
# handshake advertises and how it answers the probes in conformance/lsp/,
# then re-render the report to publish the new measurements.
run-lsp-probes:
	php conformance/src/run-lsp-probes.php

# Report which tracked tools have a newer release. `make update-tools APPLY=1`
# installs them and records the new releases; re-run the suite afterwards.
update-tools:
	php conformance/src/update-tools.php $(if $(APPLY),--apply,)

# Read the report locally the way it is published. Every page is rendered per
# request from the committed results, so this needs no build first.
serve:
	php -S localhost:8080 conformance/src/router.php
