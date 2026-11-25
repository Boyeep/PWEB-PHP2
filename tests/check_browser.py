import argparse
from io import BytesIO
from pathlib import Path

from PIL import Image
from playwright.sync_api import expect, sync_playwright


def run(base, channel, screenshots):
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(channel=channel)
        context = browser.new_context(viewport={"width": 1440, "height": 900})
        page = context.new_page()
        errors = []
        page.on("pageerror", lambda error: errors.append(str(error)))

        def screenshot(name):
            if screenshots is not None:
                screenshots.mkdir(parents=True, exist_ok=True)
                Image.open(BytesIO(page.screenshot())).save(screenshots / (name + ".webp"), quality=87, method=6)

        def visit(path, status=200):
            response = page.goto(base + path, wait_until="networkidle")
            assert response.status == status, f"{path} returned HTTP {response.status}"
            page.evaluate("document.fonts.ready")

        def no_overflow():
            assert page.evaluate("document.documentElement.scrollWidth <= window.innerWidth"), page.url

        visit("/")
        expect(page.get_by_role("heading", name="Masuk ke akun")).to_be_visible()
        page.get_by_role("button", name="Lihat", exact=True).click()
        assert page.locator("#login-password").get_attribute("type") == "text"
        page.get_by_role("button", name="Sembunyikan").click()
        screenshot("halaman-masuk")
        page.get_by_role("button", name="Admin", exact=True).click()
        page.wait_for_url("**/dashboard/admin")
        expect(page.get_by_role("heading", name="Ringkasan pengguna")).to_be_visible()
        assert page.locator(".row").first.evaluate("(element) => getComputedStyle(element).display") == "flex"
        screenshot("dashboard-admin")
        page.get_by_role("link", name="Pengguna", exact=True).click()
        expect(page.get_by_role("heading", name="Manajemen pengguna")).to_be_visible()
        screenshot("manajemen-pengguna")
        visit("/pengguna/tambah")
        expect(page.get_by_role("heading", name="Tambah pengguna")).to_be_visible()
        screenshot("tambah-pengguna")
        visit("/pengguna/edit?id=2")
        expect(page.get_by_role("heading", name="Edit pengguna")).to_be_visible()
        screenshot("edit-pengguna")

        for width in (375, 768, 1280):
            page.set_viewport_size({"width": width, "height": 900})
            for path in ("/dashboard/admin", "/pengguna", "/pengguna/tambah", "/blog"):
                visit(path)
                no_overflow()
                if width == 375 and path == "/pengguna":
                    screenshot("manajemen-ponsel")
            print(f"OK tampilan {width}px")

        page.set_viewport_size({"width": 1440, "height": 900})
        visit("/dashboard/admin")
        page.get_by_role("button", name="Keluar").click()
        page.wait_for_url(base + "/")
        page.get_by_role("button", name="Panitia", exact=True).click()
        page.wait_for_url("**/dashboard/panitia")
        visit("/pengguna", 403)
        expect(page.get_by_role("heading", name="Akses ditolak")).to_be_visible()
        screenshot("rbac-403")
        assert not errors, errors
        print("OK login cepat, kata sandi, Bootstrap, dan halaman 403")
        browser.close()


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("base")
    parser.add_argument("--channel", help="Gunakan chrome atau msedge yang sudah terpasang")
    parser.add_argument("--screenshots", type=Path)
    args = parser.parse_args()
    run(args.base.rstrip("/"), args.channel, args.screenshots)
