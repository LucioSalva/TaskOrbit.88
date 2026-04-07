#!/bin/bash
# Orchestration script for the subtarea assign e2e tests.
# Runs each test case in its own PHP subprocess (because Controller::json exits).
set -u

cd /var/www/html || exit 1

# --- Pick fixture: first active subtarea in the live DB ---
FIX_ID=$(php -r '
$db = new PDO("pgsql:host=host.docker.internal;port=5432;dbname=TaskOrbit", "postgres", "admin");
$row = $db->query("SELECT id FROM subtareas WHERE deleted_at IS NULL ORDER BY id LIMIT 1")->fetch();
echo $row ? $row["id"] : 0;
')
if [[ -z "$FIX_ID" || "$FIX_ID" == "0" ]]; then
  echo "FATAL: no subtareas in DB" >&2
  exit 2
fi
echo ">>> Using subtarea fixture id=$FIX_ID"

# Reset to NULL before tests
php -r '
$db = new PDO("pgsql:host=host.docker.internal;port=5432;dbname=TaskOrbit", "postgres", "admin");
$db->prepare("UPDATE subtareas SET usuario_asignado_id = NULL WHERE id = ?")->execute(["'$FIX_ID'"]);
'

PASS=0
FAIL=0
LOG=()

run_case() {
  local label="$1"
  local sid="$2"
  local user="$3"
  local expected="$4"
  local msg="${5:-}"

  echo ""
  echo "---- $label ----"
  # stderr of the child carries STATUS/BODY/DBAFTER/RESULT lines
  out=$(php bin/test_subtarea_assign.php "$sid" "$user" "$expected" "$msg" 2>&1 >/dev/null)
  echo "$out"
  if echo "$out" | grep -q "RESULT=PASS"; then
    PASS=$((PASS+1))
    LOG+=("[ OK ] $label")
  else
    FAIL=$((FAIL+1))
    LOG+=("[FAIL] $label")
  fi
}

# CASE 1 — happy path
run_case "CASE 1  happy path: assign to ADMIN id=11" "$FIX_ID" "11" 200 ""

# CASE 2 — invalid user id
run_case "CASE 2  invalid user id=999999" "$FIX_ID" "999999" 422 "no existe"

# CASE 3 — nonexistent subtarea
run_case "CASE 3  subtarea 999999 not found" "999999" "11" 404 "no encontrada"

# CASE 4 — multiple reassignments
run_case "CASE 4.a reassign to USER id=12" "$FIX_ID" "12" 200 ""
run_case "CASE 4.b reassign to ADMIN id=13" "$FIX_ID" "13" 200 ""
run_case "CASE 4.c reassign to USER id=14" "$FIX_ID" "14" 200 ""

# Extra — GOD cannot be assigned
run_case "EXTRA   cannot assign to GOD id=9" "$FIX_ID" "9" 422 "GOD"

# Extra — empty = unassign
run_case "EXTRA   unassign (empty string)" "$FIX_ID" "" 200 ""

# Extra — non-numeric -> 400
run_case "EXTRA   non-numeric 'abc' -> 400" "$FIX_ID" "abc" 400 "inv"

# Final cleanup
php -r '
$db = new PDO("pgsql:host=host.docker.internal;port=5432;dbname=TaskOrbit", "postgres", "admin");
$db->prepare("UPDATE subtareas SET usuario_asignado_id = NULL WHERE id = ?")->execute(["'$FIX_ID'"]);
'
echo ""
echo "==================== RESULTS ===================="
for line in "${LOG[@]}"; do echo "$line"; done
echo ""
echo "PASSED: $PASS"
echo "FAILED: $FAIL"
[[ $FAIL -eq 0 ]] || exit 1
exit 0
