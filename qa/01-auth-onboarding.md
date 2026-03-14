# 01 — Authentication & Onboarding

## Registration

### TC-01-001: Successful registration
**Priority:** P0
**Preconditions:** No existing account with test email
**Steps:**
1. Navigate to `/register`
2. Enter name, email, password, confirm password
3. Click "Create Account"
**Expected:** Account created, redirected to `/projects`. Welcome toast shown.
**Notes:** Verify user record exists in DB. Check password is hashed.

### TC-01-002: Registration with duplicate email
**Priority:** P1
**Preconditions:** User admin@admin.com already exists
**Steps:**
1. Navigate to `/register`
2. Enter name, email: admin@admin.com, valid password
3. Submit
**Expected:** Validation error: "email already taken." No redirect.

### TC-01-003: Registration with weak password
**Priority:** P2
**Preconditions:** None
**Steps:**
1. Navigate to `/register`
2. Enter password: "123"
3. Submit
**Expected:** Validation error on password field (minimum length, complexity).

### TC-01-004: Registration with mismatched passwords
**Priority:** P1
**Preconditions:** None
**Steps:**
1. Enter password: "SecurePass123!"
2. Enter confirm: "DifferentPass456!"
3. Submit
**Expected:** Validation error: passwords don't match.

### TC-01-005: Registration form — empty fields
**Priority:** P2
**Preconditions:** None
**Steps:**
1. Click submit with all fields empty
**Expected:** Validation errors on all required fields. No request sent.

## Login

### TC-01-006: Successful login
**Priority:** P0
**Preconditions:** Seeded user exists (admin@admin.com / password)
**Steps:**
1. Navigate to `/login`
2. Enter admin@admin.com / password
3. Submit
**Expected:** Redirected to `/projects`. Session cookie set.

### TC-01-007: Login with wrong password
**Priority:** P0
**Preconditions:** User exists
**Steps:**
1. Enter correct email, wrong password
2. Submit
**Expected:** Error message: "Invalid credentials." No redirect. No session.

### TC-01-008: Login with non-existent email
**Priority:** P1
**Preconditions:** None
**Steps:**
1. Enter nonexistent@example.com
2. Submit
**Expected:** Generic error (don't reveal whether email exists).

### TC-01-009: Session persistence across page refresh
**Priority:** P0
**Preconditions:** Logged in
**Steps:**
1. Navigate to `/projects`
2. Hard refresh (F5 / Ctrl+R)
**Expected:** Still logged in, projects load. Not redirected to login.

### TC-01-010: Logout
**Priority:** P0
**Preconditions:** Logged in
**Steps:**
1. Click logout (if available in UI) or navigate to logout endpoint
**Expected:** Session destroyed. Redirected to `/login`. API calls return 401.

### TC-01-011: Protected routes redirect to login
**Priority:** P0
**Preconditions:** Not logged in (no session)
**Steps:**
1. Navigate directly to `/projects`
2. Navigate directly to `/agents`
3. Navigate directly to `/settings`
**Expected:** All redirect to `/login`.

### TC-01-012: Login page redirects if already authenticated
**Priority:** P2
**Preconditions:** Already logged in
**Steps:**
1. Navigate to `/login`
**Expected:** Redirected to `/projects` (or dashboard).

## Landing Page

### TC-01-013: Landing page renders without auth
**Priority:** P0
**Preconditions:** Not logged in
**Steps:**
1. Navigate to `/`
**Expected:** Landing page renders with hero, features, pricing, FAQ. No errors in console.

### TC-01-014: Landing page — dark/light mode toggle
**Priority:** P3
**Preconditions:** On landing page
**Steps:**
1. Click theme toggle
2. Verify colors switch
3. Toggle back
**Expected:** Theme switches cleanly. No layout shift. Persists on refresh.

### TC-01-015: Landing page — mobile responsive
**Priority:** P1
**Preconditions:** None
**Steps:**
1. Open landing page at 375px width (mobile)
2. Check hero, features grid, pricing cards, FAQ
3. Open mobile menu
**Expected:** All sections stack properly. No horizontal scroll. Mobile menu works.

### TC-01-016: Landing page — navigation links
**Priority:** P2
**Preconditions:** On landing page
**Steps:**
1. Click each nav link (Features, How It Works, Pricing, FAQ)
**Expected:** Smooth scroll to corresponding section.

### TC-01-017: Landing page — CTA buttons
**Priority:** P1
**Preconditions:** On landing page
**Steps:**
1. Click "Get Started Free"
2. Click "Create Your Account"
**Expected:** Both navigate to `/register`.

### TC-01-018: Landing page — Sign In link
**Priority:** P1
**Preconditions:** On landing page, not logged in
**Steps:**
1. Click "Sign In"
**Expected:** Navigates to `/login`.
