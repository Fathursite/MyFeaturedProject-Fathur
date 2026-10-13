import { NextResponse } from "next/server";
import { generate } from "@/lib/gemini";
import { calcMetrics, profileContext, type Plan, type Profile } from "@/lib/health";

type ChatMessage = { role: "user" | "ai"; text: string };

export async function POST(req: Request) {
  try {
    if (!process.env.GEMINI_API_KEY) {
      return NextResponse.json({ error: "GEMINI_API_KEY belum diatur di .env.local" }, { status: 500 });
    }
    const { message, history, profile, plan } = (await req.json()) as {
      message: string;
      history: ChatMessage[];
      profile: Profile;
      plan: Plan;
    };
    if (!message?.trim()) {
      return NextResponse.json({ error: "Pesan kosong." }, { status: 400 });
    }

    const metrics = calcMetrics(profile);
    const systemInstruction = `Kamu adalah Asisten Kesehatan & Nutrisi profesional yang ramah, empatik, memotivasi, dan berbasis sains. Jawab dalam Bahasa Indonesia.

PROFIL PENGGUNA:
${profileContext(profile, metrics)}

RENCANA YANG SUDAH DIBUAT (ringkas):
${JSON.stringify({ nutrition: plan.nutrition, exercise: plan.exercise, recovery: plan.recovery, habits: plan.habits })}

ATURAN:
- Jawab ringkas (maksimal sekitar 150 kata), praktis, dan relevan dengan profil serta rencana di atas.
- Patuhi pantangan/alergi pengguna. Jika mengganti menu, pertahankan kalori dan makro yang kurang lebih setara.
- Gunakan teks biasa dengan daftar sederhana ("- "), tanpa tabel dan tanpa heading markdown.
- Jika pertanyaan menyangkut nyeri, cedera, penyakit, atau obat, beri info umum yang aman dan sarankan menemui dokter.
- Jangan mendiagnosis. Tolak sopan pertanyaan di luar topik kesehatan.`;

    const contents = [
      ...history.slice(-12).map((m) => ({
        role: m.role === "user" ? "user" : "model",
        parts: [{ text: m.text }],
      })),
      { role: "user", parts: [{ text: message }] },
    ];

    const response = await generate({
      contents,
      config: { systemInstruction, temperature: 0.6 },
    });

    return NextResponse.json({ reply: response.text ?? "Maaf, saya belum bisa menjawab itu." });
  } catch (err) {
    console.error("Chat error:", err);
    return NextResponse.json({ error: "Gagal memproses pesan. Coba lagi." }, { status: 500 });
  }
}