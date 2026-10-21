"""Every feature, as admin, librarian, student and guest.

Types into every search box letter by letter, uses every filter and sort,
submits every form with good and bad data, and compares what the page shows
with what is in the database.

Run:  python tests/test_functional.py        (see tests/helpers.py for settings)
"""
import asyncio
import json
import re
import sys

from playwright.async_api import async_playwright

from helpers import BASE_URL as B, PROJECT, TMP, Results, q, qi, reset_database

R = Results("Functional tests")
check = R.check
js_errors = R.js_errors


async def new_page(br, creds=None, mobile=False):
    ctx = await br.new_context(viewport={"width": 390, "height": 844} if mobile else {"width": 1366, "height": 900})
    pg = await ctx.new_page()
    pg.on("pageerror", lambda e: js_errors.append(str(e)))
    pg.on("dialog", lambda d: asyncio.ensure_future(d.accept()))
    if creds:
        await pg.goto(B + "/login.php")
        await pg.fill("#username", creds[0])
        await pg.fill("#password", creds[1])
        await pg.click("button[type=submit]")
        await pg.wait_for_load_state()
    return ctx, pg


async def type_slowly(pg, sel, text):
    await pg.fill(sel, "")
    await pg.type(sel, text, delay=40)
    await pg.wait_for_timeout(150)


async def visible_count(pg, sel):
    return await pg.evaluate(f"() => [...document.querySelectorAll('{sel}')].filter(e => e.offsetParent !== null).length")


async def alert_text(pg):
    a = pg.locator(".alert:visible")
    return (await a.first.inner_text()) if await a.count() else ""


# ------------------------------------------------------------------ AUTH
async def test_auth(br):
    A = "auth"
    ctx, pg = await new_page(br)
    await pg.goto(B + "/login.php")
    await pg.fill("#username", "admin"); await pg.fill("#password", "wrong"); await pg.click("button[type=submit]")
    check(A, "wrong password rejected", "Invalid username or password" in await alert_text(pg))
    check(A, "username kept after failed login", await pg.input_value("#username") == "admin")
    await pg.fill("#username", "admin@library.com"); await pg.fill("#password", "admin123"); await pg.click("button[type=submit]"); await pg.wait_for_load_state()
    check(A, "login with email works", pg.url.endswith("index.php"), pg.url)
    await pg.goto(B + "/logout.php")
    await pg.goto(B + "/index.php")
    check(A, "logout ends the session", "landing.php" in pg.url, pg.url)
    # register validation
    cases = [
        ({"full_name": "", "username": "abc", "email": "a@b.kz", "password": "secret1", "confirm_password": "secret1"}, "fill in all required"),
        ({"full_name": "X Y", "username": "ab", "email": "a@b.kz", "password": "secret1", "confirm_password": "secret1"}, "at least 3"),
        ({"full_name": "X Y", "username": "bad name!", "email": "a@b.kz", "password": "secret1", "confirm_password": "secret1"}, "letters, numbers"),
        ({"full_name": "X Y", "username": "newkid", "email": "not-an-email", "password": "secret1", "confirm_password": "secret1"}, "valid email"),
        ({"full_name": "X Y", "username": "newkid", "email": "n@k.kz", "password": "123", "confirm_password": "123"}, "at least 6"),
        ({"full_name": "X Y", "username": "newkid", "email": "n@k.kz", "password": "secret1", "confirm_password": "secret2"}, "do not match"),
        ({"full_name": "X Y", "username": "admin", "email": "n@k.kz", "password": "secret1", "confirm_password": "secret1"}, "already taken"),
        ({"full_name": "X Y", "username": "newkid", "email": "admin@library.com", "password": "secret1", "confirm_password": "secret1"}, "already exists"),
    ]
    for data, expect in cases:
        r = await ctx.request.post(B + "/register.php", form=data)
        body = await r.text()
        check(A, f"register rejects: {expect}", expect.lower() in body.lower(), re.sub(r"\s+", " ", body)[:120] if expect.lower() not in body.lower() else "")
    r = await ctx.request.post(B + "/register.php", form={"full_name": "Dana Seitkali", "class": "Year 9C - Venus", "username": "dana_s", "email": "dana@school.kz", "password": "secret1", "confirm_password": "secret1"}, max_redirects=0)
    check(A, "valid registration redirects to login", r.status == 302 and "registered=1" in (r.headers.get("location") or ""), r.status)
    check(A, "registration creates member + user", q("select concat(m.name,'|',m.class,'|',u.role) from users u join members m on m.id=u.member_id where u.username='dana_s'") == "Dana Seitkali|Year 9C - Venus|user")
    await ctx.close()
    # session expiry on an AJAX endpoint
    ctx, pg = await new_page(br)
    r = await ctx.request.get(B + "/get_book_reviews.php?book_id=3", headers={"Sec-Fetch-Dest": "empty"}, max_redirects=0)
    check(A, "logged-out AJAX gets JSON 401, not the login page", r.status == 401 and "application/json" in (r.headers.get("content-type") or ""), f"{r.status} {r.headers.get('content-type')}")
    await ctx.close()


# ------------------------------------------------------------------ DASHBOARD
async def test_dashboard(br):
    A = "dashboard"
    ctx, pg = await new_page(br, ("librarian", "librarian123"))
    vals = await pg.locator(".stat-value").all_inner_texts()
    exp = [qi("select count(*) from books"), qi("select count(*) from members where status='active'"),
           qi("select count(*) from borrowings where status='active'"), qi("select count(*) from borrowings where status='active' and due_date<curdate()")]
    check(A, "staff stats match database", [int(v) for v in vals] == exp, f"{vals} vs {exp}")
    rows = await pg.locator(".data-table tbody tr").count()
    check(A, "overdue table lists every overdue loan", rows == exp[3], f"{rows} vs {exp[3]}")
    await pg.click("text=Issue or return"); await pg.wait_for_load_state()
    check(A, "'Issue or return' opens borrowings", pg.url.endswith("borrowings.php"))
    await pg.go_back(); await pg.click("text=Add a book"); await pg.wait_for_load_state()
    check(A, "'Add a book' opens books", pg.url.endswith("books.php"))
    await pg.goto(B + "/index.php")
    await pg.locator(".data-table tbody tr a").first.click(); await pg.wait_for_load_state()
    check(A, "student name in overdue table opens profile", "profile.php?id=" in pg.url)
    await ctx.close()

    ctx, pg = await new_page(br, ("emma", "123"))
    mid = 7
    vals = [int(v) for v in await pg.locator(".stat-value").all_inner_texts()]
    exp = [qi(f"select count(*) from borrowings where member_id={mid}"), qi(f"select count(*) from borrowings where member_id={mid} and status='active'"),
           qi(f"select count(*) from borrowings where member_id={mid} and status='returned'")]
    check(A, "student stats match database", vals[:3] == exp, f"{vals} vs {exp}")
    items = await pg.locator(".reading-item").count()
    check(A, "student 'Your books' list shows up to 6 loans", items == min(6, exp[0]), items)
    await pg.click("text=Full history"); await pg.wait_for_load_state()
    check(A, "'Full history' opens own profile", pg.url.endswith("profile.php"))
    await pg.goto(B + "/index.php"); await pg.click("text=Find a book"); await pg.wait_for_load_state()
    check(A, "'Find a book' opens catalogue", pg.url.endswith("books.php"))
    await ctx.close()


# ------------------------------------------------------------------ BOOKS
async def test_books(br):
    A = "books"
    ctx, pg = await new_page(br, ("user", "user123"))
    await pg.goto(B + "/books.php"); await pg.wait_for_timeout(300)
    total = qi("select count(*) from books")
    check(A, "every book is shown", await pg.locator(".book-card").count() == total)
    # search: type letter by letter and compare with SQL after each keystroke
    for term in ["orw", "tolk", "978-0-14", "king", "classic", "Абай", "zzzz", "the "]:
        for i in range(1, len(term) + 1):
            part = term[:i]
            await pg.fill("#searchInput", part)
            await pg.wait_for_timeout(30)
        shown = await visible_count(pg, ".book-card")
        like = term.strip().lower().replace("'", "''")
        exp = qi(f"select count(*) from books where lower(concat_ws(' ',title,author,coalesce(isbn,''),coalesce(category,''))) like '%{like}%'")
        check(A, f"search '{term}' (typed) shows matching books", shown == exp, f"{shown} vs db {exp}")
        empty_visible = await pg.locator("#noResultsMessage").is_visible()
        check(A, f"search '{term}' empty-state correct", empty_visible == (exp == 0))
    for term in ["асхат", "АСХАТ", "аманк"]:
        await type_slowly(pg, "#searchInput", term)
        check(A, f"Cyrillic search '{term}' finds the book", await visible_count(pg, ".book-card") == 1, await visible_count(pg, ".book-card"))
    await pg.fill("#searchInput", "")
    # category filter, every option
    opts = await pg.locator("#categoryFilter option").all_inner_texts()
    for c in opts[1:]:
        await pg.select_option("#categoryFilter", label=c)
        shown = await visible_count(pg, ".book-card")
        exp = qi(f"select count(*) from books where lower(category)=lower('{c.replace(chr(39), chr(39)*2)}')")
        check(A, f"category '{c}'", shown == exp, f"{shown} vs {exp}")
    await pg.select_option("#categoryFilter", "")
    for val, cond in [("yes", "available_quantity>0"), ("no", "available_quantity<=0")]:
        await pg.select_option("#availabilityFilter", val)
        shown = await visible_count(pg, ".book-card")
        check(A, f"availability '{val}'", shown == qi(f"select count(*) from books where {cond}"), shown)
    await pg.select_option("#availabilityFilter", "")
    # combined search + category
    await pg.select_option("#categoryFilter", label="Classic") if "Classic" in opts else None
    await pg.fill("#searchInput", "the")
    shown = await visible_count(pg, ".book-card")
    exp = qi("select count(*) from books where lower(category)='classic' and lower(concat_ws(' ',title,author,coalesce(isbn,''),coalesce(category,''))) like '%the%'")
    check(A, "search + category combined", shown == exp, f"{shown} vs {exp}")
    await pg.fill("#searchInput", ""); await pg.select_option("#categoryFilter", "")
    # sorting
    for opt, key in [("title", "title"), ("author", "author")]:
        await pg.select_option("#sortOrder", opt)
        got = await pg.evaluate(f"() => [...document.querySelectorAll('.book-card')].map(c => c.dataset.{key})")
        check(A, f"sort by {opt}", got == sorted(got, key=lambda s: s), got[:5])
    await pg.select_option("#sortOrder", "rating")
    got = await pg.evaluate("() => [...document.querySelectorAll('.book-card')].map(c => parseFloat(c.dataset.rating))")
    check(A, "sort by rating", got == sorted(got, reverse=True), got[:5])
    await pg.select_option("#sortOrder", "reviews")
    got = await pg.evaluate("() => [...document.querySelectorAll('.book-card')].map(c => parseInt(c.dataset.reviews))")
    check(A, "sort by most reviewed", got == sorted(got, reverse=True), got[:5])
    # view toggle persists
    await pg.click("#viewListBtn"); await pg.reload(); await pg.wait_for_timeout(300)
    check(A, "list view remembered after reload", await pg.evaluate("document.getElementById('booksGrid').classList.contains('is-list')"))
    await pg.fill("#searchInput", "orwell")
    check(A, "search works in list view", await visible_count(pg, ".book-card") == 1)
    await pg.fill("#searchInput", ""); await pg.click("#viewGridBtn")
    # every card opens the right book
    ids = await pg.evaluate("() => [...document.querySelectorAll('.book-card')].map(c => c.dataset.bookId)")
    wrong = []
    for bid in ids:
        await pg.locator(f'.book-card[data-book-id="{bid}"]').click()
        await pg.wait_for_function("document.getElementById('bookInfoTitle').textContent !== 'Book'", timeout=5000)
        title = await pg.inner_text("#bookInfoTitle")
        if title != q(f"select title from books where id={bid}"):
            wrong.append((bid, title))
        await pg.keyboard.press("Escape")
    check(A, f"all {len(ids)} cards open their own book", not wrong, wrong[:3])
    # keyboard: Enter on a focused card
    await pg.locator(".book-card").first.focus(); await pg.keyboard.press("Enter"); await pg.wait_for_timeout(400)
    check(A, "Enter key opens a focused card", await pg.locator("#bookInfoModal").is_visible())
    await pg.keyboard.press("Escape")
    # review as student: no stars -> error, then 4 stars
    await pg.locator('.book-card[data-book-id="3"]').click(); await pg.wait_for_timeout(500)
    await pg.click("#reviewForm button[type=submit]"); await pg.wait_for_timeout(200)
    check(A, "review without stars is refused", "Pick 1 to 5" in await pg.inner_text("#reviewMsg"))
    await pg.locator("label[for=star4]").click(); await pg.fill("#comment", "Student review <b>bold?</b>")
    await pg.click("#reviewForm button[type=submit]"); await pg.wait_for_timeout(700)
    check(A, "student review saved under own account", q("select concat(member_id,'|',rating) from book_reviews where comment like 'Student review%'") == "5|4")
    html = await pg.inner_html("#reviewsContent")
    check(A, "review text shown as text, not HTML", "&lt;b&gt;" in html and "<b>bold" not in html)
    # second review by the same student updates instead of duplicating
    await pg.locator("label[for=star2]").click(); await pg.fill("#comment", "Student review changed"); await pg.click("#reviewForm button[type=submit]"); await pg.wait_for_timeout(700)
    check(A, "same student reviewing again updates their review", qi("select count(*) from book_reviews where book_id=3 and member_id=5") == 1)
    await ctx.close()

    # staff
    ctx, pg = await new_page(br, ("librarian", "librarian123"))
    await pg.goto(B + "/books.php")
    await pg.locator('.book-card[data-book-id="3"]').click(); await pg.wait_for_timeout(700)
    check(A, "staff sees who has the book", await pg.locator("#borrowersContent li").count() == qi("select count(*) from borrowings where book_id=3 and status='active'"))
    await pg.locator("label[for=star5]").click(); await pg.fill("#comment", "Librarian review")
    await pg.click("#reviewForm button[type=submit]"); await pg.wait_for_timeout(800)
    check(A, "librarian can post a review", "Thanks" in await pg.inner_text("#reviewMsg"), await pg.inner_text("#reviewMsg"))
    # edit copies: 4 copies with 2 on loan -> set to 6 -> expect 4 free
    loans = qi("select count(*) from borrowings where book_id=3 and status='active'")
    await pg.click("text=Edit book"); await pg.wait_for_timeout(600)
    await pg.fill("#quantity", "6"); await pg.click("#bookSubmit"); await pg.wait_for_load_state(); await pg.wait_for_timeout(300)
    check(A, "changing copies updates the free count", q("select concat(quantity,'/',available_quantity) from books where id=3") == f"6/{6 - loans}",
          q("select concat(quantity,'/',available_quantity) from books where id=3"))
    # fewer copies than are on loan must be refused
    await pg.locator('.book-card[data-book-id="3"]').click(); await pg.wait_for_timeout(500); await pg.click("text=Edit book"); await pg.wait_for_timeout(500)
    await pg.fill("#quantity", str(max(loans - 1, 0))); await pg.click("#bookSubmit"); await pg.wait_for_timeout(700)
    err = await pg.inner_text("#bookFormError") if await pg.locator("#bookFormError").is_visible() else ""
    check(A, "cannot set fewer copies than are on loan", "on loan" in err.lower(), err)
    await pg.keyboard.press("Escape")
    # add: empty title blocked by browser, duplicate ISBN gets a clear message
    await pg.goto(B + "/books.php")
    await pg.click(".page-header >> text=Add book"); await pg.wait_for_timeout(300); await pg.fill("#title", "Dup Test"); await pg.fill("#author", "X"); await pg.fill("#isbn", "978-0-452-26059-5")
    await pg.click("#bookSubmit"); await pg.wait_for_timeout(800)
    msg = await pg.inner_text("#bookFormError") if await pg.locator("#bookFormError").is_visible() else ""
    check(A, "duplicate ISBN on add: dialog stays open with a clear message", "isbn" in msg.lower() and "already" in msg.lower() and await pg.locator("#bookModal").is_visible(), msg or "(no message)")
    check(A, "typed data kept after the error", await pg.input_value("#title") == "Dup Test")
    await pg.keyboard.press("Escape")
    check(A, "duplicate ISBN book not created", qi("select count(*) from books where title='Dup Test'") == 0)
    # add a normal book with Cyrillic title
    await pg.click(".page-header >> text=Add book"); await pg.wait_for_timeout(300); await pg.fill("#title", "Абай жолы"); await pg.fill("#author", "Мұхтар Әуезов"); await pg.fill("#quantity", "2"); await pg.fill("#shelf_number", "3"); await pg.fill("#shelf_row", "B")
    await pg.click("#bookSubmit"); await pg.wait_for_url("**/books.php"); await pg.wait_for_load_state(); await pg.wait_for_timeout(500)
    check(A, "add book (Kazakh/Cyrillic text) saved correctly", q("select concat(title,'|',author,'|',quantity,'/',available_quantity,'|',shelf_number,shelf_row) from books where title='Абай жолы'") == "Абай жолы|Мұхтар Әуезов|2/2|3B")
    await pg.fill("#searchInput", "әуез")
    check(A, "search finds Kazakh letters", await visible_count(pg, ".book-card") == 1)
    # edit duplicate ISBN
    nid = q("select id from books where title='Абай жолы'")
    await pg.fill("#searchInput", ""); await pg.locator(f'.book-card[data-book-id="{nid}"]').click(); await pg.wait_for_timeout(500); await pg.click("text=Edit book"); await pg.wait_for_timeout(500)
    await pg.fill("#isbn", "978-0-452-26059-5"); await pg.click("#bookSubmit"); await pg.wait_for_timeout(700)
    err = await pg.inner_text("#bookFormError") if await pg.locator("#bookFormError").is_visible() else ""
    check(A, "duplicate ISBN on edit gives a clear message", "isbn" in err.lower(), err or "(no message)")
    await pg.keyboard.press("Escape")
    # cover upload: real image and fake image
    await pg.locator(f'.book-card[data-book-id="{nid}"]').click(); await pg.wait_for_timeout(500); await pg.click("text=Edit book"); await pg.wait_for_timeout(500)
    await pg.set_input_files("#cover_file", str(PROJECT / "uploads/covers/download__1__1764137327_5609.jpg"))
    await pg.click("#bookSubmit"); await pg.wait_for_load_state(); await pg.wait_for_timeout(300)
    cov = q(f"select cover_url from books where id={nid}")
    img = await ctx.request.get(B + "/" + cov)
    check(A, "cover upload saved and served", cov.startswith("uploads/covers/") and img.status == 200 and img.headers.get("content-type", "").startswith("image/"), cov)
    # CSV import: valid, duplicate, bad rows
    csv = str(TMP / "books_test.csv")
    open(csv, "w", encoding="utf-8").write("title,author,isbn,category,quantity,shelf_number,shelf_row\nCSV One,Auth,111-1,Test,2,1,A\n,No Title,222-2,Test,1,,\nCSV Two,Auth,978-0-452-26059-5,Test,1,,\nCSV Neg,Auth,333-3,Test,-1,,\n")
    await pg.click("text=Import CSV"); await pg.set_input_files("#csvFile", csv); await pg.click("#importSubmit"); await pg.wait_for_timeout(800)
    res = await pg.inner_text("#importResults")
    check(A, "CSV import: good row in, bad rows skipped with reasons", "Imported 1" in res and "Skipped 3" in res, res.replace("\n", " "))
    await pg.click("#importCancel"); await pg.wait_for_load_state()
    # semicolon CSV (Excel in many locales)
    csv2 = str(TMP / "books_semicolon.csv")
    open(csv2, "w", encoding="utf-8-sig").write("title;author;isbn;category;quantity;shelf_number;shelf_row\nSemi Book;Auth;444-4;Test;3;2;C\n")
    await pg.click("text=Import CSV"); await pg.set_input_files("#csvFile", csv2); await pg.click("#importSubmit"); await pg.wait_for_timeout(800)
    res = await pg.inner_text("#importResults")
    check(A, "CSV import accepts ';' separated file from Excel", qi("select count(*) from books where title='Semi Book' and quantity=3") == 1, res.replace("\n", " "))
    await ctx.close()

    # admin bulk delete only deletes visible + selected
    ctx, pg = await new_page(br, ("admin", "admin123"))
    await pg.goto(B + "/books.php")
    await pg.fill("#searchInput", "csv one"); await pg.check("#selectAllBooks") if await pg.locator("#selectAllBooks").is_visible() else None
    await pg.locator(".book-card:visible .book-select-checkbox").first.check()
    await pg.wait_for_timeout(100)
    await pg.check("#selectAllBooks")
    check(A, "select-all selects only the visible books", await pg.inner_text("#selectedCount") == "1 selected", await pg.inner_text("#selectedCount"))
    await pg.click("#deleteSelectedBtn"); await pg.wait_for_load_state(); await pg.wait_for_timeout(300)
    check(A, "bulk delete removes exactly the selected book", qi("select count(*) from books where title='CSV One'") == 0 and qi("select count(*) from books where title='Semi Book'") == 1)
    await ctx.close()


# ------------------------------------------------------------------ BORROWINGS
async def test_borrowings(br):
    A = "borrowings"
    ctx, pg = await new_page(br, ("librarian", "librarian123"))
    await pg.goto(B + "/borrowings.php"); await pg.wait_for_timeout(300)
    exp = {"active": qi("select count(*) from borrowings where status='active'"), "overdue": qi("select count(*) from borrowings where status='active' and due_date<curdate()"),
           "returned": qi("select count(*) from borrowings where status='returned'"), "all": qi("select count(*) from borrowings")}
    for k, v in exp.items():
        await pg.click(f"button[data-filter={k}]")
        shown = await visible_count(pg, ".loan-row")
        badge = int(await pg.inner_text(f"button[data-filter={k}] .seg-count"))
        check(A, f"tab '{k}' rows and counter match database", shown == v == badge, f"rows {shown}, badge {badge}, db {v}")
    await pg.click("button[data-filter=all]")
    for term in ["chen", "orwell", "year 9", "gatsby", "zzz"]:
        await type_slowly(pg, "#loanSearch", term)
        shown = await visible_count(pg, ".loan-row")
        exp_n = qi(f"select count(*) from borrowings br join books b on b.id=br.book_id join members m on m.id=br.member_id where lower(concat_ws(' ',b.title,b.author,m.name,coalesce(m.class,''))) like '%{term}%'")
        check(A, f"search '{term}' (typed) on All tab", shown == exp_n, f"{shown} vs {exp_n}")
    await pg.fill("#loanSearch", "")
    # issue: validation
    await pg.click("text=Issue a book"); await pg.wait_for_timeout(500)
    await pg.click("#borrowForm button[type=submit]")
    check(A, "issue without student is refused", "student" in (await pg.inner_text("#formError")).lower())
    await type_slowly(pg, "#memberSearch", "ADIL")
    opts = await pg.locator("#memberSearchResults .combo-option").all_inner_texts()
    check(A, "student search is case-insensitive while typing", any("Adil" in o for o in opts), opts)
    await type_slowly(pg, "#memberSearch", "venus")
    opts = await pg.locator("#memberSearchResults .combo-option").count()
    check(A, "student search by class", opts == qi("select count(*) from members where status='active' and lower(class) like '%venus%'"), opts)
    await pg.locator("#memberSearchResults .combo-option").first.dispatch_event("mousedown")
    await pg.click("#borrowForm button[type=submit]")
    check(A, "issue without book is refused", "book" in (await pg.inner_text("#formError")).lower())
    await type_slowly(pg, "#bookSearch", "hobb")
    await pg.locator("#bookSearchResults .combo-option").first.dispatch_event("mousedown")
    await pg.fill("#due_date", "2000-01-01")
    await pg.click("#borrowForm button[type=submit]")
    check(A, "due date before borrow date is refused", "after" in (await pg.inner_text("#formError")).lower())
    # typing again after choosing clears the choice
    await type_slowly(pg, "#bookSearch", "hobbi")
    check(A, "editing the book text clears the old choice", await pg.input_value("#book_id") == "")
    await pg.locator("#bookSearchResults .combo-option").first.dispatch_event("mousedown")
    await pg.fill("#due_date", "2030-01-01")
    before = qi("select available_quantity from books where title='The Hobbit'")
    await pg.click("#borrowForm button[type=submit]"); await pg.wait_for_load_state()
    check(A, "issuing lowers free copies by 1", qi("select available_quantity from books where title='The Hobbit'") == before - 1)
    bid = q("select id from borrowings order by id desc limit 1")
    # the new loan appears and can be returned; filter kept after return
    await pg.click("button[data-filter=all]")
    await pg.locator(f"form:has(input[name=borrowing_id][value='{bid}']) button").click(); await pg.wait_for_load_state()
    check(A, "return restores the copy", qi("select available_quantity from books where title='The Hobbit'") == before)
    check(A, "tab is kept after an action", "filter=all" in pg.url, pg.url)
    # book with no free copies cannot be issued (server side)
    full = q("select id from books where quantity=1 and available_quantity=1 limit 1")
    if full:
        await ctx.request.post(B + "/borrowings.php", form={"action": "borrow", "member_id": "28", "book_id": full, "borrow_date": "2026-10-21", "due_date": "2026-11-04"})
        r = await ctx.request.post(B + "/borrowings.php", form={"action": "borrow", "member_id": "28", "book_id": full, "borrow_date": "2026-10-21", "due_date": "2026-11-04"})
        check(A, "server refuses a book with no free copies", qi(f"select available_quantity from books where id={full}") == 0 and "no copies left" in (await r.text()).lower(), q(f"select available_quantity from books where id={full}"))
    # garbage dates do not crash
    r = await ctx.request.post(B + "/borrowings.php", form={"action": "borrow", "member_id": "28", "book_id": "7", "borrow_date": "garbage", "due_date": "also-garbage"})
    check(A, "invalid dates handled without a crash", r.status == 200 and "Fatal" not in await r.text(), r.status)
    await ctx.close()


# ------------------------------------------------------------------ MEMBERS
async def test_members(br):
    A = "members"
    ctx, pg = await new_page(br, ("librarian", "librarian123"))
    await pg.goto(B + "/members.php"); await pg.wait_for_timeout(300)
    total = qi("select count(*) from members")
    check(A, "every member listed", await pg.locator(".member-row").count() == total)
    for term in ["abdul", "INT", "askhat", "venus", "zzz"]:
        await type_slowly(pg, "#memberSearch", term)
        shown = await visible_count(pg, ".member-row")
        t = term.lower()
        exp = qi(f"select count(*) from members m left join users u on u.member_id=m.id where lower(concat_ws(' ',m.name,coalesce(m.group_name,''),coalesce(u.username,''),coalesce(m.class,''))) like '%{t}%'")
        check(A, f"search '{term}' (typed)", shown == exp, f"{shown} vs {exp}")
    await pg.fill("#memberSearch", "")
    for c in (await pg.locator("#classFilter option").all_inner_texts())[1:]:
        await pg.select_option("#classFilter", label=c)
        shown = await visible_count(pg, ".member-row")
        check(A, f"class filter '{c}'", shown == qi(f"select count(*) from members where class='{c}'"), shown)
    await pg.select_option("#classFilter", "")
    await pg.select_option("#statusFilter", "overdue")
    check(A, "'has overdue books' filter", await visible_count(pg, ".member-row") == qi("select count(distinct member_id) from borrowings where status='active' and due_date<curdate()"))
    await pg.select_option("#statusFilter", "inactive")
    check(A, "'inactive' filter", await visible_count(pg, ".member-row") == qi("select count(*) from members where status='inactive'"))
    await pg.select_option("#statusFilter", "")
    # clicking a row opens the profile; Edit does not
    await pg.locator(".member-row").first.locator("text=Edit").click(); await pg.wait_for_timeout(500)
    check(A, "Edit opens the dialog, not the profile", pg.url.endswith("members.php") and await pg.locator("#memberModal").is_visible())
    await pg.keyboard.press("Escape")
    # add with only a first name, edit status to inactive
    await pg.click("text=Add member"); await pg.wait_for_timeout(400)
    await pg.fill("#first_name", "Aigerim"); await pg.click("#memberSubmit"); await pg.wait_for_load_state()
    check(A, "add with only a first name", qi("select count(*) from members where name='Aigerim'") == 1)
    await pg.click("text=Add member"); await pg.wait_for_timeout(400)
    await pg.fill("#first_name", "Әсел"); await pg.fill("#last_name", "Нұрқызы"); await pg.fill("#group_name", "ҚАЗ"); await pg.click("#memberSubmit"); await pg.wait_for_load_state()
    for term in ["әсел", "ӘСЕЛ", "нұрқ", "қаз"]:
        await type_slowly(pg, "#memberSearch", term)
        check(A, f"Kazakh search '{term}'", await visible_count(pg, ".member-row") == 1, await visible_count(pg, ".member-row"))
    await pg.fill("#memberSearch", "")
    await pg.goto(B + "/borrowings.php"); await pg.click("text=Issue a book"); await pg.wait_for_timeout(500)
    await type_slowly(pg, "#memberSearch", "әсел")
    check(A, "issue dialog finds a Kazakh name typed in lower case", await pg.locator("#memberSearchResults .combo-option").count() == 1)
    await pg.locator("#memberSearchResults .combo-option").first.dispatch_event("mousedown")
    await type_slowly(pg, "#bookSearch", "dracula"); await pg.locator("#bookSearchResults .combo-option").first.dispatch_event("mousedown")
    await pg.click("#borrowForm button[type=submit]"); await pg.wait_for_load_state()
    await type_slowly(pg, "#loanSearch", "НҰРҚЫЗЫ")
    check(A, "loan search finds a Kazakh name typed in capitals", await visible_count(pg, ".loan-row") == 1, await visible_count(pg, ".loan-row"))
    await pg.goto(B + "/members.php")
    mid = q("select id from members where name='Aigerim'")
    await pg.locator(".member-row", has_text="Aigerim").locator("text=Edit").click(); await pg.wait_for_timeout(700)
    await pg.select_option("#status", "inactive"); await pg.click("#memberSubmit"); await pg.wait_for_load_state()
    check(A, "status can be changed to inactive", q(f"select status from members where id={mid}") == "inactive")
    r = await ctx.request.post(B + "/members.php", form={"save_member": "1", "first_name": "", "last_name": "", "membership_date": "2026-01-01"})
    check(A, "empty name refused", "Enter a first or last name" in await r.text())
    r = await ctx.request.post(B + "/members.php", form={"save_member": "1", "first_name": "Bad", "membership_date": "not-a-date"})
    check(A, "invalid date handled without a crash", r.status == 200 and "Fatal" not in await r.text(), r.status)
    # classes: invalid ranges refused, extra classes with odd input
    r = await ctx.request.post(B + "/update_classes.php", data=json.dumps({"min_grade": 9, "max_grade": 3, "sections": ["A"], "start_month": 9}), headers={"Content-Type": "application/json"})
    check(A, "grade range 9 to 3 refused", r.status == 400, r.status)
    r = await ctx.request.post(B + "/update_classes.php", data=json.dumps({"min_grade": 1, "max_grade": 12, "sections": [], "start_month": 13}), headers={"Content-Type": "application/json"})
    check(A, "empty sections / month 13 refused", r.status == 400, r.status)
    await pg.click("text=Classes"); await pg.wait_for_timeout(400)
    await pg.fill("textarea[name=manual_classes]", "Year 10A - Oxford, 7H"); await pg.click("text=Add classes"); await pg.wait_for_load_state()
    msg = await alert_text(pg)
    check(A, "extra class names like 'Year 10A - Oxford' accepted", "Added" in msg or "duplicates" in msg, msg)
    # member CSV: template from the site (with BOM) and a ';' file
    tpl = await ctx.request.get(B + "/download_members_template.php")
    path = str(TMP / "members_template.csv")
    open(path, "wb").write(await tpl.body())
    await pg.click("text=Import CSV"); await pg.set_input_files("#csv_file", path); await pg.click("#importModal button[type=submit]"); await pg.wait_for_load_state()
    msg = await alert_text(pg)
    check(A, "the site's own CSV template imports", "Import finished" in msg, msg)
    path2 = str(TMP / "members_semi.csv")
    open(path2, "w", encoding="utf-8-sig").write("First Name;Last Name;Class;Group\nNurlan;Bekov;Year 8A;HUM\n")
    await pg.click("text=Import CSV"); await pg.set_input_files("#csv_file", path2); await pg.click("#importModal button[type=submit]"); await pg.wait_for_load_state()
    msg = await alert_text(pg)
    check(A, "member CSV with ';' from Excel imports", qi("select count(*) from members where name='Nurlan Bekov'") == 1, msg)
    await ctx.close()


# ------------------------------------------------------------------ PROFILE
async def test_profile(br):
    A = "profile"
    ctx, pg = await new_page(br, ("emma", "123"))
    await pg.goto(B + "/profile.php")
    rows = await pg.locator(".data-table tbody tr").count()
    check(A, "history lists every loan", rows == qi("select count(*) from borrowings where member_id=7"))
    for cur, new, conf, expect in [("wrong", "abcdef", "abcdef", "incorrect"), ("123", "abc", "abc", "at least 6")]:
        r = await ctx.request.post(B + "/profile.php", form={"change_password": "1", "current_password": cur, "new_password": new, "confirm_password": conf}, max_redirects=0)
        await pg.goto(B + "/profile.php")
        check(A, f"password change refused: {expect}", expect in (await alert_text(pg)).lower(), await alert_text(pg))
    await pg.click(".avatar-edit"); await pg.click("text=Use a link"); await pg.fill("#avatar_url", "javascript:alert(1)")
    await pg.click("#avatarForm button[type=submit]"); await pg.wait_for_load_state()
    check(A, "non-http photo link refused", q("select coalesce(avatar_url,'') from members where id=7") == "")
    fake = str(TMP / "fake.png"); open(fake, "w").write("<?php echo 'x'; ?>")
    await pg.click(".avatar-edit"); await pg.set_input_files("#avatar_file", fake); await pg.click("#avatarForm button[type=submit]"); await pg.wait_for_load_state()
    check(A, "PHP file disguised as .png refused", "Only JPG" in await alert_text(pg))
    await ctx.close()


async def test_admin_delete(br):
    A = "members"
    ctx, pg = await new_page(br, ("admin", "admin123"))
    busy = q("select member_id from borrowings where status='active' limit 1")
    await pg.goto(B + f"/members.php?delete={busy}")
    check(A, "member with books on loan cannot be deleted", "still has" in await alert_text(pg) and qi(f"select count(*) from members where id={busy}") == 1, await alert_text(pg))
    await pg.goto(B + "/members.php?delete=2")
    check(A, "admin cannot delete their own member card", qi("select count(*) from members where id=2") == 1, await alert_text(pg))
    free = q("select m.id from members m where not exists (select 1 from borrowings b where b.member_id=m.id and b.status='active') and m.id not in (select member_id from users) limit 1")
    await pg.goto(B + "/members.php")
    await pg.locator(f'.member-row[onclick*="id={free}\'"] >> text=Delete').click(); await pg.wait_for_load_state()
    check(A, "member without loans can be deleted", qi(f"select count(*) from members where id={free}") == 0, await alert_text(pg))
    await ctx.close()


async def test_integrity(br):
    A = "integrity"
    bad = qi("select count(*) from books b where b.available_quantity <> b.quantity - (select count(*) from borrowings br where br.book_id=b.id and br.status='active')")
    check(A, "free copies = copies - loans for every book after all tests", bad == 0, bad)
    check(A, "no negative free copies", qi("select count(*) from books where available_quantity<0") == 0)


async def main():
    reset_database()
    async with async_playwright() as p:
        br = await p.chromium.launch()
        only = [a for a in sys.argv[1:] if not a.startswith("-")] or ["auth", "dashboard", "books", "borrowings", "members", "admin_delete", "profile", "integrity"]
        for name in only:
            try:
                await globals()["test_" + name](br)
            except Exception as e:
                check(name, "test crashed", False, repr(e)[:300])
        await br.close()
    ok = R.report(verbose="-v" in sys.argv)
    sys.exit(0 if ok else 1)


if __name__ == "__main__":
    asyncio.run(main())
