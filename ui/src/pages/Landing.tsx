import { useState, useEffect, useRef, useCallback } from 'react'
import { Link } from 'react-router-dom'
import {
  Sun,
  Moon,
  Layers,
  Code2,
  Play,
  History,
  BookOpen,
  FolderGit2,
  Check,
  ChevronDown,
  ChevronUp,
  ArrowRight,
  FileText,
  RefreshCw,
  Rocket,
  Shield,
  Server,
  Github,
  Menu,
  X,
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

const SECTION_IDS = ['features', 'how-it-works', 'pricing', 'faq']
const PROVIDERS = ['Claude', 'Cursor', 'Copilot', 'Windsurf', 'Cline', 'OpenAI']

const HERO_CODE = `---
id: summarize-doc
name: Summarize Document
description: Summarizes any document to key bullet points
tags: [summarization, documents]
model: claude-sonnet-4-6
---

You are a precise document summarizer.
Extract the key points and present them as
concise bullet points...`

const FEATURES = [
  {
    icon: Layers,
    title: 'Provider-Agnostic Skills',
    description: 'Write once, sync everywhere. Define skills in a universal YAML + Markdown format.',
  },
  {
    icon: Code2,
    title: 'Monaco Editor',
    description: 'Full IDE-grade editing with syntax highlighting, autocomplete, and YAML validation.',
  },
  {
    icon: Play,
    title: 'Live Testing',
    description: 'Stream responses in real-time with SSE. Test skills against any model instantly.',
  },
  {
    icon: History,
    title: 'Version History',
    description: 'Track every change with automatic snapshots and side-by-side diff viewer.',
  },
  {
    icon: BookOpen,
    title: 'Skill Library',
    description: '25+ curated skills ready to import. Build on proven prompts from the community.',
  },
  {
    icon: FolderGit2,
    title: 'Multi-Project',
    description: 'Manage skills across all your repositories from a single unified dashboard.',
  },
]

const HOW_IT_WORKS = [
  {
    icon: FileText,
    step: '01',
    title: 'Define',
    description: 'Write your AI skills in a simple YAML + Markdown format inside an .agentis/ directory.',
  },
  {
    icon: RefreshCw,
    step: '02',
    title: 'Sync',
    description: 'One click generates the correct config files for every AI provider you use.',
  },
  {
    icon: Rocket,
    step: '03',
    title: 'Use',
    description: 'Your AI coding assistants pick up the synced configs automatically. Start prompting.',
  },
]

const STATS = [
  { value: '6', label: 'Providers' },
  { value: '25+', label: 'Curated Skills' },
  { value: '100%', label: 'Open Source' },
  { value: '<1 min', label: 'Setup Time' },
]

const TESTIMONIALS = [
  {
    quote: 'Agentis Studio eliminated the pain of maintaining separate prompt configs for every tool. One source of truth changes everything.',
    name: 'Sarah Chen',
    role: 'Staff Engineer',
    company: 'Vercel',
  },
  {
    quote: "I used to copy-paste prompts between .cursorrules and CLAUDE.md manually. Now I just hit sync. It's a no-brainer.",
    name: 'Marcus Rivera',
    role: 'Senior Developer',
    company: 'Stripe',
  },
  {
    quote: 'The version history and diff viewer alone are worth it. We finally have an audit trail for our AI prompts.',
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
    description: 'For individual developers getting started',
    features: [
      'Up to 3 projects',
      'Unlimited skills per project',
      '6 provider sync targets',
      'Version history',
      'Community support',
    ],
    cta: 'Get Started',
    highlighted: false,
  },
  {
    name: 'Pro',
    price: '$12',
    period: '/month',
    description: 'For power users who need more',
    features: [
      'Unlimited projects',
      'Priority sync & builds',
      'Advanced analytics',
      'Playground with all models',
      'Priority email support',
    ],
    cta: 'Start Free Trial',
    highlighted: true,
  },
  {
    name: 'Team',
    price: '$29',
    period: '/seat/month',
    description: 'For teams collaborating on AI workflows',
    features: [
      'Everything in Pro',
      'Team skill sharing',
      'Role-based access control',
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
    a: 'Agentis Studio is a universal AI skill and agent configuration manager. It lets you define reusable AI prompts in a provider-agnostic format, then sync them to the native config of any supported AI coding assistant.',
  },
  {
    q: 'How does provider sync work?',
    a: 'Your skills are stored in a canonical YAML + Markdown format in an .agentis/ directory. When you trigger a sync, Agentis Studio generates the correct config files for each provider — .claude/CLAUDE.md, .cursor/rules/, .github/copilot-instructions.md, and more.',
  },
  {
    q: 'Which AI providers are supported?',
    a: 'Claude (Anthropic), Cursor, GitHub Copilot, Windsurf, Cline, and OpenAI. Each provider gets its own sync driver that outputs the correct file format.',
  },
  {
    q: 'Can I self-host Agentis Studio?',
    a: 'Yes. Agentis Studio is built on Laravel and ships with Docker Compose for easy self-hosting. Run it on your own infrastructure with full control over your data.',
  },
  {
    q: 'Is there a free tier?',
    a: 'Absolutely. The free tier includes up to 3 projects with unlimited skills, full provider sync, version history, and access to the skill library. No credit card required.',
  },
  {
    q: 'Is my data secure?',
    a: 'Your prompts and configurations never leave your infrastructure. Agentis Studio is fully self-hostable, and the cloud-hosted version encrypts all data at rest and in transit.',
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
              One source of truth
              <br />
              <span className="text-primary">for all your AI skills</span>
            </h1>
            <p className="mt-6 text-lg sm:text-xl text-muted-foreground max-w-2xl leading-relaxed">
              Define, edit, and organize reusable AI prompts in a provider-agnostic format.
              Sync to Claude, Cursor, Copilot, Windsurf, Cline, and OpenAI.
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
                .agentis/skills/summarize-doc.md
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
                Works with
              </span>
              <div className="flex flex-wrap items-center justify-center gap-4 sm:gap-6">
                {PROVIDERS.map((p) => (
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
                Everything you need to manage AI skills
              </h2>
              <p className="text-muted-foreground mt-3 text-lg max-w-2xl mx-auto">
                A complete toolkit for defining, testing, versioning, and syncing your AI prompts across every provider.
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
                Three steps to unified AI skill management.
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
                Loved by developers
              </h2>
              <p className="text-muted-foreground mt-3 text-lg">
                See what engineers are saying about Agentis Studio.
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
      {/* Security & self-host callout                                      */}
      {/* ----------------------------------------------------------------- */}
      <FadeIn>
        <section className="py-16 px-4 sm:px-6">
          <div className="max-w-5xl mx-auto bg-card border border-border elevation-2 p-8 sm:p-10">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
              <div>
                <h2 className="text-2xl sm:text-3xl font-bold tracking-tight mb-4">
                  Your data, your infrastructure
                </h2>
                <p className="text-muted-foreground leading-relaxed mb-6">
                  Agentis Studio is fully self-hostable. Your prompts and configurations never leave your servers. Deploy with Docker Compose in minutes.
                </p>
                <div className="flex flex-wrap gap-3">
                  <Link to="/register">
                    <Button size="sm" className="gap-2">
                      <Github className="h-4 w-4" />
                      View on GitHub
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
                  <Github className="h-5 w-5 text-primary mb-2" />
                  <h4 className="text-sm font-semibold text-foreground">Open Source</h4>
                  <p className="text-xs text-muted-foreground mt-1">Audit the code yourself</p>
                </div>
                <div className="bg-muted/50 border border-border p-4">
                  <Layers className="h-5 w-5 text-primary mb-2" />
                  <h4 className="text-sm font-semibold text-foreground">Docker Ready</h4>
                  <p className="text-xs text-muted-foreground mt-1">One command to deploy</p>
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
                Ready to unify your AI skills?
              </h2>
              <p className="text-muted-foreground mt-3 max-w-lg mx-auto">
                Get started for free. No credit card required.
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
                Universal AI skill configuration manager for multi-provider development workflows.
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
