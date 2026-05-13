"""
auto_login_batch.py — Login batch semua pangkalan dari accounts.json
Dipanggil Laravel: python auto_login_batch.py --accounts accounts.json --from 2026-05-01 --to 2026-05-05

Output: JSON array hasil per pangkalan
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
    """Login satu pangkalan, return token + info."""
    result = {
        "success":         False,
        "label":           label,
        "email":           email,
        "token":           None,
        "pangkalan_id":    None,
        "registration_id": None,
        "store_name":      None,
        "stock_available": None,
        "stock_redeem":    None,
        "sold":            None,
        "error":           None,
    }

    async with async_playwright() as p:
        browser = await p.chromium.launch(
            headless=True,
            args=["--no-sandbox", "--disable-dev-shm-usage",
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
                wait_until="networkidle",
                timeout=30000
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
                result["error"] = "Token tidak tertangkap — cek kredensial atau reCAPTCHA"
                return result

            result["token"] = token_holder["token"]

            # Decode JWT
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

    # Ambil info stok dari API
    if result["token"]:
        try:
            async with aiohttp.ClientSession() as session:
                async with session.get(
                    "https://api-map.my-pertamina.id/general/products/v1/products/user",
                    headers={
                        "Authorization": f"Bearer {result['token']}",
                        "Accept":        "application/json",
                        "User-Agent":    "Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X)",
                        "Origin":        "https://subsiditepatlpg.mypertamina.id",
                    },
                    timeout=aiohttp.ClientTimeout(total=10)
                ) as res:
                    if res.status == 200:
                        data = await res.json()
                        if data.get("success") and data.get("data"):
                            d = data["data"]
                            result["registration_id"]  = d.get("registrationId")
                            result["store_name"]       = d.get("storeName")
                            result["stock_available"]  = d.get("stockAvailable")
                            result["stock_redeem"]     = d.get("stockRedeem")
                            result["sold"]             = d.get("sold")
                            # Gunakan store_name sebagai label jika belum ada
                            if not result["label"]:
                                result["label"] = d.get("storeName", email)
                            result["success"] = True
        except Exception as e:
            result["success"] = result["token"] is not None
            result["error"]   = f"Token OK tapi info stok gagal: {str(e)}"

    return result


async def fetch_transactions(token: str, start_date: str, end_date: str) -> dict:
    """Ambil data transaksi dari API."""
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
        start = datetime.strptime(start_date, "%Y-%m-%d")
        end   = datetime.strptime(end_date,   "%Y-%m-%d")

        current = start
        while current <= end:
            batch_end = min(current + timedelta(days=6), end)

            try:
                async with session.get(
                    "https://api-map.my-pertamina.id/general/v3/transactions/report",
                    headers=headers,
                    params={
                        "search":    "",
                        "sort":      "latest",
                        "startDate": current.strftime("%Y-%m-%d"),
                        "endDate":   batch_end.strftime("%Y-%m-%d"),
                    },
                    timeout=aiohttp.ClientTimeout(total=30)
                ) as res:
                    if res.status == 401:
                        return {"success": False, "error": "Token expired (401)"}

                    if res.status == 200:
                        body = await res.json()
                        if body.get("success"):
                            customers = body["data"].get("customersReport", [])
                            all_customers.extend(customers)
                            if body["data"].get("summaryReport"):
                                summary = body["data"]["summaryReport"]
                                summary["date"] = batch_end.strftime("%Y-%m-%d")

            except Exception as e:
                return {"success": False, "error": str(e)}

            await asyncio.sleep(0.5)
            current = batch_end + timedelta(days=1)

    return {
        "success":   True,
        "customers": all_customers,
        "summary":   summary,
    }


async def process_account(account: dict, start_date: str, end_date: str, index: int, total: int) -> dict:
    """Login + fetch transaksi untuk satu akun."""
    label = account.get("label", account.get("email", ""))
    email = account.get("email", "")
    pin   = account.get("pin",   "")

    print(f"[{index}/{total}] {label} ({email})...", file=sys.stderr)

    # Login
    login_result = await login_one(email, pin, label)

    if not login_result["success"]:
        print(f"  ✗ Login gagal: {login_result['error']}", file=sys.stderr)
        return {**login_result, "transactions": [], "summary": None}

    print(f"  ✓ Login OK — {login_result['store_name']}", file=sys.stderr)

    # Fetch transaksi
    tx_result = await fetch_transactions(login_result["token"], start_date, end_date)

    if not tx_result["success"]:
        print(f"  ✗ Fetch gagal: {tx_result['error']}", file=sys.stderr)
        return {**login_result, "transactions": [], "summary": None}

    print(f"  ✓ {len(tx_result['customers'])} transaksi raw ({start_date} s/d {end_date})", file=sys.stderr)

    return {
        **login_result,
        "transactions": tx_result["customers"],
        "summary":      tx_result["summary"],
    }


async def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--accounts",   required=True,  help="Path ke accounts.json")
    parser.add_argument("--from",       dest="from_date", required=True)
    parser.add_argument("--to",         dest="to_date",   required=True)
    parser.add_argument("--concurrent", type=int, default=1,
                        help="Jumlah login paralel (default 1, max 3 untuk hindari deteksi)")
    args = parser.parse_args()

    # Baca accounts.json
    accounts_path = Path(args.accounts)
    if not accounts_path.exists():
        print(json.dumps({"success": False, "error": f"File tidak ditemukan: {args.accounts}"}))
        sys.exit(1)

    with open(accounts_path, encoding="utf-8") as f:
        accounts = json.load(f)

    if not accounts:
        print(json.dumps({"success": False, "error": "accounts.json kosong"}))
        sys.exit(1)

    total   = len(accounts)
    results = []

    # Proses secara concurrent (default: satu per satu)
    semaphore = asyncio.Semaphore(args.concurrent)

    async def process_with_semaphore(account, idx):
        async with semaphore:
            return await process_account(account, args.from_date, args.to_date, idx, total)

    tasks = [
        process_with_semaphore(acc, i + 1)
        for i, acc in enumerate(accounts)
    ]

    results = await asyncio.gather(*tasks)

    # Output JSON ke stdout — dibaca Laravel
    output = {
        "success": True,
        "total":   total,
        "results": list(results),
    }
    print(json.dumps(output, ensure_ascii=False))


if __name__ == "__main__":
    asyncio.run(main())
