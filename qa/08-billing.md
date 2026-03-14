# 08 — Billing & Subscriptions

## Plan Display

### TC-08-001: Billing page — plan cards render
**Priority:** P0
**Preconditions:** Logged in, navigate to `/billing`
**Steps:**
1. View billing page
**Expected:** Three plans displayed: Free ($0), Pro ($19/mo), Team ($39/seat/mo). Features listed for each.

### TC-08-002: Monthly/yearly toggle
**Priority:** P1
**Preconditions:** On billing page
**Steps:**
1. Toggle between monthly and yearly pricing
**Expected:** Prices update. Yearly shows discount (if applicable).

### TC-08-003: Current plan highlighted
**Priority:** P1
**Preconditions:** User on Free plan
**Steps:**
1. View billing page
**Expected:** Free plan shows "Current Plan" badge. Upgrade buttons on Pro and Team.

## Subscription Flow

### TC-08-004: Subscribe to Pro — Stripe checkout
**Priority:** P0
**Preconditions:** On Free plan, Stripe configured
**Steps:**
1. Click "Start Free Trial" or "Upgrade" on Pro plan
2. Complete Stripe checkout (use test card 4242 4242 4242 4242)
**Expected:** Subscription created. Redirected back to billing page. Plan shows as Pro. Features unlocked.

### TC-08-005: Subscribe to Team
**Priority:** P0
**Preconditions:** On Free or Pro plan
**Steps:**
1. Click upgrade on Team plan
2. Complete Stripe checkout
**Expected:** Team subscription active. Per-seat billing starts.

### TC-08-006: Change plan — downgrade Pro to Free
**Priority:** P1
**Preconditions:** On Pro plan
**Steps:**
1. Click "Change Plan" → select Free
2. Confirm
**Expected:** Downgrade scheduled for end of billing period. Warning about features that will be lost. Projects over limit handled gracefully.

### TC-08-007: Cancel subscription
**Priority:** P0
**Preconditions:** Active paid subscription
**Steps:**
1. Click "Cancel Subscription"
2. Confirm
**Expected:** Subscription cancelled. Access continues until end of billing period. Status shows "Cancelling."

### TC-08-008: Resume cancelled subscription
**Priority:** P1
**Preconditions:** Subscription cancelled but still in billing period
**Steps:**
1. Click "Resume Subscription"
**Expected:** Cancellation reversed. Subscription continues. Status back to "Active."

## Payment Management

### TC-08-009: Update payment method
**Priority:** P1
**Preconditions:** Active subscription
**Steps:**
1. Click "Update Payment Method"
2. Enter new card details via Stripe Elements
3. Confirm
**Expected:** Payment method updated. Confirmation shown.

### TC-08-010: View invoices
**Priority:** P2
**Preconditions:** At least one invoice exists
**Steps:**
1. View invoices section
**Expected:** Invoice list with date, amount, status. Download link for each.

## Usage Tracking

### TC-08-011: Usage metrics display
**Priority:** P1
**Preconditions:** User has executed agents this month
**Steps:**
1. View usage section on billing page
**Expected:** Shows tokens used this period, API calls, sync operations. Progress bar against plan limits.

### TC-08-012: Usage — approaching limit warning
**Priority:** P1
**Preconditions:** Usage at 80%+ of plan limit
**Steps:**
1. View billing page
**Expected:** Warning banner: "You've used 85% of your monthly token allowance." Upgrade CTA.

## Tier Enforcement

### TC-08-013: Free tier — project limit enforced
**Priority:** P0
**Preconditions:** Free plan, already has 3 projects
**Steps:**
1. Try to create a 4th project
**Expected:** Blocked with message: "Free plan allows up to 3 projects. Upgrade to Pro for unlimited."

### TC-08-014: Free tier — gated features
**Priority:** P1
**Preconditions:** Free plan
**Steps:**
1. Try to publish to marketplace
2. Try to access advanced analytics
3. Try to use AI generation (if gated)
**Expected:** Each blocked with upgrade prompt. Feature visible but locked.

### TC-08-015: Pro tier — all features unlocked
**Priority:** P1
**Preconditions:** Pro plan
**Steps:**
1. Create unlimited projects
2. Publish to marketplace
3. Access analytics
4. Use AI generation
**Expected:** All Pro features accessible without restriction.

### TC-08-016: Stripe Connect — creator earnings
**Priority:** P2
**Preconditions:** Pro user with marketplace sales
**Steps:**
1. Set up Stripe Connect
2. View earnings dashboard
**Expected:** Connect status shown. Earnings from marketplace sales tracked. Payout info displayed.
