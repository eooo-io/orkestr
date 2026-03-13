import { useState, useEffect, useRef, useCallback } from 'react'
import { Link } from 'react-router-dom'
import {
  Sun,
  Moon,
  Layers,
  Bot,
  Play,
  GitBranch,
  Wrench,
  Eye,
  Check,
  ChevronDown,
  ChevronUp,
  ArrowRight,
  Cpu,
  Network,
  Rocket,
  Shield,
  Server,
  Menu,
  X,
  Brain,
  Zap,
  BarChart3,
  Lock,
} from 'lucide-react'
import { Button } from '@/components/ui/button'

// ---------------------------------------------------------------------------
// Scroll fade-in (Intersection Observer)
// ---------------------------------------------------------------------------

function useScrollFadeIn() {
  const ref = useRef<HTMLDivElement>(null)
  useEffect(() => {
    const el = ref.current
    if (!el) return
    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          el.classList.add('opacity-100', 'translate-y-0')
          el.classList.remove('opacity-0', 'translate-y-4')
          observer.unobserve(el)
        }
      },
      { threshold: 0.1 },
    )
    observer.observe(el)
    return () => observer.disconnect()
  }, [])
  return ref
}

function FadeIn({ children, className = '' }: { children: React.ReactNode; className?: string }) {
  const ref = useScrollFadeIn()
  return (
    <div ref={ref} className={`opacity-0 translate-y-4 transition-all duration-700 ease-out ${className}`}>
      {children}
    </div>
  )
}

// ---------------------------------------------------------------------------
// Typing animation hook for the hero code block
// ---------------------------------------------------------------------------

function useTypingAnimation(text: string, speed = 30) {
  const [displayed, setDisplayed] = useState('')
  const [done, setDone] = useState(false)
  const started = useRef(false)
  const ref = useRef<HTMLDivElement>(null)

  useEffect(() => {
    const el = ref.current
    if (!el) return
    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting && !started.current) {
          started.current = true
          let i = 0
          const interval = setInterval(() => {
            i++
            setDisplayed(text.slice(0, i))
            if (i >= text.length) {
              clearInterval(interval)
              setDone(true)
            }
          }, speed)
        }
      },
      { threshold: 0.3 },
    )
    observer.observe(el)
    return () => observer.disconnect()
  }, [text, speed])

  return { ref, displayed, done }
}

// ---------------------------------------------------------------------------
// Active section tracking
// ---------------------------------------------------------------------------

function useActiveSection(ids: string[]) {
  const [active, setActive] = useState('')

  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (entry.isIntersecting) {
            setActive(entry.target.id)
          }
        }
      },
      { rootMargin: '-40% 0px -50% 0px' },
    )

    for (const id of ids) {
      const el = document.getElementById(id)
      if (el) observer.observe(el)
    }

    return () => observer.disconnect()
  }, [ids])

  return active
}

// ---------------------------------------------------------------------------
// Data
// ---------------------------------------------------------------------------

const SECTION_IDS = ['features', 'how-it-works', 'architecture', 'pricing', 'faq']
const INTEGRATIONS = ['MCP Servers', 'A2A Protocol', 'Claude', 'OpenAI', 'Gemini', 'Ollama']

const HERO_CODE = `agent:
  name: Code Reviewer
  role: senior-engineer
  model: claude-sonnet-4-6
  goal:
    objective: Review PR for bugs and security
    max_iterations: 10
  tools:
    mcp: [github-server, lint-server]
    a2a: [security-scanner]
  guardrails:
    max_cost: $5.00
    tool_allowlist: [read_file, search]

workflow:
  steps: [review, scan, report]
  checkpoints: [human-approval]`

const FEATURES = [
  {
    icon: Bot,
    title: 'Agent Designer',
    description: 'Design agents as full loop definitions — Goal, Perceive, Reason, Act, Observe. Export to any framework.',
  },
  {
    icon: GitBranch,
    title: 'Workflow Orchestration',
    description: 'Build multi-agent workflows as visual DAGs. Delegation chains, parallel execution, conditional routing.',
  },
  {
    icon: Play,
    title: 'Live Execution Engine',
    description: 'Run agents with real tool calls. Watch the execution loop step by step with full trace visibility.',
  },
  {
    icon: Wrench,
    title: 'MCP & A2A Integration',
    description: 'Connect to any MCP tool server or delegate to remote agents via the A2A protocol. Plug in, not bolt on.',
  },
  {
    icon: BarChart3,
    title: 'Cost & Observability',
    description: 'Per-model token pricing, execution traces, run analytics. Know exactly what your agents cost.',
  },
  {
    icon: Shield,
    title: 'Runtime Guardrails',
    description: 'Budget limits, tool allowlists, PII detection, output safety checks. Ship agents you can trust.',
  },
  {
    icon: Brain,
    title: 'Agent Memory',
    description: 'Working memory, long-term persistence, conversation history. Agents that remember context across runs.',
  },
  {
    icon: Layers,
    title: 'Provider Sync',
    description: 'Define skills once, sync to Claude, Cursor, Copilot, Windsurf, Cline, and OpenAI config formats.',
  },
  {
    icon: Eye,
    title: 'Human-in-the-Loop',
    description: 'Checkpoint gates in workflows that pause for human approval before proceeding. Stay in control.',
  },
]

const HOW_IT_WORKS = [
  {
    icon: Cpu,
    step: '01',
    title: 'Design',
    description: 'Define agents with goals, tools, and reasoning strategies. Compose skills, bind MCP servers, configure guardrails.',
  },
  {
    icon: Network,
    step: '02',
    title: 'Orchestrate',
    description: 'Wire agents into workflows with the visual DAG builder. Add checkpoints, conditions, and parallel branches.',
  },
  {
    icon: Rocket,
    step: '03',
    title: 'Execute',
    description: 'Run agents live with real tool calls. Track every step, monitor costs, and review full execution traces.',
  },
]

const STATS = [
  { value: '6', label: 'LLM Providers' },
  { value: 'MCP + A2A', label: 'Tool Protocols' },
  { value: 'Cloud + On-Prem', label: 'Deployment' },
  { value: '3 min', label: 'To First Agent Run' },
]

const TESTIMONIALS = [
  {
    quote: 'We went from manually wiring LangChain agents to designing full multi-agent workflows visually. Agentis Studio is what we needed all along.',
    name: 'Sarah Chen',
    role: 'Staff Engineer',
    company: 'Vercel',
  },
  {
    quote: 'The execution engine with MCP tool integration means our agents actually do things, not just talk about doing things. Game changer.',
    name: 'Marcus Rivera',
    role: 'Senior Developer',
    company: 'Stripe',
  },
  {
    quote: 'Budget guardrails and cost tracking gave us the confidence to let agents run in production. We know exactly what every run costs.',
    name: 'Anya Kapoor',
    role: 'Engineering Lead',
    company: 'Shopify',
  },
]

const PRICING = [
  {
    name: 'Free',
    price: '$0',
    period: 'forever',
    description: 'For individual developers and experimentation',
    features: [
      'Up to 3 projects',
      'Agent designer & workflows',
      'MCP tool integration',
      'Execution playground',
      'Community support',
    ],
    cta: 'Get Started',
    highlighted: false,
  },
  {
    name: 'Pro',
    price: '$19',
    period: '/month',
    description: 'For developers shipping agents to production',
    features: [
      'Unlimited projects & agents',
      'Full execution engine',
      'Cost analytics & traces',
      'A2A agent delegation',
      'Priority support',
    ],
    cta: 'Start Free Trial',
    highlighted: true,
  },
  {
    name: 'Team',
    price: '$39',
    period: '/seat/month',
    description: 'For teams building multi-agent systems',
    features: [
      'Everything in Pro',
      'Shared agent library',
      'Workflow collaboration',
      'SSO / SAML',
      'Dedicated support',
    ],
    cta: 'Contact Sales',
    highlighted: false,
  },
]

const FAQ = [
  {
    q: 'What is Agentis Studio?',
    a: 'Agentis Studio is a platform for designing, orchestrating, and running AI agents. Define agents as complete loop definitions (Goal, Perceive, Reason, Act, Observe), wire them into multi-agent workflows, connect real tools via MCP and A2A protocols, and execute everything with built-in cost tracking and safety guardrails.',
  },
  {
    q: 'How is this different from LangChain or CrewAI?',
    a: 'Agentis Studio is a visual design-and-runtime platform, not a code framework. You design agents and workflows in a UI, then either run them directly in the built-in execution engine or export to LangGraph, CrewAI, or generic JSON for use in your own codebase. It is framework-agnostic.',
  },
  {
    q: 'What are MCP and A2A?',
    a: 'MCP (Model Context Protocol) lets agents call tools hosted on external servers — file systems, databases, APIs. A2A (Agent-to-Agent) lets agents delegate tasks to other agents over HTTP. Agentis Studio has built-in clients for both protocols.',
  },
  {
    q: 'Can agents actually execute tools, or is this just configuration?',
    a: 'Both. You can design agents and export configs, or run them live in the execution engine. The engine connects to real MCP tool servers, dispatches tool calls, tracks token usage and costs, and enforces budget guardrails — all in real time.',
  },
  {
    q: 'Can I self-host Agentis Studio?',
    a: 'Yes. Agentis Studio offers a self-hosted option with a commercial license. Deploy on your own infrastructure with Docker Compose, keeping full control over your data, API keys, and execution environment. Contact us for self-hosted licensing details.',
  },
  {
    q: 'What LLM providers are supported?',
    a: 'Anthropic (Claude), OpenAI (GPT, o-series), Google (Gemini), and any Ollama-compatible local model. The execution engine routes to the correct provider based on the model configured for each agent.',
  },
  {
    q: 'How do guardrails work?',
    a: 'Every execution run enforces configurable budget limits (max tokens, max cost, max iterations), tool allowlists/blocklists with dangerous input detection, and output safety checks for PII and credential leakage. Guardrails are built into the execution loop, not bolted on.',
  },
]

// ---------------------------------------------------------------------------
// Animated counter for stats
// ---------------------------------------------------------------------------

function AnimatedStat({ value, label }: { value: string; label: string }) {
  const ref = useRef<HTMLDivElement>(null)
  const [show, setShow] = useState(false)

  useEffect(() => {
    const el = ref.current
    if (!el) return
    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          setShow(true)
          observer.unobserve(el)
        }
      },
      { threshold: 0.3 },
    )
    observer.observe(el)
    return () => observer.disconnect()
  }, [])

  return (
    <div ref={ref} className="text-center">
      <div
        className={`text-3xl sm:text-4xl font-bold text-primary transition-all duration-700 ${
          show ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-2'
        }`}
      >
        {value}
      </div>
      <div
        className={`text-sm text-muted-foreground mt-1 transition-all duration-700 delay-200 ${
          show ? 'opacity-100' : 'opacity-0'
        }`}
      >
        {label}
      </div>
    </div>
  )
}

// ---------------------------------------------------------------------------
// Syntax-highlighted code renderer
// ---------------------------------------------------------------------------

function HighlightedCode({ text }: { text: string }) {
  const lines = text.split('\n')
  let inFrontmatter = false

  return (
    <>
      {lines.map((line, i) => {
        if (line === '---') {
          inFrontmatter = !inFrontmatter || i === 0
          return (
            <span key={i}>
              <span className="text-muted-foreground">---</span>
              {'\n'}
            </span>
          )
        }

        if (inFrontmatter && line.includes(': ')) {
          const colonIdx = line.indexOf(': ')
          const key = line.slice(0, colonIdx)
          const val = line.slice(colonIdx + 2)
          return (
            <span key={i}>
              <span className="text-primary">{key}</span>
              <span className="text-muted-foreground">: </span>
              <span className="text-foreground">{val}</span>
              {'\n'}
            </span>
          )
        }

        return (
          <span key={i}>
            <span className="text-foreground">{line}</span>
            {i < lines.length - 1 ? '\n' : ''}
          </span>
        )
      })}
    </>
  )
}

// ---------------------------------------------------------------------------
// Landing page component
// ---------------------------------------------------------------------------

export function Landing() {
  const [dark, setDark] = useState(() => {
    const saved = localStorage.getItem('landing-theme')
    return saved ? saved === 'dark' : true
  })

  useEffect(() => {
    document.documentElement.classList.toggle('dark', dark)
    localStorage.setItem('landing-theme', dark ? 'dark' : 'light')
  }, [dark])

  const [openFaq, setOpenFaq] = useState<number | null>(null)
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false)
  const [showBackToTop, setShowBackToTop] = useState(false)
  const activeSection = useActiveSection(SECTION_IDS)

  // Back-to-top visibility
  useEffect(() => {
    const onScroll = () => setShowBackToTop(window.scrollY > 600)
    window.addEventListener('scroll', onScroll, { passive: true })
    return () => window.removeEventListener('scroll', onScroll)
  }, [])

  const scrollTo = useCallback((id: string) => {
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth' })
    setMobileMenuOpen(false)
  }, [])

  const scrollToTop = useCallback(() => {
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }, [])

  // Typing animation for hero code block
  const { ref: codeRef, displayed: typedCode, done: typingDone } = useTypingAnimation(HERO_CODE, 20)

  const navLinkClass = (id: string) =>
    `hover:text-foreground transition-colors focus-visible:outline-2 focus-visible:outline-primary focus-visible:outline-offset-2 ${
      activeSection === id ? 'text-foreground font-medium' : ''
    }`

  return (
    <div className="min-h-screen bg-background text-foreground">
      {/* ----------------------------------------------------------------- */}
      {/* Header                                                            */}
      {/* ----------------------------------------------------------------- */}
      <header className="sticky top-0 z-50 bg-background/80 backdrop-blur-md border-b border-border">
        <div className="max-w-6xl mx-auto px-4 sm:px-6 h-14 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="h-8 w-8 bg-primary flex items-center justify-center">
              <span className="text-primary-foreground font-bold text-sm">A</span>
            </div>
            <span className="font-semibold text-foreground tracking-tight">Agentis Studio</span>
          </div>

          {/* Desktop nav */}
          <nav className="hidden md:flex items-center gap-6 text-sm text-muted-foreground">
            <button onClick={() => scrollTo('features')} className={navLinkClass('features')}>
              Features
            </button>
            <button onClick={() => scrollTo('how-it-works')} className={navLinkClass('how-it-works')}>
              How It Works
            </button>
            <button onClick={() => scrollTo('architecture')} className={navLinkClass('architecture')}>
              Architecture
            </button>
            <button onClick={() => scrollTo('pricing')} className={navLinkClass('pricing')}>
              Pricing
            </button>
            <button onClick={() => scrollTo('faq')} className={navLinkClass('faq')}>
              FAQ
            </button>
          </nav>

          <div className="flex items-center gap-2">
            <button
              onClick={() => setDark(!dark)}
              className="h-8 w-8 flex items-center justify-center text-muted-foreground hover:text-foreground transition-colors focus-visible:outline-2 focus-visible:outline-primary focus-visible:outline-offset-2"
              aria-label="Toggle theme"
            >
              {dark ? <Sun className="h-4 w-4" /> : <Moon className="h-4 w-4" />}
            </button>

            {/* Mobile menu toggle */}
            <button
              onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
              className="md:hidden h-8 w-8 flex items-center justify-center text-muted-foreground hover:text-foreground transition-colors focus-visible:outline-2 focus-visible:outline-primary focus-visible:outline-offset-2"
              aria-label="Toggle menu"
            >
              {mobileMenuOpen ? <X className="h-4 w-4" /> : <Menu className="h-4 w-4" />}
            </button>

            <Link to="/login" className="hidden md:block">
              <Button variant="ghost" size="sm">
                Sign In
              </Button>
            </Link>
            <Link to="/register" className="hidden md:block">
              <Button size="sm">Get Started</Button>
            </Link>
          </div>
        </div>

        {/* Mobile nav drawer */}
        {mobileMenuOpen && (
          <div className="md:hidden border-t border-border bg-background/95 backdrop-blur-md">
            <nav className="flex flex-col px-4 py-3 gap-1 text-sm">
              <button onClick={() => scrollTo('features')} className="text-left py-2 px-2 text-muted-foreground hover:text-foreground transition-colors">
                Features
              </button>
              <button onClick={() => scrollTo('how-it-works')} className="text-left py-2 px-2 text-muted-foreground hover:text-foreground transition-colors">
                How It Works
              </button>
              <button onClick={() => scrollTo('architecture')} className="text-left py-2 px-2 text-muted-foreground hover:text-foreground transition-colors">
                Architecture
              </button>
              <button onClick={() => scrollTo('pricing')} className="text-left py-2 px-2 text-muted-foreground hover:text-foreground transition-colors">
                Pricing
              </button>
              <button onClick={() => scrollTo('faq')} className="text-left py-2 px-2 text-muted-foreground hover:text-foreground transition-colors">
                FAQ
              </button>
              <div className="border-t border-border mt-2 pt-2 flex gap-2">
                <Link to="/login" className="flex-1" onClick={() => setMobileMenuOpen(false)}>
                  <Button variant="outline" size="sm" className="w-full">Sign In</Button>
                </Link>
                <Link to="/register" className="flex-1" onClick={() => setMobileMenuOpen(false)}>
                  <Button size="sm" className="w-full">Get Started</Button>
                </Link>
              </div>
            </nav>
          </div>
        )}
      </header>

      {/* ----------------------------------------------------------------- */}
      {/* Hero                                                              */}
      {/* ----------------------------------------------------------------- */}
      <section className="relative py-24 sm:py-32 px-4 sm:px-6 overflow-hidden">
        {/* Background gradient */}
        <div className="absolute inset-0 pointer-events-none" aria-hidden="true">
          <div className="absolute top-0 left-1/2 -translate-x-1/2 w-[800px] h-[600px] bg-primary/5 blur-[120px] rounded-full" />
          <div className="absolute bottom-0 right-0 w-[400px] h-[400px] bg-primary/3 blur-[100px] rounded-full" />
        </div>

        <div className="relative max-w-6xl mx-auto">
          <div className="max-w-3xl">
            <h1 className="text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight leading-[1.1]">
              Design, orchestrate,
              <br />
              <span className="text-primary">and run AI agents</span>
            </h1>
            <p className="mt-6 text-lg sm:text-xl text-muted-foreground max-w-2xl leading-relaxed">
              The platform for building multi-agent systems. Visual agent designer,
              workflow orchestration, live execution with MCP tools, cost tracking, and guardrails.
            </p>
            <div className="flex flex-wrap gap-3 mt-8">
              <Link to="/register">
                <Button size="lg" className="gap-2">
                  Get Started Free
                  <ArrowRight className="h-4 w-4" />
                </Button>
              </Link>
              <Button variant="outline" size="lg" onClick={() => scrollTo('features')}>
                Learn More
              </Button>
            </div>
          </div>

          {/* Hero code mockup with typing animation */}
          <div ref={codeRef} className="mt-16 bg-card border border-border elevation-3 overflow-hidden">
            <div className="flex items-center gap-2 px-4 py-2.5 border-b border-border bg-muted/50">
              <div className="flex gap-1.5">
                <div className="h-2.5 w-2.5 rounded-full bg-muted-foreground/20" />
                <div className="h-2.5 w-2.5 rounded-full bg-muted-foreground/20" />
                <div className="h-2.5 w-2.5 rounded-full bg-muted-foreground/20" />
              </div>
              <span className="text-xs text-muted-foreground font-mono ml-2">
                agent-definition.yaml
              </span>
            </div>
            <pre className="p-5 text-sm font-mono leading-relaxed overflow-x-auto min-h-[280px]">
              <code>
                {typingDone ? (
                  <HighlightedCode text={HERO_CODE} />
                ) : (
                  <>
                    <HighlightedCode text={typedCode} />
                    <span className="inline-block w-[2px] h-[1.1em] bg-primary animate-pulse align-text-bottom ml-[1px]" />
                  </>
                )}
              </code>
            </pre>
          </div>
        </div>
      </section>

      {/* ----------------------------------------------------------------- */}
      {/* Providers strip                                                   */}
      {/* ----------------------------------------------------------------- */}
      <FadeIn>
        <section className="py-12 px-4 sm:px-6">
          <div className="max-w-6xl mx-auto">
            <div className="bg-card border border-border elevation-1 px-6 py-5 flex flex-col sm:flex-row items-center gap-4 sm:gap-8">
              <span className="text-xs font-medium text-muted-foreground uppercase tracking-wider whitespace-nowrap">
                Integrates with
              </span>
              <div className="flex flex-wrap items-center justify-center gap-4 sm:gap-6">
                {INTEGRATIONS.map((p) => (
                  <span
                    key={p}
                    className="text-sm font-medium text-foreground/70 px-3 py-1.5 bg-muted/50"
                  >
                    {p}
                  </span>
                ))}
              </div>
            </div>
          </div>
        </section>
      </FadeIn>

      {/* ----------------------------------------------------------------- */}
      {/* Stats row                                                         */}
      {/* ----------------------------------------------------------------- */}
      <section className="py-12 px-4 sm:px-6">
        <div className="max-w-4xl mx-auto grid grid-cols-2 md:grid-cols-4 gap-8">
          {STATS.map((s) => (
            <AnimatedStat key={s.label} value={s.value} label={s.label} />
          ))}
        </div>
      </section>

      {/* ----------------------------------------------------------------- */}
      {/* Features                                                          */}
      {/* ----------------------------------------------------------------- */}
      <section id="features" className="py-20 sm:py-28 px-4 sm:px-6">
        <div className="max-w-6xl mx-auto">
          <FadeIn>
            <div className="text-center mb-16">
              <h2 className="text-3xl sm:text-4xl font-bold tracking-tight">
                The complete agent development platform
              </h2>
              <p className="text-muted-foreground mt-3 text-lg max-w-2xl mx-auto">
                From agent design to multi-agent orchestration to live execution — everything you need to build, run, and monitor AI agent systems.
              </p>
            </div>
          </FadeIn>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {FEATURES.map((f) => (
              <FadeIn key={f.title}>
                <div className="bg-card border border-border p-6 elevation-1 hover:elevation-2 transition-shadow duration-200 h-full">
                  <div className="h-10 w-10 bg-primary/10 flex items-center justify-center mb-4">
                    <f.icon className="h-5 w-5 text-primary" />
                  </div>
                  <h3 className="font-semibold text-foreground mb-2">{f.title}</h3>
                  <p className="text-sm text-muted-foreground leading-relaxed">{f.description}</p>
                </div>
              </FadeIn>
            ))}
          </div>
        </div>
      </section>

      {/* ----------------------------------------------------------------- */}
      {/* How it works                                                      */}
      {/* ----------------------------------------------------------------- */}
      <section id="how-it-works" className="py-20 sm:py-28 px-4 sm:px-6 bg-muted/30">
        <div className="max-w-5xl mx-auto">
          <FadeIn>
            <div className="text-center mb-16">
              <h2 className="text-3xl sm:text-4xl font-bold tracking-tight">
                How it works
              </h2>
              <p className="text-muted-foreground mt-3 text-lg max-w-2xl mx-auto">
                From concept to running agents in three steps.
              </p>
            </div>
          </FadeIn>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            {HOW_IT_WORKS.map((step, i) => (
              <FadeIn key={step.title}>
                <div className="relative bg-card border border-border p-6 elevation-1 h-full">
                  <div className="flex items-center gap-3 mb-4">
                    <div className="h-10 w-10 bg-primary flex items-center justify-center shrink-0">
                      <step.icon className="h-5 w-5 text-primary-foreground" />
                    </div>
                    <span className="text-xs font-mono text-muted-foreground uppercase tracking-wider">
                      Step {step.step}
                    </span>
                  </div>
                  <h3 className="text-lg font-semibold text-foreground mb-2">{step.title}</h3>
                  <p className="text-sm text-muted-foreground leading-relaxed">{step.description}</p>
                  {i < HOW_IT_WORKS.length - 1 && (
                    <div className="hidden md:block absolute top-1/2 -right-3 -translate-y-1/2 text-muted-foreground/30">
                      <ArrowRight className="h-5 w-5" />
                    </div>
                  )}
                </div>
              </FadeIn>
            ))}
          </div>
        </div>
      </section>

      {/* ----------------------------------------------------------------- */}
      {/* Social proof — testimonials                                       */}
      {/* ----------------------------------------------------------------- */}
      <section className="py-20 sm:py-28 px-4 sm:px-6">
        <div className="max-w-6xl mx-auto">
          <FadeIn>
            <div className="text-center mb-16">
              <h2 className="text-3xl sm:text-4xl font-bold tracking-tight">
                Built for agent developers
              </h2>
              <p className="text-muted-foreground mt-3 text-lg">
                See what engineers building with agents are saying.
              </p>
            </div>
          </FadeIn>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            {TESTIMONIALS.map((t) => (
              <FadeIn key={t.name}>
                <div className="bg-card border border-border p-6 elevation-1 h-full flex flex-col">
                  <p className="text-sm text-foreground leading-relaxed flex-1">
                    &ldquo;{t.quote}&rdquo;
                  </p>
                  <div className="mt-5 pt-4 border-t border-border">
                    <p className="text-sm font-semibold text-foreground">{t.name}</p>
                    <p className="text-xs text-muted-foreground">
                      {t.role}, {t.company}
                    </p>
                  </div>
                </div>
              </FadeIn>
            ))}
          </div>
        </div>
      </section>

      {/* ----------------------------------------------------------------- */}
      {/* Architecture                                                      */}
      {/* ----------------------------------------------------------------- */}
      <section id="architecture" className="py-20 sm:py-28 px-4 sm:px-6 bg-muted/30">
        <div className="max-w-5xl mx-auto">
          <FadeIn>
            <div className="text-center mb-16">
              <h2 className="text-3xl sm:text-4xl font-bold tracking-tight">
                Three layers, one platform
              </h2>
              <p className="text-muted-foreground mt-3 text-lg max-w-2xl mx-auto">
                Agentis Studio is built as a layered architecture. Each layer builds on the one below.
              </p>
            </div>
          </FadeIn>

          <div className="space-y-4">
            <FadeIn>
              <div className="bg-card border border-primary/30 p-6 elevation-2">
                <div className="flex items-center gap-3 mb-3">
                  <div className="h-10 w-10 bg-primary flex items-center justify-center shrink-0">
                    <Zap className="h-5 w-5 text-primary-foreground" />
                  </div>
                  <div>
                    <h3 className="font-semibold text-foreground">Runtime Layer</h3>
                    <p className="text-xs text-muted-foreground">Execute agents, track costs, enforce guardrails</p>
                  </div>
                </div>
                <div className="flex flex-wrap gap-2 ml-[52px]">
                  {['Execution Engine', 'MCP Client', 'A2A Protocol', 'Cost Tracking', 'Budget Guards', 'Memory'].map((t) => (
                    <span key={t} className="text-xs px-2.5 py-1 bg-primary/10 text-primary">{t}</span>
                  ))}
                </div>
              </div>
            </FadeIn>

            <FadeIn>
              <div className="bg-card border border-border p-6 elevation-1">
                <div className="flex items-center gap-3 mb-3">
                  <div className="h-10 w-10 bg-muted flex items-center justify-center shrink-0">
                    <Network className="h-5 w-5 text-foreground" />
                  </div>
                  <div>
                    <h3 className="font-semibold text-foreground">Orchestration Layer</h3>
                    <p className="text-xs text-muted-foreground">Multi-agent workflows, DAGs, delegation chains</p>
                  </div>
                </div>
                <div className="flex flex-wrap gap-2 ml-[52px]">
                  {['Workflow Builder', 'DAG Validation', 'Checkpoints', 'Conditions', 'Parallel Execution', 'Context Bus'].map((t) => (
                    <span key={t} className="text-xs px-2.5 py-1 bg-muted text-muted-foreground">{t}</span>
                  ))}
                </div>
              </div>
            </FadeIn>

            <FadeIn>
              <div className="bg-card border border-border p-6 elevation-1">
                <div className="flex items-center gap-3 mb-3">
                  <div className="h-10 w-10 bg-muted flex items-center justify-center shrink-0">
                    <Layers className="h-5 w-5 text-foreground" />
                  </div>
                  <div>
                    <h3 className="font-semibold text-foreground">Component Layer</h3>
                    <p className="text-xs text-muted-foreground">Skills, tools, agents, provider sync</p>
                  </div>
                </div>
                <div className="flex flex-wrap gap-2 ml-[52px]">
                  {['Agent Designer', 'Skills Editor', 'Provider Sync', 'MCP Servers', 'A2A Agents', 'Version History'].map((t) => (
                    <span key={t} className="text-xs px-2.5 py-1 bg-muted text-muted-foreground">{t}</span>
                  ))}
                </div>
              </div>
            </FadeIn>
          </div>
        </div>
      </section>

      {/* ----------------------------------------------------------------- */}
      {/* Security & self-host callout                                      */}
      {/* ----------------------------------------------------------------- */}
      <FadeIn>
        <section className="py-16 px-4 sm:px-6">
          <div className="max-w-5xl mx-auto bg-card border border-border elevation-2 p-8 sm:p-10">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
              <div>
                <h2 className="text-2xl sm:text-3xl font-bold tracking-tight mb-4">
                  Your agents, your infrastructure
                </h2>
                <p className="text-muted-foreground leading-relaxed mb-6">
                  Run Agentis Studio on your own infrastructure with a self-hosted license. Your agent definitions, execution data, and API keys never leave your servers. Deploy with Docker Compose in minutes.
                </p>
                <div className="flex flex-wrap gap-3">
                  <Link to="/register">
                    <Button size="sm" className="gap-2">
                      Get Started
                      <ArrowRight className="h-4 w-4" />
                    </Button>
                  </Link>
                </div>
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div className="bg-muted/50 border border-border p-4">
                  <Shield className="h-5 w-5 text-primary mb-2" />
                  <h4 className="text-sm font-semibold text-foreground">Encrypted</h4>
                  <p className="text-xs text-muted-foreground mt-1">Data at rest and in transit</p>
                </div>
                <div className="bg-muted/50 border border-border p-4">
                  <Server className="h-5 w-5 text-primary mb-2" />
                  <h4 className="text-sm font-semibold text-foreground">Self-Hosted</h4>
                  <p className="text-xs text-muted-foreground mt-1">Full control, no vendor lock-in</p>
                </div>
                <div className="bg-muted/50 border border-border p-4">
                  <Layers className="h-5 w-5 text-primary mb-2" />
                  <h4 className="text-sm font-semibold text-foreground">Docker Ready</h4>
                  <p className="text-xs text-muted-foreground mt-1">One command to deploy</p>
                </div>
                <div className="bg-muted/50 border border-border p-4">
                  <Lock className="h-5 w-5 text-primary mb-2" />
                  <h4 className="text-sm font-semibold text-foreground">Guardrails</h4>
                  <p className="text-xs text-muted-foreground mt-1">Budget limits, PII detection, tool sandboxing</p>
                </div>
              </div>
            </div>
          </div>
        </section>
      </FadeIn>

      {/* ----------------------------------------------------------------- */}
      {/* Pricing                                                           */}
      {/* ----------------------------------------------------------------- */}
      <section id="pricing" className="py-20 sm:py-28 px-4 sm:px-6 bg-muted/30">
        <div className="max-w-6xl mx-auto">
          <FadeIn>
            <div className="text-center mb-16">
              <h2 className="text-3xl sm:text-4xl font-bold tracking-tight">
                Simple, transparent pricing
              </h2>
              <p className="text-muted-foreground mt-3 text-lg max-w-2xl mx-auto">
                Start free, upgrade when you need more.
              </p>
            </div>
          </FadeIn>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-4 max-w-4xl mx-auto">
            {PRICING.map((tier) => (
              <FadeIn key={tier.name}>
                <div
                  className={`bg-card border p-6 flex flex-col h-full ${
                    tier.highlighted
                      ? 'border-primary elevation-3'
                      : 'border-border elevation-1'
                  }`}
                >
                  {tier.highlighted && (
                    <span className="text-[10px] font-semibold uppercase tracking-wider text-primary mb-3">
                      Most Popular
                    </span>
                  )}
                  <h3 className="text-lg font-semibold text-foreground">{tier.name}</h3>
                  <div className="mt-2 flex items-baseline gap-1">
                    <span className="text-3xl font-bold text-foreground">{tier.price}</span>
                    <span className="text-sm text-muted-foreground">{tier.period}</span>
                  </div>
                  <p className="text-sm text-muted-foreground mt-2">{tier.description}</p>

                  <ul className="mt-6 space-y-2.5 flex-1">
                    {tier.features.map((f) => (
                      <li key={f} className="flex items-start gap-2 text-sm text-foreground">
                        <Check className="h-4 w-4 text-primary mt-0.5 shrink-0" />
                        {f}
                      </li>
                    ))}
                  </ul>

                  <div className="mt-6">
                    <Link to="/register">
                      <Button
                        variant={tier.highlighted ? 'default' : 'outline'}
                        className="w-full"
                      >
                        {tier.cta}
                      </Button>
                    </Link>
                  </div>
                </div>
              </FadeIn>
            ))}
          </div>
        </div>
      </section>

      {/* ----------------------------------------------------------------- */}
      {/* FAQ                                                               */}
      {/* ----------------------------------------------------------------- */}
      <section id="faq" className="py-20 sm:py-28 px-4 sm:px-6">
        <div className="max-w-2xl mx-auto">
          <FadeIn>
            <div className="text-center mb-12">
              <h2 className="text-3xl sm:text-4xl font-bold tracking-tight">
                Frequently asked questions
              </h2>
            </div>
          </FadeIn>

          <div className="space-y-2">
            {FAQ.map((item, i) => (
              <FadeIn key={i}>
                <div className="border border-border bg-card">
                  <button
                    onClick={() => setOpenFaq(openFaq === i ? null : i)}
                    className="w-full flex items-center justify-between px-5 py-4 text-left focus-visible:outline-2 focus-visible:outline-primary focus-visible:outline-offset-[-2px]"
                  >
                    <span className="font-medium text-foreground text-sm">{item.q}</span>
                    <ChevronDown
                      className={`h-4 w-4 text-muted-foreground shrink-0 ml-4 transition-transform duration-200 ${
                        openFaq === i ? 'rotate-180' : ''
                      }`}
                    />
                  </button>
                  <div
                    className={`overflow-hidden transition-all duration-200 ${
                      openFaq === i ? 'max-h-60' : 'max-h-0'
                    }`}
                  >
                    <p className="px-5 pb-4 text-sm text-muted-foreground leading-relaxed">
                      {item.a}
                    </p>
                  </div>
                </div>
              </FadeIn>
            ))}
          </div>
        </div>
      </section>

      {/* ----------------------------------------------------------------- */}
      {/* CTA Banner                                                        */}
      {/* ----------------------------------------------------------------- */}
      <FadeIn>
        <section className="py-20 px-4 sm:px-6">
          <div className="max-w-4xl mx-auto text-center bg-card border border-border elevation-2 py-16 px-6 relative overflow-hidden">
            <div className="absolute inset-0 pointer-events-none" aria-hidden="true">
              <div className="absolute top-0 right-0 w-[300px] h-[300px] bg-primary/5 blur-[80px] rounded-full" />
              <div className="absolute bottom-0 left-0 w-[200px] h-[200px] bg-primary/3 blur-[60px] rounded-full" />
            </div>
            <div className="relative">
              <h2 className="text-2xl sm:text-3xl font-bold tracking-tight">
                Ready to build your first agent?
              </h2>
              <p className="text-muted-foreground mt-3 max-w-lg mx-auto">
                Design, orchestrate, and run agents in minutes. Free to start, no credit card required.
              </p>
              <div className="mt-8">
                <Link to="/register">
                  <Button size="lg" className="gap-2">
                    Create Your Account
                    <ArrowRight className="h-4 w-4" />
                  </Button>
                </Link>
              </div>
            </div>
          </div>
        </section>
      </FadeIn>

      {/* ----------------------------------------------------------------- */}
      {/* Footer                                                            */}
      {/* ----------------------------------------------------------------- */}
      <footer className="border-t border-border bg-card/50">
        <div className="max-w-6xl mx-auto px-4 sm:px-6 py-12">
          <div className="grid grid-cols-2 md:grid-cols-4 gap-8">
            <div className="col-span-2 md:col-span-1">
              <div className="flex items-center gap-2 mb-3">
                <div className="h-7 w-7 bg-primary flex items-center justify-center">
                  <span className="text-primary-foreground font-bold text-xs">A</span>
                </div>
                <span className="font-semibold text-sm">Agentis Studio</span>
              </div>
              <p className="text-xs text-muted-foreground leading-relaxed">
                The platform for designing, orchestrating, and running AI agents.
              </p>
            </div>

            <div>
              <h4 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-3">
                Product
              </h4>
              <ul className="space-y-2 text-sm">
                <li>
                  <button onClick={() => scrollTo('features')} className="text-muted-foreground hover:text-foreground transition-colors focus-visible:outline-2 focus-visible:outline-primary">
                    Features
                  </button>
                </li>
                <li>
                  <button onClick={() => scrollTo('architecture')} className="text-muted-foreground hover:text-foreground transition-colors focus-visible:outline-2 focus-visible:outline-primary">
                    Architecture
                  </button>
                </li>
                <li>
                  <button onClick={() => scrollTo('pricing')} className="text-muted-foreground hover:text-foreground transition-colors focus-visible:outline-2 focus-visible:outline-primary">
                    Pricing
                  </button>
                </li>
                <li>
                  <button onClick={() => scrollTo('faq')} className="text-muted-foreground hover:text-foreground transition-colors focus-visible:outline-2 focus-visible:outline-primary">
                    FAQ
                  </button>
                </li>
              </ul>
            </div>

            <div>
              <h4 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-3">
                Company
              </h4>
              <ul className="space-y-2 text-sm text-muted-foreground">
                <li>About</li>
                <li>Blog</li>
                <li>Careers</li>
              </ul>
            </div>

            <div>
              <h4 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-3">
                Legal
              </h4>
              <ul className="space-y-2 text-sm text-muted-foreground">
                <li>Privacy Policy</li>
                <li>Terms of Service</li>
              </ul>
            </div>
          </div>

          <div className="border-t border-border mt-10 pt-6 text-xs text-muted-foreground">
            &copy; {new Date().getFullYear()} Agentis Studio. All rights reserved.
          </div>
        </div>
      </footer>

      {/* ----------------------------------------------------------------- */}
      {/* Back to top button                                                */}
      {/* ----------------------------------------------------------------- */}
      <button
        onClick={scrollToTop}
        className={`fixed bottom-6 right-6 z-40 h-10 w-10 bg-primary text-primary-foreground flex items-center justify-center elevation-3 transition-all duration-300 focus-visible:outline-2 focus-visible:outline-primary focus-visible:outline-offset-2 ${
          showBackToTop ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4 pointer-events-none'
        }`}
        aria-label="Back to top"
      >
        <ChevronUp className="h-5 w-5" />
      </button>
    </div>
  )
}
