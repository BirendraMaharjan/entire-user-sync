# Entire User Sync

Lightweight WordPress plugin to synchronize user data between systems. This repository contains the plugin source, assets, and developer tooling.

## Quick checklist (do these first)
- Install PHP dependencies: `composer install`
- Install JS dependencies and build assets (if needed): `npm install && npm run build`
- Run code style checks: `vendor/bin/phpcs --standard=phpcs.xml.dist src/`
- Run unit tests: `vendor/bin/phpunit`
- Generate translation template: `vendor/bin/wp i18n make-pot . languages/entire-user-sync.pot`

## Installation (developer / local)
1. Clone this repository into `wp-content/plugins/entire-user-sync`.
2. From the plugin directory run:

```bash
composer install
npm install
npm run build
```

3. Activate the plugin from WordPress admin.

## Usage
- Configure the plugin under Settings → Entire User Sync (if present). See `templates/admin` for the admin page markup.
- Sync operations are exposed via the plugin API and a REST endpoint at `wp-json/eus/v1/` (see `src/Sync/Api.php`).

## Development
- Code style: PHPCS configured via `phpcs.xml.dist`. Use the WordPress standard. Run:

```bash
vendor/bin/phpcs --standard=phpcs.xml.dist --extensions=php src/
```

## Internationalization
- Textdomain: `entire-user-sync`.
- POT file: `languages/entire-user-sync.pot`.

## Security
- All nonces and capability checks must be verified for admin or AJAX endpoints. See `src/` for examples.
- Report security issues to the repository maintainer (see SECURITY.md or open an issue and mark it private).

## Contributing
- Follow the coding standards in `phpcs.xml.dist`.
- Add PHPDoc for all public classes and methods.

## Changelog
- See `readme.txt`

## License
This plugin is licensed under GPLv2 or later. See the `LICENSE` file in the repo.

