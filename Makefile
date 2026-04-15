.PHONY: init-submodules pull-submodules render-report-html

REFERENCE_SUBMODULES := \
	references/python-typing \
	references/fig-standards \
	references/mago \
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

render-report-html:
	php conformance/src/render-report-html.php
