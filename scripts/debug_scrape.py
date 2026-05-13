"""
debug_scrape.py - cek login dan fetch transaksi
Usage: python scripts/debug_scrape.py --email xxx --pin xxx --from 2026-05-01 --to 2026-05-06
"""
import asyncio
import aiohttp
import argparse
import json
import base64
from playwright.async_api import async_playwright

async def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--email", required=True)
    parser.add_argument("--pin",   required=True)
    parser.add_argument("--from",  dest="from_date", required=True)
    parser.add_argument("--to",    dest="to_date",   required=True)
    args = parser.parse_args()

    print(f"\n[1] Login sebagai {args.email}...")

    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=False)
        context = await browser.new_context(
            user_agent="Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15",
            viewport={"width": 390, "height": 844},
        )
        page    = await context.new_page()
        token_h = {"token": None}

        def handle_req(req):
            auth = req.headers.get("authorization","")
            if auth.startswith("Bearer ") and not token_h["token"]:
                token_h["token"] = auth.replace("Bearer ","").strip()

        page.on("request", handle_req)

        await page.goto(
            "https://subsiditepatlpg.mypertamina.id/merchant-login",
            wait_until="domcontentloaded",
            timeout=60000
        )
        await asyncio.sleep(2)

        await page.locator("input").nth(0).fill(args.email)
        await asyncio.sleep(0.3)
        await page.locator("input").nth(1).fill(args.pin)
        await asyncio.sleep(0.3)
        await page.locator("button").filter(has_text="MASUK").click()

        for _ in range(30):
            if token_h["token"]: break
            await asyncio.sleep(1)

        await browser.close()

    token = token_h["token"]
    if not token:
        print("GAGAL: Token tidak tertangkap!")
        return

    # Decode JWT
    try:
        parts   = token.split(".")
        payload = json.loads(base64.b64decode(parts[1] + "=" * (4 - len(parts[1]) % 4)))
        import datetime
        exp = datetime.datetime.fromtimestamp(payload.get("exp", 0))
        print(f"OK: Token berhasil")
        print(f"    sub     : {payload.get('sub')}")
        print(f"    expires : {exp}")
    except Exception as e:
        print(f"OK: Token berhasil (decode error: {e})")

    headers = {
        "Authorization": f"Bearer {token}",
        "Accept":        "application/json",
        "User-Agent":    "Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X)",
        "Origin":        "https://subsiditepatlpg.mypertamina.id",
        "Referer":       "https://subsiditepatlpg.mypertamina.id/",
    }

    print(f"\n[2] Ambil info stok...")
    async with aiohttp.ClientSession() as session:
        async with session.get(
            "https://api-map.my-pertamina.id/general/products/v1/products/user",
            headers=headers,
            timeout=aiohttp.ClientTimeout(total=10)
        ) as res:
            body = await res.json()
            if body.get("success"):
                d = body["data"]
                print(f"    Store    : {d.get('storeName')}")
                print(f"    Available: {d.get('stockAvailable')}")
                print(f"    Redeem   : {d.get('stockRedeem')}")
                print(f"    Sold     : {d.get('sold')}")
            else:
                print(f"    GAGAL {res.status}: {body}")

    print(f"\n[3] Ambil transaksi {args.from_date} s/d {args.to_date}...")
    async with aiohttp.ClientSession() as session:
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
                print(f"    Summary: {summary}")

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
                    print(f"\n    Response data keys: {list(body.get('data',{}).keys())}")
            else:
                print(f"    GAGAL: {body}")

    print("\n[SELESAI]")

asyncio.run(main())