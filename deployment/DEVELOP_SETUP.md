# Develop Setup

`public_html/develop` now uses the same application code as `public_html`, but switches behavior by host or `APP_ENV`.

## Hosting values

Set these values for `develop.denemeags.com`:

```apache
SetEnv APP_ENV develop
SetEnv APP_BASE_URL https://develop.denemeags.com
SetEnv DB_HOST localhost
SetEnv DB_NAME u120099295_deneme_develop
SetEnv DB_USER u120099295_deneme_develop
SetEnv DB_PASS change-me
```

Optional mail overrides:

```apache
SetEnv MAIL_FROM_ADDRESS noreply@denemeags.com
SetEnv MAIL_REPLY_TO support@denemeags.com
```

## Filesystem layout

- Private develop product files: `develop_uploads/products`
- Private develop videos: `develop_uploads/videos`
- Public develop uploads: `public_html/develop/uploads`
- Develop cache: `public_html/develop/cache`
- Develop temp: `public_html/develop/tmp`

## Media seeding

Initial seed already mirrors:

- `uploads/*` -> `develop_uploads/*`
- `public_html/uploads/*` -> `public_html/develop/uploads/*`

Repeat the same copy flow after future live media changes only if develop needs refreshed fixtures.

## Publish rule

Code changes move from `public_html/develop` testing back into `public_html`.
Do not copy:

- develop DB credentials
- develop uploads
- develop cache/tmp
- develop webhook logs

