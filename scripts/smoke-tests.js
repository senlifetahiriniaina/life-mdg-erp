#!/usr/bin/env node
//
// Tests de fumée post-déploiement — appelés par le job `post-deploy-tests` de
// `.github/workflows/deploy.yml`. Vérifie que l'application répond
// correctement juste après un déploiement, avant de considérer le déploiement
// réussi (sinon `rollback` se déclenche).
//
// Variables d'environnement attendues (déjà fournies par deploy.yml) :
//   BASE_URL   — ex. https://erp.example.com
//   API_TOKEN  — bearer token pour /api/metrics (secrets.SMOKE_TEST_TOKEN)

const axios = require('axios');

const BASE_URL = process.env.BASE_URL;
const API_TOKEN = process.env.API_TOKEN;

if (!BASE_URL) {
  console.error('[smoke-tests] BASE_URL manquant.');
  process.exit(1);
}

const checks = [
  {
    name: 'GET /api/health répond 200',
    run: async () => {
      const res = await axios.get(`${BASE_URL}/api/health`, { timeout: 10_000 });
      if (res.status !== 200) {
        throw new Error(`statut HTTP ${res.status}`);
      }
    },
  },
  {
    name: 'GET / (page d\'accueil) répond 200',
    run: async () => {
      const res = await axios.get(BASE_URL, { timeout: 10_000, maxRedirects: 5 });
      if (res.status !== 200) {
        throw new Error(`statut HTTP ${res.status}`);
      }
    },
  },
  {
    name: 'GET /api/metrics répond 200 (authentifié)',
    run: async () => {
      if (!API_TOKEN) {
        console.log('  (SMOKE_TEST_TOKEN absent — vérification ignorée)');
        return;
      }
      const res = await axios.get(`${BASE_URL}/api/metrics`, {
        timeout: 10_000,
        headers: { Authorization: `Bearer ${API_TOKEN}` },
      });
      if (res.status !== 200) {
        throw new Error(`statut HTTP ${res.status}`);
      }
    },
  },
];

(async () => {
  let failed = 0;
  for (const check of checks) {
    process.stdout.write(`[smoke-tests] ${check.name} ... `);
    try {
      await check.run();
      console.log('OK');
    } catch (err) {
      console.log('ÉCHEC');
      console.error(`  ${err.message}`);
      failed++;
    }
  }

  if (failed > 0) {
    console.error(`\n[smoke-tests] ${failed}/${checks.length} vérification(s) en échec.`);
    process.exit(1);
  }

  console.log(`\n[smoke-tests] ${checks.length}/${checks.length} vérifications passées.`);
})();
