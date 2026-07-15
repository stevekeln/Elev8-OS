# Elev8 OS Release Process

## One-click build

1. Open GitHub Desktop.
2. Select the feature branch being tested.
3. Confirm **No local changes**.
4. Double-click `Build Elev8 OS Release.bat`.
5. Wait for **RELEASE BUILD PASSED**.
6. Upload the generated ZIP through WordPress.

## Output

Releases are stored outside the Git repository in `C:\GitHub\Elev8-OS-Releases\`. Each build creates a ZIP and a JSON manifest.

## Safety checks

The builder stops for uncommitted changes, missing required files, PHP syntax errors, forbidden files, missing software, invalid branches, or an invalid ZIP structure.

## Branching

`main` is production, `develop` is the complete working foundation, and every opportunity starts from `develop`.

Never package the plugin manually after installing this builder.
