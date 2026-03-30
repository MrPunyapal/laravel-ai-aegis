# feat: PHP 8.2+, Laravel 12 support, DX commands, CI workflows & full coverage

## What's new

### Compatibility
- PHP `^8.2` support (was `^8.3`)
- Laravel 12 support alongside Laravel 13

### Developer Experience
- `php artisan aegis:install` — publishes config with guided next-step instructions
- `php artisan aegis:test "your prompt"` — runs injection detection and PII scanning locally, useful for debugging without a full agent setup
- `AEGIS_PII_TYPES` env var (comma-separated) to configure PII types without touching code
- `'aegis'` named middleware alias — use `->middleware('aegis')` instead of the full class reference

### Performance
- `PromptInjectionDetector` pre-lowercases attack vector keys once at construction time instead of on every `evaluate()` call
- `AegisMiddleware` skips the entire pipeline when both `blockInjections` and `pseudonymize` are disabled

### CI
- Lint & static analysis workflow — PHP 8.2 + Laravel 12 + `prefer-lowest`
- Test matrix — PHP 8.2/8.3/8.4 × Laravel 12/13 × prefer-lowest/prefer-stable (PHP 8.2 + Laravel 13 excluded — L13 requires PHP ^8.3)

### Quality
- PHPStan level 5 → 6
- 100% code coverage and 100% type coverage
- New tests for `AegisSecurityException`, `AegisRecorder`, `AegisServiceProvider`, artisan commands, and middleware edge cases
