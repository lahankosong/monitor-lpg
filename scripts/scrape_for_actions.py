"""
scrape_for_actions.py — Script untuk GitHub Actions
Batch login ke MyPertamina dan kirim token ke Laravel API
"""

import asyncio
import aiohttp
import json
import sys
import os
from pathlib import Path
from datetime import datetime
from playwright.async_api import async_playwright

# Flush output untuk GitHub Actions logs
sys.stdout.reconfigure(line_buffering=True)
sys.stderr.reconfigure(line_buffering=True)

def log(msg: str):
    timestamp = datetime.now().strftime("%H:%M:%S")
    print(f"[{timestamp}] {msg}", flush=True)

async def login_one(email: str, pin: str, label: str = "") -> dict:
    """Login ke satu akun MyPertamina dan capture token"""
    result = {
        "success": False,
        "email": email,
        "label": label,
        "token": None,
        "pangkalan_id": None,
        "store_name": None,
        "error": None,
    }

    async with async_playwright() as p:
        browser = await p.chromium.launch(
            headless=True,
            args=[
                "--no-sandbox",
                "--disable-dev-shm-usage",
                "--disable-blink-features=AutomationControlled",
                "--disable-infobars",
                "--disable-extensions",
            ]
        )

        context = await browser.new_context(
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36",
            viewport={"width": 1280, "height": 800},
            locale="id-ID",
            timezone_id="Asia/Jakarta",
        )

        # Hide webdriver flag
        await context.add_init_script("""
            Object.defineProperty(navigator, 'webdriver', { get: () => undefined });
            Object.defineProperty(navigator, 'plugins', { get: () => [1,2,3,4,5] });
            window.chrome = { runtime: {} };
        """)

        page = await context.new_page()
        token_holder = {"token": None}

        def handle_request(request):
            auth = request.headers.get("authorization", "")
            if auth.startswith("Bearer ") and not token_holder["token"]:
                token_holder["token"] = auth.replace("Bearer ", "").strip()

        page.on("request", handle_request)

        try:
            log(f"  [{label}] Membuka halaman login...")
            await page.goto(
                "https://subsiditepatlpg.mypertamina.id/merchant-login",
                wait_until="domcontentloaded",
                timeout=60000
            )
            await asyncio.sleep(3)

            # Input email
            log(f"  [{label}] Input email...")
            email_input = page.locator('input[type="text"], input[name="email"], input[placeholder*="email" i]').first
            await email_input.fill(email)
            await asyncio.sleep(0.5)

            # Input PIN
            log(f"  [{label}] Input PIN...")
            pin_input = page.locator('input[type="password"], input[name="pin"], input[placeholder*="pin" i]').first
            await pin_input.fill(pin)
            await asyncio.sleep(0.5)

            # Klik tombol login
            log(f"  [{label}] Klik login...")
            login_btn = page.locator('button[type="submit"], button:has-text("Masuk"), button:has-text("Login")').first
            await login_btn.click()

            # Tunggu navigasi atau token
            await asyncio.sleep(5)

            # Coba ambil nama toko
            store_name = None
            try:
                store_el = page.locator('[class*="store"], [class*="merchant"], h1, h2').first
                store_name = await store_el.inner_text(timeout=3000)
                store_name = store_name.strip()[:100] if store_name else None
            except:
                pass

            # Cek apakah berhasil dapat token
            if token_holder["token"]:
                log(f"  [{label}] ✓ Token berhasil didapat")

                # Decode token untuk dapat pangkalan_id
                try:
                    import base64
                    parts = token_holder["token"].split('.')
                    payload_b64 = parts[1]
                    # Add padding
                    payload_b64 += '=' * (4 - len(payload_b64) % 4)
                    payload = json.loads(base64.urlsafe_b64decode(payload_b64))
                    result["pangkalan_id"] = payload.get("sub")
                except Exception as e:
                    log(f"  [{label}] Warning: gagal decode token - {e}")

                result["success"] = True
                result["token"] = token_holder["token"]
                result["store_name"] = store_name
            else:
                result["error"] = "Token tidak ditemukan setelah login"
                log(f"  [{label}] ✗ Gagal: token tidak ditemukan")

        except Exception as e:
            result["error"] = str(e)
            log(f"  [{label}] ✗ Error: {e}")

        finally:
            await browser.close()

    return result


async def send_to_laravel(tokens: list, api_url: str, api_key: str, date_from: str, date_to: str):
    """Kirim batch token ke Laravel API"""
    log(f"Mengirim {len(tokens)} token ke Laravel...")

    payload = {
        "tokens": tokens,
        "scrape_after": True,
        "date_from": date_from,
        "date_to": date_to,
    }

    headers = {
        "Content-Type": "application/json",
        "X-API-Key": api_key,
    }

    async with aiohttp.ClientSession() as session:
        try:
            async with session.post(
                f"{api_url}/api/github-actions/tokens",
                json=payload,
                headers=headers,
                timeout=aiohttp.ClientTimeout(total=300)
            ) as resp:
                result = await resp.json()
                if resp.status == 200:
                    log(f"✓ Laravel response: {result.get('message', 'OK')}")
                    return result
                else:
                    log(f"✗ Laravel error ({resp.status}): {result}")
                    return None
        except Exception as e:
            log(f"✗ Error mengirim ke Laravel: {e}")
            return None


async def main():
    log("=" * 50)
    log("MyPertamina Batch Scraper untuk GitHub Actions")
    log("=" * 50)

    # Baca konfigurasi dari environment
    api_url = os.environ.get("LARAVEL_API_URL", "").rstrip("/")
    api_key = os.environ.get("LARAVEL_API_KEY", "")
    date_from = os.environ.get("DATE_FROM", "") or datetime.now().strftime("%Y-%m-%d")
    date_to = os.environ.get("DATE_TO", "") or datetime.now().strftime("%Y-%m-%d")

    if not api_url or not api_key:
        log("ERROR: LARAVEL_API_URL dan LARAVEL_API_KEY harus diset!")
        sys.exit(1)

    log(f"API URL: {api_url}")
    log(f"Date range: {date_from} - {date_to}")

    # Baca accounts dari file (dari GitHub Secret)
    accounts_file = Path(__file__).parent / "accounts_secret.json"
    if not accounts_file.exists():
        # Fallback ke accounts.json untuk testing lokal
        accounts_file = Path(__file__).parent / "accounts.json"

    if not accounts_file.exists():
        log("ERROR: File accounts tidak ditemukan!")
        sys.exit(1)

    with open(accounts_file, "r") as f:
        accounts = json.load(f)

    if not accounts:
        log("ERROR: Tidak ada akun dalam file!")
        sys.exit(1)

    log(f"Memproses {len(accounts)} akun...")
    log("-" * 50)

    # Login ke semua akun
    successful_tokens = []
    failed_count = 0

    for i, acc in enumerate(accounts, 1):
        email = acc.get("email", "")
        pin = acc.get("pin", "")
        label = acc.get("label", "") or acc.get("name", "") or email[:20]

        log(f"[{i}/{len(accounts)}] {label}")

        if not email or not pin:
            log(f"  Skipped: email atau pin kosong")
            failed_count += 1
            continue

        result = await login_one(email, pin, label)

        if result["success"]:
            successful_tokens.append({
                "email": email,
                "token": result["token"],
                "pangkalan_id": result["pangkalan_id"],
                "store_name": result["store_name"],
            })
        else:
            failed_count += 1

        # Delay antar akun untuk hindari rate limit
        if i < len(accounts):
            await asyncio.sleep(2)

    log("-" * 50)
    log(f"Login selesai: {len(successful_tokens)} berhasil, {failed_count} gagal")

    # Kirim ke Laravel jika ada token
    if successful_tokens:
        await send_to_laravel(successful_tokens, api_url, api_key, date_from, date_to)
    else:
        log("Tidak ada token untuk dikirim ke Laravel")

    log("=" * 50)
    log("Selesai!")


if __name__ == "__main__":
    asyncio.run(main())
