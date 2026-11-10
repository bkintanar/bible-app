#!/usr/bin/env python3
"""End-to-end check of the reader's interactive features in headless Chrome (no dependencies:
plain W3C WebDriver over HTTP + Chrome DevTools for touch). Runs against PRODUCTION, read-only
except for the reader's own localStorage in a throwaway browser profile.

    V=$(google-chrome --version | grep -oE '[0-9.]+$')
    npx --yes @puppeteer/browsers@2.10.5 install chromedriver@$V --path /tmp/cd
    python3 scripts/audit/browser/reader_check.py /tmp/cd/chromedriver/linux-$V/chromedriver-linux64/chromedriver /tmp/out

Covers: selector open/pick/Escape/focus return, arrow keys (also blocked while the selector is
open), A-/A+ and persistence, dark mode, Strong's popover, in-chapter search focus and results,
touch swipes, and printing (WebDriver Print -> PDF page count). Screenshots land in <out>.
"""
import base64, json, subprocess, sys, time, urllib.request

CD = sys.argv[1]; OUT = sys.argv[2]; BASE = 'https://bible.b8.co.nz'
proc = subprocess.Popen([CD, '--port=9515'], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
time.sleep(1.5)

def call(method, path, body=None):
    req = urllib.request.Request('http://127.0.0.1:9515' + path, method=method,
                                 data=json.dumps(body).encode() if body is not None else None,
                                 headers={'Content-Type': 'application/json'})
    with urllib.request.urlopen(req, timeout=60) as r:
        return json.loads(r.read())['value']

results = []
def check(name, ok, detail=''):
    results.append((name, bool(ok), detail)); print(('PASS ' if ok else 'FAIL ') + name + (f'  [{detail}]' if detail else ''), flush=True)

try:
    sid = call('POST', '/session', {'capabilities': {'alwaysMatch': {'browserName': 'chrome', 'goog:chromeOptions': {
        'args': ['--headless=new', '--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', '--window-size=1280,900']}}}})['sessionId']
    S = f'/session/{sid}'
    js = lambda script, *args: call('POST', f'{S}/execute/sync', {'script': script, 'args': list(args)})
    jsa = lambda script: call('POST', f'{S}/execute/async', {'script': script, 'args': []})
    go = lambda url: call('POST', f'{S}/url', {'url': BASE + url})
    find = lambda css: call('POST', f'{S}/element', {'using': 'css selector', 'value': css})
    EL = 'element-6066-11e4-a52e-4f735466cecf'
    click = lambda css: call('POST', f"{S}/element/{find(css)[EL]}/click", {})
    keys = lambda text: call('POST', f'{S}/actions', {'actions': [{'type': 'key', 'id': 'kb', 'actions':
        [a for ch in text for a in ({'type': 'keyDown', 'value': ch}, {'type': 'keyUp', 'value': ch})]}]})
    shot = lambda name: open(f'{OUT}/{name}.png', 'wb').write(base64.b64decode(call('GET', f'{S}/screenshot')))
    wait = lambda cond, t=8: next((True for _ in range(int(t / 0.25)) if (js('return ' + cond) or time.sleep(0.25))), False)

    go('/books/Gen/chapters/1'); time.sleep(1.5)
    js("localStorage.clear(); document.documentElement.classList.remove('dark'); document.documentElement.removeAttribute('data-font-size');")
    go('/books/Gen/chapters/1'); time.sleep(1.5)
    check('page loads with Livewire + Alpine', js('return !!window.Livewire && !!window.Alpine'))
    shot('01-gen1-light')

    # Book chooser
    click('h1 button'); wait("document.querySelector('[role=dialog]').getClientRects().length > 0")
    check('selector opens', js("return document.querySelector('[role=dialog]').getClientRects().length > 0"))
    time.sleep(0.4)
    check('focus moves into the dialog', js("return document.querySelector('[role=dialog]').contains(document.activeElement)"))
    js("[...document.querySelectorAll('[role=dialog] button')].find(b => b.textContent.trim() === 'Exodus').click()"); time.sleep(0.5)
    n = js("return [...document.querySelectorAll('[role=dialog] a[href*=\"/books/Exod/chapters/\"]')].filter(a => a.offsetParent !== null).length")
    check('picking Exodus lists its 40 chapters as links', n == 40, f'{n} links')
    shot('02-selector-exodus')
    keys(''); time.sleep(1.0)  # ArrowRight while the selector is open
    check('arrow keys do not change chapter while the selector is open', js('return location.pathname') == '/books/Gen/chapters/1', js('return location.pathname'))
    keys(''); time.sleep(0.6)  # Escape
    check('Escape closes the selector', js("return document.querySelector('[role=dialog]').getClientRects().length === 0"))
    check('focus returns to the chapter heading', js("return document.activeElement === document.querySelector('h1 button')"),
          js("return document.activeElement && (document.activeElement.tagName + ' ' + (document.activeElement.textContent||'').trim().slice(0,30))"))

    # Text size
    before = js("return parseFloat(getComputedStyle(document.querySelector('.page-current .reader-text')).fontSize)")
    click('button[aria-label="Larger text"]'); time.sleep(0.3)
    after = js("return parseFloat(getComputedStyle(document.querySelector('.page-current .reader-text')).fontSize)")
    check('A+ enlarges the text', after > before, f'{before}px -> {after}px')
    go('/books/Gen/chapters/1'); time.sleep(1.5)
    kept = js("return [document.documentElement.getAttribute('data-font-size'), parseFloat(getComputedStyle(document.querySelector('.page-current .reader-text')).fontSize)]")
    check('text size survives a reload', kept[0] == 'lg' and kept[1] == after, str(kept))
    click('button[aria-label="Smaller text"]'); time.sleep(0.3)
    check('A- returns to the default', js("return document.documentElement.getAttribute('data-font-size')") == 'base')

    # Dark mode
    click('button[aria-label="Toggle dark mode"]'); time.sleep(0.3)
    check('dark mode toggles', js("return document.documentElement.classList.contains('dark')"))
    shot('03-gen1-dark')
    click('button[aria-label="Toggle dark mode"]'); time.sleep(0.3)

    # Strong's popover
    js("document.querySelector('.page-current .strong-word[data-strongs]').click()"); time.sleep(0.3)
    pop = js("const p = document.getElementById('strongs-popover'); return p && !p.hidden ? p.textContent : null")
    check("tapping a word shows its Strong's number", pop and "Strong's" in pop, str(pop))
    shot('04-strongs-popover')
    keys(''); time.sleep(0.2)
    check('Escape hides the popover', js("return document.getElementById('strongs-popover').hidden"))

    # In-chapter search box
    click('button[aria-label="Search"]'); time.sleep(0.4)
    check('search box opens with focus', js("return document.activeElement && document.activeElement.getAttribute('aria-label') === 'Search the Bible'"))
    keys(''); time.sleep(0.4)
    check('Escape closes the search box and focus returns to its button', js("return document.activeElement === document.querySelector('button[aria-label=\"Search\"]')"))
    click('button[aria-label="Search"]'); time.sleep(0.4)
    keys('let there be light'); wait("document.body.innerText.includes('results for')", 10)
    check('in-chapter search returns results', js("return document.body.innerText.includes('results for')"))
    shot('05-search-results')

    # Arrow keys
    go('/books/Gen/chapters/1'); time.sleep(1.5)
    js("document.activeElement && document.activeElement.blur()")
    keys(''); time.sleep(1.2)  # ArrowLeft: Gen 1 has no previous chapter
    check('ArrowLeft on Gen 1 stays put', js('return location.pathname') == '/books/Gen/chapters/1')
    keys(''); wait("location.pathname === '/books/Gen/chapters/2'", 8)  # ArrowRight
    check('ArrowRight goes to Gen 2', js('return location.pathname') == '/books/Gen/chapters/2', js('return location.pathname'))

    # Swipe (real touch events through Chrome DevTools): left swipe = next chapter, right = previous
    cdp = lambda cmd, params: call('POST', f'{S}/goog/cdp/execute', {'cmd': cmd, 'params': params})
    def swipe(x0, x1, y=420, steps=8):
        cdp('Input.dispatchTouchEvent', {'type': 'touchStart', 'touchPoints': [{'x': x0, 'y': y}]})
        for i in range(1, steps + 1):
            cdp('Input.dispatchTouchEvent', {'type': 'touchMove', 'touchPoints': [{'x': x0 + (x1 - x0) * i / steps, 'y': y}]}); time.sleep(0.02)
        cdp('Input.dispatchTouchEvent', {'type': 'touchEnd', 'touchPoints': []})
    go('/books/Gen/chapters/2'); time.sleep(1.5)
    swipe(1000, 250); wait("location.pathname === '/books/Gen/chapters/3'", 8)
    check('swiping left turns to the next chapter', js('return location.pathname') == '/books/Gen/chapters/3', js('return location.pathname'))
    time.sleep(1.0); swipe(250, 1000); wait("location.pathname === '/books/Gen/chapters/2'", 8)
    check('swiping right turns back', js('return location.pathname') == '/books/Gen/chapters/2', js('return location.pathname'))
    swipe(600, 560); time.sleep(1.2)
    check('a short drag does not turn the page', js('return location.pathname') == '/books/Gen/chapters/2')

    # Print (WebDriver Print Page -> PDF)
    go('/books/Matt/chapters/5'); time.sleep(1.5)
    pdf = base64.b64decode(call('POST', f'{S}/print', {'background': False}))
    open(f'{OUT}/matt5.pdf', 'wb').write(pdf)
    pages = pdf.count(b'/Type /Page') - pdf.count(b'/Type /Pages')
    check('printing Matt 5 gives the whole chapter (several pages)', pages >= 3, f'{pages} pages')

    call('DELETE', S)
finally:
    proc.terminate()
print(f"\n{sum(ok for _, ok, _ in results)}/{len(results)} checks passed")
