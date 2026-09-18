.PHONY: test up down logs

test:
	bash tests/run-smoke.sh

up:
	docker compose up --build -d

down:
	docker compose down

logs:
	docker compose logs -f app db
