# End-to-end tests

These [Playwright](https://playwright.dev/) tests drive a real wiki through the
browser: they log in as a sysop, open `Special:ManageNamespaces`, create
namespaces with different combinations of settings, and then verify that the
wiki behaves accordingly (namespace names and aliases resolve, content and
subpage flags are applied, search defaults change, transclusion is blocked, and
required edit rights are enforced).

The suite runs automatically on every pull request that targets `main`, in the
[`e2e` workflow](../../.github/workflows/e2e.yml). The workflow builds a
[Canasta](https://canasta.wiki/) MediaWiki instance with the Canasta CLI,
installs this extension into it, and then runs these tests against it.

## Running the tests locally

1. Create a Canasta wiki and install the extension into it:

   ```sh
   curl -fsSL https://get.canasta.wiki | bash -s -- --docker
   printf 'HTTP_PORT=8080\nHTTPS_PORT=8443\nCADDY_AUTO_HTTPS=off\n' > canasta.env
   canasta create --id namespacemanager-e2e --wiki main \
       --domain-name localhost --envfile canasta.env \
       --admin Admin --password "<password>"
   cp -r /path/to/NamespaceManager namespacemanager-e2e/extensions/NamespaceManager
   printf '<?php\nwfLoadExtension( "NamespaceManager" );\n' \
       > namespacemanager-e2e/config/settings/global/50-NamespaceManager.php
   canasta restart --id namespacemanager-e2e
   canasta maintenance update --id namespacemanager-e2e --wiki main
   ```

2. Install the browser and run the tests:

   ```sh
   npm ci
   npx playwright install --with-deps chromium
   E2E_ADMIN_PASSWORD="<password>" npm run test:e2e
   ```

## Configuration

The tests are configured through environment variables:

| Variable | Default | Description |
| --- | --- | --- |
| `E2E_BASE_URL` | `http://localhost:8080` | Base URL of the wiki under test. |
| `E2E_ARTICLE_PATH` | `/wiki/$1` | Article path of the wiki. |
| `E2E_SCRIPT_PATH` | `/w` | Script path of the wiki. |
| `E2E_ADMIN_USER` | `Admin` | Sysop account used by the tests. |
| `E2E_ADMIN_PASSWORD` | `CanastaE2EPassword1` | Password of that account. |

The tests replace the whole namespace configuration of the wiki, so they run
serially and reset the configuration before and after each test. Do not point
them at a production wiki.
