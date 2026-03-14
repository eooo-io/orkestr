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
  Plug2,
  Share2,
} from 'lucide-react'
import { Button } from '@/components/ui/button'

// ---------------------------------------------------------------------------
// Brand icons (official SVG paths from Simple Icons)
// ---------------------------------------------------------------------------

function ClaudeIcon({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="currentColor" className={className}>
      <path d="m4.7144 15.9555 4.7174-2.6471.079-.2307-.079-.1275h-.2307l-.7893-.0486-2.6956-.0729-2.3375-.0971-2.2646-.1214-.5707-.1215-.5343-.7042.0546-.3522.4797-.3218.686.0608 1.5179.1032 2.2767.1578 1.6514.0972 2.4468.255h.3886l.0546-.1579-.1336-.0971-.1032-.0972L6.973 9.8356l-2.55-1.6879-1.3356-.9714-.7225-.4918-.3643-.4614-.1578-1.0078.6557-.7225.8803.0607.2246.0607.8925.686 1.9064 1.4754 2.4893 1.8336.3643.3035.1457-.1032.0182-.0728-.164-.2733-1.3539-2.4467-1.445-2.4893-.6435-1.032-.17-.6194c-.0607-.255-.1032-.4674-.1032-.7285L6.287.1335 6.6997 0l.9957.1336.419.3642.6192 1.4147 1.0018 2.2282 1.5543 3.0296.4553.8985.2429.8318.091.255h.1579v-.1457l.1275-1.706.2368-2.0947.2307-2.6957.0789-.7589.3764-.9107.7468-.4918.5828.2793.4797.686-.0668.4433-.2853 1.8517-.5586 2.9021-.3643 1.9429h.2125l.2429-.2429.9835-1.3053 1.6514-2.0643.7286-.8196.85-.9046.5464-.4311h1.0321l.759 1.1293-.34 1.1657-1.0625 1.3478-.8804 1.1414-1.2628 1.7-.7893 1.36.0729.1093.1882-.0183 2.8535-.607 1.5421-.2794 1.8396-.3157.8318.3886.091.3946-.3278.8075-1.967.4857-2.3072.4614-3.4364.8136-.0425.0304.0486.0607 1.5482.1457.6618.0364h1.621l3.0175.2247.7892.522.4736.6376-.079.4857-1.2142.6193-1.6393-.3886-3.825-.9107-1.3113-.3279h-.1822v.1093l1.0929 1.0686 2.0035 1.8092 2.5075 2.3314.1275.5768-.3218.4554-.34-.0486-2.2039-1.6575-.85-.7468-1.9246-1.621h-.1275v.17l.4432.6496 2.3436 3.5214.1214 1.0807-.17.3521-.6071.2125-.6679-.1214-1.3721-1.9246L14.38 17.959l-1.1414-1.9428-.1397.079-.674 7.2552-.3156.3703-.7286.2793-.6071-.4614-.3218-.7468.3218-1.4753.3886-1.9246.3157-1.53.2853-1.9004.17-.6314-.0121-.0425-.1397.0182-1.4328 1.9672-2.1796 2.9446-1.7243 1.8456-.4128.164-.7164-.3704.0667-.6618.4008-.5889 2.386-3.0357 1.4389-1.882.929-1.0868-.0062-.1579h-.0546l-6.3385 4.1164-1.1293.1457-.4857-.4554.0608-.7467.2307-.2429 1.9064-1.3114Z" />
    </svg>
  )
}

function OpenAIIcon({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="currentColor" className={className}>
      <path d="M22.2819 9.8211a5.9847 5.9847 0 0 0-.5157-4.9108 6.0462 6.0462 0 0 0-6.5098-2.9A6.0651 6.0651 0 0 0 4.9807 4.1818a5.9847 5.9847 0 0 0-3.9977 2.9 6.0462 6.0462 0 0 0 .7427 7.0966 5.98 5.98 0 0 0 .511 4.9107 6.051 6.051 0 0 0 6.5146 2.9001A5.9847 5.9847 0 0 0 13.2599 24a6.0557 6.0557 0 0 0 5.7718-4.2058 5.9894 5.9894 0 0 0 3.9977-2.9001 6.0557 6.0557 0 0 0-.7475-7.0729zm-9.022 12.6081a4.4755 4.4755 0 0 1-2.8764-1.0408l.1419-.0804 4.7783-2.7582a.7948.7948 0 0 0 .3927-.6813v-6.7369l2.02 1.1686a.071.071 0 0 1 .038.052v5.5826a4.504 4.504 0 0 1-4.4945 4.4944zm-9.6607-4.1254a4.4708 4.4708 0 0 1-.5346-3.0137l.142.0852 4.783 2.7582a.7712.7712 0 0 0 .7806 0l5.8428-3.3685v2.3324a.0804.0804 0 0 1-.0332.0615L9.74 19.9502a4.4992 4.4992 0 0 1-6.1408-1.6464zM2.3408 7.8956a4.485 4.485 0 0 1 2.3655-1.9728V11.6a.7664.7664 0 0 0 .3879.6765l5.8144 3.3543-2.0201 1.1685a.0757.0757 0 0 1-.071 0l-4.8303-2.7865A4.504 4.504 0 0 1 2.3408 7.872zm16.5963 3.8558L13.1038 8.364 15.1192 7.2a.0757.0757 0 0 1 .071 0l4.8303 2.7913a4.4944 4.4944 0 0 1-.6765 8.1042v-5.6772a.79.79 0 0 0-.407-.667zm2.0107-3.0231l-.142-.0852-4.7735-2.7818a.7759.7759 0 0 0-.7854 0L9.409 9.2297V6.8974a.0662.0662 0 0 1 .0284-.0615l4.8303-2.7866a4.4992 4.4992 0 0 1 6.6802 4.66zM8.3065 12.863l-2.02-1.1638a.0804.0804 0 0 1-.038-.0567V6.0742a4.4992 4.4992 0 0 1 7.3757-3.4537l-.142.0805L8.704 5.459a.7948.7948 0 0 0-.3927.6813zm1.0976-2.3654l2.602-1.4998 2.6069 1.4998v2.9994l-2.5974 1.4997-2.6067-1.4997Z" />
    </svg>
  )
}

function GeminiIcon({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="currentColor" className={className}>
      <path d="M11.04 19.32Q12 21.51 12 24q0-2.49.93-4.68.96-2.19 2.58-3.81t3.81-2.55Q21.51 12 24 12q-2.49 0-4.68-.93a12.3 12.3 0 0 1-3.81-2.58 12.3 12.3 0 0 1-2.58-3.81Q12 2.49 12 0q0 2.49-.96 4.68-.93 2.19-2.55 3.81a12.3 12.3 0 0 1-3.81 2.58Q2.49 12 0 12q2.49 0 4.68.96 2.19.93 3.81 2.55t2.55 3.81" />
    </svg>
  )
}

function OllamaIcon({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="currentColor" className={className}>
      <path d="M16.361 10.26a.894.894 0 0 0-.558.47l-.072.148.001.207c0 .193.004.217.059.353.076.193.152.312.291.448.24.238.51.3.872.205a.86.86 0 0 0 .517-.436.752.752 0 0 0 .08-.498c-.064-.453-.33-.782-.724-.897a1.06 1.06 0 0 0-.466 0zm-9.203.005c-.305.096-.533.32-.65.639a1.187 1.187 0 0 0-.06.52c.057.309.31.59.598.667.362.095.632.033.872-.205.14-.136.215-.255.291-.448.055-.136.059-.16.059-.353l.001-.207-.072-.148a.894.894 0 0 0-.565-.472 1.02 1.02 0 0 0-.474.007Zm4.184 2c-.131.071-.223.25-.195.383.031.143.157.288.353.407.105.063.112.072.117.136.004.038-.01.146-.029.243-.02.094-.036.194-.036.222.002.074.07.195.143.253.064.052.076.054.255.059.164.005.198.001.264-.03.169-.082.212-.234.15-.525-.052-.243-.042-.28.087-.355.137-.08.281-.219.324-.314a.365.365 0 0 0-.175-.48.394.394 0 0 0-.181-.033c-.126 0-.207.03-.355.124l-.085.053-.053-.032c-.219-.13-.259-.145-.391-.143a.396.396 0 0 0-.193.032zm.39-2.195c-.373.036-.475.05-.654.086-.291.06-.68.195-.951.328-.94.46-1.589 1.226-1.787 2.114-.04.176-.045.234-.045.53 0 .294.005.357.043.524.264 1.16 1.332 2.017 2.714 2.173.3.033 1.596.033 1.896 0 1.11-.125 2.064-.727 2.493-1.571.114-.226.169-.372.22-.602.039-.167.044-.23.044-.523 0-.297-.005-.355-.045-.531-.288-1.29-1.539-2.304-3.072-2.497a6.873 6.873 0 0 0-.855-.031zm.645.937a3.283 3.283 0 0 1 1.44.514c.223.148.537.458.671.662.166.251.26.508.303.82.02.143.01.251-.043.482-.08.345-.332.705-.672.957a3.115 3.115 0 0 1-.689.348c-.382.122-.632.144-1.525.138-.582-.006-.686-.01-.853-.042-.57-.107-1.022-.334-1.35-.68-.264-.28-.385-.535-.45-.946-.03-.192.025-.509.137-.776.136-.326.488-.73.836-.963.403-.269.934-.46 1.422-.512.187-.02.586-.02.773-.002zm-5.503-11a1.653 1.653 0 0 0-.683.298C5.617.74 5.173 1.666 4.985 2.819c-.07.436-.119 1.04-.119 1.503 0 .544.064 1.24.155 1.721.02.107.031.202.023.208a8.12 8.12 0 0 1-.187.152 5.324 5.324 0 0 0-.949 1.02 5.49 5.49 0 0 0-.94 2.339 6.625 6.625 0 0 0-.023 1.357c.091.78.325 1.438.727 2.04l.13.195-.037.064c-.269.452-.498 1.105-.605 1.732-.084.496-.095.629-.095 1.294 0 .67.009.803.088 1.266.095.555.288 1.143.503 1.534.071.128.243.393.264.407.007.003-.014.067-.046.141a7.405 7.405 0 0 0-.548 1.873c-.062.417-.071.552-.071.991 0 .56.031.832.148 1.279L3.42 24h1.478l-.05-.091c-.297-.552-.325-1.575-.068-2.597.117-.472.25-.819.498-1.296l.148-.29v-.177c0-.165-.003-.184-.057-.293a.915.915 0 0 0-.194-.25 1.74 1.74 0 0 1-.385-.543c-.424-.92-.506-2.286-.208-3.451.124-.486.329-.918.544-1.154a.787.787 0 0 0 .223-.531c0-.195-.07-.355-.224-.522a3.136 3.136 0 0 1-.817-1.729c-.14-.96.114-2.005.69-2.834.563-.814 1.353-1.336 2.237-1.475.199-.033.57-.028.776.01.226.04.367.028.512-.041.179-.085.268-.19.374-.431.093-.215.165-.333.36-.576.234-.29.46-.489.822-.729.413-.27.884-.467 1.352-.561.17-.035.25-.04.569-.04.319 0 .398.005.569.04a4.07 4.07 0 0 1 1.914.997c.117.109.398.457.488.602.034.057.095.177.132.267.105.241.195.346.374.43.14.068.286.082.503.045.343-.058.607-.053.943.016 1.144.23 2.14 1.173 2.581 2.437.385 1.108.276 2.267-.296 3.153-.097.15-.193.27-.333.419-.301.322-.301.722-.001 1.053.493.539.801 1.866.708 3.036-.062.772-.26 1.463-.533 1.854a2.096 2.096 0 0 1-.224.258.916.916 0 0 0-.194.25c-.054.109-.057.128-.057.293v.178l.148.29c.248.476.38.823.498 1.295.253 1.008.231 2.01-.059 2.581a.845.845 0 0 0-.044.098c0 .006.329.009.732.009h.73l.02-.074.036-.134c.019-.076.057-.3.088-.516.029-.217.029-1.016 0-1.258-.11-.875-.295-1.57-.597-2.226-.032-.074-.053-.138-.046-.141.008-.005.057-.074.108-.152.376-.569.607-1.284.724-2.228.031-.26.031-1.378 0-1.628-.083-.645-.182-1.082-.348-1.525a6.083 6.083 0 0 0-.329-.7l-.038-.064.131-.194c.402-.604.636-1.262.727-2.04a6.625 6.625 0 0 0-.024-1.358 5.512 5.512 0 0 0-.939-2.339 5.325 5.325 0 0 0-.95-1.02 8.097 8.097 0 0 1-.186-.152.692.692 0 0 1 .023-.208c.208-1.087.201-2.443-.017-3.503-.19-.924-.535-1.658-.98-2.082-.354-.338-.716-.482-1.15-.455-.996.059-1.8 1.205-2.116 3.01a6.805 6.805 0 0 0-.097.726c0 .036-.007.066-.015.066a.96.96 0 0 1-.149-.078A4.857 4.857 0 0 0 12 3.03c-.832 0-1.687.243-2.456.698a.958.958 0 0 1-.148.078c-.008 0-.015-.03-.015-.066a6.71 6.71 0 0 0-.097-.725C8.997 1.392 8.337.319 7.46.048a2.096 2.096 0 0 0-.585-.041Zm.293 1.402c.248.197.523.759.682 1.388.03.113.06.244.069.292.007.047.026.152.041.233.067.365.098.76.102 1.24l.002.475-.12.175-.118.178h-.278c-.324 0-.646.041-.954.124l-.238.06c-.033.007-.038-.003-.057-.144a8.438 8.438 0 0 1 .016-2.323c.124-.788.413-1.501.696-1.711.067-.05.079-.049.157.013zm9.825-.012c.17.126.358.46.498.888.28.854.36 2.028.212 3.145-.019.14-.024.151-.057.144l-.238-.06a3.693 3.693 0 0 0-.954-.124h-.278l-.119-.178-.119-.175.002-.474c.004-.669.066-1.19.214-1.772.157-.623.434-1.185.68-1.382.078-.062.09-.063.159-.012z" />
    </svg>
  )
}

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
const INTEGRATIONS: { label: string; icon: React.ComponentType<{ className?: string }> }[] = [
  { label: 'Claude', icon: ClaudeIcon },
  { label: 'OpenAI', icon: OpenAIIcon },
  { label: 'Gemini', icon: GeminiIcon },
  { label: 'Ollama', icon: OllamaIcon },
  { label: 'MCP Servers', icon: Plug2 },
  { label: 'A2A Protocol', icon: Share2 },
]

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
    title: 'Autonomy & Guardrails',
    description: 'Three autonomy tiers (supervised, semi-autonomous, autonomous), per-agent budgets, tool allowlists, and human approval gates. Ship agents you can trust.',
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
    quote: 'We went from manually wiring LangChain agents to designing full multi-agent workflows visually. eooo is what we needed all along.',
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
    q: 'What is eooo.ai?',
    a: 'eooo is an agent orchestration platform — pronounced "yo." Design AI agent teams, define their autonomy, and run them. The name stands for Execute, Orchestrate, Observe, Optimize — the agent lifecycle loop. Define agents as complete loop definitions, wire them into multi-agent workflows, connect real tools via MCP and A2A protocols, and run everything with built-in cost tracking and safety guardrails.',
  },
  {
    q: 'How is this different from Lovable, Replit, or other AI app builders?',
    a: 'Lovable and Replit use AI to generate application code — they build apps for you. eooo builds the AI agents themselves. You define how an agent thinks (goal, reasoning, tool use), wire agents into multi-step workflows, and run them with real tool calls. The output is not a web app — it is a running agent system with cost controls, execution traces, and safety guardrails. eooo is infrastructure for AI agents, not a code generator.',
  },
  {
    q: 'How is this different from LangChain or CrewAI?',
    a: 'LangChain and CrewAI are code frameworks — you write Python to define agents and chains. eooo is a visual design-and-runtime platform. You design agents and workflows in a UI, run them directly in the built-in execution engine, or export to LangGraph, CrewAI, or generic JSON for use in your own codebase. No framework lock-in.',
  },
  {
    q: 'What are MCP and A2A?',
    a: 'MCP (Model Context Protocol) lets agents call tools hosted on external servers — file systems, databases, APIs. A2A (Agent-to-Agent) lets agents delegate tasks to other agents over HTTP. eooo has built-in clients for both protocols.',
  },
  {
    q: 'Can agents actually execute tools, or is this just configuration?',
    a: 'Both. You can design agents and export configs, or run them live in the execution engine. The engine connects to real MCP tool servers, dispatches tool calls, tracks token usage and costs, and enforces budget guardrails — all in real time.',
  },
  {
    q: 'Can I self-host eooo?',
    a: 'Yes. eooo offers a self-hosted option with a commercial license. Deploy on your own infrastructure with Docker Compose, keeping full control over your data, API keys, and execution environment. Contact us for self-hosted licensing details.',
  },
  {
    q: 'What LLM providers are supported?',
    a: 'Anthropic (Claude Opus 4.6, Sonnet 4.6, Haiku 4.5), OpenAI (GPT-5.4, GPT-5.4 Thinking, GPT-5 Mini, o3), Google (Gemini 3.1 Pro, Gemini 3 Flash, Gemini 3.1 Flash Lite), and any Ollama-compatible local model. The execution engine routes to the correct provider based on the model configured for each agent.',
  },
  {
    q: 'How do guardrails work?',
    a: 'Every execution run enforces configurable budget limits (max tokens, max cost, max iterations), tool allowlists/blocklists with dangerous input detection, and output safety checks for PII and credential leakage. Guardrails are built into the execution loop, not bolted on.',
  },
  {
    q: 'Why build eooo, and why now?',
    a: 'AI models are now capable enough to reason, use tools, and collaborate — but there is no good way to manage them as a team. eooo exists to close that gap. We believe the next step is building agent teams that operate as first-class members of your organization: each with a defined role, clear boundaries, and the right level of autonomy — from fully supervised to fully autonomous. The models are ready. The tooling to manage them at that level has not existed until now.',
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
            <img src="/logo.png" alt="eooo.ai" className="h-8 w-8 object-contain" />
            <span className="font-semibold text-foreground tracking-tight">eooo.ai</span>
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
            <Link to="/compare" className="text-muted-foreground hover:text-foreground transition-colors">
              Compare
            </Link>
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
              <Link to="/compare" onClick={() => setMobileMenuOpen(false)} className="text-left py-2 px-2 text-muted-foreground hover:text-foreground transition-colors">
                Compare
              </Link>
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
              You Orchestrate.
              <br />
              <span className="text-primary">Your agents execute.</span>
            </h1>
            <p className="mt-4 text-sm font-mono text-muted-foreground/60 tracking-widest uppercase">
              Execute &middot; Orchestrate &middot; Observe &middot; Optimize
            </p>
            <p className="mt-4 text-lg sm:text-xl text-muted-foreground max-w-2xl leading-relaxed">
              Design AI agent teams, define their autonomy, and run them.
              Provider-agnostic multi-model routing with automatic fallback chains,
              real tool calls, cost tracking, and guardrails — all from one platform.
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
                    key={p.label}
                    className="flex items-center gap-2 text-sm font-medium text-foreground/70 px-3 py-1.5 bg-muted/50"
                  >
                    <p.icon className="h-5 w-5" />
                    {p.label}
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
                eooo is built as a layered architecture. Each layer builds on the one below.
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
                  Run eooo on your own infrastructure with a self-hosted license. Your agent definitions, execution data, and API keys never leave your servers. Deploy with Docker Compose in minutes.
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
                Start orchestrating in minutes. Free to start, no credit card required.
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
                <img src="/logo.png" alt="eooo.ai" className="h-7 w-7 object-contain" />
                <span className="font-semibold text-sm">eooo.ai</span>
              </div>
              <p className="text-xs text-muted-foreground leading-relaxed">
                You Orchestrate. Your agents execute.
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
                <li>
                  <Link to="/compare" className="text-muted-foreground hover:text-foreground transition-colors focus-visible:outline-2 focus-visible:outline-primary">
                    Compare
                  </Link>
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
            &copy; {new Date().getFullYear()} eooo.ai. All rights reserved.
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
