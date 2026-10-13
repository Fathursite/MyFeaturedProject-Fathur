import { GoogleGenAI, type GenerateContentParameters } from "@google/genai";

const MODELS = [
  process.env.GEMINI_MODEL ?? "gemini-3.8-flash",
  process.env.GEMINI_FALLBACK_MODEL ?? "gemini-flash-latest",
];

const sleep = (ms: number) => new Promise((r) => setTimeout(r, ms));

export async function generate(params: Omit<GenerateContentParameters, "model">) {
  const ai = new GoogleGenAI({ apiKey: process.env.GEMINI_API_KEY });
  let lastErr: unknown;

  for (const model of MODELS) {
    for (let attempt = 0; attempt < 3; attempt++) {
      try {
        return await ai.models.generateContent({ model, ...params });
      } catch (err) {
        lastErr = err;
        const status = (err as { status?: number }).status;
        if (status === 503 || status === 429) {
          await sleep(1000 * 2 ** attempt); // tunggu 1s, 2s, 4s lalu coba lagi
          continue;
        }
        if (status === 404) break; // model tidak ada, lanjut ke model cadangan
        throw err;
      }
    }
  }
  throw lastErr;
}