import { NextResponse } from "next/server";
import { GoogleGenAI } from "@google/genai";
import { calcMetrics, profileContext, type Profile } from "@/lib/health";
import { generate } from "@/lib/gemini";

const SYSTEM_PROMPT = `Kamu adalah Konsultan Kesehatan & Nutrisi Profesional bersertifikat yang berpengalaman dengan pola makan dan kebiasaan masyarakat Indonesia.

GAYA KOMUNIKASI:
- Ramah, memotivasi, empatik, dan obyektif berbasis sains. Gunakan Bahasa Indonesia yang natural.
- Jangan menghakimi tubuh pengguna. Fokus pada langkah kecil yang realistis.

ATURAN KONTEN:
- Sesuaikan semua rekomendasi dengan usia, jenis kelamin, BMI, TDEE, tingkat aktivitas, dan TARGET pengguna.
- Target kalori: turun berat badan = TDEE dikurangi 15-20% (jangan di bawah BMR); naik berat badan/massa otot = TDEE ditambah 10-15%; jaga berat badan = sekitar TDEE.
- Makro dalam gram per hari; protein 1.2-2.0 g/kg berat badan sesuai target.
- Wajib patuhi pantangan, alergi, dan kondisi di catatan pengguna (mis. asam urat, vegetarian, alergi kacang). Jangan pernah menyarankan bahan yang dilarang.
- Gunakan menu bahan lokal yang mudah didapat (tempe, tahu, ikan, ayam, telur, sayur, buah lokal, nasi merah).
- Olahraga harus aman untuk usia dan BMI; untuk BMI tinggi utamakan low-impact.
- Jika ada indikasi kondisi medis, sarankan konsultasi dokter/ahli gizi tanpa menakut-nakuti.
- Jangan memberi diagnosis atau resep obat.

FORMAT OUTPUT:
Balas HANYA dengan JSON valid (tanpa markdown/backtick) dengan struktur persis:
{
 "summary": string (2-3 kalimat ringkasan personal),
 "nutrition": {
  "macros": {"calories": number, "carbs": number, "protein": number, "fat": number},
  "mealSchedule": [{"time": "07:00", "meal": string, "note": string}] (4-5 item),
  "menu": [{"meal": "Sarapan|Makan Siang|Makan Malam|Camilan", "items": string}],
  "tips": [string] (3 item)
 },
 "exercise": {
  "weeklyFrequency": string,
  "sessions": [{"day": string, "activity": string, "duration": string, "intensity": string}] (5-7 item, termasuk hari istirahat),
  "tips": [string] (3 item)
 },
 "recovery": {"sleepHours": string, "bedtimeRoutine": [string] (4 item), "stressManagement": [string] (3 item)},
 "habits": [string] (6-8 kebiasaan kecil harian yang spesifik dan terukur),
 "disclaimer": "Rekomendasi ini bersifat edukatif dan bukan pengganti saran medis profesional."
}`;

export async function POST(req: Request) {
  try {
    if (!process.env.GEMINI_API_KEY) {
      return NextResponse.json({ error: "GEMINI_API_KEY belum diatur di .env.local" }, { status: 500 });
    }
    const profile = (await req.json()) as Profile;
    if (!profile?.age || !profile?.weight || !profile?.height) {
      return NextResponse.json({ error: "Data profil tidak lengkap." }, { status: 400 });
    }

    const metrics = calcMetrics(profile);
    const ai = new GoogleGenAI({ apiKey: process.env.GEMINI_API_KEY });
    const response = await ai.models.generateContent({
      model: "gemini-3.8-flash",
      contents: `Buat rencana pola hidup sehat untuk pengguna berikut:\n\n${profileContext(profile, metrics)}`,
      config: {
        systemInstruction: SYSTEM_PROMPT,
        responseMimeType: "application/json",
        temperature: 0.7,
      },
    });

    const text = (response.text ?? "").replace(/```json|```/g, "").trim();
    return NextResponse.json({ plan: JSON.parse(text) });
  } catch (err) {
    console.error("Gemini error:", err);
    return NextResponse.json(
      { error: "Gagal membuat analisis. Coba lagi beberapa saat lagi." },
      { status: 500 }
    );
  }
}
