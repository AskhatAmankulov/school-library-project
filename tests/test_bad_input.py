"""Bad input on every endpoint: missing and fake IDs, letters for numbers,
arrays for text, very long text, impossible dates, broken JSON, wrong methods.

A check passes when the site answers without a crash (no HTTP 500 and no PHP
error text in the reply).

Run:  python tests/test_bad_input.py        (see tests/helpers.py for settings)
"""
import asyncio
import re
import sys

from playwright.async_api import async_playwright

from helpers import BASE_URL as B, annotate, reset_database

LONG = "Ж" * 300
PHPERR = re.compile(r"(Fatal error|Warning:|Notice:|Deprecated:|Uncaught|Stack trace)", re.I)
fails, passes = [], 0


async def login(ctx, u, p):
    await ctx.request.post(B + "/login.php", form={"username": u, "password": p})


async def hit(ctx, method, path, label, **kw):
    global passes
    r = await (ctx.request.get(B + path, max_redirects=0, **kw) if method == "GET" else ctx.request.post(B + path, max_redirects=0, **kw))
    body = await r.text()
    bad = r.status >= 500 or PHPERR.search(body)
    if bad:
        fails.append(f"{r.status} {method} {path} [{label}] -> {re.sub(r'<[^>]+>|\\s+', ' ', body)[:200]}")
    else:
        passes += 1
    return r


async def main():
    reset_database()
    async with async_playwright() as p:
        br = await p.chromium.launch()
        ctx = await br.new_context()
        # guest: login/register with odd input
        for form, label in [({}, "empty"), ({"username[]": "a", "password": "x"}, "array username"),
                            ({"username": LONG, "password": LONG}, "long"), ({"username": "' OR 1=1 -- ", "password": "x"}, "sql-ish")]:
            await hit(ctx, "POST", "/login.php", label, form=form)
        for form, label in [({}, "empty"), ({"full_name[]": "a"}, "array"),
                            ({"full_name": LONG, "username": "longname", "email": "l@l.kz", "password": "secret1", "confirm_password": "secret1"}, "300-char name"),
                            ({"full_name": "A", "username": "x" * 60, "email": "l2@l.kz", "password": "secret1", "confirm_password": "secret1"}, "60-char username")]:
            await hit(ctx, "POST", "/register.php", label, form=form)
        await login(ctx, "admin", "admin123")

        ids = ["", "0", "-1", "abc", "999999", "1e9", "%27", "3%20OR%201=1"]
        for ep, param in [("/get_book.php", "book_id"), ("/get_book_reviews.php", "book_id"), ("/get_book_borrowers.php", "book_id"),
                          ("/get_member.php", "id"), ("/get_member_history.php", "member_id"), ("/profile.php", "id")]:
            for v in ids:
                await hit(ctx, "GET", f"{ep}?{param}={v}", v)
            await hit(ctx, "GET", f"{ep}?{param}[]=1", "array")
            await hit(ctx, "GET", ep, "missing")
        for ep in ["/search_books.php", "/search_members.php"]:
            for v in ["", "a", "%25", "_", LONG, "q[]=1"]:
                await hit(ctx, "GET", f"{ep}?q={v}" if not v.startswith("q[") else f"{ep}?{v}", v[:10])
        for ep in ["/get_all_books.php", "/get_all_members.php", "/get_classes.php", "/download_members_template.php", "/index.php", "/books.php",
                   "/members.php", "/borrowings.php", "/landing.php", "/members.php?delete=abc", "/members.php?delete=999999", "/borrowings.php?filter=<script>"]:
            await hit(ctx, "GET", ep, "plain")

        # JSON endpoints: wrong method, bad JSON, wrong shapes
        for ep in ["/delete_book.php", "/delete_books.php", "/update_classes.php"]:
            await hit(ctx, "GET", ep, "GET")
            for body in ["", "not json", "[]", "{}", '{"book_id":"x"}', '{"book_ids":"x"}', '{"book_ids":[0,-1,"a"]}',
                         '{"min_grade":"a","max_grade":[],"sections":"A"}', '{"min_grade":1,"max_grade":5,"sections":[["A"]]}']:
                await hit(ctx, "POST", ep, body[:20], data=body, headers={"Content-Type": "application/json"})

        # form endpoints
        await hit(ctx, "GET", "/update_book.php", "GET")
        for form, label in [({}, "empty"), ({"book_id": "abc"}, "bad id"), ({"book_id": "999999", "title": "T", "author": "A"}, "missing book"),
                            ({"book_id": "1", "title": LONG, "author": "A", "quantity": "5"}, "300-char title"),
                            ({"book_id": "1", "title": "T", "author": "A", "quantity": "-3"}, "negative qty"),
                            ({"book_id": "1", "title[]": "T", "author": "A"}, "array title"),
                            ({"book_id": "1", "title": "The Great Gatsby", "author": "F. Scott Fitzgerald", "quantity": "abc"}, "qty letters"),
                            ({"book_id": "1", "title": "The Great Gatsby", "author": "F. Scott Fitzgerald", "quantity": "5", "shelf_row": "TOOLONG"}, "long row")]:
            await hit(ctx, "POST", "/update_book.php", label, multipart=form)
        for form, label in [({"title": LONG, "author": "A", "quantity": "1", "ajax": "1"}, "300-char title"),
                            ({"title": "T", "author": "A", "quantity": "1", "shelf_row": "TOOLONG", "ajax": "1"}, "long row"),
                            ({"title": "T", "author": "A", "quantity": "1", "cover_url": "javascript:alert(1)", "ajax": "1"}, "js url"),
                            ({"title": "T", "author": "A", "quantity": "1", "cover_url": "https://x.kz/" + "a" * 600, "ajax": "1"}, "600-char url"),
                            ({"title[]": "T", "ajax": "1"}, "array")]:
            await hit(ctx, "POST", "/books.php", label, multipart=form)
        await hit(ctx, "POST", "/submit_review.php", "empty", form={})
        for form, label in [({"book_id": "3", "rating": "9"}, "rating 9"), ({"book_id": "999999", "rating": "3"}, "missing book"),
                            ({"book_id": "3", "rating": "3", "comment": "x" * 5000}, "5000-char comment"), ({"book_id[]": "3", "rating": "3"}, "array"),
                            ({"book_id": "3", "rating": "3", "member_id": "999999"}, "missing member")]:
            await hit(ctx, "POST", "/submit_review.php", label, form=form)
        await hit(ctx, "GET", "/submit_review.php", "GET")
        for form, label in [({"action": "borrow"}, "empty borrow"), ({"action": "borrow", "member_id": "999999", "book_id": "7", "borrow_date": "2026-10-21", "due_date": "2026-11-01"}, "missing member"),
                            ({"action": "borrow", "member_id": "28", "book_id": "999999"}, "missing book"),
                            ({"action": "borrow", "member_id": "28", "book_id": "7", "borrow_date": "2026-02-31", "due_date": "2026-13-01"}, "impossible dates"),
                            ({"action": "return", "borrowing_id": "abc"}, "bad return id"), ({"action": "return"}, "no id"), ({"action": "nonsense"}, "unknown action"),
                            ({"action[]": "borrow"}, "array action")]:
            await hit(ctx, "POST", "/borrowings.php", label, form=form)
        for form, label in [({"save_member": "1", "first_name": LONG, "membership_date": "2026-01-01"}, "300-char name"),
                            ({"save_member": "1", "first_name": "A", "class": LONG, "membership_date": "2026-01-01"}, "300-char class"),
                            ({"save_member": "1", "first_name": "A", "membership_date": "2026-02-31"}, "Feb 31"),
                            ({"save_member": "1", "edit_member_id": "999999", "first_name": "Ghost", "membership_date": "2026-01-01"}, "edit missing"),
                            ({"save_member": "1", "first_name[]": "A"}, "array"),
                            ({"add_manual_classes": "1", "manual_classes": "<script>, " + "x" * 50}, "bad classes"),
                            ({"add_manual_classes": "1", "manual_classes": ""}, "empty classes")]:
            await hit(ctx, "POST", "/members.php", label, form=form)
        await hit(ctx, "POST", "/members.php", "import no file", multipart={"import_csv": "1"})
        await hit(ctx, "POST", "/members.php", "import empty file", multipart={"import_csv": "1", "csv_file": {"name": "e.csv", "mimeType": "text/csv", "buffer": b""}})
        await hit(ctx, "POST", "/members.php", "import header only", multipart={"import_csv": "1", "csv_file": {"name": "h.csv", "mimeType": "text/csv", "buffer": b"First Name,Last Name,Class,Group\n"}})
        await hit(ctx, "POST", "/members.php", "import binary", multipart={"import_csv": "1", "csv_file": {"name": "b.csv", "mimeType": "text/csv", "buffer": bytes(range(256)) * 20}})
        await hit(ctx, "POST", "/import_csv.php", "no file", multipart={})
        await hit(ctx, "POST", "/import_csv.php", "txt file", multipart={"csvFile": {"name": "x.txt", "mimeType": "text/plain", "buffer": b"a,b"}})
        await hit(ctx, "POST", "/import_csv.php", "binary", multipart={"csvFile": {"name": "b.csv", "mimeType": "text/csv", "buffer": bytes(range(256)) * 20}})
        await hit(ctx, "POST", "/import_csv.php", "long title", multipart={"csvFile": {"name": "l.csv", "mimeType": "text/csv", "buffer": ("t,a,i,c,q\n" + LONG + ",A,,C,1\n").encode()}})
        await hit(ctx, "POST", "/import_csv.php", "letters qty", multipart={"csvFile": {"name": "q.csv", "mimeType": "text/csv", "buffer": b"t,a,i,c,q,s,r\nBook,A,,C,many,x,TOOLONGROW\n"}})
        for form, label in [({"change_password": "1"}, "empty pw"), ({"update_avatar": "1", "avatar_source": "url", "avatar_url": "https://x.kz/" + "a" * 600}, "600-char url"),
                            ({"update_avatar": "1", "member_id": "999999", "avatar_source": "url", "avatar_url": "https://x.kz/a.png"}, "missing member"),
                            ({"update_avatar": "1"}, "no file")]:
            await hit(ctx, "POST", "/profile.php", label, form=form)
        await ctx.close()
        await br.close()
    for f in fails:
        print("FAIL", f)
        annotate("Bad-input tests", f)
    print(f"Bad-input tests: {passes} passed, {len(fails)} failed")
    sys.exit(1 if fails else 0)


if __name__ == "__main__":
    asyncio.run(main())
