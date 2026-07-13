# Architecture Direction

## Current platform

- WordPress
- PHP
- MySQL/MariaDB
- Amelia
- WooCommerce
- Standard WordPress users and permissions

## Architectural goal

Elev8 OS should be modular. Each major capability should have a clear boundary so one feature can be changed without risking unrelated features.

Suggested modules:

```text
elev8-os/
├── elev8-os.php
├── includes/
│   ├── Core/
│   ├── Admin/
│   ├── Artists/
│   ├── Partnerships/
│   ├── Payouts/
│   ├── Referrals/
│   ├── Waitlist/
│   ├── CRM/
│   ├── Reporting/
│   ├── Development/
│   ├── Integrations/
│   │   ├── Amelia/
│   │   └── WooCommerce/
│   └── Support/
├── assets/
├── templates/
├── languages/
└── uninstall.php
```

The existing plugin does not have to be reorganized immediately. This is the target direction for the Founders Edition.

## Core rules

### Do not hard-code Amelia names or IDs

Store stable Amelia IDs internally and dynamically resolve display names.

### Treat Amelia as an external integration

All direct Amelia queries should live in an integration layer. The rest of Elev8 OS should ask the integration layer for normalized data.

### Do not rely on undocumented tables without safeguards

Amelia database structures may change. Integration code should:

- Check whether tables and columns exist
- Fail safely
- Log useful diagnostics
- Avoid fatal errors
- Provide fallbacks where reasonable

### Use WordPress capabilities

Sensitive pages must check explicit capabilities. Artist users should only see their own private data.

### Separate public and private data

Public artist profile data must be intentionally selected. Tax information, payout details, customer lists, and internal notes must never appear publicly.

### Version the database

Store:

- Plugin version
- Database schema version
- Migration history

Every schema change must have a forward migration and a documented rollback or recovery plan.

### Escape output and sanitize input

Use WordPress escaping and sanitization consistently:

- `esc_html`
- `esc_attr`
- `esc_url`
- `wp_kses_post`
- `sanitize_text_field`
- `sanitize_email`
- `absint`
- Nonces for every write action

### Avoid fatal errors

Optional integrations and cache tools must be checked before calling their functions.

## Proposed custom data areas

The exact table design will be finalized after the source audit.

Potential custom tables:

- `elev8_artist_profiles`
- `elev8_partnership_rules`
- `elev8_payouts`
- `elev8_referrals`
- `elev8_referral_events`
- `elev8_waitlists`
- `elev8_waitlist_entries`
- `elev8_customer_profiles`
- `elev8_customer_interests`
- `elev8_class_expenses`
- `elev8_opportunities`
- `elev8_problems`
- `elev8_bug_reports`
- `elev8_release_notes`
- `elev8_notifications`

WordPress post types may be appropriate for editorial content. Financial, transactional, and high-volume records should generally use dedicated tables.

## Logging

Elev8 OS should have a diagnostic log with:

- Timestamp
- Severity
- Module
- User ID
- Safe context
- Error message
- Plugin version

Sensitive customer or tax information must not be written into logs.
