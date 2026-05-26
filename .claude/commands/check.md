# Static Analysis

Run the full static analysis suite: PHPStan, PHP CS Fixer check, and PHPUnit.

```bash
composer stan && composer cs:check && composer tests:unit
```

Fix any issues found:
- **PHPStan errors**: fix the underlying type/logic issue — do not suppress with `@phpstan-ignore` or `assert()`
- **CS Fixer violations**: run `composer cs:fix` to auto-fix, then re-run `composer cs:check` to verify
- **PHPUnit failures**: fix the failing code or test, then re-run until green

Key reminders for this codebase:
- Private TypeHandler methods that return PhpParser statement arrays passed to `Ast::if()` / `Ast::foreach()` must be annotated `@return list<Stmt>`, not `@return Stmt[]`
- Composer script names: `stan`, `cs:fix`, `cs:check`, `tests:unit` — there is no `composer fixer`
