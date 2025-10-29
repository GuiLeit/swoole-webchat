# Setting Up GitHub Actions for Pull Request Validation

This repository includes automated testing via GitHub Actions that will run before allowing pull requests to be merged.

## What's Configured

- **Triggers**: Tests run on:
  - Push to `main` or `develop` branches
  - All pull requests targeting `main` or `develop`
  
- **Test Environment**:
  - PHP 8.4 with Redis and Swoole extensions
  - Redis 7 service for integration tests
  - Composer dependency caching for faster builds
  - Code coverage reporting

## Branch Protection Setup

To require tests to pass before merging, set up branch protection rules:

### 1. Go to Repository Settings
- Navigate to your repository on GitHub
- Click **Settings** → **Branches**

### 2. Add Branch Protection Rule
- Click **Add rule**
- Branch name pattern: `main` (and/or `develop`)
- Check the following options:
  - ✅ **Require status checks to pass before merging**
  - ✅ **Require branches to be up to date before merging**
  - ✅ **CI** (this will appear after the first workflow run)
  - ✅ **Require pull request reviews before merging** (recommended)
  - ✅ **Dismiss stale pull request approvals when new commits are pushed**

### 3. Additional Recommendations
- ✅ **Restrict pushes that create files** (prevents large files)
- ✅ **Do not allow bypassing the above settings** (applies to admins too)

## Local Testing

Before pushing, you can run tests locally:

```bash
# Run all tests
composer test

# Or directly with PHPUnit
vendor/bin/phpunit

# Run specific test suite
vendor/bin/phpunit --testsuite=Unit
vendor/bin/phpunit --testsuite=Integration
```

## Workflow Features

- **Fast builds**: Dependencies are cached between runs
- **Service dependencies**: Redis is available for integration tests
- **Code coverage**: Generated and can be uploaded to Codecov
- **Environment variables**: Tests run with proper Redis configuration
- **Validation**: Composer files are validated for integrity

## Troubleshooting

If tests fail in CI but pass locally:
1. Check that your local environment matches the CI environment (PHP 8.2, Redis 7)
2. Ensure all dependencies are committed in `composer.lock`
3. Verify environment variables are properly set in tests

## Coverage Reporting

The workflow generates coverage reports that can be:
- Viewed as HTML in the `coverage/` directory locally
- Uploaded to services like Codecov for tracking
- Used to ensure code quality standards