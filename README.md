# InternFeel

Real internship experiences from students who've been there. Peep before you leap.

This repo holds the custom WordPress theme, the `internfeel-core` plugin and the hardening must-use plugin for [internfeel.com](https://internfeel.com). WordPress core and third-party plugins are installed on the server, not kept here.

- [docs/plan.md](docs/plan.md): scope, decisions and schedule
- [docs/architecture.md](docs/architecture.md): how the site is built
- [docs/development.md](docs/development.md): local dev, deploys, CI and operations
- [CLAUDE.md](CLAUDE.md): conventions in one page

## Quick start

```bash
npm install
npm run playground
```

Then open http://127.0.0.1:9400 (already signed in as admin). See development.md section 3 for wp-env (Docker).
