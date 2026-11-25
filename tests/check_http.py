import argparse
import re
import secrets
from pathlib import Path
from urllib.parse import urlparse

import requests


def run(base, demo):
    session = requests.Session()
    suffix = secrets.token_hex(4)
    count = 0

    def check(condition, label):
        nonlocal count
        if not condition:
            raise AssertionError(label)
        count += 1
        print(f"OK {label}")

    def get(path, status=200, client=session):
        response = client.get(base + path, allow_redirects=False, timeout=25)
        assert response.status_code == status, f"GET {path}: HTTP {response.status_code}, expected {status}"
        return response

    def post(path, data, status=303, client=session, files=None):
        response = client.post(base + path, data=data, files=files, allow_redirects=False, timeout=25)
        assert response.status_code == status, f"POST {path}: HTTP {response.status_code}, expected {status}"
        return response

    def token(response):
        return re.search(r'name="csrf" value="([a-f0-9]{64})"', response.text)[1]

    def sign_in(role, client=session, alias=None, password="RuangDemo26!"):
        landing = get("/", client=client)
        prior = client.cookies.get("ruangakses_session")
        response = post("/login", {
            "csrf": token(landing), "email": (alias or role) + "@ruangakses.test", "password": password,
        }, client=client)
        check(response.headers["Location"] == "/dashboard/" + role, f"redirect login {role}")
        check(client.cookies.get("ruangakses_session") != prior, f"rotasi sesi {role}")
        return get("/dashboard/" + role, client=client)

    def sign_out(page, client=session):
        return post("/logout", {"csrf": token(page)}, client=client)

    landing = get("/")
    check("RuangAkses" in landing.text and 'name="email"' in landing.text, "halaman masuk")
    check("no-store" in landing.headers["Cache-Control"], "halaman akun bebas cache")
    cookies = landing.raw.headers.getlist("Set-Cookie")
    check(all("httponly" in value.lower() and "samesite=lax" in value.lower() for value in cookies), "atribut cookie")
    if base.startswith("https://"):
        check(all("; secure" in value.lower() for value in cookies), "cookie HTTPS")
    check("CRUD Akun PPDB" in get("/blog").text, "blog publik")
    check(get("/pengguna", 303).headers["Location"] == "/", "akun anonim dialihkan")
    check("Akses ditolak" in post("/login", {"csrf": "salah"}, 403).text, "token login wajib")
    check("Email atau kata sandi salah" in post("/login", {
        "csrf": token(landing), "email": "admin@ruangakses.test", "password": "salah",
    }, 422).text, "kata sandi salah ditolak")

    dashboard = sign_in("admin")
    check("Ringkasan pengguna" in dashboard.text, "statistik admin")
    management = get("/pengguna")
    check("Nadia Prameswari" in management.text and "password_hash" not in management.text, "daftar pengguna")
    edit_url = "/pengguna/edit?id="
    delete_url = "/pengguna/hapus?id="
    create_url = "/pengguna/tambah"
    form = get(create_url)
    csrf = token(form)
    alias = "qa" + suffix
    email = alias + "@ruangakses.test"
    record = {"csrf": csrf, "nama_lengkap": "Akun Uji <QA> " + suffix, "email": email, "role": "siswa", "password": "TesAkun123!"}
    check("sudah digunakan" in post(create_url, record | {"email": "admin@ruangakses.test"}, 422).text, "email duplikat")
    check("Pilih salah satu peran" in post(create_url, record | {"role": "owner"}, 422).text, "peran divalidasi")
    check("minimal 10 karakter" in post(create_url, record | {"password": "singkat"}, 422).text, "kata sandi divalidasi")
    check(post(create_url, record).headers["Location"] == "/pengguna", "buat akun")
    result = get("/pengguna?q=" + suffix + "&role=siswa")
    check("&lt;QA&gt;" in result.text and "<QA>" not in result.text, "pencarian nama dan escaping")
    account_id = re.search(r'/pengguna/edit\?id=(\d+)', result.text)[1]
    check(email in get("/pengguna?q=" + email).text, "pencarian email")
    check(email in get("/pengguna?q=" + account_id).text, "pencarian ID")
    check(email not in get("/pengguna?q=" + suffix + "&role=admin").text, "filter peran")
    check("Pengguna tidak ditemukan" in get("/pengguna?q=%25").text, "pencarian karakter literal")
    edited = record | {"nama_lengkap": "Akun Tersimpan " + suffix, "password": ""}
    post(edit_url + account_id, edited)
    check(edited["nama_lengkap"] in get("/pengguna?q=" + suffix).text, "edit tanpa ganti kata sandi")
    check("Peran akun sendiri" in post(edit_url + "1", record | {
        "nama_lengkap": "Nadia Prameswari", "email": "admin@ruangakses.test",
    }, 422).text, "perlindungan peran admin sendiri")
    check("sedang Anda gunakan" in post(delete_url + "1", {"csrf": csrf}, 409).text, "perlindungan hapus akun sendiri")
    check("riwayat pendaftaran" in post(delete_url + "4", {"csrf": csrf}, 409).text, "perlindungan riwayat pendaftaran")
    check("Akses ditolak" in post(delete_url + account_id, {"csrf": "salah"}, 403).text, "perlindungan CSRF hapus")
    disposable = record | {"email": "hapus" + email}
    post(create_url, disposable)
    disposable_page = get("/pengguna?q=hapus" + email)
    disposable_id = re.search(r'/pengguna/hapus\?id=(\d+)', disposable_page.text)[1]
    check("Hapus akun" in get(delete_url + disposable_id).text, "halaman konfirmasi hapus")
    check('href="/pengguna/edit?id=' + disposable_id + '"' in get("/pengguna?q=hapus" + email).text, "GET tidak menghapus data")
    post(delete_url + disposable_id, {"csrf": csrf})
    check("Pengguna tidak ditemukan" in get("/pengguna?q=hapus" + email).text, "POST menghapus akun")

    workspace = session.cookies.get("ruangakses_workspace")
    student = requests.Session()
    if demo:
        student.cookies.set("ruangakses_workspace", workspace, domain=urlparse(base).hostname, path="/")
    student_dash = sign_in("siswa", client=student, alias=alias, password=record["password"])
    check("Anda belum mengirim pendaftaran" in student_dash.text, "dasbor akun yang baru dibuat")
    check("Akses ditolak" in get("/pengguna", 403, student).text, "siswa ditolak dari manajemen")
    check("Akses ditolak" in post(create_url, record | {"csrf": token(student_dash)}, 403, student).text, "siswa ditolak dari POST admin")
    check("Pendaftaran tidak ditemukan" in get("/pendaftaran/detail?id=1", 404, student).text, "siswa gagal membaca data siswa lain")
    check(get("/dokumen?id=1", 404, student).status_code == 404, "pembatasan kepemilikan dokumen")
    registration = {"csrf": token(student_dash), "nisn": str(secrets.randbelow(9_000_000_000) + 1_000_000_000),
                    "no_hp": "081234567899", "jurusan": "Rekayasa Perangkat Lunak"}
    document = (Path(__file__).resolve().parents[1] / "database" / "contoh-dokumen.png").read_bytes()
    check("Isi dokumen harus" in post("/pendaftaran/isi", registration, 422, student, {
        "dokumen": ("contoh.png", b"isi bukan gambar", "image/png"),
    }).text, "unggahan diperiksa berdasarkan isi")
    post("/pendaftaran/isi", registration, client=student, files={"dokumen": ("contoh.png", document, "image/png")})
    student_dash = get("/dashboard/siswa", client=student)
    doc_id = re.search(r'/pendaftaran/detail\?id=(\d+)', student_dash.text)[1]
    detail = "/pendaftaran/detail?id=" + doc_id
    document_path = "/dokumen?id=" + doc_id
    check("Menunggu" in get(detail, client=student).text, "pendaftaran siswa tersimpan")
    download = get(document_path, client=student)
    check(download.content == document and "attachment" in download.headers["Content-Disposition"], "unduh dokumen sendiri")
    check(get("/pendaftaran/isi", 409, student).status_code == 409, "siswa menunggu keputusan")

    post(edit_url + account_id, edited | {"password": "GantiAkun123!"})
    check(get("/dashboard/siswa", 303, student).headers["Location"] == "/", "pergantian kata sandi membatalkan sesi lama")
    student_dash = sign_in("siswa", client=student, alias=alias, password="GantiAkun123!")
    sign_out(get("/pengguna"))
    committee = sign_in("panitia")
    check(get("/pengguna", 403).status_code == 403, "panitia ditolak dari manajemen")
    check(get("/dashboard/admin", 403).status_code == 403, "panitia ditolak dari dasbor admin")
    check("Akun Tersimpan " + suffix in get("/pendaftaran?status=menunggu").text, "panitia memantau antrean")
    check(get(document_path, 403).status_code == 403, "panitia tidak bisa unduh dokumen")
    check(post(detail, {"csrf": token(committee), "status": "terverifikasi"}, 403).status_code == 403, "panitia tidak bisa memberi keputusan")
    sign_out(committee)
    verifier = sign_in("verifikator")
    check(get("/pengguna", 403).status_code == 403, "verifikator ditolak dari manajemen")
    decision = {"csrf": token(verifier), "status": "perlu_revisi", "catatan": ""}
    check("Tuliskan catatan" in post(detail, decision, 422).text, "catatan revisi wajib")
    post(detail, decision | {"catatan": "Mohon unggah ulang dokumen contoh."})
    check("Mohon unggah ulang" in get(detail, client=student).text, "siswa melihat catatan revisi")
    post("/pendaftaran/isi", registration | {"csrf": token(student_dash)}, client=student, files={
        "dokumen": ("contoh.png", document, "image/png"),
    })
    check("Menunggu" in get(detail).text, "revisi kembali ke antrean")
    post(detail, decision | {"status": "terverifikasi"})
    check("Terverifikasi" in get(detail, client=student).text, "hasil verifikasi terlihat oleh siswa")
    check(post(detail, decision | {"status": "terverifikasi"}, 409).status_code == 409, "verifikasi yang selesai tidak ditimpa")
    sign_out(get(detail))
    check(get(detail, 303).headers["Location"] == "/", "logout mencabut akses")
    if demo:
        check(session.cookies.get("ruangakses_workspace") == workspace, "ruang demo bertahan setelah logout")
        isolated = requests.Session()
        sign_in("admin", isolated)
        check(email not in get("/pengguna?q=" + suffix, client=isolated).text, "browser baru terisolasi")
    print(f"TOTAL {count}")


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("base")
    parser.add_argument("--demo", action="store_true", help="Uji isolasi dan persistensi ruang demo SQLite")
    args = parser.parse_args()
    run(args.base.rstrip("/"), args.demo)
