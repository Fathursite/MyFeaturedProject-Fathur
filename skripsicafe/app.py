from flask import Flask, render_template, request, jsonify, session, redirect, url_for
import mysql.connector
import os
import base64
import numpy as np
import face_recognition
from PIL import Image
import io
import shutil  
from datetime import datetime, timedelta
from functools import wraps # Ditambahkan untuk membuat decorator login

app = Flask(__name__)

# --- CONFIGURASI SECRET KEY FOR SESSION ---
# Wajib ditambahkan agar Flask bisa mengenali session login terenkripsi
app.secret_key = "skripsi_absensi_secret_key_123"

# --- KONFIGURASI DATABASE ---
def get_db_connection():
    return mysql.connector.connect(
        host="localhost",
        user="root",
        password="", 
        database="skripsi_absensi"
    )

UPLOAD_FOLDER = 'static/uploads/karyawan'
os.makedirs(UPLOAD_FOLDER, exist_ok=True)

# --- REVISI: DECORATOR UNTUK MENGUNCI HALAMAN WEB ---
def login_required(f):
    @wraps(f)
    def decorated_function(*args, **kwargs):
        # Jika session 'logged_in' tidak ada, paksa tendang balik ke halaman login
        if not session.get('logged_in'):
            return redirect(url_for('login_page'))
        return f(*args, **kwargs)
    return decorated_function

# --- REVISI: RUTE PROSES LOGIN & LOGOUT ---
@app.route('/login', methods=['GET', 'POST'])
def login_page():
    if request.method == 'POST':
        input_password = request.form.get('password')
        
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        cursor.execute("SELECT password_admin FROM settings LIMIT 1")
        setting = cursor.fetchone()
        cursor.close()
        conn.close()
        
        # Validasi kecocokan password dengan tabel settings
        if setting and input_password == setting['password_admin']:
            session['logged_in'] = True  # Set status login berhasil
            return redirect(url_for('index')) # Alihkan ke halaman utama
        else:
            return render_template('login.html', error="Password Admin Salah!")
            
    return render_template('login.html')

@app.route('/logout')
def logout():
    session.clear() # Hapus seluruh data session login
    return redirect(url_for('login_page'))


# --- PROTEKSI PADA RUTE HALAMAN UTAMA (TAMBAH @login_required) ---

@app.route('/')
@login_required  # Dikunci wajib login
def index():
    return render_template('tambah.html')

@app.route('/daftar_karyawan')
@login_required  # Dikunci wajib login
def daftar_karyawan():
    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)
    cursor.execute("SELECT * FROM karyawan")
    karyawan_list = cursor.fetchall()
    cursor.close()
    conn.close()
    return render_template('daftar.html', karyawan=karyawan_list)

@app.route('/scan')
@login_required  # Dikunci wajib login
def scan_page():
    return render_template('scan.html')

@app.route('/scan_keluar')
@login_required  # Dikunci wajib login
def scan_keluar_page():
    return render_template('scan_keluar.html')


# --- Kodingan Fungsi Helper, API JSON, dan Absen Di Bawah Ini Tetap Sama ---

def hitung_durasi(masuk, keluar):
    try:
        fmt = '%H:%M:%S'
        t_masuk = datetime.strptime(str(masuk), fmt)
        t_keluar = datetime.strptime(str(keluar), fmt)
        tdelta = t_keluar - t_masuk
        total_detik = int(tdelta.total_seconds())
        if total_detik < 0: return "Waktu tidak valid"
        jam = total_detik // 3600
        menit = (total_detik % 3600) // 60
        detik = total_detik % 60
        if jam == 0 and menit == 0: return f"{detik} detik"
        elif jam == 0: return f"{menit} menit {detik} detik"
        else: return f"{jam} jam {menit} menit"
    except Exception as e:
        print(f"Error hitung durasi: {e}")
        return "0 menit"

def format_datetime_for_json(rows):
    for row in rows:
        if 'jam_masuk' in row and isinstance(row['jam_masuk'], timedelta):
            row['jam_masuk'] = str(row['jam_masuk'])
        if 'jam_keluar' in row and isinstance(row['jam_keluar'], timedelta):
            row['jam_keluar'] = str(row['jam_keluar'])
        if 'tanggal' in row and hasattr(row['tanggal'], 'strftime'):
            row['tanggal'] = row['tanggal'].strftime("%Y-%m-%d")
    return rows

@app.route('/simpan_karyawan', methods=['POST'])
def simpan_karyawan():
    conn = None
    try:
        data = request.json
        no_karyawan = data['no_karyawan']
        nama = data['nama']
        posisi = data['posisi']
        photos = data['photos']
        user_folder = os.path.join(UPLOAD_FOLDER, no_karyawan)
        os.makedirs(user_folder, exist_ok=True)
        file_paths = {}
        for pos, base64_str in photos.items():
            img_bytes = base64.b64decode(base64_str.split(',')[1])
            with Image.open(io.BytesIO(img_bytes)) as img:
                rgb_img = img.convert('RGB')
                file_name = f"{pos.lower()}.jpg"
                file_path = os.path.join(user_folder, file_name)
                rgb_img.save(file_path, "JPEG")
                file_paths[pos] = file_path.replace("\\", "/")
        
        face_img = face_recognition.load_image_file(file_paths['DEPAN'])
        encodings = face_recognition.face_encodings(face_img)
        
        if len(encodings) > 0:
            encoding_str = ",".join([str(x) for x in encodings[0]])
        else:
            return jsonify({"status": "error", "message": "Wajah tidak terdeteksi!"})
            
        conn = get_db_connection()
        cursor = conn.cursor()
        sql = "INSERT INTO karyawan (no_karyawan, nama, posisi, foto_depan, foto_kiri, foto_kanan, foto_atas, foto_bawah, face_encoding) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)"
        values = (no_karyawan, nama, posisi, file_paths['DEPAN'], file_paths.get('KIRI'), file_paths.get('KANAN'), file_paths.get('ATAS'), file_paths.get('BAWAH'), encoding_str)
        cursor.execute(sql, values)
        conn.commit()
        return jsonify({"status": "success", "message": f"Berhasil mendaftarkan {nama}"})
    except Exception as e:
        return jsonify({"status": "error", "message": str(e)})
    finally:
        if conn: conn.close()

@app.route('/get_absen/<no_karyawan>')
def get_absen(no_karyawan):
    conn = None
    try:
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        cursor.execute("SELECT * FROM absensi WHERE no_karyawan = %s ORDER BY tanggal DESC, jam_masuk DESC", (no_karyawan,))
        history = cursor.fetchall()
        history = format_datetime_for_json(history)
        return jsonify(history)
    except Exception as e:
        return jsonify({"status": "error", "message": str(e)})
    finally:
        if conn: conn.close()

@app.route('/proses_absen', methods=['POST'])
def proses_absen():
    conn = None
    try:
        data = request.json
        img_base64 = data['image']
        tipe_scan = data.get('tipe', 'masuk')
        
        img_bytes = base64.b64decode(img_base64.split(',')[1])
        unknown_img_pil = Image.open(io.BytesIO(img_bytes)).convert('RGB')
        unknown_img = np.array(unknown_img_pil)
        
        face_locations = face_recognition.face_locations(unknown_img)
        face_encodings = face_recognition.face_encodings(unknown_img, face_locations)
        
        if not face_encodings:
            return jsonify({"status": "unknown", "message": "Wajah tidak terlihat!"})
            
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        cursor.execute("SELECT no_karyawan, nama, posisi, face_encoding FROM karyawan")
        karyawan_data = cursor.fetchall()
        
        for k in karyawan_data:
            known_encoding = np.fromstring(k['face_encoding'], sep=",")
            matches = face_recognition.compare_faces([known_encoding], face_encodings[0], tolerance=0.5)
            
            if matches[0]:
                now = datetime.now()
                tgl = now.strftime("%Y-%m-%d")
                jam_sekarang = now.strftime("%H:%M:%S")

                cursor.execute("SELECT * FROM absensi WHERE no_karyawan = %s AND tanggal = %s", (k['no_karyawan'], tgl))
                record_hari_ini = cursor.fetchone()

                if tipe_scan == 'masuk':
                    if record_hari_ini:
                        return jsonify({"status": "already", "message": f"{k['nama']} sudah absen masuk!"})
                    
                    sql = "INSERT INTO absensi (no_karyawan, nama, posisi, jam_masuk, tanggal, metode) VALUES (%s, %s, %s, %s, %s, %s)"
                    cursor.execute(sql, (k['no_karyawan'], k['nama'], k['posisi'], jam_sekarang, tgl, 'Face Recognition'))
                    conn.commit()
                    return jsonify({"status": "success", "nama": k['nama'], "pesan": "Berhasil absen masuk"})

                else: 
                    if not record_hari_ini:
                        return jsonify({"status": "error", "message": "Anda belum absen masuk hari ini!"})
                    if record_hari_ini['jam_keluar']:
                        return jsonify({"status": "already", "message": "Anda sudah absen keluar!"})
                    
                    durasi = hitung_durasi(record_hari_ini['jam_masuk'], jam_sekarang)
                    sql = "UPDATE absensi SET jam_keluar = %s, total_kerja = %s WHERE id = %s"
                    cursor.execute(sql, (jam_sekarang, durasi, record_hari_ini['id']))
                    conn.commit()
                    return jsonify({"status": "success", "nama": k['nama'], "pesan": f"Berhasil absen keluar. Total kerja: {durasi}"})

        return jsonify({"status": "unregistered", "message": "Karyawan tidak terdaftar"})
    except Exception as e:
        return jsonify({"status": "error", "message": str(e)})
    finally:
        if conn: conn.close()

@app.route('/get_recent_absen')
def get_recent_absen():
    conn = None
    try:
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        cursor.execute("SELECT * FROM absensi WHERE tanggal = CURDATE() ORDER BY jam_masuk DESC")
        res = cursor.fetchall()
        res = format_datetime_for_json(res)
        return jsonify(res)
    except Exception as e:
        print(f"Error Database Log: {str(e)}")
        return jsonify([])
    finally:
        if conn: conn.close()

@app.route('/absen_manual', methods=['POST'])
def absen_manual():
    conn = None
    try:
        data = request.json
        no_karyawan = data['no_karyawan']
        password = data['password']
        tipe_scan = data.get('tipe', 'masuk')

        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        cursor.execute("SELECT password_admin FROM settings LIMIT 1")
        setting = cursor.fetchone()
        
        if password != setting['password_admin']:
            return jsonify({"status": "error", "message": "Password Salah!"})
            
        cursor.execute("SELECT nama, posisi FROM karyawan WHERE no_karyawan = %s", (no_karyawan,))
        k = cursor.fetchone()
        if not k:
            return jsonify({"status": "error", "message": "Karyawan tidak ada!"})
            
        now = datetime.now()
        tgl = now.strftime("%Y-%m-%d")
        jam_sekarang = now.strftime("%H:%M:%S")

        cursor.execute("SELECT * FROM absensi WHERE no_karyawan = %s AND tanggal = %s", (no_karyawan, tgl))
        record_hari_ini = cursor.fetchone()

        if tipe_scan == 'masuk':
            if record_hari_ini: return jsonify({"status": "error", "message": "Sudah absen masuk"})
            cursor.execute("INSERT INTO absensi (no_karyawan, nama, posisi, jam_masuk, tanggal, metode) VALUES (%s, %s, %s, %s, %s, %s)",
                           (no_karyawan, k['nama'], k['posisi'], jam_sekarang, tgl, 'Manual'))
        else:
            if not record_hari_ini: return jsonify({"status": "error", "message": "Belum absen masuk"})
            durasi = hitung_durasi(record_hari_ini['jam_masuk'], jam_sekarang)
            cursor.execute("UPDATE absensi SET jam_keluar = %s, total_kerja = %s WHERE id = %s", 
                           (jam_sekarang, durasi, record_hari_ini['id']))
            
        conn.commit()
        return jsonify({"status": "success", "message": "Berhasil!"})
    except Exception as e:
        return jsonify({"status": "error", "message": str(e)})
    finally:
        if conn: conn.close()

@app.route('/hapus_karyawan/<no_karyawan>', methods=['DELETE'])
def hapus_karyawan(no_karyawan):
    conn = None
    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute("DELETE FROM absensi WHERE no_karyawan = %s", (no_karyawan,))
        cursor.execute("DELETE FROM karyawan WHERE no_karyawan = %s", (no_karyawan,))
        conn.commit()
        
        user_folder = os.path.join(UPLOAD_FOLDER, no_karyawan)
        if os.path.exists(user_folder):
            shutil.rmtree(user_folder)
            
        return jsonify({"status": "success", "message": "Karyawan berhasil dihapus"}), 200
    except Exception as e:
        return jsonify({"status": "error", "message": str(e)}), 500
    finally:
        if conn: conn.close()

@app.route('/hapus_absen/<int:id>', methods=['DELETE'])
def hapus_absen(id):
    conn = None
    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute("DELETE FROM absensi WHERE id = %s", (id,))
        conn.commit()
        return jsonify({"status": "success", "message": "Data absen berhasil dihapus"})
    except Exception as e:
        return jsonify({"status": "error", "message": str(e)})
    finally:
        if conn: conn.close()
        
if __name__ == '__main__':
    app.run(debug=True, host='0.0.0.0', port=5000)