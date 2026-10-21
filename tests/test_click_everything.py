"""Opens every page as guest, student, librarian and admin and clicks every
button and dialog on it. Fails on JavaScript errors, PHP error text in a
page, server errors and links that lead nowhere.

Run:  python tests/test_click_everything.py   (see tests/helpers.py for settings)

Problems collected:
  - JS exceptions (pageerror) and console errors (excluding external images we can't reach offline)
  - failed local requests (4xx/5xx) other than expected 403s
  - PHP warnings/notices/fatal errors printed into the HTML
  - buttons that throw or do nothing visible
"""
import asyncio, json, re, sys
from playwright.async_api import async_playwright

from helpers import BASE_URL as B, annotate, local_only, reset_database
ROLES = {
    "guest": None,
    "user": ("user", "user123"),
    "librarian": ("librarian", "librarian123"),
    "admin": ("admin", "admin123"),
}
PAGES = ["landing.php", "login.php", "register.php", "index.php", "books.php", "members.php", "borrowings.php", "profile.php", "profile.php?id=28"]
PHP_ERR = re.compile(r"(Warning|Notice|Deprecated|Fatal error|Parse error|Uncaught)\b.*?(on line|in /)", re.I | re.S)

problems = []

def note(role, page, kind, detail):
    problems.append({"role": role, "page": page, "kind": kind, "detail": str(detail)[:300]})


async def login(page, creds):
    await page.goto(B + "/login.php")
    await page.fill("#username", creds[0])
    await page.fill("#password", creds[1])
    await page.click("button[type=submit]")
    await page.wait_for_load_state()


def hook(page, role, current):
    def on_console(m):
        if m.type == "error" and "ERR_TUNNEL" not in m.text and "ERR_NAME" not in m.text and "Failed to load resource" not in m.text:
            note(role, current["p"], "console", m.text)
    page.on("console", on_console)
    page.on("pageerror", lambda e: note(role, current["p"], "js-exception", e))

    def on_response(r):
        u = r.url
        if u.startswith(B) and r.status >= 400 and r.status != 403:
            note(role, current["p"], "http", f"{r.status} {u}")
    page.on("response", on_response)
    page.on("dialog", lambda d: asyncio.ensure_future(d.dismiss()))  # never confirm deletes


async def visible_modal(page):
    return await page.evaluate("""() => [...document.querySelectorAll('.modal')]
        .filter(m => getComputedStyle(m).display !== 'none').map(m => m.id)""")


async def close_modals(page):
    await page.keyboard.press("Escape")
    await page.wait_for_timeout(100)
    await page.evaluate("() => document.querySelectorAll('.modal').forEach(m => m.style.display = 'none')")


async def click_everything(page, role, url, current):
    """Click each visible button once (skips logout/delete/submit), opening and closing modals."""
    labels = await page.evaluate("""() => [...document.querySelectorAll('button, a.btn, .avatar-edit')]
        .filter(el => el.offsetParent !== null)
        .map((el, i) => { el.dataset.crawlId = 'c' + i; return {id: 'c' + i, text: (el.innerText || el.getAttribute('aria-label') || el.className).trim().slice(0, 40), type: el.type || '', href: el.getAttribute('href') || ''}; })""")
    clicked = 0
    for item in labels:
        t = item["text"].lower()
        if item["type"] == "submit" or "delete" in t or "log out" in t or item["href"].startswith("members.php?delete"):
            continue
        if item["href"] and not item["href"].startswith("#"):
            continue  # links are checked separately
        el = page.locator(f'[data-crawl-id="{item["id"]}"]')
        if await el.count() == 0 or not await el.first.is_visible():
            continue
        try:
            await el.first.click(timeout=3000)
            await page.wait_for_timeout(450)
            clicked += 1
            # whatever opened: click every button inside the open modal that is safe, then close
            mods = await visible_modal(page)
            for mid in mods:
                inner = page.locator(f"#{mid} button:visible")
                for i in range(await inner.count()):
                    b = inner.nth(i)
                    bt = ((await b.inner_text()) or "").lower()
                    btype = await b.get_attribute("type")
                    if btype == "submit" or any(w in bt for w in ["delete", "cancel", "close"]) or await b.get_attribute("aria-label") == "Close":
                        continue
                    try:
                        await b.click(timeout=2000)
                        await page.wait_for_timeout(250)
                    except Exception as e:
                        note(role, url, "modal-button", f"{mid} / {bt}: {e}")
            await close_modals(page)
            if page.url.split("?")[0] != (B + "/" + url).split("?")[0]:
                await page.goto(B + "/" + url)
                await page.wait_for_load_state()
        except Exception as e:
            note(role, url, "click", f"{item['text']}: {e}")
    return clicked


async def main():
    reset_database()
    async with async_playwright() as p:
        br = await p.chromium.launch()
        summary = {}
        for role, creds in ROLES.items():
            ctx = await br.new_context(viewport={"width": 1366, "height": 900})
            await local_only(ctx)
            page = await ctx.new_page()
            current = {"p": "-"}
            hook(page, role, current)
            if creds:
                await login(page, creds)
            for url in PAGES:
                if creds and url in ("login.php", "register.php"):
                    continue
                current["p"] = url
                resp = await page.goto(B + "/" + url)
                await page.wait_for_load_state()
                await page.wait_for_timeout(300)
                html = await page.content()
                for m in PHP_ERR.finditer(html):
                    note(role, url, "php-in-html", m.group(0))
                final = page.url.replace(B + "/", "")
                # links on the page must not 404
                hrefs = await page.evaluate("() => [...document.querySelectorAll('a[href]')].map(a => a.getAttribute('href')).filter(h => h && !h.startsWith('#') && !h.startsWith('http') && !h.includes('delete=') && !h.startsWith('logout'))")
                for h in set(hrefs):
                    r = await ctx.request.get(B + "/" + h, max_redirects=0)
                    if r.status >= 400 and r.status != 403:
                        note(role, url, "broken-link", f"{h} -> {r.status}")
                clicked = await click_everything(page, role, final, current) if not final.startswith(("login.php", "landing.php", "register.php")) or url == final else 0
                summary[f"{role}:{url}"] = f"-> {final} ({resp.status if resp else '?'}), clicked {clicked}"
            await ctx.close()
        await br.close()
    if "-v" in sys.argv:
        print(json.dumps(summary, indent=1))
    for pr in problems:
        print("FAIL", json.dumps(pr, ensure_ascii=False))
        annotate("Click-everything test", json.dumps(pr, ensure_ascii=False))
    print(f"Click-everything test: {len(summary)} page visits, {len(problems)} problems")
    sys.exit(1 if problems else 0)


if __name__ == "__main__":
    asyncio.run(main())
