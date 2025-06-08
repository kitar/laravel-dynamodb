# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

### Testing
- Run all tests: `./vendor/bin/phpunit`
- Run a single test: `vendor/bin/phpunit tests/Path/To/TestFile.php`
- Run a single test method: `vendor/bin/phpunit --filter testMethodName`

### Development
- Install dependencies: `composer install`
- Update dependencies: `composer update`

## Architecture Overview

This is a Laravel package that provides DynamoDB integration by adapting Laravel's database layer to work with AWS DynamoDB.

### Key Design Patterns

1. **Adapter Pattern**: The package adapts Laravel's database abstractions to DynamoDB
   - `Connection` extends Laravel's base connection class
   - `Model` extends Eloquent with DynamoDB-specific behavior
   - Query results are processed through `Processor` to match Laravel's expectations

2. **Builder Pattern**: DynamoDB queries are constructed using a fluent interface
   - `Query\Builder` provides chainable methods
   - Separate query objects for different DynamoDB operations (filter, condition, keyCondition)
   - `ExpressionAttributes` manages placeholder generation for expressions

3. **Grammar Translation**: `Query\Grammar` translates Laravel-style queries to DynamoDB API format
   - Uses AWS Marshaler for type conversions
   - Compiles expressions using DynamoDB syntax
   - Handles reserved words and attribute name conflicts

### Important Architectural Decisions

- **No Eloquent Relationships**: Models intentionally don't support relationships as DynamoDB is NoSQL
- **Primary Keys**: Models require `primaryKey` and optionally `sortKey` properties
- **Authentication**: Custom `AuthUserProvider` supports both primary key and API token authentication using DynamoDB indexes
- **Batch Operations**: Native support for DynamoDB batch operations (batchGetItem, batchPutItem, etc.)
- **Testing**: Use `dryRun()` method to inspect generated DynamoDB parameters without making API calls

### Testing Approach

Tests use Mockery to mock AWS SDK calls. When writing tests:
- Mock the DynamoDB client for unit tests
- Use `dryRun()` to test query building without API calls
- Follow existing test patterns in the `tests/` directory

### Version Compatibility

- PHP: 8.1, 8.2, 8.3, 8.4
- Laravel: 10.x through 12.x
- AWS SDK: ^3.0
