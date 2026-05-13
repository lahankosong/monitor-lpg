"""
auto_login_batch.py v2 — output ke file JSON, bukan stdout
"""

import asyncio
import aiohttp
import argparse
import json
import sys
import base64
from pathlib import Path
from playwright.async_api import async_playwright


async def login_one(email: str, pin: str, label: str = "") -> dict:
    result = {
        "success": False, "label": label, "email": email,
        "token": None, "pangkalan_id": None, "registration_id": None,
        "store_name": None, "stock_available": None,
        "stock_redeem": None, "sold": None, "error": None,
    }

    async with async_playwright() as p:
        browser = await p.chromium.launch(
            headless=True,
            args=["--no-sandbox","--disable-dev-shm-usage",
                  "--disable-blink-features=AutomationControlled"]
        )
        context = await browser.new_context(
            user_agent="Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15",
            viewport={"width": 390, "height": 844},
        )
        page = await context.new_page()
        token_holder = {"token": None}

        def handle_request(request):
            auth = request.headers.get("authorization", "")
            if auth.startswith("Bearer ") and not token_holder["token"]:
                token_holder["token"] = auth.replace("Bearer ", "").strip()

        page.on("request", handle_request)

        try:
            await page.goto(
                "https://subsiditepatlpg.mypertamina.id/merchant-login",
                wait_until="networkidle", timeout=30000
            )
            await page.locator("input").nth(0).fill(email)
            await asyncio.sleep(0.3)
            await page.locator("input").nth(1).fill(pin)
            await asyncio.sleep(0.3)
            await page.locator("button").filter(has_text="MASUK").click()

            for _ in range(30):
                if token_holder["token"]:
                    break
                await asyncio.sleep(1)

            if not token_holder["token"]:
                result["error"] = "Token tidak tertangkap"
                return result

            result["token"] = token_holder["token"]

            try:
                parts   = result["token"].split(".")
                payload = json.loads(base64.b64decode(
                    parts[1] + "=" * (4 - len(parts[1]) % 4)
                ))
                result["pangkalan_id"] = payload.get("sub")
            except Exception:
                pass

        except Exception as e:
            result["error"] = f"Browser error: {str(e)}"
            return result
        finally:
            await browser.close()

    # Ambil info stok
    if result["token"]:
        try:
            async with aiohttp.ClientSession() as session:
                async with session.get(
                    "https://api-map.my-pertamina.id/general/products/v1/products/user",
                    headers={
                        "Authorization": f"Bearer {result['token']}",
                        "Accept": "application/json",
                        "User-Agent": "Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X)",
                        "Origin": "https://subsiditepatlpg.mypertamina.id",
                    },
                    timeout=aiohttp.ClientTimeout(total=10)
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
                                "success":         True,
                            })
                            if not result["label"]:
                                result["label"] = d.get("storeName", email)
        except Exception as e:
            result["success"] = result["token"] is not None
            result["error"]   = f"Info stok gagal: {str(e)}"

    return result


async def fetch_transactions(token: str, start_date: str, end_date: str) -> dict:
    all_customers = []
    summary       = None

    async with aiohttp.ClientSession() as session:
        headers = {
            "Authorization": f"Bearer {token}",
            "Accept":        "application/json",
            "User-Agent":    "Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X)",
            "Origin":        "https://subsiditepatlpg.mypertamina.id",
            "Referer":       "https://subsiditepatlpg.mypertamina.id/",
        }

        from datetime import datetime, timedelta
        start   = datetime.strptime(start_date, "%Y-%m-%d")
        end     = datetime.strptime(end_date,   "%Y-%m-%d")
        current = start

        while current <= end:
            batch_end = min(current + timedelta(days=6), end)
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
            except Exception as e:
                return {"success": False, "error": str(e)}

            await asyncio.sleep(0.5)
            current = batch_end + timedelta(days=1)

    return {"success": True, "customers": all_customers, "summary": summary}


async def process_account(account: dict, start_date: str, end_date: str,
                           index: int, total: int) -> dict:
    label = account.get("label", account.get("email", ""))
    email = account.get("email", "")
    pin   = account.get("pin",   "")

    print(f"[{index}/{total}] {label} ({email})...", flush=True)

    login_result = await login_one(email, pin, label)

    if not login_result["success"]:
        print(f"  ✗ Login gagal: {login_result['error']}", flush=True)
        return {**login_result, "transactions": [], "summary": None,
                "from": start_date, "to": end_date}

    print(f"  ✓ Login OK — {login_result['store_name']}", flush=True)

    tx_result = await fetch_transactions(login_result["token"], start_date, end_date)

    if not tx_result["success"]:
        print(f"  ✗ Fetch gagal: {tx_result['error']}", flush=True)
        return {**login_result, "transactions": [], "summary": None,
                "from": start_date, "to": end_date}

    count = len(tx_result["customers"])
    print(f"  ✓ {count} transaksi raw ({start_date} s/d {end_date})", flush=True)

    return {
        **login_result,
        "transactions": tx_result["customers"],
        "summary":      tx_result["summary"],
        "from":         start_date,
        "to":           end_date,
    }


async def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--accounts",    required=True)
    parser.add_argument("--from",        dest="from_date", required=True)
    parser.add_argument("--to",          dest="to_date",   required=True)
    parser.add_argument("--output",      required=True, help="Path file output JSON")
    parser.add_argument("--concurrent",  type=int, default=1)
    args = parser.parse_args()

    accounts_path = Path(args.accounts)
    if not accounts_path.exists():
        error = {"success": False, "error": f"File tidak ditemukan: {args.accounts}"}
        Path(args.output).write_text(json.dumps(error), encoding="utf-8")
        sys.exit(1)

    with open(accounts_path, encoding="utf-8") as f:
        accounts = json.load(f)

    total    = len(accounts)
    results  = []
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
        "total":   total,
        "results": list(results),
    }

    # Tulis ke file output — BUKAN stdout
    output_path = Path(args.output)
    output_path.parent.mkdir(parents=True, exist_ok=True)
    output_path.write_text(
        json.dumps(output, ensure_ascii=False),
        encoding="utf-8"
    )

    print(f"\nDONE: {total} pangkalan diproses. Hasil disimpan ke: {args.output}", flush=True)
    sys.exit(0)


if __name__ == "__main__":
    asyncio.run(main())
