# Security Cleanup: .env History Removal

## Problem

The `.env` file was committed to git history in commits:
- `b81f254` — creacion e implementacion de TaskOrbit
- `baff819` — se esta agregando la parte legal

This means database credentials, API tokens, and other secrets are exposed in the git history even though `.env` is now untracked.

## Step 1: Remove .env from Git History

### Option A: Using git filter-repo (recommended)

```bash
# Install git filter-repo if not available
pip install git-filter-repo

# Remove .env from all history
git filter-repo --path .env --invert-paths

# Force push to overwrite remote history
git push origin --force --all
git push origin --force --tags
```

### Option B: Using BFG Repo-Cleaner

```bash
# Download BFG from https://rtyley.github.io/bfg-repo-cleaner/
java -jar bfg.jar --delete-files .env

# Clean up
git reflog expire --expire=now --all
git gc --prune=now --aggressive

# Force push
git push origin --force --all
```

## Step 2: Rotate ALL Credentials

After removing history, assume all values from .env are compromised. Rotate:

1. **Database password** (`DB_PASSWORD`)
   - Change PostgreSQL password: `ALTER USER postgres WITH PASSWORD 'new_secure_password';`
   - Update .env with new password

2. **Session name** (`SESSION_NAME`)
   - Already updated to `taskorbit_session`

## Step 3: Verify Cleanup

```bash
# Verify .env is not in current tracking
git ls-files .env
# Expected: no output

# Verify .env is not in history (after filter-repo)
git log --all --full-history -- .env
# Expected: no output

# Verify .gitignore includes .env
grep "^\.env$" .gitignore
# Expected: .env
```

## Step 4: Notify All Contributors

After force pushing:
1. All team members must re-clone the repository or run:
   ```bash
   git fetch origin
   git reset --hard origin/main
   ```
2. Each contributor must create their own `.env` from `.env.example`

## WARNING

- Force push rewrites history for ALL collaborators
- Coordinate with the team before executing
- Back up the repository before running filter-repo/BFG
- All open PRs and branches will need to be rebased
