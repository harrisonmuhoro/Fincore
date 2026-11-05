# MODULE 05 — Financial Master Data Management

## 1. Purpose and scope
Own the authoritative chart of accounts, funds/funding sources, economic classifications, programme/sub-programme financial dimensions, cost-centre/account combinations, accounting rules, currency metadata, financial years, accounting periods, and financial calendar status. Every financial module references this versioned master data. Module 01 owns generic system settings and numbering only.

## 2. Functional requirements
Maintain coded hierarchical account structures, fund/source classifications and dimension dictionaries; configure allowed account combinations; define KES and any later approved currency metadata; configure financial years and periods; activate/supersede effective-dated master-data versions; open/close/reopen periods through controlled actions; publish version/status references to consumers; report invalid or inactive references and calendar transitions.

## 3. Actors and responsibilities
Finance master-data steward maintains draft COA and dimensions. Financial controller reviews combinations and calendar setup. Authorized independent finance authority activates master-data versions and approves period open/close/reopen. Accountants consume active references; system administrators operate the platform but do not approve finance data by default; auditors have read-only change history.

## 4. Permissions and access restrictions
| Permission | Responsible role | Approval required | Restrictions |
|---|---|---|---|
| `finance.coa.view` | Accountant/auditor | No | Read-only version/effective-date scope. |
| `finance.coa.edit` | Finance master-data steward | No, draft only | No edits to active referenced versions. |
| `finance.account_combination.validate` | Finance master-data steward | No | Validation has no activation/posting side effect. |
| `finance.account_combination.activate` | Independent finance authority | Yes | Maker-checker; active version is immutable. |
| `finance.financial_year.view` | Calendar steward/accountant | No | Read-only calendar and status scope. |
| `finance.accounting_period.view` | Accountant/period controller | No | Read-only status, transition history and close evidence. |
| `finance.financial_year.create` | Calendar steward | No, draft only | No overlapping financial years; no assumed dates. |
| `finance.financial_year.activate` | Finance calendar approver | Yes, independent | Validate period coverage and approved calendar policy. |
| `finance.accounting_period.open` | Period authority | Yes where policy requires | Year active and prerequisites satisfied. |
| `finance.accounting_period.close` | Period authority | Yes, independent | Ledger readiness evidence required; this module owns status. |
| `finance.accounting_period.reopen` | Privileged finance authority | Yes, independent | Reason/evidence and audit; no generic admin bypass. |
| `finance.master_data.audit.view` | Auditor | No | Read-only transition/change history. |

County authority defines approval limits and calendar policy. Platform administrator has no implied authority to activate or reopen periods.
## 5. Module-specific business rules
Codes are unique within their configured classification and effective period. Parent-child structures cannot cycle. Account combinations must reference active accounts, funds and required dimensions; invalid combinations cannot be activated or posted. Financial year dates cannot overlap for the same calendar. Periods must belong to exactly one year, have ordered non-overlapping dates and explicit open/closed/future status. Closing a period prevents new posting; reopening is a distinct audited approval, not a status edit. Calendar changes never silently re-date posted transactions. KES is default; multi-currency usage requires an approved policy. Do not invent accounting basis, chart coding, fiscal dates or approval thresholds.

## 6. Detailed business workflows
**COA/dimension activation:** steward drafts coded structure → validate uniqueness, hierarchy and references → controller reviews impact on existing combinations → independent authority activates a version with effective date → consumers receive version event. If used by posted journals, prior version is retired, not deleted.
**Financial year and period setup:** calendar owner creates year dates → validates no overlap and period coverage/order → finance reviewer checks alignment to configured budget/accounting calendar → independent authority activates year/periods → status is future/open. Opening a period verifies prior-period prerequisites if configured. Close action checks pending posting/reconciliation checklist, requests independent approval, then marks closed and emits close event. Reopen requires explicit reason/evidence, authority and audit.
Failures: overlap, missing period coverage, invalid dimensions, or unresolved prerequisite blocks activation/close. No partial calendar change is committed.

## 7. State transitions and lifecycle rules
| Current state | Action | Next state | Authorized actor | Preconditions / side effects |
|---|---|---|---|---|
| Draft master version | Validate and submit | In review | Master-data steward | Codes, hierarchy and combinations validated; snapshot version recorded. |
| In review | Approve activation | Active | Independent finance authority | No unresolved collision; effective date set; MasterDataVersionActivated emitted. |
| Active version | Supersede | Superseded | Master-data authority | Replacement active; historical references remain readable. |
| Future financial year | Activate | Current/active | Finance calendar approver | Dates/periods valid, non-overlapping; YearActivated emitted. |
| Future/closed period | Open | Open | Period authority | Year active, prerequisites satisfied, action audited. |
| Open period | Close | Closed | Independent period authority | Reconciliation/close checks pass; FinancialPeriodClosed emitted. |
| Closed period | Reopen request/approve | Open (reopened) | Privileged independent authority | Reason and evidence required; reopen event and access trail retained. |
| Any referenced master record | Deactivate/supersede | Inactive/superseded | Master-data authority | No destructive delete; existing transaction references remain valid. |

## 8. Database ownership and entity relationships
Own finance_accounts(id, code, name, account_type, parent_id, effective_from/to, status), funds, funding_sources, economic_classifications, dimension_types, dimensions, account_combinations, accounting_rules, currencies, financial_calendars, financial_years, accounting_periods, period_transition_history, master_data_versions and approval/audit references. Financial years own periods (one-to-many); combinations reference the relevant account/fund/classification/dimensions. Use unique code+version/effective constraints, date indexes, non-overlapping year/period validation and immutable transition history. Monetary columns in any owned financial table use DECIMAL. Other domains reference these IDs; they do not copy editable chart/calendar masters.

## 9. Backend responsibilities
FinancialMasterDataService validates versioned COA and combination graphs; CalendarService enforces year/period overlap, coverage, activation, close/reopen prerequisites; domain actions wrap activation and status changes in transactions. Form Requests validate payloads; Policies authorize finance roles; Resources return active version/status metadata; events include MasterDataVersionActivated, FinancialYearActivated, AccountingPeriodOpened, FinancialPeriodClosed and FinancialPeriodReopened. Jobs notify consumers/rebuild validation caches after commit. Period close command coordinates readiness queries with ledger (13) but module 05 owns period status.

## 10. API contracts and endpoint responsibilities
| Method | Endpoint | Purpose / request and validation | Permission / approval | Response and audit |
|---|---|---|---|---|
| GET | `/api/v1/finance/financial-years` | List years with status/effective dates. | `finance.financial_year.view` | 200 paginated; scoped. |
| POST | `/api/v1/finance/financial-years` | Create draft year and period calendar; validate no overlap/coverage. | `finance.financial_year.create` | 201 draft; 409 overlap; 422 invalid dates. |
| POST | `/api/v1/finance/financial-years/{id}/activate` | Activate approved calendar version. | `finance.financial_year.activate`; independent | 200 active/versioned; audit and event. |
| GET | `/api/v1/finance/accounting-periods` | List periods by year/status. | `finance.accounting_period.view` | 200 paginated; stable IDs. |
| POST | `/api/v1/finance/accounting-periods/{id}/open` | Open eligible period. | `finance.accounting_period.open`; approval per policy | 200 opened; 409 invalid state. |
| POST | `/api/v1/finance/accounting-periods/{id}/close` | Close after Module 13 readiness. | `finance.accounting_period.close`; independent approval | 200 closed; event; 409 unresolved checks. |
| POST | `/api/v1/finance/accounting-periods/{id}/reopen` | Reopen with reason/evidence. | `finance.accounting_period.reopen`; privileged independent approval | 200 reopened; audit; 403/409/422 as applicable. |
| POST | `/api/v1/finance/account-combinations/validate` | Validate active account/fund/dimension references. | `finance.account_combination.validate` | 200 result with rule version; no write activation. |

All state changes record actor, reason, approval, version and correlation ID; closed-period state is never updated through generic PATCH.
## 11. Frontend responsibilities
COA tree editor with parent/type validation; dimension and fund registers; combination validation workspace showing failed rule paths; version comparison and activation queue; financial year calendar view; period register/calendar with future/open/closed/reopened badges; close checklist from ledger reconciliation status; controlled reopen request/evidence form; change history and invalid-reference report. Only authorized finance roles see actions; backend still rechecks.

## 12. Cross-module dependencies and integration events
Depends on 01 configuration, 02 identity, 03 org references and 04 approval. Budget 06/07, procurement/AP, ledger 13, treasury 14, revenue 15, assets 16, grants 18, payroll 19, debt 20 and reports 22 consume active references/calendar. Consumers never own copies. Events: MasterDataVersionActivated, AccountCombinationActivated, FinancialYearActivated, AccountingPeriodOpened, FinancialPeriodClosed, FinancialPeriodReopened. Outbox consumers invalidate caches and update read projections idempotently. Module 13 supplies close-readiness status; it does not own period state.

## 13. Security and audit requirements
Role-restricted access to chart/calendar administration; maker-checker for activation and close/reopen; no self-approval. Record before/after version hashes, actor, time, reason, evidence, correlation ID and approval. Closed period controls are enforced by ledger posting authorization and database/domain transaction, not frontend. Preserve historical account/calendar references. Monitor privileged changes.

## 14. Testing requirements
Test chart code uniqueness/effective dating, account hierarchy cycles, dimension combination rules, calendar overlap/period coverage, period transitions and permissions. Feature-test open/close/reopen endpoints and audit events. Integration-test consumers reject inactive combinations and closed periods. Concurrency-test simultaneous period close/post and ensure one serial outcome; verify failed activation/close rolls back with no partial status. Test KES decimal metadata and no float conversion.

## 15. Acceptance criteria
Exactly one authoritative financial year/calendar owner exists; periods belong to years, transitions are approved/audited, closed periods block posting, reopen is controlled, active master references are versioned, consumers can validate IDs without duplicate writes, and all lifecycle/API/security tests pass.

## 16. Unresolved business decisions
County finance authority must approve fiscal calendar dates, accounting basis, chart structure/codes, fund and economic classification taxonomy, dimension requirements, currency scope, period-close checklist, reopen authority, retention and effective-date policy. No statutory or official threshold is assumed.
