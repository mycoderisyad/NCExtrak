# NCExtrak

NCExtrak is a Nextcloud app for extracting archives directly from the Files context menu.

## Features

- Right-click action: **Extract here**
- Formats: ZIP, TAR, GZ, BZ2, RAR, 7Z
- Extraction target: dedicated subfolder named after archive
- Hybrid execution:
  - files <= 50 MB run synchronously
  - files > 50 MB are queued in background jobs
- Notification on background completion/failure

## Runtime dependencies

### Required

- PHP 8.2+
- Nextcloud 30-33

### Optional for full format support

- `p7zip` / `p7zip-full` for 7Z extraction
- `unrar` binary or PHP `rar` extension for RAR extraction

## Configuration

Set these values in `config/config.php` to override defaults:

```php
'ncextrak.sync_size_limit' => 50 * 1024 * 1024,
'ncextrak.max_entries' => 100000,
'ncextrak.max_size' => 2 * 1024 * 1024 * 1024 * 1024,
'ncextrak.work_dir' => '/mnt/fast-disk/ncextrak-work',
'ncextrak.work_reserve' => 2 * 1024 * 1024 * 1024,
'ncextrak.expected_expansion_factor' => 2,
```

### Large archive guidance (10 GB to 100+ GB)

- Use async mode (default for files above `ncextrak.sync_size_limit`)
- Set `ncextrak.work_dir` to a disk with large free space
- Increase `ncextrak.max_size` to match your expected uncompressed output
- Keep `ncextrak.work_reserve` at least 2-10 GB to avoid disk full conditions
- Keep Nextcloud cron active because large extraction runs in background jobs

## Development

```bash
npm ci
composer install
npm run build
```

Lint and format:

```bash
npm run lint
composer run lint
npm run format
composer run format
```

## Manual installation on server

1. Download release zip from GitHub Releases.
2. Extract into `<nextcloud>/apps/ncextrak`.
3. Set ownership:
   - `chown -R www-data:www-data <nextcloud>/apps/ncextrak`
4. Enable app:
   - `sudo -u www-data php occ app:enable ncextrak`
5. Ensure Nextcloud cron is configured for background jobs.

## Packaging

Use:

```bash
make package VERSION=0.1.0
```

Output zip is generated in `build/`.
