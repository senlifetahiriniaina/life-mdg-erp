# Security Update / Dependency Patch

## Summary

Brief description of what dependencies were updated and why.

**Example**:
> Updated axios to fix critical XSS vulnerability in request header handling. Also updated Laravel to 12.2.0 for performance improvements.

---

## Type of Update

- [ ] Security Patch (Critical/High severity CVE)
- [ ] Monthly Non-Critical Updates
- [ ] Major Version Upgrade
- [ ] Emergency Hotfix

---

## Vulnerabilities Fixed

### Security Issues Resolved

- [ ] No security vulnerabilities (routine update)
- [ ] CVE-YYYY-XXXXX - [Vulnerability Title]
  - **Package**: [name]
  - **Severity**: Critical / High / Moderate / Low
  - **CVSS**: [Score]
  - **Description**: [Brief description]
  - **References**: [Link to CVE, GitHub advisory, etc.]

### Additional CVEs Fixed

- [ ] CVE-YYYY-YYYYY - [Another title] (Moderate)
- [ ] CVE-YYYY-ZZZZ - [Another title] (Low)

---

## Packages Updated

### Backend (Laravel API)

| Package | Old Version | New Version | Severity | Breaking |
|---|---|---|---|---|
| laravel/framework | 11.x | 12.0.0 | Security | Yes |
| spatie/laravel-permission | 6.8.x | 6.9.0 | Patch | No |
| stripe/stripe-php | 20.0.0 | 20.1.0 | Patch | No |

### Frontend (Vue 3)

| Package | Old Version | New Version | Severity | Breaking |
|---|---|---|---|---|
| vue | 3.3.x | 3.4.x | Minor | No |
| primevue | 3.x | 4.x | Major | Yes |
| axios | 1.6.5 | 1.7.4 | Security | No |

---

## Breaking Changes

### Summary

List all breaking changes and required code updates.

**If no breaking changes**:
> No breaking changes. All updates are backward-compatible within constraint versions.

**If breaking changes**:

### Change 1: [Component/API Name]

**Old Behavior**:
```javascript
// Before
import { OldComponent } from 'primevue/components';
<OldComponent :prop1="value" />
```

**New Behavior**:
```javascript
// After
import { NewComponent } from 'primevue/components';
<NewComponent v-model="value" />
```

**Migration Steps**:
1. Update all occurrences of OldComponent to NewComponent
2. Change :prop1 to v-model binding
3. Test form submission workflows

**Related Documentation**: [Link to official migration guide]

---

## Testing Completed

### Backend Tests

- [x] Unit Tests: `vendor/bin/pest`
  - **Result**: All X tests passing ✅
  - **Duration**: X seconds
  
- [x] Type Checking: `vendor/bin/phpstan analyse`
  - **Result**: 0 errors ✅
  
- [x] Code Style: `vendor/bin/pint --test`
  - **Result**: Clean ✅
  
- [x] Database Migrations: `php artisan migrate`
  - **Result**: All migrations successful ✅
  
- [x] Feature Tests: Specific critical paths verified

### Frontend Tests

- [x] TypeScript Check: `npm run type-check`
  - **Result**: Clean ✅
  
- [x] Linting: `npm run lint`
  - **Result**: Clean ✅
  
- [x] Unit Tests: `npm run test`
  - **Result**: X/X passing ✅
  
- [x] E2E Tests: `npm run test:e2e`
  - **Result**: X/X passing ✅
  
- [x] Production Build: `npm run build`
  - **Result**: Build successful ✅
  - **Bundle Size**: [Size change: +/- X KB]

### Security Tests

- [x] `npm audit --audit-level=moderate`
  - **Result**: 0 vulnerabilities ✅
  
- [x] `composer audit`
  - **Result**: 0 vulnerabilities ✅
  
- [x] Secret scanning: No secrets detected ✅

### Performance Impact

- **Bundle Size Change**: ±X KB (Acceptable / Needs Review)
- **Build Time Change**: ±X seconds (Acceptable / Needs Review)
- **Runtime Performance**: Baseline maintained / [Change noted]
- **Memory Usage**: Baseline maintained / [Change noted]

---

## Staging Deployment

### Staging Timeline

- **Start Date**: [Date]
- **Recommended Duration**: [X days based on severity]
- **End Date**: [Date]
- **Status**: [Not started / In progress / Complete]

### Staging Verification

- [ ] Application boots without errors
- [ ] Critical workflows tested:
  - [ ] User login
  - [ ] [Module 1] functionality
  - [ ] [Module 2] functionality
  - [ ] [Critical workflow]
- [ ] No new error messages in logs
- [ ] Performance metrics acceptable
- [ ] Security scans show no new issues
- [ ] User testing (if applicable)

### Issues Found in Staging

- [ ] No issues found
- [ ] Issues found (listed below):
  1. [Issue 1 and resolution]
  2. [Issue 2 and resolution]

---

## Rollback Plan

### Rollback Procedure

**If critical issues discovered**:

```bash
# Revert the update commit
git revert <commit-hash>

# Or reset to previous version
git reset --hard <previous-commit>

# Deploy reverted code
# (via CI/CD or manual deployment)
```

### Rollback Risk Assessment

- **Risk Level**: Low / Medium / High
- **Can Rollback**: [Minutes to execute]
- **Data Impact**: [Potential data issues on rollback, if any]
- **User Impact**: [Estimated users affected if rollback needed]

---

## Post-Deployment Monitoring Plan

### Monitoring Duration

- **Duration**: 24-48 hours minimum
- **Alert Thresholds**: [Specific metrics to monitor]
- **Owner**: [Who's monitoring]

### Metrics to Monitor

- [ ] Error rates (target: < 0.1% increase)
- [ ] Response times (target: baseline ±5%)
- [ ] User reports (monitor #support)
- [ ] Performance metrics (Prometheus/Grafana)
- [ ] Security logs (no suspicious activity)
- [ ] Critical workflow success rates (target: > 99.9%)

### Post-Deployment Checklist

- [ ] No spike in error logs
- [ ] Performance metrics normal
- [ ] User-reported issues none
- [ ] All critical workflows functioning
- [ ] Database queries performing well
- [ ] Cache working correctly
- [ ] Integrations (Stripe, Meilisearch, etc.) working

---

## Documentation & Communication

### Update Checklist

- [x] All lock files updated (composer.lock, package-lock.json)
- [x] CHANGELOG.md updated (if major update)
- [x] Migration guide created (if breaking changes)
- [x] Team notified of breaking changes
- [x] Deployment notes provided to DevOps

### Communication

- **Slack Notification**: Sent to #engineering
- **Email Update**: [Scheduled / Sent]
- **User Communication**: [Not needed / Scheduled / Sent]
- **Blog Post**: [Not needed / Scheduled / Published]

---

## Code Review Checklist

**For Reviewers**:

- [ ] All lock files are correct (no unintended changes)
- [ ] Version constraints are appropriate
- [ ] Tests are all passing
- [ ] Breaking changes documented
- [ ] Migration guide is clear (if applicable)
- [ ] Performance impact assessed
- [ ] Security properly addressed
- [ ] No sensitive data in diffs
- [ ] Commit messages clear

---

## Checklist Before Merge

- [x] All tests passing
- [x] CI/CD checks passing
- [x] Breaking changes documented
- [x] Migration guide provided (if needed)
- [x] Code review approved (2 reviewers minimum)
- [x] Security review approved (for security patches)
- [x] Staging deployment verified
- [x] No critical issues in staging
- [x] Rollback plan documented
- [x] Team notified of deployment plan

---

## Related Issues & Discussions

- **Closes**: [GitHub issue number]
- **Related CVE**: [CVE links]
- **GitHub Advisory**: [Links to advisories]
- **Discussion**: [Link to team discussion, if any]

---

## Reviewers

**Assign to**:
- @tech-lead (required approval)
- @security-team (required for security patches)
- @frontend-lead (for frontend updates)
- @backend-lead (for backend updates)
- @devops-lead (for infrastructure impact)

---

## Deploy After

- [ ] All reviewers approved
- [ ] All CI checks passed
- [ ] Merge to develop branch
- [ ] Wait [X days] before production

**Scheduled Production Deployment**: [Date and time, preferably off-peak]

---

## Additional Notes

[Any additional context about this update that reviewers should know]

---

## Template Version

- **Template Version**: 1.0
- **See Also**: [SECURITY.md](/SECURITY.md)
