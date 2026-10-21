"""Run all three test files one after another and exit with an error if any fails.

    python tests/run_all.py

Each file puts the sample data back before it starts (see helpers.py).
"""
import subprocess
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
FILES = ["test_functional.py", "test_bad_input.py", "test_click_everything.py"]

failed = []
for name in FILES:
    print(f"\n=== {name} ===", flush=True)
    if subprocess.run([sys.executable, str(HERE / name)], cwd=HERE).returncode != 0:
        failed.append(name)

print("\nAll tests passed." if not failed else f"\nFailed: {', '.join(failed)}")
sys.exit(1 if failed else 0)
