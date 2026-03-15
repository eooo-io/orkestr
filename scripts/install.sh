#!/usr/bin/env bash
set -euo pipefail

# Orkestr by eooo.ai — One-line install script
# Usage: curl -fsSL https://get.eooo.ai/install | bash
#
# This script:
# 1. Checks prerequisites (Docker, Docker Compose, Git)
# 2. Clones the repository (or updates if already cloned)
# 3. Copies .env.example → .env with sensible defaults
# 4. Builds and starts containers
# 5. Runs migrations and seeds the database
# 6. Prints access URLs

REPO_URL="https://github.com/eooo-io/agentis-studio.git"
INSTALL_DIR="${ORKESTR_DIR:-./orkestr}"
BRANCH="${ORKESTR_BRANCH:-main}"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

info()  { echo -e "${CYAN}[INFO]${NC} $*"; }
ok()    { echo -e "${GREEN}[OK]${NC} $*"; }
warn()  { echo -e "${YELLOW}[WARN]${NC} $*"; }
error() { echo -e "${RED}[ERROR]${NC} $*" >&2; }
die()   { error "$@"; exit 1; }

banner() {
  echo ""
  echo -e "${CYAN}╔═══════════════════════════════════════╗${NC}"
  echo -e "${CYAN}║      Orkestr by eooo.ai — Install     ║${NC}"
  echo -e "${CYAN}║   AI Skill & Agent Config Manager     ║${NC}"
  echo -e "${CYAN}╚═══════════════════════════════════════╝${NC}"
  echo ""
}

# ─── Prerequisites ──────────────────────────────────────────────────────────

check_prereqs() {
  local missing=0

  if ! command -v docker &>/dev/null; then
    error "Docker is not installed. Install it from https://docs.docker.com/get-docker/"
    missing=1
  fi

  if ! docker compose version &>/dev/null 2>&1; then
    if ! command -v docker-compose &>/dev/null; then
      error "Docker Compose is not installed. Install it from https://docs.docker.com/compose/install/"
      missing=1
    fi
  fi

  if ! command -v git &>/dev/null; then
    error "Git is not installed."
    missing=1
  fi

  if [ "$missing" -eq 1 ]; then
    die "Please install the missing prerequisites and try again."
  fi

  ok "Prerequisites satisfied (Docker, Docker Compose, Git)"
}

# ─── Docker Compose wrapper ─────────────────────────────────────────────────

dc() {
  if docker compose version &>/dev/null 2>&1; then
    docker compose "$@"
  else
    docker-compose "$@"
  fi
}

# ─── Clone / Update ─────────────────────────────────────────────────────────

clone_repo() {
  if [ -d "$INSTALL_DIR/.git" ]; then
    info "Existing installation found at $INSTALL_DIR — pulling latest..."
    (cd "$INSTALL_DIR" && git pull --ff-only origin "$BRANCH")
    ok "Repository updated"
  else
    info "Cloning repository into $INSTALL_DIR..."
    git clone --branch "$BRANCH" --depth 1 "$REPO_URL" "$INSTALL_DIR"
    ok "Repository cloned"
  fi
}

# ─── Environment Setup ──────────────────────────────────────────────────────

setup_env() {
  cd "$INSTALL_DIR"

  if [ ! -f .env ]; then
    info "Creating .env from .env.example..."
    cp .env.example .env

    # Generate a random APP_KEY
    local key
    key=$(openssl rand -base64 32 2>/dev/null || head -c 32 /dev/urandom | base64)
    if [[ "$OSTYPE" == "darwin"* ]]; then
      sed -i '' "s|^APP_KEY=.*|APP_KEY=base64:${key}|" .env
    else
      sed -i "s|^APP_KEY=.*|APP_KEY=base64:${key}|" .env
    fi

    ok ".env created with generated APP_KEY"
  else
    warn ".env already exists — skipping"
  fi
}

# ─── Build & Start ──────────────────────────────────────────────────────────

build_and_start() {
  info "Building Docker containers (this may take a few minutes on first run)..."
  dc build --quiet
  ok "Containers built"

  info "Starting containers..."
  dc up -d
  ok "Containers running"

  # Wait for MariaDB to be ready
  info "Waiting for database..."
  local retries=30
  while [ $retries -gt 0 ]; do
    if dc exec -T mariadb mariadb -u root -proot -e "SELECT 1" &>/dev/null; then
      break
    fi
    retries=$((retries - 1))
    sleep 2
  done

  if [ $retries -eq 0 ]; then
    die "Database did not become ready in time. Check 'docker compose logs mariadb'."
  fi
  ok "Database is ready"
}

# ─── Migrate & Seed ─────────────────────────────────────────────────────────

run_migrations() {
  info "Running migrations and seeding database..."
  dc exec -T php php artisan migrate --seed --force
  ok "Database migrated and seeded"
}

# ─── Build Frontend ─────────────────────────────────────────────────────────

build_frontend() {
  if dc exec -T php test -f /var/www/html/ui/package.json; then
    info "Building frontend assets..."
    dc exec -T php bash -c "cd ui && npm ci --silent && npm run build"
    ok "Frontend built"
  else
    warn "Frontend package.json not found in container — skipping frontend build"
  fi
}

# ─── Print Summary ──────────────────────────────────────────────────────────

print_summary() {
  echo ""
  echo -e "${GREEN}════════════════════════════════════════════${NC}"
  echo -e "${GREEN}  Installation complete!${NC}"
  echo -e "${GREEN}════════════════════════════════════════════${NC}"
  echo ""
  echo -e "  ${CYAN}Laravel API:${NC}     http://localhost:8000"
  echo -e "  ${CYAN}Filament Admin:${NC}  http://localhost:8000/admin"
  echo -e "  ${CYAN}React SPA:${NC}       http://localhost:5173 (run: cd ui && npm run dev)"
  echo ""
  echo -e "  ${CYAN}Default login:${NC}   admin@admin.com / password"
  echo ""
  echo -e "  Useful commands:"
  echo -e "    ${YELLOW}make logs${NC}     — View container logs"
  echo -e "    ${YELLOW}make shell${NC}    — Open a shell in the PHP container"
  echo -e "    ${YELLOW}make down${NC}     — Stop containers"
  echo -e "    ${YELLOW}make fresh${NC}    — Reset database"
  echo ""
  echo -e "  Documentation: https://docs.eooo.ai"
  echo ""
}

# ─── Main ────────────────────────────────────────────────────────────────────

main() {
  banner
  check_prereqs
  clone_repo
  setup_env
  build_and_start
  run_migrations
  build_frontend
  print_summary
}

main "$@"
