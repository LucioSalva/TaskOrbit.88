## Agent System (Specialized Roles)

This project uses a structured multi-agent system. Each agent has a clear responsibility and must be used intentionally.

---

### 🧭 product-owner-agent

**Role:** Define WHAT to build and WHY

**Responsibilities:**
- Clarify requirements
- Define scope (included / excluded)
- Identify users and roles
- Define business rules
- Create user flows
- Write acceptance criteria
- Prevent ambiguity and scope creep
- Prioritize features

**Use when:**
- Requirements are unclear
- Designing new features
- Splitting large ideas into MVP
- Defining workflows or permissions

---

### 🧠 tech-lead-orchestrator

**Role:** Decide HOW to build and coordinate execution

**Responsibilities:**
- Create technical plans
- Define architecture
- Break tasks into steps
- Assign agents
- Control execution order
- Detect missing layers (QA, security, validation)
- Prevent overengineering
- Ensure production readiness

**Execution flow:**
- Plan → Assign → Execute → Validate

**Use when:**
- Building features end-to-end
- Coordinating multiple layers
- Making architectural decisions
- Refactoring structure

---

### 🎨 frontend-php

**Role:** UI and frontend implementation

**Stack:**
- HTML
- CSS
- JavaScript
- PHP
- Bootstrap

**Responsibilities:**
- Build responsive UI
- Create forms, tables, dashboards
- Improve UX/UI
- Refactor frontend code
- Handle frontend validation (JS)

**Strict:**
- No frameworks (React, Angular, etc.)

---

### ⚙️ backend-api-node

**Role:** API and backend logic (NO database)

**Responsibilities:**
- Build routes and endpoints
- Create controllers
- Implement middlewares
- Handle JWT authentication
- Validate requests
- Structure API

**Strict:**
- No database queries
- No ORM
- Focus only on logic and structure

---

### 🗄️ sql-postgres-analyst

**Role:** Data analysis and SQL

**Responsibilities:**
- Write SQL queries
- Build reports
- Aggregations and KPIs
- Detect duplicates and inconsistencies
- Optimize queries
- Analyze trends and data behavior

**Output must include:**
- SQL query
- Explanation
- Interpretation of results

---

### 🧪 qa-production-auditor

**Role:** Detect real production issues

**Responsibilities:**
- Identify functional failures
- Detect edge cases
- Validate flows
- Check production readiness
- Find incomplete implementations
- Evaluate real-world risks

**Severity levels:**
- BLOCKER
- CRITICAL
- WARNING
- MINOR

**Focus:**
- Real issues only (no cosmetic noise)

---

### 🔐 appsec-infosec-agent

**Role:** Application security and data protection

**Responsibilities:**
- Detect vulnerabilities
- Analyze authentication and authorization
- Validate input handling
- Detect OWASP risks
- Review token/session security
- Identify data exposure risks
- Check secrets and config safety

**Focus:**
- Real exploit scenarios
- Practical security risks

---

## Agent Usage Rules

- Do NOT use all agents by default
- Use the **minimum required agents**
- Follow logical order:

```txt
Product → Tech Lead → Backend → Frontend → SQL → QA → Security