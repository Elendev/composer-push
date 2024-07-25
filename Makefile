test:
	act --detect-event -W .github/workflows/pull-request.yml

lint:
	act --detect-event -W .github/workflows/lint.yml

cron:
	act --detect-event -W .github/workflows/cron.yml

coverage-reporting:
	act --detect-event -W .github/workflows/coverage-reporting.yml