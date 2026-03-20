import { useEffect, useState } from 'react'
import {
  fetchBillingPlans,
  fetchBillingStatus,
  fetchUsage,
  fetchInvoices,
  subscribeToPlan,
  changePlan,
  cancelSubscription,
  resumeSubscription,
  setupStripeConnect,
  fetchEarnings,
} from '@/api/client'
import { useConfirm } from '@/hooks/useConfirm'
import { useAppStore } from '@/store/useAppStore'
import type { BillingPlan, BillingStatus, UsageSummary } from '@/types'

export function Billing() {
  const confirm = useConfirm()
  const [plans, setPlans] = useState<BillingPlan[]>([])
  const [status, setStatus] = useState<BillingStatus | null>(null)
  const [usage, setUsage] = useState<UsageSummary | null>(null)
  const [invoices, setInvoices] = useState<Array<{ id: string; date: string; total: string; status: string; pdf_url: string }>>([])
  const [earnings, setEarnings] = useState<{ total_earned: number; pending: number; payout_count: number } | null>(null)
  const [interval, setInterval] = useState<'monthly' | 'yearly'>('monthly')
  const [loading, setLoading] = useState(true)
  const [actionLoading, setActionLoading] = useState(false)
  const { showToast } = useAppStore()

  useEffect(() => {
    loadAll()
  }, [])

  const loadAll = async () => {
    setLoading(true)
    try {
      const [p, s, u, i, e] = await Promise.all([
        fetchBillingPlans(),
        fetchBillingStatus().catch(() => null),
        fetchUsage().catch(() => null),
        fetchInvoices().catch(() => []),
        fetchEarnings().catch(() => null),
      ])
      setPlans(p)
      setStatus(s)
      setUsage(u)
      setInvoices(i)
      setEarnings(e)
    } finally {
      setLoading(false)
    }
  }

  const handleSubscribe = async (planSlug: string) => {
    setActionLoading(true)
    try {
      await subscribeToPlan(planSlug, interval)
      showToast(`Subscribed to ${planSlug}`)
      await loadAll()
    } catch {
      showToast('Subscription failed', 'error')
    } finally {
      setActionLoading(false)
    }
  }

  const handleChangePlan = async (planSlug: string) => {
    setActionLoading(true)
    try {
      await changePlan(planSlug, interval)
      showToast('Plan changed')
      await loadAll()
    } catch {
      showToast('Plan change failed', 'error')
    } finally {
      setActionLoading(false)
    }
  }

  const handleCancel = async () => {
    if (!(await confirm({ message: 'Cancel your subscription? You\'ll retain access until the end of your billing period.', title: 'Confirm Cancellation' }))) return
    setActionLoading(true)
    try {
      await cancelSubscription()
      showToast('Subscription cancelled')
      await loadAll()
    } catch {
      showToast('Cancel failed', 'error')
    } finally {
      setActionLoading(false)
    }
  }

  const handleResume = async () => {
    setActionLoading(true)
    try {
      await resumeSubscription()
      showToast('Subscription resumed')
      await loadAll()
    } catch {
      showToast('Resume failed', 'error')
    } finally {
      setActionLoading(false)
    }
  }

  const handleConnectSetup = async () => {
    try {
      const result = await setupStripeConnect()
      if (result.onboarding_url) {
        window.location.href = result.onboarding_url
      } else if (result.dashboard_url) {
        window.open(result.dashboard_url, '_blank')
      }
    } catch {
      showToast('Connect setup failed', 'error')
    }
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center h-screen">
        <div className="animate-pulse text-muted-foreground">Loading billing...</div>
      </div>
    )
  }

  const currentPlan = status?.plan?.slug ?? 'free'
  const tokenPercent = usage?.summary?.llm_tokens
    ? Math.min(100, Math.round((usage.summary.llm_tokens.used / Math.max(1, usage.summary.llm_tokens.included)) * 100))
    : 0

  return (
    <div className="max-w-6xl mx-auto px-6 py-8 space-y-10">
      <div>
        <h1 className="text-2xl font-bold text-white">Billing & Subscription</h1>
        <p className="text-sm text-zinc-400 mt-1">Manage your plan, usage, and payment methods.</p>
      </div>

      {/* Current Status */}
      {status && (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div className="p-4 bg-zinc-900 border border-zinc-800 rounded-lg">
            <div className="text-xs text-zinc-500 uppercase tracking-wider">Current Plan</div>
            <div className="text-lg font-semibold text-white mt-1">{status.plan.name}</div>
            {status.subscription && (
              <div className="text-xs text-zinc-400 mt-1">
                {status.subscription.cancelled
                  ? `Cancelling ${status.subscription.ends_at ? 'on ' + new Date(status.subscription.ends_at).toLocaleDateString() : ''}`
                  : `Status: ${status.subscription.status}`}
              </div>
            )}
            {status.subscription?.on_grace_period && (
              <button
                onClick={handleResume}
                disabled={actionLoading}
                className="mt-2 text-xs text-blue-400 hover:text-blue-300"
              >
                Resume subscription
              </button>
            )}
          </div>

          <div className="p-4 bg-zinc-900 border border-zinc-800 rounded-lg">
            <div className="text-xs text-zinc-500 uppercase tracking-wider">Token Usage (This Month)</div>
            <div className="text-lg font-semibold text-white mt-1">
              {(status.usage.tokens_used).toLocaleString()} / {status.usage.tokens_included.toLocaleString()}
            </div>
            <div className="w-full bg-zinc-700 rounded-full h-2 mt-2">
              <div
                className={`h-2 rounded-full transition-all ${tokenPercent >= 90 ? 'bg-red-500' : tokenPercent >= 70 ? 'bg-yellow-500' : 'bg-blue-500'}`}
                style={{ width: `${tokenPercent}%` }}
              />
            </div>
            <div className="text-xs text-zinc-500 mt-1">{status.usage.tokens_remaining.toLocaleString()} remaining</div>
          </div>

          <div className="p-4 bg-zinc-900 border border-zinc-800 rounded-lg">
            <div className="text-xs text-zinc-500 uppercase tracking-wider">Payment Method</div>
            {status.payment_method ? (
              <div className="text-lg font-semibold text-white mt-1">
                {status.payment_method.type} ****{status.payment_method.last_four}
              </div>
            ) : (
              <div className="text-sm text-zinc-400 mt-1">No payment method</div>
            )}
          </div>
        </div>
      )}

      {/* Plans */}
      <div>
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg font-semibold text-white">Plans</h2>
          <div className="flex bg-zinc-800 rounded-lg p-0.5">
            <button
              onClick={() => setInterval('monthly')}
              className={`px-3 py-1 text-sm rounded-md transition-all ${interval === 'monthly' ? 'bg-zinc-700 text-white' : 'text-zinc-400'}`}
            >
              Monthly
            </button>
            <button
              onClick={() => setInterval('yearly')}
              className={`px-3 py-1 text-sm rounded-md transition-all ${interval === 'yearly' ? 'bg-zinc-700 text-white' : 'text-zinc-400'}`}
            >
              Yearly (save 17%)
            </button>
          </div>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          {plans.map((plan) => {
            const isCurrent = plan.slug === currentPlan
            const price = interval === 'yearly' ? plan.price_yearly : plan.price_monthly

            return (
              <div
                key={plan.slug}
                className={`p-5 rounded-lg border ${
                  isCurrent ? 'border-blue-500/50 bg-blue-500/5' : 'border-zinc-800 bg-zinc-900'
                }`}
              >
                <div className="flex items-center justify-between">
                  <h3 className="text-white font-semibold">{plan.name}</h3>
                  {isCurrent && (
                    <span className="text-[10px] px-2 py-0.5 bg-blue-500/20 text-blue-400 rounded-full uppercase font-medium">
                      Current
                    </span>
                  )}
                </div>
                <div className="mt-2">
                  <span className="text-2xl font-bold text-white">{price}</span>
                  <span className="text-zinc-500 text-sm">/{interval === 'yearly' ? 'yr' : 'mo'}</span>
                </div>
                <p className="text-xs text-zinc-400 mt-2">{plan.description}</p>

                <div className="mt-4 space-y-1.5 text-xs text-zinc-300">
                  <div>{plan.limits.max_projects === 999 ? 'Unlimited' : plan.limits.max_projects} projects</div>
                  <div>{plan.limits.max_skills_per_project === 999 ? 'Unlimited' : plan.limits.max_skills_per_project} skills/project</div>
                  <div>{plan.limits.max_providers} providers</div>
                  <div>{plan.limits.max_members} team member{plan.limits.max_members !== 1 ? 's' : ''}</div>
                  <div>{plan.limits.included_tokens_monthly > 0 ? (plan.limits.included_tokens_monthly / 1000).toFixed(0) + 'k' : 'No'} tokens/month</div>
                </div>

                <div className="mt-3 space-y-1 text-xs">
                  {Object.entries(plan.features).map(([feature, enabled]) => (
                    <div key={feature} className={enabled ? 'text-green-400' : 'text-zinc-600'}>
                      {enabled ? '+' : '-'} {feature.replace(/_/g, ' ')}
                    </div>
                  ))}
                </div>

                <div className="mt-4">
                  {isCurrent ? (
                    status?.subscription && !status.subscription.cancelled ? (
                      <button
                        onClick={handleCancel}
                        disabled={actionLoading}
                        className="w-full py-1.5 text-sm text-zinc-400 border border-zinc-700 rounded-lg hover:border-red-500/50 hover:text-red-400 transition-all"
                      >
                        Cancel
                      </button>
                    ) : null
                  ) : (
                    <button
                      onClick={() =>
                        status?.subscription ? handleChangePlan(plan.slug) : handleSubscribe(plan.slug)
                      }
                      disabled={actionLoading}
                      className="w-full py-1.5 text-sm bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white rounded-lg transition-all"
                    >
                      {status?.subscription ? 'Switch' : 'Subscribe'}
                    </button>
                  )}
                </div>
              </div>
            )
          })}
        </div>
      </div>

      {/* Usage Details */}
      {usage && (
        <div>
          <h2 className="text-lg font-semibold text-white mb-4">Usage This Period</h2>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div className="p-4 bg-zinc-900 border border-zinc-800 rounded-lg">
              <div className="text-xs text-zinc-500">LLM Tokens</div>
              <div className="text-xl font-bold text-white">{usage.summary.llm_tokens.used.toLocaleString()}</div>
              <div className="text-xs text-zinc-400">{usage.summary.llm_tokens.requests} requests</div>
            </div>
            <div className="p-4 bg-zinc-900 border border-zinc-800 rounded-lg">
              <div className="text-xs text-zinc-500">Sync Operations</div>
              <div className="text-xl font-bold text-white">{usage.summary.sync_operations.count.toLocaleString()}</div>
            </div>
            <div className="p-4 bg-zinc-900 border border-zinc-800 rounded-lg">
              <div className="text-xs text-zinc-500">API Calls</div>
              <div className="text-xl font-bold text-white">{usage.summary.api_calls.count.toLocaleString()}</div>
            </div>
          </div>

          {usage.daily_tokens.length > 0 && (
            <div className="mt-4 p-4 bg-zinc-900 border border-zinc-800 rounded-lg">
              <div className="text-xs text-zinc-500 mb-3">Daily Token Usage (30 days)</div>
              <div className="flex items-end gap-1 h-20">
                {usage.daily_tokens.map((day) => {
                  const max = Math.max(...usage.daily_tokens.map((d) => d.tokens))
                  const height = max > 0 ? (day.tokens / max) * 100 : 0
                  return (
                    <div
                      key={day.date}
                      className="flex-1 bg-blue-500/60 rounded-t hover:bg-blue-400/80 transition-all"
                      style={{ height: `${Math.max(2, height)}%` }}
                      title={`${day.date}: ${day.tokens.toLocaleString()} tokens`}
                    />
                  )
                })}
              </div>
            </div>
          )}
        </div>
      )}

      {/* Marketplace Earnings */}
      {earnings && (
        <div>
          <h2 className="text-lg font-semibold text-white mb-4">Marketplace Earnings</h2>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div className="p-4 bg-zinc-900 border border-zinc-800 rounded-lg">
              <div className="text-xs text-zinc-500">Total Earned</div>
              <div className="text-xl font-bold text-white">${(earnings.total_earned / 100).toFixed(2)}</div>
            </div>
            <div className="p-4 bg-zinc-900 border border-zinc-800 rounded-lg">
              <div className="text-xs text-zinc-500">Pending</div>
              <div className="text-xl font-bold text-white">${(earnings.pending / 100).toFixed(2)}</div>
            </div>
            <div className="p-4 bg-zinc-900 border border-zinc-800 rounded-lg">
              <div className="text-xs text-zinc-500">Payouts</div>
              <div className="text-xl font-bold text-white">{earnings.payout_count}</div>
            </div>
          </div>
          <button
            onClick={handleConnectSetup}
            className="mt-3 px-4 py-2 text-sm bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-all"
          >
            {earnings.payout_count > 0 ? 'Open Seller Dashboard' : 'Set Up Seller Account'}
          </button>
        </div>
      )}

      {/* Invoices */}
      {invoices.length > 0 && (
        <div>
          <h2 className="text-lg font-semibold text-white mb-4">Invoices</h2>
          <div className="border border-zinc-800 rounded-lg overflow-hidden">
            {invoices.map((invoice) => (
              <div key={invoice.id} className="flex items-center justify-between px-4 py-3 border-b border-zinc-800 last:border-0">
                <div>
                  <span className="text-sm text-white">{new Date(invoice.date).toLocaleDateString()}</span>
                  <span className="text-xs text-zinc-400 ml-3">{invoice.total}</span>
                </div>
                <div className="flex items-center gap-3">
                  <span className={`text-xs px-2 py-0.5 rounded-full ${
                    invoice.status === 'paid' ? 'bg-green-500/10 text-green-400' : 'bg-yellow-500/10 text-yellow-400'
                  }`}>
                    {invoice.status}
                  </span>
                  <a
                    href={invoice.pdf_url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="text-xs text-blue-400 hover:text-blue-300"
                  >
                    PDF
                  </a>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  )
}
