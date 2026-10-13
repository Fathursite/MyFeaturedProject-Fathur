"use client";

import { useEffect, useRef, useState } from "react";
import { Bot, Send } from "lucide-react";
import type { Plan, Profile } from "@/lib/health";

type Msg = { role: "user" | "ai"; text: string };

const SUGGESTIONS = [
  "Ganti menu makan siang dengan bahan lain",
  "Apakah olahraga ini aman untuk lutut?",
  "Bagaimana kalau saya tidak sempat sarapan?",
];

export default function AIChatAssistant({ profile, plan }: { profile: Profile; plan: Plan }) {
  const [messages, setMessages] = useState<Msg[]>([
    { role: "ai", text: "Halo! Saya siap membantu menyesuaikan rencana Anda. Tanyakan apa saja soal menu, olahraga, atau kebiasaan harian." },
  ]);
  const [input, setInput] = useState("");
  const [typing, setTyping] = useState(false);
  const endRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    endRef.current?.scrollIntoView({ behavior: "smooth", block: "nearest" });
  }, [messages, typing]);

  async function send(text: string) {
    const message = text.trim();
    if (!message || typing) return;
    const history = messages.slice(1);
    setMessages((m) => [...m, { role: "user", text: message }]);
    setInput("");
    setTyping(true);
    try {
      const res = await fetch("/api/chat", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ message, history, profile, plan }),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error);
      setMessages((m) => [...m, { role: "ai", text: data.reply }]);
    } catch (e) {
      setMessages((m) => [...m, { role: "ai", text: (e as Error).message || "Terjadi kesalahan. Coba kirim ulang pertanyaan Anda." }]);
    } finally {
      setTyping(false);
    }
  }

  return (
    <section className="overflow-hidden rounded-3xl border border-slate-100 bg-white shadow-xl shadow-slate-200/50">
      <header className="flex items-center gap-3 bg-gradient-to-r from-[#064E3B] to-[#059669] px-6 py-4 text-white">
        <span className="flex h-10 w-10 items-center justify-center rounded-full bg-white/15 backdrop-blur"><Bot className="h-5 w-5" /></span>
        <div>
          <h3 className="font-bold">Asisten Kesehatan AI</h3>
          <p className="text-xs text-emerald-100">Jawaban disesuaikan dengan profil dan rencana Anda</p>
        </div>
      </header>

      <div className="h-96 space-y-4 overflow-y-auto bg-[#F8FAFC] p-5" aria-live="polite">
        {messages.map((m, i) => (
          <div key={i} className={`flex ${m.role === "user" ? "justify-end" : "justify-start"}`}>
            <p
              className={`max-w-[85%] whitespace-pre-wrap rounded-2xl px-4 py-3 text-sm leading-relaxed ${
                m.role === "user" ? "rounded-br-sm bg-[#059669] text-white" : "rounded-bl-sm bg-white text-slate-700 shadow-sm ring-1 ring-slate-100"
              }`}
            >
              {m.text}
            </p>
          </div>
        ))}
        {typing && (
          <div className="flex justify-start">
            <div className="flex gap-1 rounded-2xl rounded-bl-sm bg-white px-4 py-4 shadow-sm ring-1 ring-slate-100" aria-label="AI sedang mengetik">
              {[0, 150, 300].map((d) => (
                <span key={d} className="h-2 w-2 animate-bounce rounded-full bg-[#10B981]" style={{ animationDelay: `${d}ms` }} />
              ))}
            </div>
          </div>
        )}
        <div ref={endRef} />
      </div>

      {messages.length === 1 && (
        <div className="flex flex-wrap gap-2 border-t border-slate-100 px-5 pt-4">
          {SUGGESTIONS.map((s) => (
            <button key={s} onClick={() => send(s)} className="rounded-full border border-emerald-200 px-3 py-1.5 text-xs font-medium text-[#064E3B] transition hover:bg-emerald-50">
              {s}
            </button>
          ))}
        </div>
      )}

      <form onSubmit={(e) => { e.preventDefault(); send(input); }} className="flex gap-3 p-5">
        <input
          value={input}
          onChange={(e) => setInput(e.target.value)}
          placeholder="Tulis pertanyaan Anda…"
          className="flex-1 rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none transition focus:border-[#059669] focus:ring-4 focus:ring-emerald-100"
        />
        <button
          type="submit"
          disabled={typing || !input.trim()}
          aria-label="Kirim pesan"
          className="flex h-12 w-12 items-center justify-center rounded-xl bg-[#059669] text-white transition hover:bg-[#047857] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-emerald-200 disabled:opacity-50"
        >
          <Send className="h-5 w-5" />
        </button>
      </form>
    </section>
  );
}
