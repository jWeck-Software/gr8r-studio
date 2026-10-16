# Symfony and PostgreSQL backend in `backend/` of this repo

The app was a browser-only prototype keeping all data in localStorage. We add a Symfony 8 backend with a PostgreSQL database in `backend/` of the same repository, so the API and the frontend that consumes it change together in one commit. PostgreSQL runs in Docker for development, tests and CI; SQLite was ruled out because its PHP driver is not available on the development machine and its JSON and locking behaviour differs from production.
