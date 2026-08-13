---
name: Dependency Update
about: Track dependency updates and security patches
title: "Dependency Update: [Package] [Version]"
labels: dependencies
assignees: ''

---

## Dependency Update Request

### Package Information

- **Package Name**: [e.g., laravel/framework, vue, react-native]
- **Current Version**: [e.g., 11.45.0]
- **Target Version**: [e.g., 12.0.0]
- **Update Type**: 
  - [ ] Security Patch (critical/high CVE)
  - [ ] Regular Update (monthly cycle)
  - [ ] Major Version Upgrade
  - [ ] Emergency Hotfix

---

### Security Information

#### CVEs Fixed (if applicable)

- [ ] No CVEs (routine update)
- [ ] CVE-YYYY-XXXXX: [Brief description] (CVSS: X.X - Severity)
- [ ] CVE-YYYY-YYYYY: [Brief description] (CVSS: X.X - Severity)

#### Severity Assessment

**CVSS Score**: [0.0-10.0 or N/A]

**Severity Level**: 
- [ ] Critical (9.0-10.0) - Response: 24 hours
- [ ] High (7.0-8.9) - Response: 7 days
- [ ] Moderate (4.0-6.9) - Response: 30 days
- [ ] Low (0.1-3.9) - Response: 90 days

---

### Impact Assessment

#### Affected Applications

- [ ] Backend (Laravel API)
- [ ] Frontend - webapp-business (Vue 3)
- [ ] Frontend - webapp-ecommerce (Vue 3)
- [ ] Mobile (React Native)
- [ ] All applications

#### Breaking Changes

- [ ] No breaking changes
- [ ] Has breaking changes (documented below)

**If breaking changes**:
```
Describe any API/interface changes here:
- Component X signature changed from...to...
- Deprecated function Y removed
- Config format updated from...to...
```

**Migration Notes**:
[Link to official migration guide or summary of changes]

---

### Testing Checklist

Before merging, verify:

#### Backend (Laravel)
- [ ] `vendor/bin/pest` - All tests passing
- [ ] `vendor/bin/phpstan analyse` - Type checking clean
- [ ] `vendor/bin/pint --test` - Code style check
- [ ] `php artisan migrate` - Database migrations working
- [ ] No breaking changes to API endpoints

#### Frontend (Vue 3)
- [ ] `npm run type-check` - TypeScript checks passing
- [ ] `npm run lint` - ESLint checks passing
- [ ] `npm run test` - Unit tests passing
- [ ] `npm run test:e2e` - E2E tests passing
- [ ] `npm run build` - Production build successful
- [ ] Bundle size within acceptable range

#### Mobile (React Native)
- [ ] `npm run test:ci` - Jest tests passing
- [ ] `npm run lint` - Linting passing
- [ ] `npm run build:android` - Android build working
- [ ] `npm run build:ios` - iOS build working

#### Performance

- [ ] Bundle size: [±X KB change or baseline maintained]
- [ ] Build time: [±X seconds or baseline maintained]
- [ ] Runtime performance: [Baseline maintained or change noted]

#### Security

- [ ] `npm audit --audit-level=moderate` shows no new issues
- [ ] `composer audit` shows no new vulnerabilities
- [ ] Security-related tests passing (if applicable)

---

### Deployment Plan

#### Staging Deployment

- **Recommended Duration**: [1-2 days for patch, 3-5 days for minor, 1-2 weeks for major]
- **Key Areas to Test**:
  1. [Feature/module 1]
  2. [Feature/module 2]
  3. [Feature/module 3]

#### Rollback Plan

- **Can rollback to**: [Previous version]
- **Rollback command**: 
  ```bash
  git revert <commit-hash>
  ```
- **Risk of rollback**: [Low/Medium/High and why]

#### Post-Deployment Monitoring

- [ ] Monitor error logs for 24 hours
- [ ] Check performance metrics
- [ ] Monitor user reports
- [ ] Verify all critical workflows function
- [ ] Check integration with external services

---

### Documentation

#### Changelog Entry

```markdown
## [Version] - [Date]

### Updates
- Updated [package-name] from X.Y.Z to A.B.C
- [Description of significant changes]

### Security
- Fixed CVE-YYYY-XXXXX: [Brief description]
- [Other security improvements]

### Breaking Changes (if applicable)
- [Breaking change 1]
- [Migration instructions]

### Notes
- [Any relevant notes for users/developers]
```

#### Migration Guide (if needed)

- [ ] Not required (no breaking changes)
- [ ] Migration guide created at: [Link]
- [ ] Team notified of breaking changes
- [ ] Documentation updated

---

### Review Checklist

**Before requesting review**:

- [ ] All tests passing locally
- [ ] All lock files (composer.lock, package-lock.json) committed
- [ ] PR title clear and descriptive
- [ ] PR description complete
- [ ] No sensitive data in commits

**Review requirements**:

- [ ] Minimum 1 approval (for patch/minor)
- [ ] Minimum 2 approvals (for major version)
- [ ] Security team review (for security patches)
- [ ] Architecture review (for breaking changes)

---

### Related Issues/PRs

- Fixes: [Link to related issue(s) or CVE(s)]
- See also: [Related PRs or documentation]

---

### Additional Notes

[Any additional context or notes about this update]

---

### Labels

Please add appropriate labels:
- `dependencies` - General dependency update
- `security` - Security-related update
- `breaking-change` - Contains breaking changes
- `urgent` - Critical/High severity CVE
- `routine` - Regular monthly update
- `backend` - Affects backend only
- `frontend` - Affects frontend only
- `mobile` - Affects mobile only

---

### Assignees

**Assign to**:
- [ ] Tech Lead (for approval)
- [ ] Dev who will implement (feature branches)
- [ ] Security Lead (for security patches)

---

### Due Date

**Target merge date**: [Date based on severity timeline]
**Target deployment date**: [Date + staging duration]

---

**Last Updated**: [Date]  
**Status**: [Open / In Progress / Ready for Review / Merged]
