"""
Nexa Agent - AI agent berbasis Flask + Google Gemini.

Fitur:
  - Percakapan multi-chat (buat, cari, ganti nama, pin, hapus, ekspor)
  - Memori percakapan (riwayat dikirim ke model, bukan hanya pesan terakhir)
  - Streaming jawaban real-time + tombol stop
  - Regenerate jawaban terakhir
  - Mode agent (persona): Umum, Programmer, Riset, Penulis, Penerjemah, Analis Data
  - Alat: Google Search (grounding + sumber), Code Execution, Tools lokal (waktu, kalkulator, statistik teks)
  - Upload file (gambar, PDF, teks, audio) dan dikirim ke model
  - Judul chat otomatis dari AI
  - Instruksi kustom, pilihan model, temperature

Jalankan:
  pip install flask google-genai
  export GEMINI_API_KEY="ISI API KEY DISINI"     (Windows PowerShell: $env:GEMINI_API_KEY="ISI API KEY DISINI")
  python app.py
"""

import ast
import json
import logging
import math
import mimetypes
import operator
import os
import sqlite3
import uuid
from contextlib import contextmanager
from datetime import datetime, timedelta, timezone

from flask import (Flask, Response, abort, jsonify, render_template, request,
                   send_from_directory, stream_with_context)
from google import genai
from google.genai import types
from werkzeug.utils import secure_filename

# --------------------------------------------------------------------------
# Konfigurasi
# --------------------------------------------------------------------------
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
UPLOAD_FOLDER = os.path.join(BASE_DIR, "uploads")
DB_PATH = os.path.join(BASE_DIR, "database.db")

GEMINI_API_KEY = os.environ.get("GEMINI_API_KEY", "")
DEFAULT_MODEL = os.environ.get("GEMINI_MODEL", "gemini-3.8-flash")# Daftar model yang boleh dipilih di UI. Ubah lewat env GEMINI_MODELS="a,b,c".
AVAILABLE_MODELS = [
    m.strip()
    for m in os.environ.get(
        "GEMINI_MODELS",
        f"{DEFAULT_MODEL},gemini-2.5-pro,gemini-2.5-flash-lite",
    ).split(",")
    if m.strip()
]
if DEFAULT_MODEL not in AVAILABLE_MODELS:
    AVAILABLE_MODELS.insert(0, DEFAULT_MODEL)

MAX_UPLOAD_MB = 20
HISTORY_LIMIT = 40  # jumlah pesan terakhir yang dikirim sebagai konteks
DEFAULT_TITLE = "Percakapan Baru"

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s")
log = logging.getLogger("nexa")

app = Flask(__name__)
app.config["UPLOAD_FOLDER"] = UPLOAD_FOLDER
app.config["MAX_CONTENT_LENGTH"] = MAX_UPLOAD_MB * 1024 * 1024
os.makedirs(UPLOAD_FOLDER, exist_ok=True)

client = genai.Client(api_key=GEMINI_API_KEY) if GEMINI_API_KEY else None

# --------------------------------------------------------------------------
# Persona agent
# --------------------------------------------------------------------------
BASE_PROMPT = (
    "Kamu adalah Nexa, asisten AI agent yang cermat, jujur, dan membantu. "
    "Jawab dalam bahasa yang dipakai pengguna (default Bahasa Indonesia). "
    "Gunakan Markdown seperlunya: blok kode dengan penanda bahasa, tabel untuk "
    "perbandingan, daftar untuk langkah. Jika tidak yakin, katakan terus terang."
)

PERSONAS = {
    "general": {
        "label": "Asisten Umum",
        "hint": "Serba bisa untuk tanya jawab sehari-hari",
        "prompt": "Bantu pengguna dengan jawaban jelas dan ringkas.",
    },
    "coder": {
        "label": "Programmer",
        "hint": "Menulis, debug, dan mereview kode",
        "prompt": (
            "Kamu adalah senior software engineer. Berikan kode yang benar, "
            "rapi, dan siap jalan. Jelaskan akar masalah saat debugging, "
            "sebutkan edge case, dan ringkas perubahan yang kamu buat."
        ),
    },
    "researcher": {
        "label": "Peneliti",
        "hint": "Analisis mendalam dengan sumber",
        "prompt": (
            "Kamu adalah analis riset. Susun jawaban terstruktur, bedakan fakta "
            "dan opini, sebutkan ketidakpastian, dan gunakan sumber bila tersedia."
        ),
    },
    "writer": {
        "label": "Penulis",
        "hint": "Draf, editing, dan penyempurnaan teks",
        "prompt": (
            "Kamu adalah editor dan penulis profesional. Perhatikan nada, alur, "
            "dan kejelasan. Tawarkan versi alternatif bila berguna."
        ),
    },
    "translator": {
        "label": "Penerjemah",
        "hint": "Terjemahan natural lintas bahasa",
        "prompt": (
            "Kamu adalah penerjemah profesional. Terjemahkan secara natural, "
            "pertahankan nada dan format asli, dan beri catatan singkat bila ada "
            "ungkapan yang butuh penyesuaian budaya."
        ),
    },
    "analyst": {
        "label": "Analis Data",
        "hint": "Hitung, olah, dan tafsirkan data",
        "prompt": (
            "Kamu adalah analis data. Tunjukkan langkah perhitungan, verifikasi "
            "angka, dan sajikan hasil dalam tabel bila cocok."
        ),
    },
}

TOOL_MODES = {
    "none": "Tanpa alat",
    "search": "Pencarian web (Google)",
    "code": "Eksekusi kode (Python)",
    "local": "Alat lokal (waktu, kalkulator, statistik)",
}

# --------------------------------------------------------------------------
# Tools lokal (function calling)
# --------------------------------------------------------------------------
_OPS = {
    ast.Add: operator.add, ast.Sub: operator.sub, ast.Mult: operator.mul,
    ast.Div: operator.truediv, ast.FloorDiv: operator.floordiv,
    ast.Mod: operator.mod, ast.Pow: operator.pow,
    ast.USub: operator.neg, ast.UAdd: operator.pos,
}
_FUNCS = {
    "sqrt": math.sqrt, "sin": math.sin, "cos": math.cos, "tan": math.tan,
    "log": math.log, "log10": math.log10, "abs": abs, "round": round,
    "min": min, "max": max,
}
_CONSTS = {"pi": math.pi, "e": math.e}


def _eval_node(node):
    if isinstance(node, ast.Constant) and isinstance(node.value, (int, float)):
        return node.value
    if isinstance(node, ast.Name) and node.id in _CONSTS:
        return _CONSTS[node.id]
    if isinstance(node, ast.BinOp) and type(node.op) in _OPS:
        left, right = _eval_node(node.left), _eval_node(node.right)
        if isinstance(node.op, ast.Pow) and abs(right) > 1000:
            raise ValueError("Pangkat terlalu besar")
        return _OPS[type(node.op)](left, right)
    if isinstance(node, ast.UnaryOp) and type(node.op) in _OPS:
        return _OPS[type(node.op)](_eval_node(node.operand))
    if (isinstance(node, ast.Call) and isinstance(node.func, ast.Name)
            and node.func.id in _FUNCS and not node.keywords):
        return _FUNCS[node.func.id](*[_eval_node(a) for a in node.args])
    raise ValueError("Ekspresi tidak didukung")


def get_current_datetime(utc_offset_hours: float = 7.0) -> dict:
    """Mengambil tanggal dan waktu saat ini.

    Args:
        utc_offset_hours: Selisih zona waktu dari UTC dalam jam (WIB = 7, WITA = 8, WIT = 9).

    Returns:
        Tanggal, waktu, dan nama hari saat ini.
    """
    tz = timezone(timedelta(hours=utc_offset_hours))
    now = datetime.now(tz)
    return {
        "iso": now.isoformat(timespec="seconds"),
        "date": now.strftime("%Y-%m-%d"),
        "time": now.strftime("%H:%M:%S"),
        "weekday": now.strftime("%A"),
        "utc_offset_hours": utc_offset_hours,
    }


def calculate(expression: str) -> dict:
    """Menghitung ekspresi matematika dengan aman.

    Args:
        expression: Contoh "(12.5 * 4) + sqrt(81)". Mendukung + - * / // % ** serta sqrt, sin, cos, tan, log, log10, abs, round, min, max, pi, e.

    Returns:
        Hasil perhitungan atau pesan error.
    """
    try:
        tree = ast.parse(expression.strip(), mode="eval")
        return {"expression": expression, "result": _eval_node(tree.body)}
    except Exception as exc:  # noqa: BLE001
        return {"expression": expression, "error": str(exc)}


def text_statistics(text: str) -> dict:
    """Menghitung statistik sebuah teks.

    Args:
        text: Teks yang akan dianalisis.

    Returns:
        Jumlah karakter, kata, kalimat, baris, dan estimasi waktu baca.
    """
    words = text.split()
    sentences = [s for s in text.replace("!", ".").replace("?", ".").split(".") if s.strip()]
    return {
        "characters": len(text),
        "words": len(words),
        "sentences": len(sentences),
        "lines": len(text.splitlines()) or (1 if text else 0),
        "reading_minutes": round(len(words) / 200, 1),
    }


LOCAL_TOOLS = [get_current_datetime, calculate, text_statistics]

# --------------------------------------------------------------------------
# Database
# --------------------------------------------------------------------------
@contextmanager
def db():
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    conn.execute("PRAGMA foreign_keys = ON")
    try:
        yield conn
        conn.commit()
    except Exception:
        conn.rollback()
        raise
    finally:
        conn.close()


def _add_column_if_missing(conn, table, column, ddl):
    cols = {r["name"] for r in conn.execute(f"PRAGMA table_info({table})")}
    if column not in cols:
        conn.execute(f"ALTER TABLE {table} ADD COLUMN {column} {ddl}")


def init_db():
    with db() as conn:
        conn.execute(
            """CREATE TABLE IF NOT EXISTS chats (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT,
                pinned INTEGER DEFAULT 0
            )"""
        )
        conn.execute(
            """CREATE TABLE IF NOT EXISTS messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                chat_id INTEGER,
                sender TEXT,
                message TEXT,
                file_path TEXT,
                FOREIGN KEY (chat_id) REFERENCES chats (id) ON DELETE CASCADE
            )"""
        )
        # Migrasi untuk database lama
        _add_column_if_missing(conn, "chats", "created_at", "TEXT")
        _add_column_if_missing(conn, "messages", "created_at", "TEXT")
        _add_column_if_missing(conn, "messages", "sources", "TEXT")
        conn.execute("CREATE INDEX IF NOT EXISTS idx_messages_chat ON messages(chat_id)")


def now_iso():
    return datetime.now(timezone.utc).isoformat(timespec="seconds")


def msg_to_dict(row):
    stored = row["file_path"]
    return {
        "id": row["id"],
        "sender": row["sender"],
        "message": row["message"],
        "file_path": stored,
        "file_name": display_name(stored),
        "sources": json.loads(row["sources"]) if row["sources"] else [],
        "created_at": row["created_at"],
    }


def display_name(stored):
    """Nama file asli: buang awalan uuid 'abcd1234_'."""
    if not stored:
        return None
    head, sep, tail = stored.partition("_")
    return tail if sep and len(head) == 8 else stored


init_db()

# --------------------------------------------------------------------------
# Halaman & konfigurasi
# --------------------------------------------------------------------------
@app.route("/")
def index():
    return render_template("index.html")


@app.route("/api/config")
def get_config():
    return jsonify({
        "models": AVAILABLE_MODELS,
        "default_model": DEFAULT_MODEL,
        "personas": {k: {"label": v["label"], "hint": v["hint"]} for k, v in PERSONAS.items()},
        "tools": TOOL_MODES,
        "api_key_set": client is not None,
        "max_upload_mb": MAX_UPLOAD_MB,
    })


@app.route("/uploads/<path:filename>")
def uploaded_file(filename):
    return send_from_directory(UPLOAD_FOLDER, filename)


# --------------------------------------------------------------------------
# CRUD chat
# --------------------------------------------------------------------------
@app.route("/api/chats", methods=["GET"])
def list_chats():
    q = (request.args.get("q") or "").strip()
    with db() as conn:
        if q:
            like = f"%{q}%"
            rows = conn.execute(
                """SELECT DISTINCT c.id, c.title, c.pinned FROM chats c
                   LEFT JOIN messages m ON m.chat_id = c.id
                   WHERE c.title LIKE ? OR m.message LIKE ?
                   ORDER BY c.pinned DESC, c.id DESC""",
                (like, like),
            ).fetchall()
        else:
            rows = conn.execute(
                "SELECT id, title, pinned FROM chats ORDER BY pinned DESC, id DESC"
            ).fetchall()
    return jsonify([dict(r) for r in rows])


@app.route("/api/chats", methods=["POST"])
def create_chat():
    with db() as conn:
        cur = conn.execute(
            "INSERT INTO chats (title, pinned, created_at) VALUES (?, 0, ?)",
            (DEFAULT_TITLE, now_iso()),
        )
        chat_id = cur.lastrowid
    return jsonify({"chat_id": chat_id, "id": chat_id, "title": DEFAULT_TITLE, "pinned": 0})


@app.route("/api/chats/<int:chat_id>", methods=["GET"])
def get_messages(chat_id):
    with db() as conn:
        if not conn.execute("SELECT 1 FROM chats WHERE id = ?", (chat_id,)).fetchone():
            return jsonify({"error": "Chat tidak ditemukan"}), 404
        rows = conn.execute(
            "SELECT * FROM messages WHERE chat_id = ? ORDER BY id", (chat_id,)
        ).fetchall()
    return jsonify([msg_to_dict(r) for r in rows])


@app.route("/api/chats/<int:chat_id>/rename", methods=["PUT"])
def rename_chat(chat_id):
    title = ((request.get_json(silent=True) or {}).get("title") or "").strip()
    if not title:
        return jsonify({"error": "Judul tidak boleh kosong"}), 400
    title = title[:80]
    with db() as conn:
        cur = conn.execute("UPDATE chats SET title = ? WHERE id = ?", (title, chat_id))
        if cur.rowcount == 0:
            return jsonify({"error": "Chat tidak ditemukan"}), 404
    return jsonify({"success": True, "title": title})


@app.route("/api/chats/<int:chat_id>/pin", methods=["PUT"])
def toggle_pin(chat_id):
    with db() as conn:
        row = conn.execute("SELECT pinned FROM chats WHERE id = ?", (chat_id,)).fetchone()
        if not row:
            return jsonify({"error": "Chat tidak ditemukan"}), 404
        new_val = 0 if row["pinned"] else 1
        conn.execute("UPDATE chats SET pinned = ? WHERE id = ?", (new_val, chat_id))
    return jsonify({"success": True, "pinned": new_val})


@app.route("/api/chats/<int:chat_id>", methods=["DELETE"])
def delete_chat(chat_id):
    with db() as conn:
        files = [r["file_path"] for r in conn.execute(
            "SELECT file_path FROM messages WHERE chat_id = ? AND file_path IS NOT NULL", (chat_id,))]
        conn.execute("DELETE FROM messages WHERE chat_id = ?", (chat_id,))
        conn.execute("DELETE FROM chats WHERE id = ?", (chat_id,))
    for name in files:
        try:
            os.remove(os.path.join(UPLOAD_FOLDER, name))
        except OSError:
            pass
    return jsonify({"success": True})


@app.route("/api/chats/<int:chat_id>/export")
def export_chat(chat_id):
    with db() as conn:
        chat = conn.execute("SELECT title FROM chats WHERE id = ?", (chat_id,)).fetchone()
        if not chat:
            abort(404)
        rows = conn.execute(
            "SELECT * FROM messages WHERE chat_id = ? ORDER BY id", (chat_id,)
        ).fetchall()
    parts = [f"# {chat['title']}\n"]
    for r in rows:
        who = "Anda" if r["sender"] == "user" else "Nexa"
        parts.append(f"## {who}\n")
        if r["file_path"]:
            parts.append(f"_Lampiran: {display_name(r['file_path'])}_\n")
        parts.append((r["message"] or "") + "\n")
    safe = secure_filename(chat["title"]) or "chat"
    return Response(
        "\n".join(parts),
        mimetype="text/markdown",
        headers={"Content-Disposition": f'attachment; filename="{safe}.md"'},
    )


# --------------------------------------------------------------------------
# Logika AI
# --------------------------------------------------------------------------
def parse_settings(raw):
    try:
        data = json.loads(raw) if raw else {}
    except json.JSONDecodeError:
        data = {}
    model = data.get("model")
    persona = data.get("persona")
    tools = data.get("tools")
    try:
        temperature = max(0.0, min(2.0, float(data.get("temperature", 0.7))))
    except (TypeError, ValueError):
        temperature = 0.7
    return {
        "model": model if model in AVAILABLE_MODELS else DEFAULT_MODEL,
        "persona": persona if persona in PERSONAS else "general",
        "tools": tools if tools in TOOL_MODES else "none",
        "temperature": temperature,
        "instructions": (data.get("instructions") or "").strip()[:2000],
    }


def build_config(s):
    system = f"{BASE_PROMPT}\n\n{PERSONAS[s['persona']]['prompt']}"
    if s["instructions"]:
        system += f"\n\nInstruksi tambahan dari pengguna:\n{s['instructions']}"
    kwargs = {"system_instruction": system, "temperature": s["temperature"]}
    if s["tools"] == "search":
        kwargs["tools"] = [types.Tool(google_search=types.GoogleSearch())]
    elif s["tools"] == "code":
        kwargs["tools"] = [types.Tool(code_execution=types.ToolCodeExecution())]
    elif s["tools"] == "local":
        kwargs["tools"] = LOCAL_TOOLS
    return types.GenerateContentConfig(**kwargs)


def file_to_part(stored_name):
    path = os.path.join(UPLOAD_FOLDER, stored_name)
    if not os.path.isfile(path):
        return None
    mime = mimetypes.guess_type(path)[0] or "application/octet-stream"
    with open(path, "rb") as fh:
        data = fh.read()
    if mime.startswith("text/") or mime in ("application/json", "application/xml"):
        text = data.decode("utf-8", errors="replace")
        return types.Part.from_text(
            text=f"[Isi file {display_name(stored_name)}]\n{text[:100_000]}")
    return types.Part.from_bytes(data=data, mime_type=mime)


def build_contents(rows):
    """Ubah riwayat DB menjadi daftar Content. File hanya dilampirkan pada pesan user terakhir."""
    rows = rows[-HISTORY_LIMIT:]
    last_user_idx = max((i for i, r in enumerate(rows) if r["sender"] == "user"), default=-1)
    contents = []
    for i, r in enumerate(rows):
        role = "user" if r["sender"] == "user" else "model"
        parts = []
        if r["file_path"] and role == "user":
            part = file_to_part(r["file_path"]) if i == last_user_idx else None
            if part:
                parts.append(part)
            else:
                parts.append(types.Part.from_text(
                    text=f"[Lampiran sebelumnya: {display_name(r['file_path'])}]"))
        text = (r["message"] or "").strip()
        if text:
            parts.append(types.Part.from_text(text=text))
        if parts:
            contents.append(types.Content(role=role, parts=parts))
    return contents


def event(kind, data=None, **extra):
    return json.dumps({"t": kind, "d": data, **extra}, ensure_ascii=False) + "\n"


def extract_sources(candidate):
    gm = getattr(candidate, "grounding_metadata", None)
    chunks = getattr(gm, "grounding_chunks", None) or []
    seen, out = set(), []
    for ch in chunks:
        web = getattr(ch, "web", None)
        if web and web.uri and web.uri not in seen:
            seen.add(web.uri)
            out.append({"title": web.title or web.uri, "uri": web.uri})
    return out


def generate_title(first_user_text, settings):
    try:
        resp = client.models.generate_content(
            model=settings["model"],
            contents=("Buat judul singkat (maksimal 5 kata, tanpa tanda kutip, tanpa titik) "
                      f"untuk percakapan yang diawali pesan ini:\n\n{first_user_text[:500]}"),
        )
        title = (resp.text or "").strip().strip('"\'`').splitlines()[0][:60]
        return title or None
    except Exception as exc:  # noqa: BLE001
        log.warning("Gagal membuat judul: %s", exc)
        return None


def stream_reply(chat_id, settings):
    """Generator NDJSON: mengalirkan jawaban model dan menyimpannya ke DB."""
    if client is None:
        yield event("error", "GEMINI_API_KEY belum diatur. Set environment variable lalu jalankan ulang server.")
        return

    with db() as conn:
        rows = conn.execute("SELECT * FROM messages WHERE chat_id = ? ORDER BY id", (chat_id,)).fetchall()
        title_row = conn.execute("SELECT title FROM chats WHERE id = ?", (chat_id,)).fetchone()

    contents = build_contents(rows)
    if not contents:
        yield event("error", "Tidak ada pesan untuk diproses.")
        return

    text_buf, sources, error = [], [], None
    try:
        stream = client.models.generate_content_stream(
            model=settings["model"], contents=contents, config=build_config(settings))
        for chunk in stream:
            if not chunk.candidates:
                continue
            cand = chunk.candidates[0]
            if cand.content and cand.content.parts:
                for part in cand.content.parts:
                    piece = ""
                    if getattr(part, "thought", False):
                        continue
                    if part.text:
                        piece = part.text
                    elif getattr(part, "executable_code", None) and part.executable_code.code:
                        piece = f"\n```python\n{part.executable_code.code}\n```\n"
                    elif getattr(part, "code_execution_result", None) and part.code_execution_result.output:
                        piece = f"\n**Output:**\n```\n{part.code_execution_result.output}\n```\n"
                    if piece:
                        text_buf.append(piece)
                        yield event("chunk", piece)
            found = extract_sources(cand)
            if found:
                sources = found
    except GeneratorExit:
        # Klien menekan Stop / menutup koneksi: simpan jawaban parsial.
        _save_ai_message(chat_id, "".join(text_buf), sources, partial=True)
        raise
    except Exception as exc:  # noqa: BLE001
        log.exception("Error Gemini")
        error = f"Error dari Gemini API: {exc}"

    full = "".join(text_buf)
    if error and not full:
        full = f"⚠️ {error}"
        yield event("error", error)
    msg_id = _save_ai_message(chat_id, full, sources)
    if sources:
        yield event("sources", sources)

    # Judul otomatis untuk percakapan baru
    if not error and title_row and title_row["title"] == DEFAULT_TITLE:
        first_user = next((r["message"] for r in rows if r["sender"] == "user" and r["message"]), None)
        if first_user:
            title = generate_title(first_user, settings) or first_user[:30]
            with db() as conn:
                conn.execute("UPDATE chats SET title = ? WHERE id = ?", (title, chat_id))
            yield event("title", title)

    yield event("done", msg_id)


def _save_ai_message(chat_id, text, sources, partial=False):
    if partial and not text.strip():
        return None
    with db() as conn:
        cur = conn.execute(
            "INSERT INTO messages (chat_id, sender, message, file_path, sources, created_at) "
            "VALUES (?, 'ai', ?, NULL, ?, ?)",
            (chat_id, text, json.dumps(sources) if sources else None, now_iso()),
        )
        return cur.lastrowid


def ndjson_response(generator):
    return Response(
        stream_with_context(generator),
        mimetype="application/x-ndjson",
        headers={"Cache-Control": "no-cache", "X-Accel-Buffering": "no"},
    )


# --------------------------------------------------------------------------
# Kirim pesan & regenerate
# --------------------------------------------------------------------------
@app.route("/api/chats/<int:chat_id>", methods=["POST"])
def send_message(chat_id):
    user_message = (request.form.get("message") or "").strip()
    settings = parse_settings(request.form.get("settings"))
    upload = request.files.get("file")

    with db() as conn:
        if not conn.execute("SELECT 1 FROM chats WHERE id = ?", (chat_id,)).fetchone():
            return jsonify({"error": "Chat tidak ditemukan"}), 404

    stored = None
    if upload and upload.filename:
        safe = secure_filename(upload.filename) or "file"
        stored = f"{uuid.uuid4().hex[:8]}_{safe}"
        upload.save(os.path.join(UPLOAD_FOLDER, stored))

    if not user_message and not stored:
        return jsonify({"error": "Pesan kosong"}), 400

    with db() as conn:
        conn.execute(
            "INSERT INTO messages (chat_id, sender, message, file_path, created_at) VALUES (?, 'user', ?, ?, ?)",
            (chat_id, user_message, stored, now_iso()),
        )
    return ndjson_response(stream_reply(chat_id, settings))


@app.route("/api/chats/<int:chat_id>/regenerate", methods=["POST"])
def regenerate(chat_id):
    settings = parse_settings(json.dumps((request.get_json(silent=True) or {}).get("settings", {})))
    with db() as conn:
        last_user = conn.execute(
            "SELECT id FROM messages WHERE chat_id = ? AND sender = 'user' ORDER BY id DESC LIMIT 1",
            (chat_id,),
        ).fetchone()
        if not last_user:
            return jsonify({"error": "Belum ada pesan untuk diulang"}), 400
        conn.execute("DELETE FROM messages WHERE chat_id = ? AND id > ?", (chat_id, last_user["id"]))
    return ndjson_response(stream_reply(chat_id, settings))


@app.errorhandler(413)
def too_large(_):
    return jsonify({"error": f"File terlalu besar (maksimal {MAX_UPLOAD_MB} MB)"}), 413


if __name__ == "__main__":
    if not GEMINI_API_KEY:
        log.warning("GEMINI_API_KEY belum diatur - chat AI tidak akan berfungsi.")
    app.run(debug=True, port=5000, threaded=True)
