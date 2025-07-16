# Bulk Post Importer - Testing Guide

This document provides comprehensive information about the test suite for the Bulk Post Importer WordPress plugin.

## Test Structure

The test suite consists of multiple test files, each focusing on different aspects of the plugin:

### Core Test Files

1. **test-file-handler.php** - Tests file upload, validation, and parsing
2. **test-field-mapping.php** - Tests field mapping functionality and data structure validation
3. **test-import-processor.php** - Tests the core import processing logic
4. **test-acf-integration.php** - Tests Advanced Custom Fields integration
5. **test-security-validation.php** - Tests security measures and input validation
6. **test-error-handling.php** - Tests error handling and edge cases
7. **test-plugin-integration.php** - Tests complete plugin workflows and integration

### Test Data Files

Located in `tests/test-data/`:

- `sample-valid.json` - Valid JSON data for testing
- `sample-valid.csv` - Valid CSV data for testing
- `sample-invalid.json` - Invalid JSON structure for error testing
- `sample-empty.json` - Empty JSON array for testing
- `sample-malformed.json` - Malformed JSON for error testing
- `sample-invalid.csv` - Invalid CSV for error testing

## Running the Tests

### Prerequisites

1. WordPress test environment must be set up
2. Database configured for testing
3. PHPUnit installed and configured

### Running Individual Test Files

```bash
# Run file handler tests
phpunit tests/test-file-handler.php

# Run import processor tests
phpunit tests/test-import-processor.php

# Run all tests
phpunit
```

### Running Specific Test Methods

```bash
# Run a specific test method
phpunit --filter test_process_valid_json_file tests/test-file-handler.php
```

## Test Coverage

### File Handler Tests (`test-file-handler.php`)

- ✅ Valid JSON file processing
- ✅ Valid CSV file processing  
- ✅ Invalid JSON structure handling
- ✅ Empty file handling
- ✅ Malformed JSON handling
- ✅ File upload validation
- ✅ File type validation
- ✅ MIME type validation
- ✅ Post type validation
- ✅ Nonce validation
- ✅ Custom post type support
- ✅ CSV edge cases (quotes, line breaks)
- ✅ File size validation

### Field Mapping Tests (`test-field-mapping.php`)

- ✅ Field mapping data structure validation
- ✅ Standard field mapping
- ✅ Custom field mapping
- ✅ Empty mapping handling
- ✅ Special characters in field names
- ✅ Nested data handling
- ✅ Inconsistent data across items
- ✅ CSV header mapping
- ✅ Post type context validation

### Import Processor Tests (`test-import-processor.php`)

- ✅ Successful import with standard fields
- ✅ Import with custom fields
- ✅ Missing title validation
- ✅ Invalid post status handling
- ✅ Invalid date format handling
- ✅ Custom post type imports
- ✅ Expired transient handling
- ✅ Post type mismatch detection
- ✅ Missing required data validation
- ✅ Nonce validation
- ✅ Invalid item data handling
- ✅ Performance with large datasets
- ✅ Gutenberg block conversion

### ACF Integration Tests (`test-acf-integration.php`)

- ✅ ACF availability detection
- ✅ Field type mappability validation
- ✅ Allowed field types verification
- ✅ ACF inactive scenarios
- ✅ Mocked ACF function testing
- ✅ Import with ACF fields
- ✅ Invalid ACF field mapping
- ✅ Field mappability edge cases

### Security & Validation Tests (`test-security-validation.php`)

- ✅ Capability requirements
- ✅ Nonce validation (file handler & import processor)
- ✅ File type validation
- ✅ MIME type validation
- ✅ File upload error handling
- ✅ Input sanitization
- ✅ Malicious file content handling
- ✅ XSS prevention
- ✅ SQL injection prevention
- ✅ File path traversal prevention
- ✅ Data validation edge cases
- ✅ WordPress security hooks integration

### Error Handling Tests (`test-error-handling.php`)

- ✅ File system errors
- ✅ Memory and resource limits
- ✅ Corrupted JSON handling
- ✅ Corrupted CSV handling
- ✅ Database connection errors
- ✅ Concurrent access and race conditions
- ✅ Transient expiration handling
- ✅ Invalid mapping configurations
- ✅ WordPress hook failures
- ✅ Plugin deactivation cleanup
- ✅ Error message formatting
- ✅ Special characters and encodings
- ✅ Timeout handling for large imports

### Plugin Integration Tests (`test-plugin-integration.php`)

- ✅ Plugin initialization
- ✅ Plugin constants
- ✅ Plugin activation/deactivation
- ✅ Complete JSON import workflow
- ✅ Complete CSV import workflow
- ✅ Custom post type import workflow
- ✅ Mixed success/failure scenarios
- ✅ Performance with large datasets
- ✅ Singleton pattern validation
- ✅ Autoloader functionality

## Test Features

### Comprehensive Coverage

The test suite covers:

- **Happy Path Scenarios**: Valid data, successful imports, proper workflows
- **Error Scenarios**: Invalid data, malformed files, security issues
- **Edge Cases**: Empty data, special characters, large datasets
- **Security**: Input validation, XSS prevention, SQL injection prevention
- **Performance**: Large dataset handling, memory management, timeout handling
- **Integration**: Complete workflows, plugin lifecycle, WordPress integration

### Security Testing

Special attention is paid to security:

- **Input Validation**: All user inputs are tested for proper sanitization
- **File Upload Security**: MIME type validation, file extension checks
- **SQL Injection Prevention**: Database queries are tested for safety
- **XSS Prevention**: Output escaping is validated
- **Path Traversal Prevention**: File path handling is secured
- **Nonce Validation**: All form submissions require valid nonces
- **Capability Checks**: Administrative functions require proper permissions

### Performance Testing

Performance scenarios include:

- **Large File Processing**: Files with thousands of records
- **Memory Usage**: Monitoring resource consumption
- **Timeout Handling**: Long-running import processes
- **Database Performance**: Bulk insert operations
- **Transient Management**: Temporary data storage and cleanup

## Test Data

### Sample Data Structure

**JSON Format:**
```json
[
    {
        "title": "Test Post 1",
        "content": "This is the content for test post 1.",
        "excerpt": "Test post 1 excerpt",
        "status": "publish",
        "date": "2024-01-01 10:00:00",
        "custom_field_1": "Custom value 1",
        "acf_text_field": "ACF text value 1",
        "acf_number_field": "123",
        "acf_email_field": "test1@example.com"
    }
]
```

**CSV Format:**
```csv
title,content,excerpt,status,date,custom_field_1,acf_text_field,acf_number_field,acf_email_field
"Test Post 1","This is CSV content.","Test excerpt","publish","2024-01-01 10:00:00","Custom value 1","ACF text value 1","123","test1@example.com"
```

## Error Scenarios Tested

### File Upload Errors

- Missing files
- Invalid file types
- Corrupted files
- Oversized files
- Upload errors (server-side)

### Data Validation Errors

- Invalid JSON syntax
- Invalid CSV structure
- Missing required fields
- Invalid post types
- Malformed dates
- Invalid post statuses

### Security Errors

- Missing nonces
- Invalid capabilities
- Malicious file content
- XSS attempts
- SQL injection attempts
- Path traversal attempts

### System Errors

- Database connection failures
- Memory limit exceeded
- Timeout errors
- File system errors
- Concurrent access issues

## Best Practices

The test suite follows WordPress testing best practices:

1. **Clean Setup/Teardown**: Each test starts with a clean environment
2. **Isolation**: Tests don't depend on each other
3. **Realistic Data**: Test data mirrors real-world scenarios
4. **Edge Cases**: Tests cover boundary conditions
5. **Error Handling**: Tests verify proper error responses
6. **Performance**: Tests monitor resource usage
7. **Security**: Tests validate security measures

## Continuous Integration

The test suite is designed to be run in continuous integration environments:

- **Automated Testing**: All tests can be run automatically
- **Coverage Reports**: Test coverage can be measured
- **Performance Monitoring**: Performance regressions can be detected
- **Security Scanning**: Security vulnerabilities can be identified

## Contributing

When adding new features or fixing bugs:

1. **Write Tests First**: Follow TDD principles
2. **Cover Edge Cases**: Consider error scenarios
3. **Test Security**: Validate security measures
4. **Check Performance**: Monitor resource usage
5. **Update Documentation**: Keep this guide current

## Troubleshooting

Common issues when running tests:

1. **Database Connection**: Ensure test database is configured
2. **File Permissions**: Test data files must be readable
3. **Memory Limits**: Large dataset tests may require more memory
4. **Timeout Issues**: Long-running tests may need timeout adjustments
5. **Plugin Dependencies**: Some tests require specific WordPress setup

## Conclusion

This comprehensive test suite ensures the Bulk Post Importer plugin is robust, secure, and performant. It covers all major functionality, edge cases, and security concerns, providing confidence in the plugin's reliability and safety for production use.