# React + Vite

For the isolated administrator access, booking, availability, credential review and account recovery browser regressions, install backend Composer dependencies and frontend dependencies with `npm ci`, then run `npx playwright install chromium --only-shell` followed by `npm run test:e2e`. Linux runners can use `--with-deps` when installing Chromium. PHP with PDO SQLite must be on PATH. The runner creates fresh database/private-storage/mail directories and uses ports 4310, 8310, 8265 and 8266. The SMTP sink only accepts synthetic .test recipients. See [release verification](../docs/release-verification.md) and [credential verification](../docs/credentials-e2e-verification.md) for coverage and remaining staging gates. `npm test` runs the Vitest suite in `src`.

This template provides a minimal setup to get React working in Vite with HMR and some ESLint rules.

Currently, two official plugins are available:

- [@vitejs/plugin-react](https://github.com/vitejs/vite-plugin-react/blob/main/packages/plugin-react/README.md) uses [Babel](https://babeljs.io/) for Fast Refresh
- [@vitejs/plugin-react-swc](https://github.com/vitejs/vite-plugin-react-swc) uses [SWC](https://swc.rs/) for Fast Refresh
