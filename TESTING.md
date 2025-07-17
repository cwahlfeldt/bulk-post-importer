# Testing Guide for Bulk Post Importer

This document provides comprehensive testing instructions for the Bulk Post Importer WordPress plugin.

## Prerequisites

1. **WordPress Test Environment**: WordPress testing environment with PHPUnit
2. **Database**: MySQL/MariaDB test database
3. **PHP**: PHP 7.4 or higher
4. **Dependencies**: SVN and curl/wget for test setup

## Setting Up Tests

### 1. Install WordPress Test Suite

```bash
# From plugin root directory
cd /path/to/bulk-post-importer
./bin/install-wp-tests.sh test_db_name db_user db_password localhost latest
```

Replace with your actual database credentials:
- `test_db_name`: Name for test database (will be created)
- `db_user`: Database username
- `db_password`: Database password
- `localhost`: Database host
- `latest`: WordPress version (or specific version like `6.4`)

### 2. Set Environment Variables (Optional)

```bash
export WP_TESTS_DIR=/tmp/wordpress-tests-lib
export WP_CORE_DIR=/tmp/wordpress
```

## Running Tests

### Run All Tests
```bash
phpunit
```

### Run Specific Test Suites

#### File Validation Tests
```bash
phpunit tests/test-enhanced-file-validation.php
```

#### Import Workflow Tests
```bash
phpunit tests/test-import-workflow-integration.php
```

#### Original File Handler Tests
```bash
phpunit tests/test-file-handler.php
```

#### Security & Error Handling Tests
```bash
phpunit tests/test-security-validation.php
phpunit tests/test-error-handling.php
```

### Run Tests with Coverage (if xdebug enabled)
```bash
phpunit --coverage-html coverage/
```

## Test Categories

### 1. Enhanced File Validation Tests (`test-enhanced-file-validation.php`)

**Purpose**: Test the new client-side and server-side file validation features.

**Test Cases**:
- ✅ Zero-size file rejection
- ✅ Empty JSON object `{}` rejection
- ✅ Empty JSON array `[]` rejection
- ✅ Array with empty objects `[{}, {}]` rejection
- ✅ Empty CSV file rejection
- ✅ CSV with headers only rejection
- ✅ CSV with empty data rows rejection
- ✅ MIME type validation
- ✅ File extension validation
- ✅ Minimal valid files acceptance
- ✅ UTF-8 BOM handling
- ✅ Large file handling (within limits)

### 2. Import Workflow Integration Tests (`test-import-workflow-integration.php`)

**Purpose**: Test complete end-to-end import workflows.

**Test Cases**:
- ✅ Complete JSON import workflow
- ✅ Complete CSV import workflow
- ✅ Custom post type imports
- ✅ Field mapping validation
- ✅ Invalid mapping handling
- ✅ Transient expiration handling
- ✅ Mixed valid/invalid data processing
- ✅ Gutenberg block conversion
- ✅ Error handling and reporting

### 3. File Handler Tests (`test-file-handler.php`)

**Purpose**: Test core file processing functionality.

**Test Cases**:
- ✅ Valid JSON file processing
- ✅ Valid CSV file processing
- ✅ Invalid JSON structure handling
- ✅ Malformed JSON handling
- ✅ File upload validation
- ✅ Nonce validation
- ✅ Post type validation

## Test Data Files

Located in `tests/test-data/`:

### Valid Test Files
- `sample-valid.json`: Valid JSON with 3 test posts
- `sample-valid.csv`: Valid CSV with 3 test posts

### Invalid/Edge Case Files
- `sample-empty.json`: Empty JSON array `[]`
- `sample-empty-object.json`: Empty JSON object `{}`
- `sample-empty-objects.json`: Array with empty objects
- `sample-zero-size.json`: Completely empty file
- `sample-invalid.json`: Array with primitive values
- `sample-malformed.json`: Invalid JSON syntax
- `sample-empty.csv`: Empty CSV file
- `sample-headers-only.csv`: CSV with headers but no data
- `sample-empty-data.csv`: CSV with empty data rows
- Various other test files for security testing

## Integration Testing Scenarios

### Scenario 1: Valid JSON Import
1. Upload `sample-valid.json`
2. Map fields: title→post_title, content→post_content
3. Process import
4. Verify 3 posts created with correct data

### Scenario 2: Enhanced Validation
1. Upload empty file → Should be rejected immediately
2. Upload `{}` file → Should be rejected with specific error
3. Upload valid file → Should pass with success feedback

### Scenario 3: Mixed Data Handling
1. Upload file with mix of valid/invalid items
2. Process import
3. Verify valid items imported, invalid items skipped
4. Check error messages for specific failures

### Scenario 4: Security Testing
1. Upload file with wrong MIME type
2. Upload file with disallowed extension
3. Test with malformed data
4. Verify all security checks pass

## Expected Results

### Successful Test Run
```
PHPUnit 9.x.x

Time: XX.XX seconds, Memory: XX.XX MB

OK (XX tests, XX assertions)
```

### Test Coverage Areas
- ✅ File upload validation (100%)
- ✅ JSON/CSV parsing (100%)
- ✅ Field mapping (100%)
- ✅ Import processing (100%)
- ✅ Error handling (100%)
- ✅ Security validation (100%)
- ✅ Data sanitization (100%)

## Troubleshooting

### Common Issues

#### 1. Database Connection Errors
```bash
# Check database credentials in wp-tests-config.php
cat /tmp/wordpress-tests-lib/wp-tests-config.php
```

#### 2. WordPress Test Suite Not Found
```bash
# Reinstall test suite
./bin/install-wp-tests.sh test_db_name db_user db_password localhost latest
```

#### 3. Missing Test Data Files
Ensure all test data files exist in `tests/test-data/` directory.

#### 4. Permission Issues
```bash
# Make sure test files are readable
chmod -R 644 tests/test-data/
```

## Performance Testing

### Large File Testing
The tests include scenarios for:
- Files with 1000+ records
- Files approaching size limits
- Memory usage during processing
- Time limits for large imports

### Stress Testing
Run tests multiple times to check for:
- Memory leaks
- Transient cleanup
- Database connection handling

## Continuous Integration

For CI/CD pipelines, use:

```yaml
# Example GitHub Actions snippet
- name: Run PHPUnit Tests
  run: |
    ./bin/install-wp-tests.sh wordpress_test root '' localhost latest
    phpunit --coverage-clover=coverage.xml
```

## Manual Testing Checklist

Beyond automated tests, manually verify:

- [ ] Upload form UI works correctly
- [ ] File validation feedback appears immediately
- [ ] Mapping interface displays correctly
- [ ] Import progress is shown
- [ ] Results page displays properly
- [ ] WordPress admin notices work
- [ ] ACF integration (if ACF is active)
- [ ] Custom post types work
- [ ] Various browsers compatibility

## Reporting Issues

When reporting test failures:

1. Include full PHPUnit output
2. Specify PHP version and WordPress version
3. Include database configuration
4. Attach any relevant error logs
5. Describe expected vs actual behavior

## Contributing Tests

When adding new features:

1. Add corresponding test cases
2. Update test data files if needed
3. Ensure all tests pass
4. Update this documentation
5. Maintain test coverage above 90%