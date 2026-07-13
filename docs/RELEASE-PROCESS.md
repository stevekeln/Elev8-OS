# Release Process

## 1. Select scope

Choose documented problems and opportunities for the release.

## 2. Create a branch

Example:

```text
release/5.0.0
```

## 3. Build and test

- Complete code changes
- Complete database migrations
- Update documentation
- Update changelog
- Test on staging
- Test all roles
- Verify backups and rollback

## 4. Review

Use a pull request and complete the pull-request checklist.

## 5. Merge

Merge approved code into `main`.

## 6. Tag

Create a release tag:

```text
v5.0.0
```

## 7. Package

Build a WordPress-installable ZIP containing only the plugin folder and required runtime files.

Do not include:

- Git metadata
- Development documents not needed at runtime
- Production secrets
- Test databases
- Private uploads

## 8. Install on production

- Back up files and database
- Install during a low-risk period
- Verify key pages immediately
- Monitor error logs
- Keep previous ZIP ready

## 9. Document

Publish release notes and record any known issues.
