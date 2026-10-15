# Buksza repository workflow

- The user requested publishing this project and subsequent completed changes to `https://github.com/broist/buksza.git`. Commit and push completed, checked work to this remote; do not force-push. Report any authentication or push failure accurately.
- Follow `webapp/AGENTS.md` for application changes, phase scope, mobile-first work and isolated testing.
- Regenerate the self-contained installer with `node scripts/package-installer.mjs` whenever packaged application or deployment source changes. Commit the generated `dist/buksza-telepito.sh`, `.sha256` and package file manifest together with their source.
- Never publish local databases, environment secrets, private keys, runtime files or generated QA screenshots/reports. `.env.example` is a blank configuration template and may be committed.
- Production data is under `/opt/buksza`; the server Git checkout is separate (`~/buksza-forras`). Updating Git does not deploy automatically: run the packaged installer with `--existing-nginx` after pulling. See `docs/TELEPITES-UBUNTU.md`.
