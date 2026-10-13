"use client";

import { useState } from "react";
import { Apple, Check, Droplets, Dumbbell, Flame, ListChecks, Moon } from "lucide-react";
import type { Metrics, Plan, Profile } from "@/lib/health";
import { GOALS } from "@/lib/health";

const STATUS = {
  Underweight: { color: "text-amber-600", bg: "bg-amber-50", ring: "ring-amber-200", label: "Berat badan kurang" },
  Normal: { color: "text-emerald-600", bg: "bg-emerald-50", ring: "ring-emerald-200", label: "Berat badan ideal" },
  Overweight: { color: "text-amber-600", bg: "bg-amber-50", ring: "ring-amber-200", label: "Berat badan berlebih" },
  Obesity: { color: "text-rose-600", bg: "bg-rose-50", ring: "ring-rose-200", label: "Obesitas" },
} as const;

const TABS = [
  { id: "nutrition", label: "Pola Makan", icon: Apple },
  { id: "exercise", label: "Olahraga", icon: Dumbbell },
  { id: "recovery", label: "Istirahat", icon: Moon },
  { id: "habits", label: "Kebiasaan", icon: ListChecks },
] as const;

type TabId = (typeof TABS)[number]["id"];

const card = "rounded-2xl border border-slate-100 bg-white p-6 shadow-xl shadow-slate-200/50";

export default function HealthDashboard({ profile, metrics, plan }: { profile: Profile; metrics: Metrics; plan: Plan }) {
  const [tab, setTab] = useState<TabId>("nutrition");
  const [done, setDone] = useState<Set<number>>(new Set());
  const s = STATUS[metrics.category];
  const markerPos = Math.min(100, Math.max(0, ((metrics.bmi - 15) / 25) * 100));
  const stageNo = TABS.findIndex((t) => t.id === tab) + 1;

  const toggle = (i: number) =>
    setDone((prev) => {
      const n = new Set(prev);
      n.has(i) ? n.delete(i) : n.add(i);
      return n;
    });

  return (
    <div className="space-y-8">
      <div className="grid gap-5 md:grid-cols-3">
        <div className={card}>
          <p className="text-sm font-medium text-slate-500">Indeks Massa Tubuh (BMI)</p>
          <div className="mt-2 flex items-end gap-3">
            <span className="text-5xl font-extrabold text-[#0F172A]">{metrics.bmi}</span>
            <span className={`mb-1 rounded-full px-3 py-1 text-xs font-semibold ring-1 ${s.bg} ${s.color} ${s.ring}`}>
              {metrics.category}
            </span>
          </div>
          <div className="relative mt-5 h-2 rounded-full bg-gradient-to-r from-amber-300 via-emerald-400 via-40% to-rose-400">
            <span
              className="absolute top-1/2 h-4 w-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-[#0F172A] shadow"
              style={{ left: `${markerPos}%` }}
            />
          </div>
          <p className="mt-3 text-sm text-slate-500">{s.label}</p>
        </div>

        <div className={card}>
          <p className="flex items-center gap-2 text-sm font-medium text-slate-500"><Flame className="h-4 w-4 text-[#059669]" /> Kebutuhan kalori harian</p>
          <p className="mt-2 text-5xl font-extrabold text-[#0F172A]">{metrics.tdee.toLocaleString("id-ID")}<span className="ml-1 text-lg font-semibold text-slate-400">kkal</span></p>
          <p className="mt-3 text-sm text-slate-500">TDEE · BMR Anda {metrics.bmr.toLocaleString("id-ID")} kkal</p>
        </div>

        <div className={card}>
          <p className="flex items-center gap-2 text-sm font-medium text-slate-500"><Droplets className="h-4 w-4 text-[#059669]" /> Kebutuhan air putih</p>
          <p className="mt-2 text-5xl font-extrabold text-[#0F172A]">{metrics.water.toString().replace(".", ",")}<span className="ml-1 text-lg font-semibold text-slate-400">liter</span></p>
          <p className="mt-3 text-sm text-slate-500">≈ {Math.round((metrics.water * 1000) / 250)} gelas (250 ml) per hari</p>
        </div>
      </div>

      <div className="rounded-2xl bg-gradient-to-br from-[#064E3B] to-[#0F172A] p-6 text-emerald-50 sm:p-8">
        <p className="text-sm text-emerald-300">Target: {GOALS[profile.goal]}</p>
        <p className="mt-2 max-w-3xl text-lg leading-relaxed">{plan.summary}</p>
      </div>

      <div className={`${card} p-0 sm:p-0`}>
        <div role="tablist" aria-label="Tahapan rencana aksi" className="flex overflow-x-auto border-b border-slate-100 p-2">
          {TABS.map((t, i) => (
            <button
              key={t.id}
              role="tab"
              aria-selected={tab === t.id}
              onClick={() => setTab(t.id)}
              className={`flex flex-1 items-center justify-center gap-2 whitespace-nowrap rounded-xl px-4 py-3 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-emerald-100 ${
                tab === t.id ? "bg-emerald-50 text-[#064E3B]" : "text-slate-500 hover:text-slate-800"
              }`}
            >
              <t.icon className="h-4 w-4" aria-hidden /> Tahap {i + 1}: {t.label}
            </button>
          ))}
        </div>

        <div role="tabpanel" className="p-6 sm:p-8">
          <h3 className="mb-6 text-xl font-bold text-[#0F172A]">
            Tahap {stageNo}: {tab === "nutrition" && "Pola Makan & Nutrisi"}
            {tab === "exercise" && "Rutinitas Olahraga & Aktivitas Fisik"}
            {tab === "recovery" && "Istirahat, Tidur & Manajemen Stres"}
            {tab === "habits" && "Target Harian & Kebiasaan Sehat"}
          </h3>

          {tab === "nutrition" && (
            <div className="space-y-8">
              <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                {[
                  ["Kalori", plan.nutrition.macros.calories, "kkal"],
                  ["Karbohidrat", plan.nutrition.macros.carbs, "g"],
                  ["Protein", plan.nutrition.macros.protein, "g"],
                  ["Lemak", plan.nutrition.macros.fat, "g"],
                ].map(([l, v, u]) => (
                  <div key={l as string} className="rounded-xl bg-slate-50 p-4">
                    <p className="text-xs text-slate-500">{l}</p>
                    <p className="mt-1 text-2xl font-bold text-[#064E3B]">{v}<span className="ml-1 text-sm font-medium text-slate-400">{u}</span></p>
                  </div>
                ))}
              </div>
              <div>
                <h4 className="mb-3 font-semibold text-slate-800">Jadwal makan</h4>
                <ul className="divide-y divide-slate-100 rounded-xl border border-slate-100">
                  {plan.nutrition.mealSchedule.map((m, i) => (
                    <li key={i} className="flex gap-4 p-4">
                      <span className="w-14 shrink-0 font-semibold text-[#059669]">{m.time}</span>
                      <div><p className="font-medium text-slate-800">{m.meal}</p><p className="text-sm text-slate-500">{m.note}</p></div>
                    </li>
                  ))}
                </ul>
              </div>
              <div>
                <h4 className="mb-3 font-semibold text-slate-800">Contoh menu sehari</h4>
                <div className="grid gap-3 sm:grid-cols-2">
                  {plan.nutrition.menu.map((m, i) => (
                    <div key={i} className="rounded-xl border border-slate-100 p-4">
                      <p className="font-semibold text-[#064E3B]">{m.meal}</p>
                      <p className="mt-1 text-sm leading-relaxed text-slate-600">{m.items}</p>
                    </div>
                  ))}
                </div>
              </div>
              <Tips items={plan.nutrition.tips} />
            </div>
          )}

          {tab === "exercise" && (
            <div className="space-y-8">
              <p className="rounded-xl bg-emerald-50 p-4 font-medium text-[#064E3B]">Frekuensi: {plan.exercise.weeklyFrequency}</p>
              <ul className="space-y-3">
                {plan.exercise.sessions.map((s, i) => (
                  <li key={i} className="flex flex-col gap-1 rounded-xl border border-slate-100 p-4 sm:flex-row sm:items-center sm:gap-6">
                    <span className="w-24 shrink-0 font-semibold text-[#059669]">{s.day}</span>
                    <span className="flex-1 font-medium text-slate-800">{s.activity}</span>
                    <span className="text-sm text-slate-500">{s.duration} · {s.intensity}</span>
                  </li>
                ))}
              </ul>
              <Tips items={plan.exercise.tips} />
            </div>
          )}

          {tab === "recovery" && (
            <div className="space-y-8">
              <p className="rounded-xl bg-emerald-50 p-4 font-medium text-[#064E3B]">Durasi tidur ideal: {plan.recovery.sleepHours}</p>
              <div className="grid gap-6 sm:grid-cols-2">
                <div><h4 className="mb-3 font-semibold text-slate-800">Rutinitas sebelum tidur</h4><Bullets items={plan.recovery.bedtimeRoutine} /></div>
                <div><h4 className="mb-3 font-semibold text-slate-800">Manajemen stres & pemulihan</h4><Bullets items={plan.recovery.stressManagement} /></div>
              </div>
            </div>
          )}

          {tab === "habits" && (
            <div>
              <div className="mb-4 flex items-center justify-between text-sm">
                <span className="text-slate-500">{done.size} dari {plan.habits.length} selesai hari ini</span>
                <div className="h-2 w-40 overflow-hidden rounded-full bg-slate-100">
                  <div className="h-full bg-[#10B981] transition-all" style={{ width: `${(done.size / plan.habits.length) * 100}%` }} />
                </div>
              </div>
              <ul className="space-y-2">
                {plan.habits.map((h, i) => (
                  <li key={i}>
                    <button
                      onClick={() => toggle(i)}
                      aria-pressed={done.has(i)}
                      className="flex w-full items-center gap-3 rounded-xl border border-slate-100 p-4 text-left transition hover:border-emerald-200 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-emerald-100"
                    >
                      <span className={`flex h-6 w-6 shrink-0 items-center justify-center rounded-md border-2 transition ${done.has(i) ? "border-[#10B981] bg-[#10B981] text-white" : "border-slate-300"}`}>
                        {done.has(i) && <Check className="h-4 w-4" />}
                      </span>
                      <span className={done.has(i) ? "text-slate-400 line-through" : "text-slate-800"}>{h}</span>
                    </button>
                  </li>
                ))}
              </ul>
            </div>
          )}
        </div>
      </div>

      <p className="text-center text-xs text-slate-500">{plan.disclaimer}</p>
    </div>
  );
}

function Bullets({ items }: { items: string[] }) {
  return (
    <ul className="space-y-2">
      {items.map((t, i) => (
        <li key={i} className="flex gap-3 text-sm leading-relaxed text-slate-600">
          <span className="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-[#10B981]" /> {t}
        </li>
      ))}
    </ul>
  );
}

function Tips({ items }: { items: string[] }) {
  return (
    <div className="rounded-xl bg-slate-50 p-5">
      <h4 className="mb-3 font-semibold text-slate-800">Tips</h4>
      <Bullets items={items} />
    </div>
  );
}
