# Partner Link Monitor

**Location:** Marketing > Partner Links (admin only)

## What it does

Monitors partner/affiliate websites to verify they still link back to screenplayreaders.com. Each partner site is checked on a configurable interval. The system fetches the partner's page HTML, parses all `<a>` tags, and records any that point to `screenplayreaders.com`.

A site is **UP** if at least one backlink is found; **DOWN** if the page returns an error or contains no backlinks.

## Per-site settings

| Field | Purpose |
|-------|---------|
| Name | Display label |
| URL | The page to crawl for backlinks |
| Check Interval (minutes) | How often to re-check (min 5, max 43200) |
| Active | Toggle monitoring on/off |
| Notes | Free-text notes |
| Coupon Code | Optional WooCommerce coupon tied to this partner |
| Coupon Discount Type | `percent` or `fixed_cart` |
| Coupon Amount | Discount value |
| Uptime Threshold (%) | If set, coupon enabled/disabled based on rolling uptime; if blank, coupon follows each individual check |

## Automatic coupon management

If a partner site has a **Coupon Code** assigned, the monitor automatically enables or disables that coupon in WooCommerce after every check:

- **With uptime threshold set** (e.g. 80%): looks at the last 20 checks. If uptime % >= threshold, coupon stays published. If it drops below, coupon is set to draft (disabled).
- **Without uptime threshold**: coupon is published when the check finds a backlink, drafted when it doesn't.

When you save a partner site with a coupon code, the portal also syncs the discount type, amount, and `individual_use: false` to WooCommerce (creates the coupon if it doesn't exist yet).

## What each check records

- Timestamp
- UP/DOWN status
- HTTP status code
- Response time (ms)
- All backlinks found (href, anchor text, rel attributes)
- Dofollow vs nofollow/sponsored/ugc classification
- Error message (if any)

## Scheduling

The artisan command `marketing:check-partner-links` runs every 5 minutes via Laravel's scheduler. It only processes sites where `next_check_at` has passed — so a site with a 1440-minute (daily) interval won't be re-checked until the next day even though the command runs every 5 minutes.

Manual check: click the **Check Now** button on any site to run an immediate check (AJAX, doesn't affect the schedule timer).

You can also run it from the CLI:
```
php artisan marketing:check-partner-links              # all due sites
php artisan marketing:check-partner-links --site=3     # one specific site
```

## Key files

| File | Role |
|------|------|
| `app/Http/Controllers/Marketing/PartnerSiteController.php` | CRUD, check runner, coupon sync, link extraction |
| `app/Models/PartnerSite.php` | Site config + uptime calculation |
| `app/Models/PartnerLinkCheck.php` | Individual check record |
| `app/Console/Commands/CheckPartnerLinks.php` | Scheduled artisan command |
| `resources/views/marketing/partner-sites/index.blade.php` | List view |
| `resources/views/marketing/partner-sites/_form.blade.php` | Add/edit form partial |
