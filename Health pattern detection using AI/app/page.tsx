"use client";

import { useRef, useState } from "react";
import { AlertCircle, ArrowDown, Zap } from "lucide-react";
import FormInput from "@/components/FormInput";
import HealthDashboard from "@/components/HealthDashboard";
import AIChatAssistant from "@/components/AIChatAssistant";
import { calcMetrics, type Metrics, type Plan, type Profile } from "@/lib/health";

export default function Home() {
  const formRef = useRef<HTMLElement>(null);
  const resultRef = useRef<HTMLElement>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");
  const [profile, setProfile] = useState<Profile | null>(null);
  const [metrics, setMetrics] = useState<Metrics | null>(null);
  const [plan, setPlan] = useState<Plan | null>(null);

  async function analyze(p: Profile) {
    setLoading(true);
    setError("");
    const m = calcMetrics(p);
    try {
      const res = await fetch("/api/gemini", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(p),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || "Gagal membuat analisis.");
      setProfile(p);
      setMetrics(m);
      setPlan(data.plan);
      setTimeout(() => resultRef.current?.scrollIntoView({ behavior: "smooth" }), 100);
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setLoading(false);
    }
  }

  return (
    <main>
      <section className="relative overflow-hidden bg-gradient-to-b from-[#0F172A] via-[#064E3B] to-[#064E3B] text-white">
        <div className="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-[#10B981]/20 blur-3xl" />
        <nav className="relative mx-auto flex max-w-6xl items-center justify-between px-6 py-6">
          <a href="#" className="flex items-center gap-2 text-lg font-bold">
            <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#10B981] text-[#0F172A]"><Zap className="h-5 w-5" fill="currentColor" /></span>
            PolaSehat.id
          </a>
          <span className="rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs backdrop-blur">Didukung Google Gemini</span>
        </nav>
        <div className="relative mx-auto max-w-4xl px-6 pb-28 pt-16 text-center sm:pt-24">
          <h1 className="text-4xl font-extrabold leading-tight tracking-tight sm:text-6xl">
            Panduan Pola Hidup Sehat Personalisasi Berbasis AI
          </h1>
          <p className="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-emerald-100/90">
            Isi data tubuh dan target Anda. Dalam hitungan detik, dapatkan rencana makan, olahraga, tidur, dan kebiasaan harian yang disusun khusus untuk Anda.
          </p>
          <button
            onClick={() => formRef.current?.scrollIntoView({ behavior: "smooth" })}
            className="group mt-10 inline-flex items-center gap-2 rounded-full bg-[#10B981] px-8 py-4 font-semibold text-[#0F172A] shadow-lg shadow-emerald-950/30 transition hover:bg-emerald-300 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-emerald-200"
          >
            Mulai Analisis Kesehatan Saya
            <ArrowDown className="h-5 w-5 transition group-hover:translate-y-0.5" />
          </button>
        </div>
      </section>

      <section ref={formRef} className="relative z-10 mx-auto -mt-16 max-w-3xl scroll-mt-6 px-6">
        <FormInput onSubmit={analyze} loading={loading} />
        {error && (
          <div role="alert" className="mt-4 flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
            <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" /> {error}
          </div>
        )}
      </section>

      {profile && metrics && plan && (
        <section ref={resultRef} className="mx-auto max-w-6xl scroll-mt-6 space-y-10 px-6 pb-24 pt-20">
          <h2 className="text-3xl font-extrabold text-[#0F172A]">Hasil analisis &amp; rencana aksi Anda</h2>
          <HealthDashboard profile={profile} metrics={metrics} plan={plan} />
          <div className="mx-auto max-w-3xl">
            <AIChatAssistant profile={profile} plan={plan} />
          </div>
        </section>
      )}

      <footer className="border-t border-slate-200 py-8 text-center text-sm text-slate-500">
        © {new Date().getFullYear()} PolaSehat.id · Rekomendasi ini bersifat edukatif dan bukan pengganti saran medis profesional.
      </footer>
    </main>
  );
}
