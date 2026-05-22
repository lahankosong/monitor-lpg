"""
scrape_for_actions.py — Script untuk GitHub Actions dengan improved error handling
Batch login ke MyPertamina dan kirim token ke Laravel API
"""

import asyncio
import aiohttp
import json
import sys
import os
import base64
import re
from pathlib import Path
from datetime import datetime
from playwright.async_api import async_playwright, TimeoutError as PlaywrightTimeoutError

# Flush output untuk GitHub Actions logs
sys.stdout.reconfigure(line_buffering=True)
sys.stderr.reconfigure(line_buffering=True)

def log(msg: str):
    timestamp = datetime.now().strftime("%H:%M:%S")
    print(f"[{timestamp}] {msg}", flush=True)

async def login_one(email: str, pin: str, label: str = "", retry: int = 0) -> dict:
    """Login ke satu akun MyPertamina dan capture token dengan retry"""
    result = {
        "success": False,
        "email": email,
        "label": label,
        "token": None,
        "pangkalan_id": None,
        "store_name": None,
        "error": None,
    }
    
    max_retries = 2
    
    async with async_playwright() as p:
        browser = None
        try:
            browser = await p.chromium.launch(
                headless=True,
                args=[
                    "--no-sandbox",
                    "--disable-dev-shm-usage",
                    "--disable-blink-features=AutomationControlled",
                    "--disable-infobars",
                    "--disable-extensions",
                    "--disable-web-security",
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
                delete window.__playwright;
            """)

            page = await context.new_page()
            token_holder = {"token": None}
            api_called = False

            def handle_request(request):
                nonlocal api_called
                auth = request.headers.get("authorization", "")
                if auth.startswith("Bearer ") and not token_holder["token"]:
                    token_holder["token"] = auth.replace("Bearer ", "").strip()
                    api_called = True
                    log(f"  [{label}] Token captured")

            page.on("request", handle_request)

            log(f"  [{label}] Opening login page...")
            await page.goto(
                "https://subsiditepatlpg.mypertamina.id/merchant-login",
                wait_until="domcontentloaded",
                timeout=60000
            )
            # ── Debug: URL, title, dump semua input ────────────────
            try:
                title = await page.title()
                log(f"  [{label}] URL  : {page.url}")
                log(f"  [{label}] Title: {title}")
            except:
                pass

            # Tunggu halaman siap — cukup 5s, tidak perlu networkidle penuh
            try:
                await page.wait_for_load_state("networkidle", timeout=5000)
            except:
                pass  # lanjut meski belum idle
            await asyncio.sleep(0.5)

            # Dump inputs — hanya jika DEBUG_LOGIN=1
            if os.environ.get("DEBUG_LOGIN"):
                try:
                    all_inputs = await page.evaluate("""() => {
                        return Array.from(document.querySelectorAll('input')).map(el =>
                            ({type:el.type, name:el.name, ph:el.placeholder.substring(0,30)}));
                    }""")
                    log(f"  [{label}] Inputs({len(all_inputs)}): " +
                        ", ".join(f"{i['type']}/{i['ph']}" for i in all_inputs[:4]))
                except Exception as e:
                    log(f"  [{label}] Dump error: {e}")

            # ── Fill email via JS (React-safe, bypass synthetic events) ──
            log(f"  [{label}] Input email...")
            try:
                filled = await page.evaluate("""(val) => {
                    const sels = [
                        'input[type="email"]', 'input[name="email"]',
                        'input[name="username"]', 'input[placeholder*="email" i]',
                        'input[placeholder*="nomor" i]', 'input[placeholder*="hp" i]',
                        'input[type="text"]', 'input[type="tel"]',
                        'input:not([type="password"]):not([type="hidden"])',
                    ];
                    for (const sel of sels) {
                        const el = document.querySelector(sel);
                        if (el && el.offsetParent !== null) {
                            const setter = Object.getOwnPropertyDescriptor(
                                window.HTMLInputElement.prototype, 'value').set;
                            setter.call(el, val);
                            el.dispatchEvent(new Event('input',  {bubbles:true}));
                            el.dispatchEvent(new Event('change', {bubbles:true}));
                            el.focus();
                            return sel;
                        }
                    }
                    return null;
                }""", email)
                if filled:
                    log(f"  [{label}] Email diisi via JS selector: {filled}")
                else:
                    log(f"  [{label}] JS fill gagal, fallback locator...")
                    inp = page.locator("input").first
                    await inp.click()
                    await inp.fill(email)
            except Exception as e:
                log(f"  [{label}] Email error: {e}")
                raise Exception(f"Email input failed: {e}")

            await asyncio.sleep(0.2)

            # ── Fill PIN via JS ───────────────────────────────────────
            log(f"  [{label}] Input PIN...")
            pin_input = None
            try:
                pin_filled = await page.evaluate("""(val) => {
                    const sels = [
                        'input[type="password"]', 'input[name="pin"]',
                        'input[name="password"]', 'input[placeholder*="pin" i]',
                        'input[placeholder*="password" i]', 'input[placeholder*="sandi" i]',
                    ];
                    for (const sel of sels) {
                        const el = document.querySelector(sel);
                        if (el) {
                            const setter = Object.getOwnPropertyDescriptor(
                                window.HTMLInputElement.prototype, 'value').set;
                            setter.call(el, val);
                            el.dispatchEvent(new Event('input',  {bubbles:true}));
                            el.dispatchEvent(new Event('change', {bubbles:true}));
                            el.focus();
                            return sel;
                        }
                    }
                    return null;
                }""", pin)
                if pin_filled:
                    log(f"  [{label}] PIN diisi via JS selector: {pin_filled}")
                    pin_input = page.locator('input[type="password"]').first
                else:
                    log(f"  [{label}] PIN field tidak ditemukan via JS")
                    inputs = await page.locator("input").all()
                    if len(inputs) >= 2:
                        pin_input = inputs[1]
                        await pin_input.fill(pin)
            except Exception as e:
                log(f"  [{label}] PIN error: {e}")

            await asyncio.sleep(0.2)

            # ── Klik tombol login via JS ──────────────────────────────
            log(f"  [{label}] Click login...")
            try:
                btn = await page.evaluate("""() => {
                    const keywords = ['Masuk','MASUK','Login','LOGIN','Sign In','Submit','Lanjut'];
                    for (const b of document.querySelectorAll('button')) {
                        const t = b.textContent.trim();
                        if (keywords.some(k => t.includes(k)) && b.offsetParent !== null) {
                            b.click(); return t;
                        }
                    }
                    const sub = document.querySelector('button[type="submit"]');
                    if (sub && sub.offsetParent !== null) { sub.click(); return 'submit'; }
                    // tombol terakhir yang visible
                    const all = Array.from(document.querySelectorAll('button'))
                        .filter(b => b.offsetParent !== null);
                    if (all.length) { all[all.length-1].click(); return all[all.length-1].textContent.trim(); }
                    return null;
                }""")
                if btn:
                    log(f"  [{label}] Tombol diklik: '{btn}'")
                else:
                    log(f"  [{label}] Tombol tidak ada, tekan Enter...")
                    await page.keyboard.press("Enter")
            except Exception as e:
                log(f"  [{label}] Click error: {e}, tekan Enter...")
                await page.keyboard.press("Enter")

            # Tunggu token atau navigasi
            log(f"  [{label}] Waiting for response...")
            try:
                await page.wait_for_function(
                    """() => {
                        const resources = performance.getEntriesByType('resource');
                        return resources.some(r => 
                            r.name.includes('/products/v1/products/user') && 
                            r.responseStatus === 200
                        );
                    }""",
                    timeout=30000
                )
                await asyncio.sleep(1)
            except PlaywrightTimeoutError:
                # Cek apakah sudah redirect (login sukses)
                current_url = page.url
                if "dashboard" in current_url or "merchant" in current_url:
                    log(f"  [{label}] Redirected to {current_url}")
                    # Coba ambil token dari localStorage
                    try:
                        token_from_storage = await page.evaluate("localStorage.getItem('token')")
                        if token_from_storage:
                            token_holder["token"] = token_from_storage
                            log(f"  [{label}] Token from localStorage")
                    except:
                        pass
                else:
                    # Screenshot on timeout
                    screenshot_path = f"/tmp/timeout_{email.replace('@', '_')}.png"
                    await page.screenshot(path=screenshot_path)
                    log(f"  [{label}] Screenshot: {screenshot_path}")

            # Ambil nama toko
            store_name = None
            try:
                store_selectors = ['[class*="store"]', '[class*="merchant"]', 'h1', 'h2', '.store-name']
                for selector in store_selectors:
                    if await page.locator(selector).count() > 0:
                        store_name = await page.locator(selector).first.inner_text(timeout=3000)
                        if store_name and store_name.strip():
                            store_name = store_name.strip()[:100]
                            break
            except:
                pass

            # Cek apakah berhasil dapat token
            if token_holder["token"]:
                log(f"  [{label}] ✓ Token berhasil didapat")

                # Decode token untuk dapat pangkalan_id dan cek expired
                try:
                    parts = token_holder["token"].split('.')
                    payload_b64 = parts[1] + "=" * (4 - len(parts[1]) % 4)
                    payload = json.loads(base64.urlsafe_b64decode(payload_b64))
                    result["pangkalan_id"] = payload.get("sub")
                    
                    # Cek expired
                    exp = payload.get("exp")
                    if exp:
                        exp_date = datetime.fromtimestamp(exp)
                        if exp_date < datetime.now():
                            log(f"  [{label}] ⚠ Token expired at {exp_date}")
                            result["error"] = "Token already expired"
                            return result
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
            
            # Screenshot on error
            if browser and 'page' in locals():
                try:
                    screenshot_path = f"/tmp/error_{email.replace('@', '_')}.png"
                    await page.screenshot(path=screenshot_path)
                    log(f"  [{label}] Screenshot: {screenshot_path}")
                except:
                    pass
            
            # Retry untuk error tertentu
            retryable_errors = ['timeout', 'network', 'connection', 'ECONNREFUSED', 'ERR_CONNECTION']
            if retry < max_retries and any(err in str(e).lower() for err in retryable_errors):
                log(f"  [{label}] Retrying... ({retry + 1}/{max_retries})")
                await asyncio.sleep(5)
                return await login_one(email, pin, label, retry + 1)

        finally:
            if browser:
                await browser.close()

    return result


async def send_to_laravel(tokens: list, api_url: str, api_key: str, date_from: str, date_to: str):
    """Kirim batch token ke Laravel API dengan retry"""
    log(f"Mengirim {len(tokens)} token ke Laravel...")

    payload = {
        "tokens": tokens,
        "scrape_after": True,
        "date_from": date_from,
        "date_to": date_to,
        "source": "github_actions",
        "timestamp": datetime.now().isoformat()
    }

    headers = {
        "Content-Type": "application/json",
        "X-API-Key": api_key,
        "User-Agent": "MyPertamina-Scraper/1.0",
    }

    max_retries = 3
    
    for attempt in range(max_retries):
        async with aiohttp.ClientSession() as session:
            try:
                # Healthcheck dulu
                try:
                    async with session.get(
                        f"{api_url}/api/health",
                        headers={"X-API-Key": api_key},
                        timeout=aiohttp.ClientTimeout(total=5)
                    ) as health_resp:
                        if health_resp.status != 200:
                            log(f"  API health check failed: {health_resp.status}")
                except:
                    log(f"  API health check timeout/skip")
                
                async with session.post(
                    f"{api_url}/api/github-actions/tokens",
                    json=payload,
                    headers=headers,
                    timeout=aiohttp.ClientTimeout(total=300)
                ) as resp:
                    result = await resp.json()
                    if resp.status == 200:
                        log(f"✓ Laravel response: {result.get('message', 'OK')}")
                        if result.get('tokens_saved') is not None:
                            log(f"  Tokens saved: {result['tokens_saved']}")
                        return result
                    else:
                        log(f"✗ Laravel error ({resp.status}): {result}")
                        if attempt < max_retries - 1:
                            log(f"  Retrying... ({attempt + 1}/{max_retries})")
                            await asyncio.sleep(5)
                        else:
                            return None
            except asyncio.TimeoutError:
                log(f"✗ Timeout sending to Laravel, attempt {attempt + 1}")
                if attempt < max_retries - 1:
                    await asyncio.sleep(5)
            except Exception as e:
                log(f"✗ Error mengirim ke Laravel: {e}")
                if attempt < max_retries - 1:
                    await asyncio.sleep(5)
    
    return None


async def main():
    log("=" * 60)
    log("MyPertamina Batch Scraper untuk GitHub Actions")
    log("=" * 60)

    # Baca konfigurasi dari environment
    api_url   = os.environ.get("LARAVEL_API_URL", "").rstrip("/")
    api_key   = os.environ.get("LARAVEL_API_KEY", "")
    date_from = os.environ.get("DATE_FROM", "")
    date_to   = os.environ.get("DATE_TO", "")
    
    # Default date: kemarin sampai hari ini
    if not date_from:
        date_from = (datetime.now() - timedelta(days=1)).strftime("%Y-%m-%d")
    if not date_to:
        date_to = datetime.now().strftime("%Y-%m-%d")

    if not api_url or not api_key:
        log("ERROR: LARAVEL_API_URL dan LARAVEL_API_KEY harus diset!")
        sys.exit(1)

    log(f"API URL: {api_url}")
    log(f"Date range: {date_from} - {date_to}")

    # ── Ambil akun dari Laravel API (single source of truth) ──────
    accounts = None

    log("Fetching accounts from Laravel API...")
    for attempt in range(3):
        try:
            import urllib.request
            req = urllib.request.Request(
                f"{api_url}/api/github-actions/accounts",
                headers={"X-API-Key": api_key, "Accept": "application/json"},
            )
            with urllib.request.urlopen(req, timeout=30) as resp:
                body = json.loads(resp.read().decode())
                if body.get("success"):
                    accounts = body["accounts"]
                    log(f"  ✓ {len(accounts)} akun diterima dari database")
                    break
                else:
                    log(f"  API error: {body.get('message')}")
        except Exception as e:
            log(f"  WARN: Gagal fetch dari API ({e}), attempt {attempt + 1}")
            if attempt < 2:
                await asyncio.sleep(3)

    # ── Fallback: env vars (untuk backward compat / testing) ──────
    if not accounts:
        accounts_base64   = os.environ.get("ACCOUNTS_BASE64", "")
        accounts_json_env = os.environ.get("ACCOUNTS_JSON", "")

        if accounts_base64:
            log("Fallback: loading accounts from ACCOUNTS_BASE64...")
            try:
                accounts = json.loads(base64.b64decode(accounts_base64).decode("utf-8"))
                log(f"  {len(accounts)} akun dari ACCOUNTS_BASE64")
            except Exception as e:
                log(f"ERROR: Gagal decode ACCOUNTS_BASE64: {e}")

        elif accounts_json_env:
            log("Fallback: loading accounts from ACCOUNTS_JSON...")
            cleaned = re.sub(r"[\x00-\x1f\x7f]", "", accounts_json_env)
            try:
                accounts = json.loads(cleaned)
                log(f"  {len(accounts)} akun dari ACCOUNTS_JSON")
            except json.JSONDecodeError as e:
                log(f"ERROR: Gagal parse ACCOUNTS_JSON: {e}")

        else:
            # Fallback terakhir: file lokal untuk testing
            accounts_file = Path(__file__).parent / "accounts.json"
            if accounts_file.exists():
                log(f"Fallback: loading accounts from file {accounts_file}")
                with open(accounts_file, "r", encoding="utf-8") as f:
                    accounts = json.load(f)
                log(f"  {len(accounts)} akun dari file")

    if not accounts:
        log("ERROR: Tidak bisa fetch accounts dari API maupun env/file!")
        sys.exit(1)

    log(f"Memproses {len(accounts)} akun...")
    log("-" * 60)

    # Login ke semua akun dengan delay
    successful_tokens = []
    failed_accounts = []

    for i, acc in enumerate(accounts, 1):
        email = acc.get("email", "")
        pin = acc.get("pin", "")
        label = acc.get("label", "") or acc.get("name", "") or email[:20]

        log(f"[{i}/{len(accounts)}] {label}")

        if not email or not pin:
            log(f"  Skipped: email atau pin kosong")
            failed_accounts.append({"email": email, "error": "Missing credentials"})
            continue

        result = await login_one(email, pin, label)

        if result["success"]:
            successful_tokens.append({
                "email": email,
                "token": result["token"],
                "pangkalan_id": result["pangkalan_id"],
                "store_name": result["store_name"],
            })
            log(f"  ✓ Success")
        else:
            failed_accounts.append({"email": email, "error": result.get("error", "Unknown")})
            log(f"  ✗ Failed: {result.get('error', 'Unknown')[:100]}")

        # Delay antar akun
        delay = 3 if i < len(accounts) else 0
        if delay:
            await asyncio.sleep(delay)

    log("-" * 60)
    log(f"Login selesai: {len(successful_tokens)} berhasil, {len(failed_accounts)} gagal")
    
    if failed_accounts:
        log("Failed accounts:")
        for fail in failed_accounts[:5]:  # Show first 5
            log(f"  - {fail['email']}: {fail['error'][:50]}")

    # Kirim ke Laravel jika ada token
    if successful_tokens:
        result = await send_to_laravel(successful_tokens, api_url, api_key, date_from, date_to)
        if result:
            log("✓ Data berhasil dikirim ke Laravel")
        else:
            log("⚠ Data gagal dikirim ke Laravel, token disimpan di log")
            # Save to file as backup
            backup_file = f"/tmp/tokens_backup_{datetime.now().strftime('%Y%m%d_%H%M%S')}.json"
            Path(backup_file).write_text(json.dumps(successful_tokens, indent=2))
            log(f"  Backup saved: {backup_file}")
    else:
        log("Tidak ada token untuk dikirim ke Laravel")
        sys.exit(1)

    log("=" * 60)
    log("Selesai!")


if __name__ == "__main__":
    from datetime import timedelta
    asyncio.run(main())