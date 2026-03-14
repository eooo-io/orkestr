import { useState, useEffect } from 'react'
import { Button } from '@/components/ui/button'

interface Props {
  value: string
  onChange: (value: string) => void
}

const PRESETS = [
  { label: 'Every minute', value: '* * * * *' },
  { label: 'Every 5 minutes', value: '*/5 * * * *' },
  { label: 'Every hour', value: '0 * * * *' },
  { label: 'Every day at midnight', value: '0 0 * * *' },
  { label: 'Every day at 9:00 AM', value: '0 9 * * *' },
  { label: 'Every weekday at 9:00 AM', value: '0 9 * * 1-5' },
  { label: 'Every Monday at 9:00 AM', value: '0 9 * * 1' },
  { label: 'First day of every month', value: '0 0 1 * *' },
]

const MINUTE_OPTIONS = ['*', '0', '5', '10', '15', '30', '*/5', '*/10', '*/15', '*/30']
const HOUR_OPTIONS = [
  '*', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11',
  '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23',
  '*/2', '*/4', '*/6', '*/12',
]
const DOM_OPTIONS = ['*', '1', '2', '5', '10', '15', '20', '25', '28']
const MONTH_OPTIONS = ['*', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12']
const DOW_OPTIONS = [
  { label: '* (any)', value: '*' },
  { label: '0 (Sun)', value: '0' },
  { label: '1 (Mon)', value: '1' },
  { label: '2 (Tue)', value: '2' },
  { label: '3 (Wed)', value: '3' },
  { label: '4 (Thu)', value: '4' },
  { label: '5 (Fri)', value: '5' },
  { label: '6 (Sat)', value: '6' },
  { label: '1-5 (Weekdays)', value: '1-5' },
]

const MONTH_NAMES = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']
const DOW_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']

function cronToHuman(expression: string): string {
  const parts = expression.trim().split(/\s+/)
  if (parts.length !== 5) return expression

  const [minute, hour, dom, month, dow] = parts

  // Check exact presets first
  const preset = PRESETS.find((p) => p.value === expression)
  if (preset) return preset.label

  const segments: string[] = []

  // Minute
  if (minute === '*') {
    segments.push('Every minute')
  } else if (minute.startsWith('*/')) {
    segments.push(`Every ${minute.slice(2)} minutes`)
  } else {
    segments.push(`At minute ${minute}`)
  }

  // Hour
  if (hour !== '*') {
    if (hour.startsWith('*/')) {
      segments.length = 0
      segments.push(`Every ${hour.slice(2)} hours`)
      if (minute !== '*' && minute !== '0') {
        segments.push(`at minute ${minute}`)
      }
    } else {
      const h = parseInt(hour)
      const m = parseInt(minute) || 0
      const ampm = h >= 12 ? 'PM' : 'AM'
      const displayH = h === 0 ? 12 : h > 12 ? h - 12 : h
      const displayM = m.toString().padStart(2, '0')
      segments.length = 0
      segments.push(`At ${displayH}:${displayM} ${ampm}`)
    }
  }

  // Day of month
  if (dom !== '*') {
    const suffix = dom === '1' || dom === '21' || dom === '31' ? 'st' :
                   dom === '2' || dom === '22' ? 'nd' :
                   dom === '3' || dom === '23' ? 'rd' : 'th'
    segments.push(`on the ${dom}${suffix}`)
  }

  // Month
  if (month !== '*') {
    const monthNum = parseInt(month)
    if (monthNum >= 1 && monthNum <= 12) {
      segments.push(`of ${MONTH_NAMES[monthNum]}`)
    } else {
      segments.push(`in month ${month}`)
    }
  }

  // Day of week
  if (dow !== '*') {
    if (dow === '1-5') {
      segments.push('on weekdays')
    } else {
      const dowNum = parseInt(dow)
      if (dowNum >= 0 && dowNum <= 6) {
        segments.push(`on ${DOW_NAMES[dowNum]}`)
      } else {
        segments.push(`on day-of-week ${dow}`)
      }
    }
  }

  return segments.join(' ')
}

export function CronBuilder({ value, onChange }: Props) {
  const [mode, setMode] = useState<'preset' | 'custom'>('preset')

  const parts = value.trim().split(/\s+/)
  const isValidParts = parts.length === 5
  const [minute, setMinute] = useState(isValidParts ? parts[0] : '*')
  const [hour, setHour] = useState(isValidParts ? parts[1] : '*')
  const [dom, setDom] = useState(isValidParts ? parts[2] : '*')
  const [month, setMonth] = useState(isValidParts ? parts[3] : '*')
  const [dow, setDow] = useState(isValidParts ? parts[4] : '*')

  // Detect if current value matches a preset
  const matchedPreset = PRESETS.find((p) => p.value === value)

  useEffect(() => {
    if (mode === 'custom') {
      const newVal = `${minute} ${hour} ${dom} ${month} ${dow}`
      if (newVal !== value) {
        onChange(newVal)
      }
    }
  }, [minute, hour, dom, month, dow, mode])

  // Sync internal fields when value changes externally
  useEffect(() => {
    const p = value.trim().split(/\s+/)
    if (p.length === 5) {
      setMinute(p[0])
      setHour(p[1])
      setDom(p[2])
      setMonth(p[3])
      setDow(p[4])
    }
  }, [value])

  const selectClass =
    'w-full border border-border bg-background px-2 py-1.5 text-sm rounded'

  return (
    <div className="space-y-3">
      <div className="flex items-center gap-2">
        <Button
          type="button"
          variant={mode === 'preset' ? 'default' : 'outline'}
          size="xs"
          onClick={() => setMode('preset')}
        >
          Presets
        </Button>
        <Button
          type="button"
          variant={mode === 'custom' ? 'default' : 'outline'}
          size="xs"
          onClick={() => setMode('custom')}
        >
          Custom
        </Button>
      </div>

      {mode === 'preset' ? (
        <div className="grid grid-cols-2 gap-1.5">
          {PRESETS.map((preset) => (
            <button
              key={preset.value}
              type="button"
              onClick={() => onChange(preset.value)}
              className={`text-left px-3 py-2 text-sm border rounded transition-colors ${
                value === preset.value
                  ? 'border-primary bg-primary/5 text-foreground'
                  : 'border-border bg-background text-muted-foreground hover:text-foreground hover:border-foreground/20'
              }`}
            >
              <span className="block text-xs font-mono text-muted-foreground">
                {preset.value}
              </span>
              <span className="block mt-0.5">{preset.label}</span>
            </button>
          ))}
        </div>
      ) : (
        <div className="space-y-2">
          <div className="grid grid-cols-5 gap-2">
            <div>
              <label className="text-xs font-medium text-muted-foreground mb-1 block">
                Minute
              </label>
              <select
                value={minute}
                onChange={(e) => setMinute(e.target.value)}
                className={selectClass}
              >
                {MINUTE_OPTIONS.map((o) => (
                  <option key={o} value={o}>{o}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="text-xs font-medium text-muted-foreground mb-1 block">
                Hour
              </label>
              <select
                value={hour}
                onChange={(e) => setHour(e.target.value)}
                className={selectClass}
              >
                {HOUR_OPTIONS.map((o) => (
                  <option key={o} value={o}>{o}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="text-xs font-medium text-muted-foreground mb-1 block">
                Day (month)
              </label>
              <select
                value={dom}
                onChange={(e) => setDom(e.target.value)}
                className={selectClass}
              >
                {DOM_OPTIONS.map((o) => (
                  <option key={o} value={o}>{o}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="text-xs font-medium text-muted-foreground mb-1 block">
                Month
              </label>
              <select
                value={month}
                onChange={(e) => setMonth(e.target.value)}
                className={selectClass}
              >
                {MONTH_OPTIONS.map((o) => (
                  <option key={o} value={o}>
                    {o === '*' ? '*' : MONTH_NAMES[parseInt(o)] || o}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label className="text-xs font-medium text-muted-foreground mb-1 block">
                Day (week)
              </label>
              <select
                value={dow}
                onChange={(e) => setDow(e.target.value)}
                className={selectClass}
              >
                {DOW_OPTIONS.map((o) => (
                  <option key={o.value} value={o.value}>{o.label}</option>
                ))}
              </select>
            </div>
          </div>
        </div>
      )}

      {/* Preview */}
      <div className="flex items-center gap-2 text-sm bg-muted/40 border border-border rounded px-3 py-2">
        <span className="font-mono text-xs text-muted-foreground">{value}</span>
        <span className="text-muted-foreground">&mdash;</span>
        <span className="text-foreground">
          {matchedPreset ? matchedPreset.label : cronToHuman(value)}
        </span>
      </div>
    </div>
  )
}

export { cronToHuman }
