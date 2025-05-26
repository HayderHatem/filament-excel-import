# Continuous Integration & Deployment

This document describes the CI/CD setup for the Filament Excel Import package.

## 🚀 GitHub Actions Workflows

The package includes a comprehensive GitHub Actions workflow that runs on every push and pull request.

### Workflow Jobs

#### 1. **Test Matrix** (`test`)
- **PHP Versions**: 8.1, 8.2, 8.3
- **Laravel Versions**: 10.x, 11.x
- **Dependency Versions**: prefer-lowest, prefer-stable
- **Features**:
  - Composer dependency caching
  - PHPUnit test execution
  - Code coverage reporting
  - Codecov integration

#### 2. **Code Style** (`code-style`)
- **Tool**: PHP CS Fixer
- **Standards**: PSR-12, PHP 8.1 Migration
- **Features**:
  - Automatic code style checking
  - Detailed diff output on failures
  - Graceful handling if not configured

#### 3. **Static Analysis** (`static-analysis`)
- **Tool**: PHPStan (Level 8)
- **Features**:
  - Type checking and static analysis
  - Memory limit optimization (2GB)
  - Configuration file support

#### 4. **Security Audit** (`security`)
- **Tool**: Composer audit
- **Features**:
  - Dependency vulnerability scanning
  - Security advisory checking

#### 5. **Package Test Runner** (`package-test`)
- **Tool**: Custom test runner
- **Features**:
  - Excel file creation testing
  - Multi-sheet support verification
  - Performance benchmarking

## 🛠️ Local Development

### Prerequisites

```bash
# Install dependencies
composer install

# Install development tools
composer require --dev friendsofphp/php-cs-fixer phpstan/phpstan
```

### Available Scripts

```bash
# Run all tests
composer test

# Run tests with coverage
composer test-coverage

# Run package test runner
composer test-runner

# Fix code style
composer cs-fix

# Check code style (dry run)
composer cs-check

# Run static analysis
composer phpstan

# Run full CI pipeline locally
composer ci
```

### Manual Commands

```bash
# PHPUnit with specific options
vendor/bin/phpunit --testdox --verbose

# PHP CS Fixer with custom config
vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php

# PHPStan with custom config
vendor/bin/phpstan analyse --configuration=phpstan.neon

# Security audit
composer audit
```

## 📊 Code Coverage

### Codecov Integration

The workflow automatically uploads coverage reports to Codecov:

- **Token**: Stored in GitHub Secrets as `CODECOV_TOKEN`
- **Format**: Clover XML
- **Reports**: Available at codecov.io

### Local Coverage

```bash
# Generate HTML coverage report
composer test-coverage

# View coverage report
open coverage/index.html
```

## 🔧 Configuration Files

### `.php-cs-fixer.php`
```php
// PHP CS Fixer configuration
// - PSR-12 compliance
// - PHP 8.1 migration rules
// - Custom formatting rules
```

### `phpstan.neon`
```yaml
# PHPStan configuration
# - Level 8 (strictest)
# - Custom ignore patterns
# - Test-specific rules
```

### `phpunit.xml`
```xml
<!-- PHPUnit configuration -->
<!-- - Coverage reporting -->
<!-- - Test environment setup -->
<!-- - Memory optimization -->
```

## 🚨 Troubleshooting

### Common CI Issues

1. **Memory Exhaustion**
   ```bash
   # Increase memory limit in phpunit.xml
   <php>
       <ini name="memory_limit" value="512M"/>
   </php>
   ```

2. **Dependency Conflicts**
   ```bash
   # Test with different dependency versions
   composer update --prefer-lowest
   composer update --prefer-stable
   ```

3. **Code Style Failures**
   ```bash
   # Fix automatically
   composer cs-fix
   
   # Check what would be fixed
   composer cs-check
   ```

4. **Static Analysis Errors**
   ```bash
   # Run with verbose output
   vendor/bin/phpstan analyse --verbose
   
   # Generate baseline
   vendor/bin/phpstan analyse --generate-baseline
   ```

### GitHub Actions Debugging

1. **Enable Debug Logging**
   - Add `ACTIONS_STEP_DEBUG: true` to workflow environment

2. **SSH into Runner**
   ```yaml
   - name: Setup tmate session
     uses: mxschmitt/action-tmate@v3
   ```

3. **Cache Issues**
   - Clear cache by changing cache key
   - Check cache hit/miss in workflow logs

## 📈 Performance Optimization

### Caching Strategy

- **Composer Dependencies**: Cached per PHP version and composer.json hash
- **PHPUnit Cache**: Cached in `.phpunit.cache` directory
- **PHPStan Cache**: Cached in `build/phpstan` directory

### Parallel Execution

- Matrix jobs run in parallel
- Independent job execution
- Fail-fast strategy for quick feedback

## 🔒 Security

### Secrets Management

Required GitHub Secrets:
- `CODECOV_TOKEN`: For coverage reporting

### Dependency Security

- Automated security audits on every run
- Composer audit integration
- Vulnerability reporting

## 📋 Checklist for Contributors

Before submitting a PR:

- [ ] All tests pass locally (`composer test`)
- [ ] Code style is correct (`composer cs-check`)
- [ ] Static analysis passes (`composer phpstan`)
- [ ] Security audit passes (`composer audit`)
- [ ] Coverage is maintained or improved
- [ ] Documentation is updated if needed

## 🎯 Future Improvements

- [ ] Add mutation testing with Infection
- [ ] Implement automatic dependency updates
- [ ] Add performance regression testing
- [ ] Integrate with SonarQube for code quality
- [ ] Add automated changelog generation 