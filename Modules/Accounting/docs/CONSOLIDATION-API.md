# Consolidation API Documentation

**Module:** Accounting  
**Version:** 1.0.0  
**Base URL:** `/api/consolidations`  
**Authentication:** Bearer Token (required)

---

## Overview

The Consolidation API provides endpoints for managing multi-entity consolidation groups, tracking intercompany transactions, and generating consolidated financial reports.

### Core Concepts

- **Consolidation Group:** A parent entity grouping with multiple subsidiaries
- **Members:** Subsidiary companies with ownership percentages
- **Intercompany Transactions:** Transactions between group entities (auto-eliminated)
- **Consolidation Entries:** Journal entries for eliminations
- **Reports:** Generated consolidated financial statements

---

## Endpoints

### 1. List Consolidation Groups

```http
GET /api/consolidations
```

**Query Parameters:**
```
search      (string, optional)   - Search by name
status      (string, optional)   - Filter by status (draft, in_progress, completed, approved)
fiscal_year (integer, optional)  - Filter by fiscal year
parent_id   (integer, optional)  - Filter by parent company
```

**Response (200 OK):**
```json
[
  {
    "id": 1,
    "name": "Q1 2026 Consolidation",
    "parent_company_id": 1,
    "parent_company": {
      "id": 1,
      "name": "Parent Corp",
      "code": "PARENT"
    },
    "consolidation_method": "full",
    "fiscal_year": 2026,
    "consolidation_date": "2026-03-31",
    "status": "draft",
    "members": [
      {
        "id": 1,
        "subsidiary_company_id": 2,
        "subsidiary": {
          "id": 2,
          "name": "Subsidiary 1"
        },
        "ownership_percentage": 100,
        "relationship_type": "subsidiary",
        "acquisition_date": "2020-01-01",
        "acquisition_price": 1000000,
        "consolidation_status": "active"
      }
    ],
    "created_by": 1,
    "created_at": "2026-05-19T10:00:00Z",
    "updated_at": "2026-05-19T10:00:00Z"
  }
]
```

---

### 2. Get Consolidation Group Details

```http
GET /api/consolidations/{id}
```

**Path Parameters:**
```
id (integer, required) - Consolidation group ID
```

**Response (200 OK):**
```json
{
  "id": 1,
  "name": "Q1 2026 Consolidation",
  "description": "Quarterly consolidation of all subsidiaries",
  "parent_company_id": 1,
  "parent_company": { ... },
  "consolidation_method": "full",
  "fiscal_year": 2026,
  "consolidation_date": "2026-03-31",
  "status": "draft",
  "elimination_tolerance": 0.01,
  "include_goodwill": true,
  "auto_eliminate_intercompany": true,
  "members": [ ... ],
  "intercompany_transactions": [
    {
      "id": 1,
      "from_company_id": 1,
      "from_company": { "id": 1, "name": "Parent Corp" },
      "to_company_id": 2,
      "to_company": { "id": 2, "name": "Subsidiary 1" },
      "transaction_type": "sales",
      "transaction_date": "2026-03-15",
      "reference_number": "INV-001",
      "amount": 50000,
      "currency": "USD",
      "exchange_rate": 1.0,
      "is_eliminated": false,
      "created_at": "2026-05-19T10:00:00Z"
    }
  ],
  "consolidation_entries": [
    {
      "id": 1,
      "entry_type": "intercompany_elimination",
      "entry_date": "2026-03-31",
      "debit_account_id": 1,
      "credit_account_id": 2,
      "amount": 50000,
      "currency": "USD",
      "amount_in_reporting_currency": 50000,
      "description": "Elimination of intercompany sales from Parent Corp to Subsidiary 1",
      "status": "pending"
    }
  ],
  "reports": [
    {
      "id": 1,
      "report_type": "consolidated_balance_sheet",
      "reporting_currency": "USD",
      "status": "draft",
      "created_by": 1,
      "created_at": "2026-05-19T10:00:00Z"
    }
  ],
  "creator": { "id": 1, "name": "John Doe" },
  "approver": null,
  "approved_at": null,
  "created_at": "2026-05-19T10:00:00Z",
  "updated_at": "2026-05-19T10:00:00Z"
}
```

---

### 3. Create Consolidation Group

```http
POST /api/consolidations
```

**Request Body:**
```json
{
  "name": "Q1 2026 Consolidation",
  "description": "Quarterly consolidation",
  "parent_company_id": 1,
  "consolidation_method": "full",
  "fiscal_year": 2026,
  "consolidation_date": "2026-03-31",
  "elimination_tolerance": 0.01,
  "include_goodwill": true,
  "auto_eliminate_intercompany": true,
  "members": [
    {
      "subsidiary_company_id": 2,
      "ownership_percentage": 100,
      "relationship_type": "subsidiary",
      "acquisition_date": "2020-01-01",
      "acquisition_price": 1000000,
      "fair_value_adjustment": 0,
      "exchange_rate": 1.0
    }
  ]
}
```

**Validation Rules:**
```
name                      (required, string, max 255)
parent_company_id         (required, exists:companies)
consolidation_method      (required, in: full, proportionate, equity)
fiscal_year               (required, integer, 4 digits)
consolidation_date        (required, date)
elimination_tolerance     (optional, numeric, min 0)
include_goodwill          (optional, boolean)
auto_eliminate_intercompany (optional, boolean)
members                   (optional, array of member objects)
```

**Response (201 Created):**
```json
{
  "id": 1,
  "name": "Q1 2026 Consolidation",
  ...
}
```

**Error Responses:**
```
400 Bad Request
{
  "message": "Validation failed",
  "errors": {
    "parent_company_id": ["The parent_company_id must exist in the companies table"]
  }
}

422 Unprocessable Entity
{
  "message": "Cannot create consolidation: Another consolidation exists for this period"
}
```

---

### 4. Add Subsidiary Member

```http
POST /api/consolidations/{id}/members
```

**Path Parameters:**
```
id (integer, required) - Consolidation group ID
```

**Request Body:**
```json
{
  "subsidiary_company_id": 2,
  "ownership_percentage": 100,
  "relationship_type": "subsidiary",
  "acquisition_date": "2020-01-01",
  "acquisition_price": 1000000,
  "fair_value_adjustment": 0,
  "is_foreign_entity": false,
  "exchange_rate_type": "closing",
  "exchange_rate": 1.0
}
```

**Response (201 Created):**
```json
{
  "message": "Member added successfully"
}
```

---

### 5. Record Intercompany Transaction

```http
POST /api/consolidations/{id}/transactions
```

**Path Parameters:**
```
id (integer, required) - Consolidation group ID
```

**Request Body:**
```json
{
  "from_company_id": 1,
  "to_company_id": 2,
  "transaction_type": "sales",
  "transaction_date": "2026-03-15",
  "reference_number": "INV-001",
  "amount": 50000,
  "currency": "USD",
  "exchange_rate": 1.0
}
```

**Validation:**
```
transaction_type: sales, purchases, services, loans, dividends, royalties
from_company_id:  must exist in consolidation group
to_company_id:    must exist in consolidation group
amount:           must be > 0
```

**Response (201 Created):**
```json
{
  "id": 1,
  "consolidation_group_id": 1,
  "from_company_id": 1,
  "to_company_id": 2,
  "transaction_type": "sales",
  "amount": 50000,
  "is_eliminated": false,
  "created_at": "2026-05-19T10:00:00Z"
}
```

---

### 6. Eliminate Intercompany Transactions

```http
POST /api/consolidations/{id}/eliminate
```

**Path Parameters:**
```
id (integer, required) - Consolidation group ID
```

**Request Body:**
```json
{}  // No body required
```

**Response (200 OK):**
```json
{
  "eliminated_count": 5,
  "message": "5 intercompany transactions eliminated",
  "elimination_entries_created": 10,
  "total_eliminated_amount": 250000
}
```

**Logic:**
```
1. Find all non-eliminated intercompany transactions
2. For each transaction:
   - Create debit entry to intercompany receivable
   - Create credit entry to intercompany sales (contra-revenue)
   - Create debit entry to intercompany cost
   - Create credit entry to intercompany payable
3. Mark transaction as eliminated
4. Return summary
```

---

### 7. Generate Consolidated Report

```http
POST /api/consolidations/{id}/reports
```

**Path Parameters:**
```
id (integer, required) - Consolidation group ID
```

**Request Body:**
```json
{
  "report_type": "consolidated_balance_sheet"
}
```

**Valid Report Types:**
```
consolidated_balance_sheet
consolidated_income_statement
consolidated_cash_flow
```

**Response (201 Created):**
```json
{
  "id": 1,
  "consolidation_group_id": 1,
  "report_type": "consolidated_balance_sheet",
  "reporting_currency": "USD",
  "status": "draft",
  "consolidated_data": {
    "2": {
      "company_name": "Subsidiary 1",
      "ownership_percentage": 100,
      "balances": {
        "current_assets": 500000,
        "fixed_assets": 1000000,
        "current_liabilities": 200000,
        "long_term_liabilities": 500000
      },
      "goodwill": 0
    }
  },
  "intercompany_eliminations": {
    "1": {
      "amount": 50000,
      "count": 2
    }
  },
  "exchange_differences": [],
  "total_adjustments": 50000,
  "created_by": 1,
  "created_at": "2026-05-19T10:00:00Z"
}
```

---

### 8. Approve Consolidation Group

```http
POST /api/consolidations/{id}/approve
```

**Path Parameters:**
```
id (integer, required) - Consolidation group ID
```

**Request Body:**
```json
{
  "status": "approved"
}
```

**Valid Statuses:**
```
approved (mark as approved)
rejected (revert to draft)
```

**Response (200 OK):**
```json
{
  "id": 1,
  "status": "approved",
  "approved_by": 1,
  "approved_at": "2026-05-19T12:00:00Z",
  "message": "Consolidation group approved"
}
```

---

## Error Codes

| Code | Description |
|------|-------------|
| 400 | Bad Request - Invalid input parameters |
| 401 | Unauthorized - Invalid or missing authentication |
| 403 | Forbidden - Insufficient permissions |
| 404 | Not Found - Resource does not exist |
| 422 | Unprocessable Entity - Validation error or business logic error |
| 500 | Internal Server Error |

---

## Rate Limiting

```
Rate Limit: 100 requests per minute per API key
Headers:
  X-RateLimit-Limit: 100
  X-RateLimit-Remaining: 95
  X-RateLimit-Reset: 1653031200
```

---

## Authentication

All endpoints require Bearer token authentication:

```
Authorization: Bearer YOUR_API_TOKEN
```

Obtain token via:
```http
POST /api/auth/login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "password"
}
```

---

## Pagination

List endpoints support pagination:

```
?page=1&per_page=20
```

Response headers:
```
X-Total-Count: 50
X-Page: 1
X-Per-Page: 20
X-Last-Page: 3
```

---

## Example Usage

### Create and consolidate a group

```bash
# 1. Create group
curl -X POST https://api.example.com/api/consolidations \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Q1 2026",
    "parent_company_id": 1,
    "consolidation_method": "full",
    "fiscal_year": 2026,
    "consolidation_date": "2026-03-31"
  }'

# Response: {"id": 1, ...}

# 2. Add member
curl -X POST https://api.example.com/api/consolidations/1/members \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "subsidiary_company_id": 2,
    "ownership_percentage": 100,
    "relationship_type": "subsidiary",
    "acquisition_date": "2020-01-01",
    "acquisition_price": 1000000
  }'

# 3. Record IC transaction
curl -X POST https://api.example.com/api/consolidations/1/transactions \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "from_company_id": 1,
    "to_company_id": 2,
    "transaction_type": "sales",
    "transaction_date": "2026-03-15",
    "reference_number": "INV-001",
    "amount": 50000
  }'

# 4. Eliminate transactions
curl -X POST https://api.example.com/api/consolidations/1/eliminate \
  -H "Authorization: Bearer TOKEN"

# Response: {"eliminated_count": 1, ...}

# 5. Generate report
curl -X POST https://api.example.com/api/consolidations/1/reports \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"report_type": "consolidated_balance_sheet"}'

# 6. Approve
curl -X POST https://api.example.com/api/consolidations/1/approve \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"status": "approved"}'
```

---

## Webhooks

Optional webhook notifications:

```
POST {webhook_url}
X-Signature: sha256=...
Content-Type: application/json

{
  "event": "consolidation.group.approved",
  "data": {
    "id": 1,
    "name": "Q1 2026 Consolidation",
    "status": "approved"
  },
  "timestamp": "2026-05-19T12:00:00Z"
}
```

**Events:**
- consolidation.group.created
- consolidation.group.approved
- consolidation.transactions.eliminated
- consolidation.report.generated

---

**API Version:** 1.0.0  
**Last Updated:** May 19, 2026  
**Maintainer:** Accounting Module Team
