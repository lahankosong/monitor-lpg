"""
auto_login_batch.py — fix anti-bot detection headless + retry mechanism
"""

import asyncio
import aiohttp
import argparse
import json
import sys
import base64
import os
from pathlib import Path
from datetime import datetime, timedelta
from playwright.async_api import async_playwright, TimeoutError as PlaywrightTimeoutError

sys.stdout.reconfigure(line_buffering=True)
sys.stderr.reconfigure(line_buffering=True)

def log(msg: str):
    timestamp = datetime.now().strftime("%H:%M:%S.%f")[:-3]
    print(f"[{timestamp}] {msg}", file=sys.stderr, flush=True)

def _jsonl_log(text: str, tipe: str = "info"):
    """Tulis ke JSONL log file agar PHP bisa baca realtime via popup progress."""
    logfile = os.environ.get("JSONL_LOGFILE", "")
    if not logfile:
        return
    try:
        entry = json.dumps({
            "time": datetime.now().strftime("%H:%M:%S"),
            "text": text,
            "type": tipe,
        }, ensure_ascii=False) + "\n"
        with open(logfile, "a", encoding="utf-8") as f:
            f.write(entry)
    except Exception:
        pass

async def login_one(email: str, pin: str, label: str = "", retry: int = 0) -> dict:
    result = {
        "success": False, "label": label, "email": email,
        "token": None, "pangkalan_id": None, "registration_id": None,
        "store_name": None, "stock_available": None,
        "stock_redeem": None, "sold": None, "error": None,
    }
    
    max_retries = 3
    
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
                    "--start-maximized",
                    "--window-size=1920,1080",
                    "--disable-web-security",
                    "--disable-features=IsolateOrigins,site-per-process",
                ]
            )

            # Context dengan properties yang menyerupai browser normal
            context = await browser.new_context(
                user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36",
                viewport={"width": 1280, "height": 800},
                locale="id-ID",
                timezone_id="Asia/Jakarta",
                extra_http_headers={
                    "Accept-Language": "id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7",
                }
            )

            # Inject script untuk sembunyikan webdriver flag
            await context.add_init_script("""
                Object.defineProperty(navigator, 'webdriver', { get: () => undefined });
                Object.defineProperty(navigator, 'plugins', { get: () => [1,2,3,4,5] });
                Object.defineProperty(navigator, 'languages', { get: () => ['id-ID', 'id', 'en-US', 'en'] });
                window.chrome = { runtime: {} };
                
                // Hapus trace Playwright
                delete window.__playwright;
                delete window.__pwInitScripts;
            """)

            page = await context.new_page()
            token_holder = {"token": None}
            screenshot_counter = 0

            def handle_request(request):
                auth = request.headers.get("authorization", "")
                if auth.startswith("Bearer ") and not token_holder["token"]:
                    token_holder["token"] = auth.replace("Bearer ", "").strip()
                    log(f"  [{label}] Token captured from {request.url}")

            page.on("request", handle_request)

            # Navigate dengan retry
            for attempt in range(max_retries):
                try:
                    await page.goto(
                        "https://subsiditepatlpg.mypertamina.id/merchant-login",
                        wait_until="domcontentloaded",
                        timeout=60000
                    )
                    break
                except Exception as e:
                    if attempt == max_retries - 1:
                        raise
                    log(f"  [{label}] Retry navigation ({attempt + 1}): {str(e)[:50]}")
                    await asyncio.sleep(2)
            
            # Log URL dan title
            log(f"  [{label}] URL  : {page.url}")
            try:
                log(f"  [{label}] Title: {await page.title()}")
            except:
                pass

            # Tunggu networkidle max 5s
            try:
                await page.wait_for_load_state("networkidle", timeout=5000)
            except:
                pass
            await asyncio.sleep(0.5)

            # Tunggu input visible (max 20s, cek tiap 1s)
            input_ready = False
            for _w in range(20):
                try:
                    count = await page.evaluate("""() =>
                        Array.from(document.querySelectorAll('input'))
                            .filter(el => el.offsetParent !== null).length
                    """)
                    if count > 0:
                        input_ready = True
                        break
                except:
                    pass
                await asyncio.sleep(1)

            if not input_ready:
                screenshot_path = f"/tmp/login_error_{email.replace('@', '_')}.png"
                await page.screenshot(path=screenshot_path)
                log(f"  [{label}] Screenshot: {screenshot_path}")
                raise Exception("Login form not found after 20s")

            # Gerak mouse natural
            await page.mouse.move(640, 400)
            await asyncio.sleep(0.3)

            # Isi email via JS (React-safe)
            log(f"  [{label}] Input email...")
            filled = await page.evaluate("""(val) => {
                const sels = [
                    'input[type="email"]','input[name="email"]',
                    'input[placeholder*="Ponsel"]','input[placeholder*="Email"]',
                    'input[placeholder*="email"]','input[type="text"]','input[type="tel"]',
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
            if not filled:
                raise Exception("Email input not found")
            await asyncio.sleep(0.3)

            # Isi PIN via JS
            log(f"  [{label}] Input PIN...")
            await page.evaluate("""(val) => {
                const el = document.querySelector('input[type="password"]');
                if (el) {
                    const setter = Object.getOwnPropertyDescriptor(
                        window.HTMLInputElement.prototype, 'value').set;
                    setter.call(el, val);
                    el.dispatchEvent(new Event('input',  {bubbles:true}));
                    el.dispatchEvent(new Event('change', {bubbles:true}));
                    el.focus();
                }
            }""", pin)
            await asyncio.sleep(0.3)

            # Klik tombol login via JS
            log(f"  [{label}] Click login...")
            btn = await page.evaluate("""() => {
                const kw = ['MASUK','Masuk','Login','LOGIN','Lanjut'];
                for (const b of document.querySelectorAll('button')) {
                    if (kw.some(k => b.textContent.includes(k)) && b.offsetParent !== null) {
                        b.click(); return b.textContent.trim();
                    }
                }
                const sub = document.querySelector('button[type="submit"]');
                if (sub && sub.offsetParent !== null) { sub.click(); return 'submit'; }
                const all = Array.from(document.querySelectorAll('button'))
                    .filter(b => b.offsetParent !== null);
                if (all.length) { all[all.length-1].click(); return all[all.length-1].textContent.trim(); }
                return null;
            }""")
            if btn:
                log(f"  [{label}] Tombol: '{btn}'")
            else:
                await page.keyboard.press("Enter")
                log(f"  [{label}] Fallback: Enter")

            # Screenshot sebelum wait token
            if os.environ.get("DEBUG_SCREENSHOTS"):
                await page.screenshot(path=f"/tmp/before_wait_{email.replace('@', '_')}.png")

            # Tunggu token dengan wait_for_function
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
                # Beri waktu sedikit untuk request handler
                await asyncio.sleep(1)
            except PlaywrightTimeoutError:
                log(f"  [{label}] Timeout waiting for API call")
                # Cek apakah sudah redirect (login sukses tanpa token capture?)
                current_url = page.url
                if "dashboard" in current_url or "merchant" in current_url:
                    log(f"  [{label}] Login likely successful (redirected to {current_url})")
                    # Coba ambil token dari localStorage
                    try:
                        token_from_storage = await page.evaluate("localStorage.getItem('token')")
                        if token_from_storage:
                            token_holder["token"] = token_from_storage
                            log(f"  [{label}] Token from localStorage")
                    except:
                        pass

            if not token_holder["token"]:
                # Screenshot on failure
                screenshot_path = f"/tmp/login_failed_{email.replace('@', '_')}.png"
                await page.screenshot(path=screenshot_path)
                log(f"  [{label}] Screenshot saved: {screenshot_path}")
                
                result["error"] = "Token tidak tertangkap — cek kredensial atau reCAPTCHA"
                return result

            result["token"] = token_holder["token"]

            # Decode token
            try:
                parts = result["token"].split(".")
                payload_b64 = parts[1] + "=" * (4 - len(parts[1]) % 4)
                payload = json.loads(base64.b64decode(payload_b64))
                result["pangkalan_id"] = payload.get("sub")
                
                # Cek expired
                exp = payload.get("exp")
                if exp:
                    exp_date = datetime.fromtimestamp(exp)
                    if exp_date < datetime.now():
                        log(f"  [{label}] Warning: Token already expired at {exp_date}")
                        result["error"] = "Token expired"
                        return result
            except Exception as e:
                log(f"  [{label}] Token decode warning: {str(e)[:50]}")

        except Exception as e:
            result["error"] = f"Browser error: {str(e)}"
            log(f"  [{label}] Error: {str(e)[:100]}")
            
            # Screenshot on error
            if browser and page:
                try:
                    screenshot_path = f"/tmp/error_{email.replace('@', '_')}.png"
                    await page.screenshot(path=screenshot_path)
                    log(f"  [{label}] Screenshot saved: {screenshot_path}")
                except:
                    pass
            
            return result
        finally:
            if browser:
                await browser.close()

    # Ambil info stok dengan retry
    if result["token"]:
        for attempt in range(max_retries):
            try:
                async with aiohttp.ClientSession() as session:
                    async with session.get(
                        "https://api-map.my-pertamina.id/general/products/v1/products/user",
                        headers={
                            "Authorization": f"Bearer {result['token']}",
                            "Accept":        "application/json",
                            "User-Agent":    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36",
                            "Origin":        "https://subsiditepatlpg.mypertamina.id",
                        },
                        timeout=aiohttp.ClientTimeout(total=15)
                    ) as res:
                        if res.status == 200:
                            data = await res.json()
                            if data.get("success") and data.get("data"):
                                d = data["data"]
                                result.update({
                                    "registration_id": d.get("registrationId"),
                                    "store_name":      d.get("storeName"),
                                    "stock_available": d.get("stockAvailable"),
                                    "stock_redeem":    d.get("stockRedeem"),
                                    "sold":            d.get("sold"),
                                    "stock_date":      d.get("stockDate"),
                                    "last_stock":      d.get("lastStock"),
                                    "last_stock_date": d.get("lastStockDate"),
                                    "success":         True,
                                })
                                if not result["label"]:
                                    result["label"] = d.get("storeName", email)
                                break
                        else:
                            log(f"  [{label}] Stock API status {res.status}, attempt {attempt + 1}")
                            if attempt == max_retries - 1:
                                result["success"] = result["token"] is not None
                                result["error"] = f"Stock API failed: HTTP {res.status}"
            except asyncio.TimeoutError:
                log(f"  [{label}] Stock API timeout, attempt {attempt + 1}")
                if attempt == max_retries - 1:
                    result["success"] = result["token"] is not None
                    result["error"] = "Stock API timeout"
            except Exception as e:
                log(f"  [{label}] Stock API error: {str(e)[:50]}, attempt {attempt + 1}")
                if attempt == max_retries - 1:
                    result["success"] = result["token"] is not None
                    result["error"] = f"Stock API error: {str(e)[:100]}"
            
            if attempt < max_retries - 1:
                await asyncio.sleep(2)

    return result


async def fetch_transactions(token: str, start_date: str, end_date: str) -> dict:
    all_customers = []
    summary       = None
    
    max_retries = 3

    async with aiohttp.ClientSession() as session:
        headers = {
            "Authorization": f"Bearer {token}",
            "Accept":        "application/json",
            "User-Agent":    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36",
            "Origin":        "https://subsiditepatlpg.mypertamina.id",
            "Referer":       "https://subsiditepatlpg.mypertamina.id/",
        }

        start   = datetime.strptime(start_date, "%Y-%m-%d")
        end     = datetime.strptime(end_date,   "%Y-%m-%d")
        current = start
        
        request_count = 0

        while current <= end:
            batch_end = min(current + timedelta(days=6), end)
            
            for attempt in range(max_retries):
                try:
                    async with session.get(
                        "https://api-map.my-pertamina.id/general/v3/transactions/report",
                        headers=headers,
                        params={
                            "search": "", "sort": "latest",
                            "startDate": current.strftime("%Y-%m-%d"),
                            "endDate":   batch_end.strftime("%Y-%m-%d"),
                        },
                        timeout=aiohttp.ClientTimeout(total=30)
                    ) as res:
                        if res.status == 200:
                            body = await res.json()
                            if body.get("success"):
                                all_customers.extend(body["data"].get("customersReport", []))
                                if body["data"].get("summaryReport"):
                                    summary = body["data"]["summaryReport"]
                                    summary["date"] = batch_end.strftime("%Y-%m-%d")
                                break
                        else:
                            log(f"  Transactions API HTTP {res.status}, attempt {attempt + 1}")
                            if attempt == max_retries - 1:
                                return {"success": False, "error": f"HTTP {res.status}"}
                except asyncio.TimeoutError:
                    log(f"  Transactions API timeout, attempt {attempt + 1}")
                    if attempt == max_retries - 1:
                        return {"success": False, "error": "Timeout"}
                except Exception as e:
                    log(f"  Transactions API error: {str(e)[:50]}")
                    if attempt == max_retries - 1:
                        return {"success": False, "error": str(e)[:100]}
                
                if attempt < max_retries - 1:
                    await asyncio.sleep(2 ** attempt)  # Exponential backoff
            
            # Rate limiting: 1 request per second maksimal
            request_count += 1
            delay = max(0.5, min(2.0, 1.0 / (request_count / 60)))  # Max 60 req/min
            await asyncio.sleep(delay)
            
            current = batch_end + timedelta(days=1)

    return {"success": True, "customers": all_customers, "summary": summary}


async def process_account(account: dict, start_date: str, end_date: str,
                           index: int, total: int) -> dict:
    label = account.get("label", account.get("email", ""))
    email = account.get("email", "")
    pin   = account.get("pin",   "")

    log(f"[{index}/{total}] {label} ({email})...")
    _jsonl_log(f"── [{index}/{total}] {label} ({email})", 'info')

    login_result = await login_one(email, pin, label)

    if not login_result["success"]:
        cause = login_result.get('error','error tidak diketahui')
        log(f"  GAGAL: {cause}")
        _jsonl_log(f"  ✗ GAGAL: {cause[:80]}", 'fail')
        return {**login_result, "transactions": [], "summary": None,
                "from": start_date, "to": end_date}

    log(f"  OK: {login_result['store_name']}")
    _jsonl_log(f"  ✓ Token berhasil — login sebagai: {login_result['store_name']}", 'ok')

    tx_result = await fetch_transactions(login_result["token"], start_date, end_date)

    if not tx_result["success"]:
        log(f"  FETCH GAGAL: {tx_result['error']}")
        return {**login_result, "transactions": [], "summary": None,
                "from": start_date, "to": end_date}

    count = len(tx_result["customers"])
    log(f"  {count} transaksi ({start_date} s/d {end_date})")
    _jsonl_log(f"  ✓ Scraping: {count} transaksi ({start_date} s/d {end_date})", 'ok')

    return {
        **login_result,
        "transactions": tx_result["customers"],
        "summary":      tx_result["summary"],
        "from":         start_date,
        "to":           end_date,
    }


async def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--accounts",   required=True)
    parser.add_argument("--from",       dest="from_date", required=True)
    parser.add_argument("--to",         dest="to_date",   required=True)
    parser.add_argument("--output",     required=True)
    parser.add_argument("--concurrent", type=int, default=1)
    parser.add_argument("--logfile",    default="",
                        help="Path ke JSONL log file untuk realtime monitoring")
    args = parser.parse_args()

    # Set JSONL logfile dari arg
    if args.logfile:
        os.environ["JSONL_LOGFILE"] = args.logfile

    accounts_path = Path(args.accounts)
    if not accounts_path.exists():
        error = {"success": False, "error": f"File tidak ditemukan: {args.accounts}"}
        Path(args.output).write_text(json.dumps(error), encoding="utf-8")
        sys.exit(1)

    with open(accounts_path, encoding="utf-8") as f:
        accounts = json.load(f)

    total     = len(accounts)
    semaphore = asyncio.Semaphore(args.concurrent)

    async def process_with_semaphore(account, idx):
        async with semaphore:
            return await process_account(
                account, args.from_date, args.to_date, idx, total
            )

    tasks   = [process_with_semaphore(acc, i+1) for i, acc in enumerate(accounts)]
    results = await asyncio.gather(*tasks)

    output = {
        "success": True, 
        "total": total, 
        "results": list(results),
        "timestamp": datetime.now().isoformat()
    }

    output_path = Path(args.output)
    output_path.parent.mkdir(parents=True, exist_ok=True)
    output_path.write_text(json.dumps(output, ensure_ascii=False), encoding="utf-8")

    # Statistik
    success_count = sum(1 for r in results if r["success"])
    log(f"\nSELESAI: {success_count}/{total} pangkalan berhasil. Hasil: {args.output}")


if __name__ == "__main__":
    os.environ["PYTHONUNBUFFERED"] = "1"
    asyncio.run(main())