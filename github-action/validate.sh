#!/usr/bin/env bash
#
# Orkestr Skill Validator & Sync
#
# Validates .orkestr/skills/*.md files for correct YAML frontmatter,
# checks for duplicate IDs and broken include references, and optionally
# syncs skills to an Orkestr server.
#
# Environment variables (set by action.yml):
#   INPUT_MODE        — "validate" or "sync"
#   INPUT_SKILLS_PATH — path to skills directory
#   INPUT_SERVER_URL  — Orkestr server URL (sync mode)
#   INPUT_API_TOKEN   — Orkestr API token (sync mode)

set -euo pipefail

# ---------------------------------------------------------------------------
# Configuration
# ---------------------------------------------------------------------------

MODE="${INPUT_MODE:-validate}"
SKILLS_PATH="${INPUT_SKILLS_PATH:-.orkestr/skills}"
SERVER_URL="${INPUT_SERVER_URL:-}"
API_TOKEN="${INPUT_API_TOKEN:-}"

# Counters
TOTAL=0
VALID=0
INVALID=0
ERRORS=()

# Associative arrays for duplicate & include checking
declare -A SKILL_IDS        # id -> file path
declare -A SKILL_ID_LIST    # all discovered ids (for include resolution)
declare -a ALL_INCLUDES     # "file:include_ref" pairs

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

log_info()  { echo "::notice::$*"; }
log_warn()  { echo "::warning::$*"; }
log_error() { echo "::error::$*"; }

# Extract YAML frontmatter from a markdown file.
# Returns the text between the opening and closing "---" delimiters.
extract_frontmatter() {
  local file="$1"
  local in_frontmatter=false
  local frontmatter=""
  local line_num=0

  while IFS= read -r line || [[ -n "$line" ]]; do
    line_num=$((line_num + 1))

    if [[ $line_num -eq 1 ]]; then
      if [[ "$line" == "---" ]]; then
        in_frontmatter=true
        continue
      else
        # No frontmatter delimiter on line 1
        return 1
      fi
    fi

    if $in_frontmatter; then
      if [[ "$line" == "---" ]]; then
        echo "$frontmatter"
        return 0
      fi
      frontmatter+="$line"$'\n'
    fi
  done < "$file"

  # Reached EOF without closing delimiter
  return 1
}

# Extract a top-level YAML scalar value by key (simple grep-based).
# Handles both quoted and unquoted values.
yaml_get() {
  local yaml="$1"
  local key="$2"
  local value

  value=$(echo "$yaml" | grep -E "^${key}:" | head -1 | sed "s/^${key}:[[:space:]]*//" | sed 's/^["'"'"']//' | sed 's/["'"'"']$//' | xargs)
  echo "$value"
}

# Extract YAML array items from an inline array like [foo, bar] or a block list.
yaml_get_array() {
  local yaml="$1"
  local key="$2"

  # Try inline array first: key: [item1, item2]
  local inline
  inline=$(echo "$yaml" | grep -E "^${key}:" | head -1 | sed "s/^${key}:[[:space:]]*//" | xargs)

  if [[ "$inline" == "["*"]" ]]; then
    # Strip brackets and split by comma
    echo "$inline" | sed 's/^\[//' | sed 's/\]$//' | tr ',' '\n' | sed 's/^[[:space:]]*//' | sed 's/[[:space:]]*$//' | sed '/^$/d'
    return 0
  fi

  # Try block list:
  #   key:
  #     - item1
  #     - item2
  local in_block=false
  while IFS= read -r line; do
    if [[ "$line" =~ ^${key}: ]]; then
      in_block=true
      continue
    fi
    if $in_block; then
      if [[ "$line" =~ ^[[:space:]]+- ]]; then
        echo "$line" | sed 's/^[[:space:]]*-[[:space:]]*//' | xargs
      elif [[ "$line" =~ ^[[:space:]] ]]; then
        continue
      else
        break
      fi
    fi
  done <<< "$yaml"
}

# Print a formatted table row
table_row() {
  printf "| %-40s | %-8s | %-50s |\n" "$1" "$2" "$3"
}

# ---------------------------------------------------------------------------
# Validation
# ---------------------------------------------------------------------------

validate_skills() {
  local skills_dir="$SKILLS_PATH"

  if [[ ! -d "$skills_dir" ]]; then
    log_error "Skills directory not found: $skills_dir"
    echo "::set-output name=total::0"
    echo "::set-output name=valid::0"
    echo "::set-output name=invalid::0"
    exit 1
  fi

  # Collect all .md files
  local files=()
  while IFS= read -r -d '' file; do
    files+=("$file")
  done < <(find "$skills_dir" -name '*.md' -type f -print0 | sort -z)

  TOTAL=${#files[@]}

  if [[ $TOTAL -eq 0 ]]; then
    log_info "No skill files found in $skills_dir"
    echo "total=0" >> "$GITHUB_OUTPUT"
    echo "valid=0" >> "$GITHUB_OUTPUT"
    echo "invalid=0" >> "$GITHUB_OUTPUT"
    exit 0
  fi

  echo ""
  echo "## Orkestr Skill Validation Report"
  echo ""

  # ---- Pass 1: Parse and validate each file ----
  for file in "${files[@]}"; do
    local relative_path="${file#./}"
    local file_errors=()

    # Extract frontmatter
    local frontmatter
    if ! frontmatter=$(extract_frontmatter "$file"); then
      file_errors+=("Missing or malformed YAML frontmatter (no opening/closing --- delimiters)")
    fi

    if [[ ${#file_errors[@]} -eq 0 ]]; then
      # Validate required fields
      local skill_id
      skill_id=$(yaml_get "$frontmatter" "id")
      local skill_name
      skill_name=$(yaml_get "$frontmatter" "name")

      if [[ -z "$skill_id" ]]; then
        file_errors+=("Missing required field: id")
      fi

      if [[ -z "$skill_name" ]]; then
        file_errors+=("Missing required field: name")
      fi

      # Check for duplicate IDs
      if [[ -n "$skill_id" ]]; then
        if [[ -n "${SKILL_IDS[$skill_id]:-}" ]]; then
          file_errors+=("Duplicate skill ID '$skill_id' (also in ${SKILL_IDS[$skill_id]})")
        else
          SKILL_IDS[$skill_id]="$relative_path"
          SKILL_ID_LIST[$skill_id]=1
        fi
      fi

      # Collect includes for cross-reference checking (pass 2)
      local includes
      includes=$(yaml_get_array "$frontmatter" "includes")
      if [[ -n "$includes" ]]; then
        while IFS= read -r inc; do
          [[ -n "$inc" ]] && ALL_INCLUDES+=("${relative_path}:${inc}")
        done <<< "$includes"
      fi

      # Validate YAML syntax: check for common issues
      # Ensure frontmatter lines follow key: value pattern or are list items
      while IFS= read -r fm_line; do
        # Skip empty lines and comments
        [[ -z "$fm_line" || "$fm_line" =~ ^[[:space:]]*# ]] && continue
        # Skip list items
        [[ "$fm_line" =~ ^[[:space:]]*- ]] && continue
        # Top-level keys must have a colon
        if [[ "$fm_line" =~ ^[a-zA-Z_] && ! "$fm_line" =~ : ]]; then
          file_errors+=("Malformed YAML line: $fm_line")
        fi
      done <<< "$frontmatter"
    fi

    # Record results
    if [[ ${#file_errors[@]} -gt 0 ]]; then
      INVALID=$((INVALID + 1))
      for err in "${file_errors[@]}"; do
        ERRORS+=("${relative_path}|FAIL|${err}")
        log_error "file=${relative_path}::${err}"
      done
    else
      VALID=$((VALID + 1))
      ERRORS+=("${relative_path}|PASS|")
    fi
  done

  # ---- Pass 2: Check for broken includes ----
  for entry in "${ALL_INCLUDES[@]}"; do
    local source_file="${entry%%:*}"
    local include_ref="${entry#*:}"

    if [[ -z "${SKILL_ID_LIST[$include_ref]:-}" ]]; then
      # Check if it was already marked invalid for other reasons
      local already_failed=false
      for i in "${!ERRORS[@]}"; do
        if [[ "${ERRORS[$i]}" == "${source_file}|FAIL|"* ]]; then
          already_failed=true
          break
        fi
      done

      # Add broken include error
      ERRORS+=("${source_file}|FAIL|Broken include reference: '$include_ref' not found")
      log_error "file=${source_file}::Broken include reference: '$include_ref' not found"

      if ! $already_failed; then
        # Reclassify from PASS to FAIL if it was passing before
        for i in "${!ERRORS[@]}"; do
          if [[ "${ERRORS[$i]}" == "${source_file}|PASS|" ]]; then
            unset 'ERRORS[i]'
            VALID=$((VALID - 1))
            INVALID=$((INVALID + 1))
            break
          fi
        done
      fi
    fi
  done

  # ---- Print summary table ----
  echo ""
  table_row "File" "Status" "Details"
  table_row "----------------------------------------" "--------" "--------------------------------------------------"

  # Deduplicate: show each file once, collecting all errors
  declare -A FILE_STATUS
  declare -A FILE_DETAILS

  for entry in "${ERRORS[@]}"; do
    local e_file="${entry%%|*}"
    local rest="${entry#*|}"
    local e_status="${rest%%|*}"
    local e_detail="${rest#*|}"

    # Once a file is FAIL, keep it as FAIL
    if [[ "${FILE_STATUS[$e_file]:-}" != "FAIL" ]]; then
      FILE_STATUS[$e_file]="$e_status"
    fi

    if [[ -n "$e_detail" ]]; then
      if [[ -n "${FILE_DETAILS[$e_file]:-}" ]]; then
        FILE_DETAILS[$e_file]="${FILE_DETAILS[$e_file]}; $e_detail"
      else
        FILE_DETAILS[$e_file]="$e_detail"
      fi
    fi
  done

  for file_key in $(echo "${!FILE_STATUS[@]}" | tr ' ' '\n' | sort); do
    local status="${FILE_STATUS[$file_key]}"
    local details="${FILE_DETAILS[$file_key]:-OK}"
    if [[ "$status" == "PASS" ]]; then
      details="OK"
    fi
    table_row "$file_key" "$status" "$details"
  done

  echo ""
  echo "**Total: $TOTAL | Valid: $VALID | Invalid: $INVALID**"
  echo ""

  # Set outputs
  echo "total=$TOTAL" >> "$GITHUB_OUTPUT"
  echo "valid=$VALID" >> "$GITHUB_OUTPUT"
  echo "invalid=$INVALID" >> "$GITHUB_OUTPUT"

  if [[ $INVALID -gt 0 ]]; then
    return 1
  fi
  return 0
}

# ---------------------------------------------------------------------------
# Sync
# ---------------------------------------------------------------------------

sync_skills() {
  # Validate inputs for sync mode
  if [[ -z "$SERVER_URL" ]]; then
    log_error "server-url is required for sync mode"
    exit 1
  fi

  if [[ -z "$API_TOKEN" ]]; then
    log_error "api-token is required for sync mode"
    exit 1
  fi

  # Strip trailing slash from server URL
  SERVER_URL="${SERVER_URL%/}"

  local skills_dir="$SKILLS_PATH"

  if [[ ! -d "$skills_dir" ]]; then
    log_error "Skills directory not found: $skills_dir"
    exit 1
  fi

  # First run validation
  echo "--- Running validation before sync ---"
  echo ""
  if ! validate_skills; then
    log_error "Validation failed. Fix errors before syncing."
    exit 1
  fi

  echo ""
  echo "--- Syncing skills to Orkestr server ---"
  echo ""

  # Collect all skill files
  local files=()
  while IFS= read -r -d '' file; do
    files+=("$file")
  done < <(find "$skills_dir" -name '*.md' -type f -print0 | sort -z)

  local synced=0
  local created=0
  local updated=0
  local unchanged=0
  local failed=0
  local sync_errors=()

  for file in "${files[@]}"; do
    local relative_path="${file#./}"
    local content
    content=$(cat "$file")

    # POST each skill file to the server
    local response
    local http_code

    http_code=$(curl -s -o /tmp/orkestr_sync_response.json -w "%{http_code}" \
      -X POST \
      -H "Authorization: Bearer $API_TOKEN" \
      -H "Content-Type: application/json" \
      -H "Accept: application/json" \
      -d "$(jq -n --arg path "$relative_path" --arg content "$content" \
        '{path: $path, content: $content}')" \
      "${SERVER_URL}/api/import/skill" 2>/dev/null) || true

    case "$http_code" in
      200)
        local status
        status=$(jq -r '.status // "synced"' /tmp/orkestr_sync_response.json 2>/dev/null || echo "synced")
        case "$status" in
          created)  created=$((created + 1));   synced=$((synced + 1)) ;;
          updated)  updated=$((updated + 1));   synced=$((synced + 1)) ;;
          unchanged) unchanged=$((unchanged + 1)) ;;
          *)        synced=$((synced + 1)) ;;
        esac
        log_info "Synced: $relative_path ($status)"
        ;;
      201)
        created=$((created + 1))
        synced=$((synced + 1))
        log_info "Created: $relative_path"
        ;;
      *)
        failed=$((failed + 1))
        local error_msg
        error_msg=$(jq -r '.message // "Unknown error"' /tmp/orkestr_sync_response.json 2>/dev/null || echo "HTTP $http_code")
        sync_errors+=("$relative_path: $error_msg")
        log_error "Failed to sync $relative_path: $error_msg (HTTP $http_code)"
        ;;
    esac
  done

  # Clean up temp file
  rm -f /tmp/orkestr_sync_response.json

  # Print sync summary
  echo ""
  echo "## Orkestr Sync Summary"
  echo ""
  echo "| Metric      | Count |"
  echo "|-------------|-------|"
  printf "| Created     | %-5d |\n" "$created"
  printf "| Updated     | %-5d |\n" "$updated"
  printf "| Unchanged   | %-5d |\n" "$unchanged"
  printf "| Failed      | %-5d |\n" "$failed"
  printf "| **Total**   | %-5d |\n" "$((created + updated + unchanged + failed))"
  echo ""

  # Set outputs
  echo "synced=$synced" >> "$GITHUB_OUTPUT"

  if [[ ${#sync_errors[@]} -gt 0 ]]; then
    echo ""
    echo "### Sync Errors"
    echo ""
    for err in "${sync_errors[@]}"; do
      echo "- $err"
    done
    echo ""
  fi

  if [[ $failed -gt 0 ]]; then
    log_error "Sync completed with $failed failures"
    exit 1
  fi

  log_info "Sync completed: $created created, $updated updated, $unchanged unchanged"
}

# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

main() {
  echo "Orkestr Skill Validator v1.0.0"
  echo "Mode: $MODE"
  echo "Skills path: $SKILLS_PATH"
  echo ""

  case "$MODE" in
    validate)
      validate_skills
      ;;
    sync)
      sync_skills
      ;;
    *)
      log_error "Unknown mode: $MODE (expected 'validate' or 'sync')"
      exit 1
      ;;
  esac
}

main
