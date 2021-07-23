.PHONY: test
test: | vendor
	docker run --rm -it -v $(PWD):/host -w /host php:8-alpine ./vendor/bin/phpunit test

vendor:
	docker run --rm -it -v $(PWD):/host -w /host composer:2 install
