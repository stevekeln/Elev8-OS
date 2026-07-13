# Security Policy

## Private development

Keep this repository private until the founders intentionally choose otherwise.

## Sensitive information

Never commit:

- WordPress passwords
- Database credentials
- API keys
- Customer data exports
- Tax documents
- Payment information
- Production backups
- `.env` files containing secrets
- `wp-config.php`

## Reporting a security issue

For now, security concerns should be reported privately to the Elev8 OS founders and should not be posted in a public issue.

## WordPress security expectations

- Use nonces on all write operations
- Check capabilities
- Sanitize input
- Escape output
- Use prepared SQL statements
- Do not expose private artist or customer records
- Avoid logging sensitive data
