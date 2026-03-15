# Company Formation: eooo.io — Action Plan

> Founding eooo.io as a US company with eooo.ai as product line.
> Solo founder, self-hosted license revenue model for Orkestr.

---

## Step 1: Choose Entity Type + State

**Recommended: Delaware LLC** (convert to C-Corp later if raising)

Why Delaware over Wyoming for this situation:
- Building enterprise infrastructure software — Delaware's Chancery Court and case law are what enterprise customers and future investors expect
- If raising VC later, converting a Delaware LLC to a Delaware C-Corp is the cleanest path
- US residence address available; form in Delaware and use a registered agent there ($50-150/yr)

**Two paths to formation:**

| Path | Cost | What you get | Timeline |
|------|------|-------------|----------|
| **Stripe Atlas** | $500 | Delaware LLC + EIN + Mercury bank account + Stripe payments + legal templates | ~3-5 business days |
| **DIY via Delaware Division of Corporations** | ~$140 (filing) + $50-150/yr (registered agent) | Just the LLC, you handle everything else | ~1-2 weeks |

**Recommendation: Stripe Atlas.** For $500 you skip weeks of admin — they handle formation, EIN application, bank account, and give you operating agreement templates.

## Step 2: Tax Considerations (US + Germany)

This is the one area where **professional advice is required**, due to dual-residency situation:

- **US taxes:** As a single-member LLC, income passes through to personal return (Form 1040 + Schedule C). Need an EIN (Employer Identification Number).
- **Germany:** If still a German tax resident (183+ days/year, or registered address), Germany will want to tax worldwide income. The US-Germany tax treaty prevents double taxation, but filing must be done correctly in both countries.
- **Critical:** Before earning revenue, engage a **cross-border tax advisor** who knows US-Germany treaty. Services like Greenback Expat Tax or 1040 Abroad specialize in this. Budget ~$500-1500 for the first consultation + filing setup.
- **Once relocated:** Deregistering from Germany (Abmeldung) and being only US tax resident simplifies enormously — just US taxes.

## Step 3: Banking & Payments

- **Mercury** (comes with Stripe Atlas, or sign up independently) — best startup bank, no fees, great API
- **Stripe** for payment processing — needed for commercial licenses
- Keep personal and business finances completely separate from day one

## Step 4: Intellectual Property

| Action | Cost | Priority |
|--------|------|----------|
| **USPTO trademark "eooo"** | ~$250-350/class (use TEAS Plus) | HIGH — do within first month |
| **USPTO trademark "Orkestr"** | ~$250-350/class | HIGH — same filing |
| **Domain verification** | eooo.io, eooo.ai already owned | DONE |
| **Copyright notice** | Free — add to LICENSE file | Do with launch |

File in **Class 9** (computer software) and **Class 42** (SaaS/technology services). Can file yourself via USPTO's TEAS system, or use a service like Trademarkia (~$200 + filing fees).

## Step 5: Licensing & Legal Docs

For a self-hosted infrastructure product:

1. **Source license** — Choose one:
   - **AGPL-3.0** for open source + separate commercial license (MongoDB/Sentry model)
   - **BSL (Business Source License)** — source-available, converts to open source after X years (MariaDB/HashiCorp model)
   - **Fair Source** — newer framework, similar intent to BSL

2. **Commercial License Agreement** — for enterprise self-hosted deployments. Covers: grant of rights, restrictions, support terms, warranty disclaimer, liability cap. Templates available from Fair Source or adapt from Sentry's.

3. **Terms of Service** — for the cloud tier / website

4. **Privacy Policy** — required if collecting any user data (even analytics)

## Step 6: Pre-Launch Checklist

Before accepting first customer payment:

- [ ] LLC formed and EIN obtained
- [ ] Business bank account open
- [ ] Stripe connected for payments
- [ ] Trademark applications filed
- [ ] License file in repo (AGPL or BSL)
- [ ] Commercial license agreement drafted
- [ ] Terms of service on website
- [ ] Privacy policy on website
- [ ] Cross-border tax advisor engaged (if still in Germany)
- [ ] Business insurance (optional but smart — general liability + E&O, ~$500-1000/yr)

## Step 7: First Customers Strategy

Once the legal foundation is set:

1. **Demo video** (2-3 min) showing the "configure once, sync everywhere" workflow
2. **Launch on Hacker News** — "Show HN: Self-hosted agent orchestration for teams using Claude, Cursor, Copilot"
3. **r/selfhosted** and **r/LocalLLaMA** posts
4. **Dev.to / Hashnode** article on the problem being solved
5. **GitHub repo visibility** — clear value prop in README, screenshots, one-line install
6. **Pricing page** — published pricing signals seriousness:
   - **Free** — OSS, community support
   - **Pro** ($49/mo) — commercial license, email support
   - **Enterprise** (custom) — SLA + dedicated support

## Key Decisions Pending

- [ ] Stripe Atlas vs. DIY formation?
- [ ] Which US state is residence in? (affects foreign-qualification of Delaware LLC)
- [ ] AGPL vs. BSL vs. Fair Source license choice?
- [ ] Relocate timeline?
