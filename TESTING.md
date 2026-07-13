# Testing Standards

No update should go directly from development to the live site.

## Required environments

### Development

Where code is changed.

### Staging

A copy of the site used to test the release with real-like data.

### Production

The live Elev8 Arts site.

## Release gates

A release is not ready until:

- [ ] Plugin activates without errors
- [ ] Existing data remains intact
- [ ] Database migrations complete
- [ ] Admin pages load
- [ ] Artist profile saves
- [ ] Public artist page loads
- [ ] Private artist data remains private
- [ ] Partnership calculations match expected examples
- [ ] Payout totals match source data
- [ ] Booking buttons go to the correct destination
- [ ] Social links work
- [ ] Contact phone opens a call action
- [ ] Contact email opens an email action
- [ ] Payment links work
- [ ] Referral links retain attribution
- [ ] QR code opens the intended URL
- [ ] Amelia missing or changed tables do not cause a fatal error
- [ ] WooCommerce missing or disabled does not cause a fatal error
- [ ] Mobile layout is usable
- [ ] Administrator can recover from invalid data
- [ ] Error logs contain no new critical errors

## Financial test cases

Every partnership model must have documented examples.

Example:

```text
Gross net revenue: $280
Elev8 receives 40% until Elev8 has received $100
Revenue after that point: $30
Elev8 receives 15% of that $30 = $4.50
Total Elev8 share = $104.50
Artist share = $175.50
```

Rounding rules must be documented and consistent.

## Permission tests

Test as:

- Administrator
- Manager
- Connected artist
- Different artist
- Logged-out visitor

Confirm that one artist cannot access another artist's private information.

## Regression tests

Every bug fixed should result in a test case so the same bug does not return later.

## Backup and rollback

Before installing a release on production:

- Back up files
- Back up database
- Record current plugin version
- Keep the previous plugin ZIP
- Document how to restore it
