.PHONY: init-submodules pull-submodules render-report-html serve update-tools install-intelephense install-phpy

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
	references/phan.wiki

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

pull-submodules: init-submodules
	git submodule update --remote --merge $(REFERENCE_SUBMODULES)

install-intelephense:
	cd vendor-bin/intelephense && npm install

install-phpy:
	cd vendor-bin/phpy && npm install
	find vendor-bin/phpy/node_modules -name 'devsense.php.ls' -exec chmod +x {} \;

render-report-html:
	php conformance/src/render-report-html.php

# Report which tracked tools have a newer release. `make update-tools APPLY=1`
# installs them and records the new releases; re-run the suite afterwards.
update-tools:
	php conformance/src/update-tools.php $(if $(APPLY),--apply,)

# Read the report locally the way it is published. Every page is rendered per
# request from the committed results, so this needs no build first.
serve:
	php -S localhost:8080 conformance/src/router.php
