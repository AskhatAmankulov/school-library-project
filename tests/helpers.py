"""Shared settings for the test scripts.

Everything can be changed with environment variables, so the same tests run
against Docker (the defaults), XAMPP, or GitHub Actions:

    BASE_URL  address of the site               default http://localhost:8080
    DB_HOST   database host                     default 127.0.0.1
    DB_PORT   database port                     default 3307 (Docker); XAMPP uses 3306
    DB_USER   database user                     default root
    DB_PASS   database password                 default library (Docker); XAMPP uses an empty password
    DB_NAME   database name                     default library

The tests add, edit and delete data. reset_database() puts the sample data
back before each test file, so only run them against a copy you don't mind
resetting (Docker is ideal).
"""
import os
import re
import tempfile
from pathlib import Path

import pymysql

BASE_URL = os.environ.get("BASE_URL", "http://localhost:8080").rstrip("/")
PROJECT = Path(__file__).resolve().parent.parent
TMP = Path(tempfile.mkdtemp(prefix="library-tests-"))

DB = dict(
    host=os.environ.get("DB_HOST", "127.0.0.1"),
    port=int(os.environ.get("DB_PORT", "3307")),
    user=os.environ.get("DB_USER", "root"),
    password=os.environ.get("DB_PASS", "library"),
    database=os.environ.get("DB_NAME", "library"),
    charset="utf8mb4",
    autocommit=True,
)


def q(sql):
    """Run one query and return the result as text, like the mysql command line (-N)."""
    conn = pymysql.connect(**DB)
    try:
        with conn.cursor() as cur:
            cur.execute(sql)
            rows = cur.fetchall() if cur.description else []
    finally:
        conn.close()
    return "\n".join("\t".join("NULL" if v is None else str(v) for v in row) for row in rows)


def qi(sql):
    v = q(sql).strip()
    return int(v) if re.fullmatch(r"-?\d+", v) else v


def _run_sql_file(cur, path):
    # The files are plain SQL dumps: run them statement by statement.
    text = Path(path).read_text(encoding="utf-8")
    text = re.sub(r"(?m)^--.*$", "", text)
    for stmt in re.split(r";\s*\n", text):
        if stmt.strip():
            cur.execute(stmt)


def reset_database():
    """Recreate the database from library.sql + add_users_table.sql + fix_data.sql."""
    settings = {k: v for k, v in DB.items() if k != "database"}
    conn = pymysql.connect(**settings)
    try:
        with conn.cursor() as cur:
            cur.execute(f"DROP DATABASE IF EXISTS `{DB['database']}`")
            cur.execute(f"CREATE DATABASE `{DB['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci")
            cur.execute(f"USE `{DB['database']}`")
            for name in ("library.sql", "add_users_table.sql", "fix_data.sql"):
                _run_sql_file(cur, PROJECT / name)
    finally:
        conn.close()


class Results:
    """Collects PASS/FAIL lines and prints a summary."""

    def __init__(self, title):
        self.title = title
        self.rows = []
        self.js_errors = []

    def check(self, area, name, ok, detail=""):
        self.rows.append((area, name, bool(ok), str(detail)[:250]))

    @property
    def failed(self):
        return [r for r in self.rows if not r[2]]

    def report(self, verbose=False):
        for area, name, ok, detail in self.rows:
            if verbose or not ok:
                print(("PASS " if ok else "FAIL ") + f"[{area}] {name}" + (f"  -> {detail}" if not ok and detail else ""))
        ok = len(self.rows) - len(self.failed)
        extra = f"; JS errors: {self.js_errors}" if self.js_errors else ""
        print(f"{self.title}: {ok} passed, {len(self.failed)} failed{extra}")
        return not self.failed and not self.js_errors
