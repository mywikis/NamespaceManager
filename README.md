# NamespaceManager

NamespaceManager 2.0 manages custom MediaWiki namespaces from a database-backed
Codex interface. It supports MediaWiki 1.43 and newer.

## Installation

1. Clone this repository into `extensions/NamespaceManager`.
2. Add `wfLoadExtension( 'NamespaceManager' );` to `LocalSettings.php`.
3. Run `php maintenance/run.php update`.
4. Visit `Special:ManageNamespaces` as a user with the
   `managenamespaces` right. The right is granted to `sysop` by default.

The update creates `namespacemanager_namespace` through MediaWiki's abstract
schema system on MySQL, PostgreSQL, or SQLite.

## Upgrading from 1.x

Version 2.0 no longer reads namespace definitions from JSON during requests.
After installing the new version and running `update.php`, import the old file:

```sh
php extensions/NamespaceManager/maintenance/convertNamespaceJson.php
```

The script uses the former `NamespaceManagerDataPath` setting by default,
including `$1` database-name substitution. Use `--file` to select another file:

```sh
php extensions/NamespaceManager/maintenance/convertNamespaceJson.php \
	--file=/path/to/namespaces.json
```

The script refuses to modify a non-empty table unless `--replace` is supplied.
Validate a file without writing it by adding `--dry-run`.

## Namespace definition format

The conversion script and Action API retain the 1.x JSON definition shape:

```json
[
	{
		"id": 3000,
		"name": "Property",
		"content": true,
		"visualeditor": false,
		"searchdefault": true,
		"talksearchdefault": false,
		"subpages": false,
		"talksubpages": true,
		"includable": true,
		"talkincludable": false,
		"aliases": [ "Properties" ],
		"talkaliases": [ "Properties talk" ],
		"editpermissions": [],
		"talkeditpermissions": [ "propertymanagers" ]
	}
]
```

`id` must be an unused even number from 3000 through 4998. `name` is required.
`talkname` is optional and defaults to the subject name followed by `_talk`.
Boolean fields default to `false`, except `includable` and `talkincludable`,
which default to `true`. Array fields default to empty arrays.

## Storage and caching

Each subject namespace occupies one typed database row. Alias and permission
lists are JSON-encoded within that row. The database is the sole runtime source
of truth.

Definitions are memoized within the request and cached through
`WANObjectCache`, including process caching, hot refresh, stampede protection,
and a cross-server check key. Saves replace the complete definition set in one
database transaction and invalidate both the check key and cached value.
Primary-database reads are used when regenerating the cache because replica lag
must not temporarily change title interpretation.

Saved definitions apply to subsequent requests.

## Action API

The `namespacemanager` action requires the `managenamespaces` right and POST
requests.

- `operation=get` returns the ordered definition list.
- `operation=save` accepts the list as JSON in `namespaces`, requires a CSRF
  token, validates the complete list, and atomically replaces all definitions.

The Codex interface uses this API rather than posting directly to the Special
Page.

## Development

Install development dependencies and run the static checks:

```sh
composer install
composer test
npm install
npm test
```

Run extension tests from a MediaWiki checkout:

```sh
vendor/bin/phpunit extensions/NamespaceManager/tests/phpunit
```

Run the browser-based end-to-end tests against a wiki that has the extension
installed:

```sh
npx playwright install --with-deps chromium
npm run test:e2e
```

See [tests/e2e/README.md](tests/e2e/README.md) for how the wiki under test is
created; the same suite runs on every pull request to `main`.
