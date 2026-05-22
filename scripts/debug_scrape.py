"""
debug_scrape.py - cek login dan fetch transaksi dengan screenshot on failure
Usage: python scripts/debug_scrape.py --email xxx --pin xxx --from 2026-05-01 --to 2026-05-06
"""
import asyncio
import aiohttp
import argparse
import json
import base64
import os
from datetime import datetime
from playwright.async_api import async_playwright, TimeoutError as PlaywrightTimeoutError

async def take_screenshot(page, name):
    """Take screenshot for debugging"""
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    filename = f"/tmp/debug_{name}_{timestamp}.png"
    await page.screenshot(path=filename)
    print(f"Screenshot saved: {filename}")

async def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--email", required=True)
    parser.add_argument("--pin",   required=True)
    parser.add_argument("--from",  dest="from_date", required=True)
    parser.add_argument("--to",    dest="to_date",   required=True)
    parser.add_argument("--headless", action="store_true", help="Run in headless mode")
    args = parser.parse_args()

    print(f"\n[1] Login sebagai {args.email}...")
    print(f"    Headless mode: {args.headless}")

    async with async_playwright() as p:
        browser = await p.chromium.launch(
            headless=args.headless,
            args=[
                "--no-sandbox",
                "--disable-dev-shm-usage",
                "--disable-blink-features=AutomationControlled",
            ]
        )
        
        context = await browser.new_context(
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36",
            viewport={"width": 1280, "height": 800},
            locale="id-ID",
            timezone_id="Asia/Jakarta",
        )
        
        await context.add_init_script("""
            Object.defineProperty(navigator, 'webdriver',  { get: () => undefined });
            Object.defineProperty(navigator, 'plugins',    { get: () => [1,2,3,4,5] });
            Object.defineProperty(navigator, 'languages',  { get: () => ['id-ID','id','en-US','en'] });
            window.chrome = { runtime: {} };
        """)
        
        page    = await context.new_page()
        token_h = {"token": None}
        errors = []

        def handle_req(req):
            auth = req.headers.get("authorization","")
            if auth.startswith("Bearer ") and not token_h["token"]:
                token_h["token"] = auth.replace("Bearer ","").strip()
                print(f"✓ Token captured from {req.url.split('?')[0]}")

        page.on("request", handle_req)

        try:
            print("  Opening login page...")
            await page.goto(
                "https://subsiditepatlpg.mypertamina.id/merchant-login",
                wait_until="domcontentloaded",
                timeout=60000
            )

            # Log URL dan title untuk debug
            print(f"  URL  : {page.url}")
            print(f"  Title: {await page.title()}")

            # Tunggu networkidle
            try:
                await page.wait_for_load_state("networkidle", timeout=10000)
            except:
                pass
            await asyncio.sleep(2)

            # Dump semua input yang ada
            try:
                inputs = await page.evaluate("""() => {
                    return Array.from(document.querySelectorAll('input')).map(el => ({
                        type: el.type, name: el.name,
                        placeholder: el.placeholder,
                        visible: el.offsetParent !== null
                    }));
                }""")
                print(f"  Input elements: {len(inputs)}")
                for inp in inputs:
                    print(f"    → type={inp['type']} name='{inp['name']}' placeholder='{inp['placeholder']}' visible={inp['visible']}")
            except Exception as e:
                print(f"  Gagal dump inputs: {e}")

            # Cek apakah ada form
            input_count = await page.evaluate("""() =>
                Array.from(document.querySelectorAll('input'))
                    .filter(el => el.offsetParent !== null).length
            """)
            
            if input_count == 0:
                await take_screenshot(page, "no_form")
                # Dump HTML untuk debug
                html = await page.content()
                print(f"  HTML length: {len(html)}")
                print(f"  HTML preview: {html[:500]}")
                raise Exception("Login form not found")
            
            await asyncio.sleep(1)

            print("  Filling credentials...")
            # Pakai JS evaluate — React-safe, tidak perlu wait_for_selector lagi
            await page.evaluate("""(val) => {
                const sels = ['input[type="text"]','input[type="email"]',
                              'input[placeholder*="Ponsel"]','input[placeholder*="Email"]'];
                for (const sel of sels) {
                    const el = document.querySelector(sel);
                    if (el && el.offsetParent !== null) {
                        const setter = Object.getOwnPropertyDescriptor(
                            window.HTMLInputElement.prototype, 'value').set;
                        setter.call(el, val);
                        el.dispatchEvent(new Event('input',  {bubbles:true}));
                        el.dispatchEvent(new Event('change', {bubbles:true}));
                        el.focus();
                        return;
                    }
                }
            }""", args.email)
            print(f"  Email diisi: {args.email}")
            await asyncio.sleep(0.3)

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
            }""", args.pin)
            print(f"  PIN diisi")
            await asyncio.sleep(0.3)

            print("  Clicking login button...")
            login_btn_text = await page.evaluate("""() => {
                const keywords = ['MASUK','Masuk','Login','LOGIN','Lanjut'];
                for (const b of document.querySelectorAll('button')) {
                    if (keywords.some(k => b.textContent.includes(k)) && b.offsetParent !== null) {
                        b.click(); return b.textContent.trim();
                    }
                }
                const sub = document.querySelector('button[type="submit"]');
                if (sub) { sub.click(); return 'submit'; }
                return null;
            }""")
            print(f"  Tombol diklik: {login_btn_text}")
            if not login_btn_text:
                await page.keyboard.press("Enter")
                print("  Fallback: Enter")
            # Dummy assignment agar kode di bawah tidak error
            login_btn = True

            # Tunggu navigasi atau token
            for i in range(30):
                if token_h["token"]:
                    break
                # Cek apakah sudah redirect
                current_url = page.url
                if "dashboard" in current_url or "merchant" in current_url:
                    print(f"  Redirected to: {current_url}")
                    # Coba ambil dari localStorage
                    try:
                        token_from_storage = await page.evaluate("localStorage.getItem('token')")
                        if token_from_storage:
                            token_h["token"] = token_from_storage
                            print("  Token from localStorage")
                            break
                    except:
                        pass
                await asyncio.sleep(1)

        except Exception as e:
            errors.append(f"Login error: {str(e)}")
            await take_screenshot(page, "error")
            print(f"  ERROR: {e}")
            await browser.close()
            return

        if not token_h["token"]:
            await take_screenshot(page, "no_token")
            print("  GAGAL: Token tidak tertangkap!")
            print("  URL akhir:", page.url)
            await browser.close()
            return

        await browser.close()

    token = token_h["token"]
    print(f"\n✓ Token captured: {token[:50]}...")

    # Decode JWT
    try:
        parts   = token.split(".")
        # Add padding
        payload_b64 = parts[1] + "=" * (4 - len(parts[1]) % 4)
        payload = json.loads(base64.b64decode(payload_b64))
        import datetime
        exp = datetime.datetime.fromtimestamp(payload.get("exp", 0))
        iat = datetime.datetime.fromtimestamp(payload.get("iat", 0))
        print(f"  JWT Info:")
        print(f"    sub (pangkalan_id): {payload.get('sub')}")
        print(f"    issued at        : {iat}")
        print(f"    expires          : {exp}")
        print(f"    remaining        : {exp - datetime.datetime.now()}")
    except Exception as e:
        print(f"  JWT decode error: {e}")

    headers = {
        "Authorization": f"Bearer {token}",
        "Accept":        "application/json",
        "User-Agent":    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36",
        "Origin":        "https://subsiditepatlpg.mypertamina.id",
        "Referer":       "https://subsiditepatlpg.mypertamina.id/",
    }

    print(f"\n[2] Ambil info stok...")
    async with aiohttp.ClientSession() as session:
        try:
            async with session.get(
                "https://api-map.my-pertamina.id/general/products/v1/products/user",
                headers=headers,
                timeout=aiohttp.ClientTimeout(total=10)
            ) as res:
                body = await res.json()
                if body.get("success"):
                    d = body["data"]
                    print(f"    Registration ID: {d.get('registrationId')}")
                    print(f"    Store Name     : {d.get('storeName')}")
                    print(f"    Available      : {d.get('stockAvailable')}")
                    print(f"    Redeem         : {d.get('stockRedeem')}")
                    print(f"    Sold           : {d.get('sold')}")
                    print(f"    Stock Date     : {d.get('stockDate')}")
                else:
                    print(f"    GAGAL {res.status}: {body.get('message', 'Unknown error')}")
        except asyncio.TimeoutError:
            print("    Timeout!")
        except Exception as e:
            print(f"    Error: {e}")

    print(f"\n[3] Ambil transaksi {args.from_date} s/d {args.to_date}...")
    async with aiohttp.ClientSession() as session:
        try:
            async with session.get(
                "https://api-map.my-pertamina.id/general/v3/transactions/report",
                headers=headers,
                params={
                    "search":    "",
                    "sort":      "latest",
                    "startDate": args.from_date,
                    "endDate":   args.to_date,
                },
                timeout=aiohttp.ClientTimeout(total=30)
            ) as res:
                body = await res.json()
                print(f"    HTTP status: {res.status}")

                if body.get("success"):
                    customers = body["data"].get("customersReport", [])
                    summary   = body["data"].get("summaryReport", {})
                    print(f"    Transaksi ditemukan: {len(customers)}")
                    print(f"    Summary: {json.dumps(summary, indent=4)}")

                    if customers:
                        print(f"\n    Contoh transaksi pertama:")
                        c = customers[0]
                        print(f"    - customerReportId : {c.get('customerReportId')}")
                        print(f"    - nationalityId    : {c.get('nationalityId')}")
                        print(f"    - name             : {c.get('name')}")
                        print(f"    - categories       : {c.get('categories')}")
                        print(f"    - total            : {c.get('total')}")
                        print(f"    - createdAt        : {c.get('createdAt')}")
                    else:
                        print("    (tidak ada transaksi di periode ini)")
                else:
                    print(f"    GAGAL: {body.get('message', 'Unknown error')}")
        except asyncio.TimeoutError:
            print("    Timeout!")
        except Exception as e:
            print(f"    Error: {e}")

    print("\n[SELESAI]")

asyncio.run(main())