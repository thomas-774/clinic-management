// WCAG 2.1 AA browser audit (NFR-U.1, T11-13). For every page of every role,
// in Arabic and English, in headless Edge:
//   - axe-core with the WCAG 2.0 / 2.1 A and AA rules (colour contrast included),
//   - reflow at 320 px and at 640 px (= 200 % zoom of a 1280 px window):
//     the page must not scroll sideways,
//   - a keyboard pass: Tab through the page; every stop must show a focus
//     indicator and sit on a visible element.
//   - CSP (NFR-S.1): `vite preview` sends the same Content-Security-Policy as
//     Nginx (vite.config.js); every `securitypolicyviolation` on the page or
//     in its dialogs fails the run (the browser's own extensions excepted).
// The app is the production build served by `vite preview`; every API call is
// answered from fixtures.json (captured from the fake PerformanceSeeder data)
// inside the browser, so no backend and no real account is involved.
//
//   npm run a11y:audit -- [--only=<text>] [--out=<file.json>] [--build=no]
//   EDGE_PATH=... to use another Chromium-based browser.
import { spawn, spawnSync } from 'node:child_process'
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const here = dirname(fileURLToPath(import.meta.url))
const root = join(here, '..', '..')
const args = Object.fromEntries(process.argv.slice(2).map((arg) => arg.replace(/^--/, '').split('=')))
const PORT = 4174
const ORIGIN = `http://localhost:${PORT}`
const CDP_PORT = 9334
const EDGE = process.env.EDGE_PATH ?? 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'
const fixtures = JSON.parse(readFileSync(join(here, 'fixtures.json'), 'utf8'))
const axeSource = readFileSync(join(root, 'node_modules', 'axe-core', 'axe.min.js'), 'utf8')
const { patient: P, prescription: RX } = fixtures.ids

/** [role, path, name, actions?]: actions open a dialog and audit it too. */
export const PAGES = [
  ['public', '/login', 'Login'],
  ['public', '/register', 'Register'],
  ['public', '/status', 'Status'],
  ['patient', '/patient', 'Patient home'],
  ['patient', '/patient/book', 'Book an appointment', [{ click: '[role=group] button', name: 'confirm dialog' }]],
  ['patient', '/patient/appointments', 'My appointments'],
  ['doctor', '/doctor', 'Dashboard'],
  ['doctor', '/doctor/schedule', 'Schedule'],
  ['doctor', '/doctor/patients', 'Patients', [{ clickText: ['New patient', 'مريض جديد'], name: 'new patient dialog' }]],
  ['doctor', `/doctor/patients/${P}`, 'Patient details'],
  ['doctor', `/doctor/visits/new?patient=${P}`, 'New visit'],
  ['doctor', `/doctor/patients/${P}/prescriptions/new`, 'New prescription'],
  ['doctor', `/doctor/prescriptions/${RX}/edit`, 'Edit prescription'],
  ['doctor', `/doctor/prescriptions/${RX}/print`, 'Print prescription'],
  ['doctor', '/doctor/settings', 'Settings: general'],
  ['doctor', '/doctor/settings?tab=drugs', 'Settings: drugs'],
  ['doctor', '/doctor/settings?tab=prescription', 'Settings: prescription'],
  ['doctor', '/doctor/settings?tab=activity', 'Settings: activity'],
  ['doctor', '/doctor/reports', 'Reports'],
  ['assistant', '/assistant', 'Assistant: today'],
  ['assistant', '/assistant/patients', 'Assistant: patients'],
  ['assistant', `/assistant/patients/${P}`, 'Assistant: patient page'],
  ['assistant', '/assistant/schedule', 'Assistant: schedule'],
]

const LOADING = { ar: 'جارٍ التحميل…', en: 'Loading…' }
const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms))

/** The fixture for one API call, or null. */
export function answer(role, method, url) {
  if (method !== 'GET') return { data: null, message: null }
  const { pathname, searchParams } = new URL(url)
  const path = pathname.replace(/^\/api\/v1/, '')
  if (path === '/health') return fixtures.public['/health']
  const set = fixtures[role] ?? {}
  if (path.endsWith('/reports/summary')) return set[`${path}?${searchParams.get('period')}`]
  if (path.endsWith('/appointments') && searchParams.get('from') !== searchParams.get('to')) return set[`${path}?week`]
  if (set[path]) return set[path]
  const generic = path.replace(/\/drugs\/\d+$/, `/drugs/${fixtures.ids.drug}`).replace(/\/prescriptions\/\d+$/, `/prescriptions/${RX}`)
  return set[generic] ?? null
}

/** Minimal CDP client over the browser's WebSocket (flattened sessions). */
export async function connect(wsUrl) {
  const socket = new WebSocket(wsUrl)
  await new Promise((resolve, reject) => {
    socket.onopen = resolve
    socket.onerror = reject
  })
  let nextId = 1
  const pending = new Map()
  const listeners = []
  socket.onmessage = ({ data }) => {
    const message = JSON.parse(data)
    if (message.id && pending.has(message.id)) {
      const { resolve, reject } = pending.get(message.id)
      pending.delete(message.id)
      if (message.error) reject(new Error(message.error.message))
      else resolve(message.result)
    } else if (message.method) {
      listeners.forEach((listener) => listener(message))
    }
  }
  const send = (method, params = {}, sessionId) =>
    new Promise((resolve, reject) => {
      const id = nextId++
      pending.set(id, { resolve, reject })
      socket.send(JSON.stringify({ id, method, params, sessionId }))
    })
  return { send, on: (listener) => listeners.push(listener), close: () => socket.close() }
}

export async function waitFor(check, timeout = 15000) {
  const until = Date.now() + timeout
  while (Date.now() < until) {
    if (await check()) return true
    await sleep(150)
  }
  return false
}

async function main() {
  // A production build whose API is the preview origin: same-origin calls, answered below.
  if (args.build !== 'no') {
    const built = spawnSync('npx vite build --outDir dist-a11y', { cwd: root, shell: true, stdio: 'inherit', env: { ...process.env, VITE_API_URL: `${ORIGIN}/api/v1` } })
    if (built.status !== 0) throw new Error('vite build failed')
  }
  const preview = spawn(`npx vite preview --port ${PORT} --strictPort --outDir dist-a11y`, { cwd: root, shell: true, stdio: 'ignore' })
  const profile = mkdtempSync(join(tmpdir(), 'a11y-edge-'))
  const edge = spawn(EDGE, ['--headless=new', `--remote-debugging-port=${CDP_PORT}`, `--user-data-dir=${profile}`, '--no-first-run', '--window-size=1280,900', 'about:blank'], { stdio: 'ignore' })
  const results = []
  let cdp

  try {
    await waitFor(() => fetch(ORIGIN).then((r) => r.ok, () => false), 30000)
    let version
    await waitFor(async () => {
      version = await fetch(`http://127.0.0.1:${CDP_PORT}/json/version`).then((r) => r.json(), () => null)
      return Boolean(version)
    })
    cdp = await connect(version.webSocketDebuggerUrl)
    const { targetId } = await cdp.send('Target.createTarget', { url: 'about:blank' })
    const { sessionId } = await cdp.send('Target.attachToTarget', { targetId, flatten: true })
    const send = (method, params) => cdp.send(method, params, sessionId)
    const evaluate = async (expression) => (await send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true })).result.value

    let role = 'public'
    const unmocked = new Set()
    cdp.on(({ method, params, sessionId: from }) => {
      if (method !== 'Fetch.requestPaused' || from !== sessionId) return
      const body = answer(role, params.request.method, params.request.url)
      if (!body) unmocked.add(`${role} ${params.request.method} ${params.request.url.replace(ORIGIN, '')}`)
      send('Fetch.fulfillRequest', {
        requestId: params.requestId,
        responseCode: body ? 200 : 404,
        responseHeaders: [{ name: 'Content-Type', value: 'application/json' }],
        body: Buffer.from(JSON.stringify(body ?? { message: 'Not in the audit fixtures.' })).toString('base64'),
      }).catch(() => {})
    })
    await send('Page.enable')
    await send('Fetch.enable', { patterns: [{ urlPattern: `${ORIGIN}/api/v1/*` }] })

    const viewport = (width) => send('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: false })
    const ready = (language) =>
      waitFor(() => evaluate(`!!document.querySelector('h1') && !document.body.innerText.includes(${JSON.stringify(LOADING[language])})`))

    const runAxe = async () => {
      if (!(await evaluate('!!window.axe'))) await send('Runtime.evaluate', { expression: axeSource })
      return evaluate(`axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'] }, resultTypes: ['violations'] })
        .then((r) => r.violations.map((v) => ({ id: v.id, impact: v.impact, help: v.help, nodes: v.nodes.slice(0, 5).map((n) => ({ target: n.target.join(' '), summary: n.failureSummary })) })))`)
    }

    const overflow = async (width) => {
      await viewport(width)
      await sleep(1000)
      const value = await evaluate(`(() => {
        const extra = document.documentElement.scrollWidth - document.documentElement.clientWidth
        // The culprits: boxes past the edge (either side, for RTL) that no scrolling box contains.
        const scrolls = (e) => { for (let p = e.parentElement; p && p !== document.body; p = p.parentElement) { if (/(auto|scroll|hidden)/.test(getComputedStyle(p).overflowX)) return true } return false }
        const past = (e) => { const r = e.getBoundingClientRect(); return (r.right > innerWidth + 1 || r.left < -1) && getComputedStyle(e).position !== 'fixed' }
        const label = (e) => e.tagName.toLowerCase() + (e.className && typeof e.className === 'string' ? '.' + e.className.split(' ').slice(0, 4).join('.') : '')
        // Innermost boxes past the edge outside any scrolling box: what to fix.
        const wide = extra > 1 ? [...document.querySelectorAll('body *')].filter((e) => past(e) && !scrolls(e) && ![...e.children].some((c) => past(c) && !scrolls(c)))
          .slice(0, 4).map(label) : []
        return { extra, wide }
      })()`)
      await viewport(1280)
      return value
    }

    const keyboard = async () => {
      await evaluate(`document.activeElement?.blur(); window.scrollTo(0, 0)`)
      const stops = []
      for (let i = 0; i < 80; i++) {
        await send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9 })
        await send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9 })
        await sleep(40)
        const stop = await evaluate(`(() => {
          const e = document.activeElement
          if (!e || e === document.body) return null
          const s = getComputedStyle(e)
          const r = e.getBoundingClientRect()
          const ring = (s.outlineStyle !== 'none' && parseFloat(s.outlineWidth) > 0) || (s.boxShadow && s.boxShadow !== 'none')
          const name = (e.getAttribute('aria-label') || e.innerText || e.value || e.placeholder || '').trim().slice(0, 40)
          return { key: e.outerHTML.slice(0, 120) + '|' + (e.innerText || e.value || ''), what: e.tagName.toLowerCase() + (name ? ' "' + name + '"' : ''), ring, visible: r.width > 0 && r.height > 0 }
        })()`)
        if (!stop) break
        if (stops.length && stop.key === stops[0].key) break
        stops.push(stop)
      }
      return {
        stops: stops.length,
        order: stops.map((s) => s.what),
        noRing: stops.filter((s) => !s.ring).map((s) => s.what),
        hidden: stops.filter((s) => !s.visible).map((s) => s.what),
      }
    }

    for (const [pageRole, path, name, actions = []] of PAGES) {
      if (args.only && !`${name} ${path}`.toLowerCase().includes(args.only.toLowerCase())) continue
      for (const language of ['ar', 'en']) {
        role = pageRole
        const token = pageRole === 'public' ? 'null' : "'a11y-audit'"
        const { identifier } = await send('Page.addScriptToEvaluateOnNewDocument', {
          source:
            `localStorage.setItem('clinic.lang', '${language}'); ${token === 'null' ? "localStorage.removeItem('clinic.token')" : `localStorage.setItem('clinic.token', ${token})`}; window.print = () => {};` +
            // Edge's own component extensions run an eval in some pages; they are not the app.
            ` window.__csp = []; document.addEventListener('securitypolicyviolation', (e) => { if (!/^(chrome|edge)-extension/.test(e.sourceFile)) window.__csp.push(e.effectiveDirective + ' ' + (e.blockedURI || 'inline')) });`,
        })
        await viewport(1280)
        await send('Page.navigate', { url: ORIGIN + path })
        const loaded = await ready(language)
        await sleep(500)
        const entry = { page: name, path, role: pageRole, language, loaded, axe: await runAxe(), reflow320: await overflow(320), reflow640: await overflow(640), keyboard: await keyboard() }
        entry.dialogs = []
        for (const action of actions) {
          const opened = await evaluate(`(() => {
            const el = ${action.click ? `document.querySelector(${JSON.stringify(action.click)})` : `[...document.querySelectorAll('button')].find((b) => ${JSON.stringify(action.clickText)}.includes(b.innerText.trim()))`}
            if (!el) return false
            el.click()
            return true
          })()`)
          await sleep(400)
          const hasDialog = opened && (await evaluate(`!!document.querySelector('[role=dialog]')`))
          entry.dialogs.push({ name: action.name, opened: hasDialog, axe: hasDialog ? await runAxe() : [] })
        }
        entry.csp = await evaluate('window.__csp ?? []')
        await send('Page.removeScriptToEvaluateOnNewDocument', { identifier })
        results.push(entry)
        const serious = [...entry.axe, ...entry.dialogs.flatMap((d) => d.axe)].filter((v) => ['serious', 'critical'].includes(v.impact)).length
        console.log(
          `${loaded ? ' ' : '!'} ${language} ${name.padEnd(26)} axe ${String(entry.axe.length).padStart(2)} (serious/critical ${serious})` +
            ` · reflow 320 ${entry.reflow320.extra}px 640 ${entry.reflow640.extra}px · tab stops ${entry.keyboard.stops}, no ring ${entry.keyboard.noRing.length}, hidden ${entry.keyboard.hidden.length}` +
            entry.dialogs.map((d) => ` · ${d.name} ${d.opened ? `axe ${d.axe.length}` : 'NOT OPENED'}`).join('') +
            ` · CSP ${entry.csp.length}${entry.csp.length ? ` (${[...new Set(entry.csp)].join(', ')})` : ''}`,
        )
      }
    }
    if (unmocked.size) console.log(`\nCalls without a fixture:\n  ${[...unmocked].join('\n  ')}`)
  } finally {
    cdp?.close()
    edge.kill()
    // The shell's child (vite) outlives a plain kill() on Windows.
    if (process.platform === 'win32') spawn('taskkill', ['/PID', String(preview.pid), '/T', '/F'], { stdio: 'ignore' })
    else preview.kill()
    // Edge's launcher exits at once and leaves its renderers; stop every process of this profile.
    if (process.platform === 'win32') {
      spawn('powershell', ['-NoProfile', '-Command', `Get-CimInstance Win32_Process -Filter "Name='msedge.exe'" | Where-Object { $_.CommandLine -like '*${profile}*' } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force }`], { stdio: 'ignore' }).on('exit', () => {
        try {
          rmSync(profile, { recursive: true, force: true })
        } catch {
          // a renderer may still hold a file; the OS cleans the temp folder
        }
      })
    }
  }

  const out = args.out ?? join(root, 'a11y-audit-results.json')
  writeFileSync(out, JSON.stringify(results, null, 1))
  console.log(`\nResults: ${out}`)
  const blocking = results.flatMap((r) => [...r.axe, ...r.dialogs.flatMap((d) => d.axe)]).filter((v) => ['serious', 'critical'].includes(v.impact))
  process.exitCode = blocking.length || results.some((r) => !r.loaded || r.csp.length) ? 1 : 0
}

if (fileURLToPath(import.meta.url) === process.argv[1]) main()
