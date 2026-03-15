#!/usr/bin/env bash
set -euo pipefail

# Orkestr Production Readiness Validator
# Checks docker-compose.yml and .env for production best practices

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

pass=0
warn=0
fail=0

check_pass() { echo -e "  ${GREEN}✓${NC} $*"; pass=$((pass + 1)); }
check_warn() { echo -e "  ${YELLOW}⚠${NC} $*"; warn=$((warn + 1)); }
check_fail() { echo -e "  ${RED}✗${NC} $*"; fail=$((fail + 1)); }

echo ""
echo "Orkestr — Production Readiness Check"
echo "═════════════════════════════════════"
echo ""

# ─── .env checks ────────────────────────────────────────────────────────────

echo "Environment (.env):"

if [ ! -f .env ]; then
  check_fail ".env file not found"
else
  check_pass ".env file exists"

  # APP_DEBUG should be false
  if grep -qE '^APP_DEBUG=false' .env; then
    check_pass "APP_DEBUG=false"
  else
    check_fail "APP_DEBUG should be false in production"
  fi

  # APP_ENV should be production
  if grep -qE '^APP_ENV=production' .env; then
    check_pass "APP_ENV=production"
  else
    check_warn "APP_ENV is not set to production"
  fi

  # APP_KEY should be set and not empty
  if grep -qE '^APP_KEY=base64:.+' .env; then
    check_pass "APP_KEY is set"
  else
    check_fail "APP_KEY must be set (run: php artisan key:generate)"
  fi

  # DB password should not be default
  db_pass=$(grep '^DB_PASSWORD=' .env | cut -d= -f2-)
  if [ "$db_pass" = "root" ] || [ "$db_pass" = "password" ] || [ -z "$db_pass" ]; then
    check_fail "DB_PASSWORD is weak or default — change it"
  else
    check_pass "DB_PASSWORD is non-default"
  fi

  # Session should use database or redis in production
  session_driver=$(grep '^SESSION_DRIVER=' .env | cut -d= -f2-)
  if [ "$session_driver" = "file" ]; then
    check_warn "SESSION_DRIVER=file — consider database or redis for production"
  else
    check_pass "SESSION_DRIVER=$session_driver"
  fi

  # HTTPS / APP_URL
  app_url=$(grep '^APP_URL=' .env | cut -d= -f2-)
  if echo "$app_url" | grep -q '^https://'; then
    check_pass "APP_URL uses HTTPS"
  else
    check_warn "APP_URL does not use HTTPS ($app_url)"
  fi
fi

echo ""
echo "Docker Compose:"

# ─── docker-compose.yml checks ──────────────────────────────────────────────

compose_file="docker-compose.yml"
if [ ! -f "$compose_file" ]; then
  compose_file="docker-compose.yaml"
fi

if [ ! -f "$compose_file" ]; then
  check_fail "docker-compose.yml not found"
else
  check_pass "docker-compose file exists"

  # Check for restart policies
  if grep -q 'restart:' "$compose_file"; then
    check_pass "Restart policies defined"
  else
    check_warn "No restart policies — add 'restart: unless-stopped' for production"
  fi

  # Check for health checks
  if grep -q 'healthcheck:' "$compose_file"; then
    check_pass "Health checks defined"
  else
    check_warn "No health checks defined in docker-compose"
  fi

  # Check for volume mounts (data persistence)
  if grep -q 'volumes:' "$compose_file"; then
    check_pass "Volumes configured for data persistence"
  else
    check_warn "No volumes — database data may be lost on container restart"
  fi

  # Check for exposed ports on database
  if grep -A5 'mariadb:' "$compose_file" | grep -q '3306:3306'; then
    check_warn "MariaDB port 3306 is exposed — consider removing in production"
  else
    check_pass "MariaDB port not publicly exposed"
  fi
fi

echo ""
echo "Services:"

# ─── Running service checks ─────────────────────────────────────────────────

if command -v docker &>/dev/null; then
  if docker compose ps --format json 2>/dev/null | grep -q '"running"'; then
    check_pass "Containers are running"
  elif docker compose ps 2>/dev/null | grep -q 'Up'; then
    check_pass "Containers are running"
  else
    check_warn "Containers are not running (run: make up)"
  fi
else
  check_warn "Docker not available — cannot check running services"
fi

# ─── Summary ─────────────────────────────────────────────────────────────────

echo ""
echo "═════════════════════════════════════"
echo -e "  ${GREEN}Passed:${NC}   $pass"
echo -e "  ${YELLOW}Warnings:${NC} $warn"
echo -e "  ${RED}Failed:${NC}   $fail"
echo "═════════════════════════════════════"

if [ $fail -gt 0 ]; then
  echo -e "\n${RED}Fix the failures above before deploying to production.${NC}"
  exit 1
elif [ $warn -gt 0 ]; then
  echo -e "\n${YELLOW}Review the warnings above for production hardening.${NC}"
  exit 0
else
  echo -e "\n${GREEN}All checks passed — ready for production!${NC}"
  exit 0
fi
