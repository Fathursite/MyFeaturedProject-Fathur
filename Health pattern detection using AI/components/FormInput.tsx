"use client";

import { useState } from "react";
import { Activity, CalendarDays, Loader2, NotebookPen, Ruler, Scale, Sparkles, Target } from "lucide-react";
import { ACTIVITY, GOALS, type ActivityKey, type Gender, type GoalKey, type Profile } from "@/lib/health";

type Props = { onSubmit: (p: Profile) => void; loading: boolean };

const field =
  "w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#059669] focus:ring-4 focus:ring-emerald-100";

function Label({ icon: Icon, text }: { icon: typeof Scale; text: string }) {
  return (
    <span className="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700">
      <Icon className="h-4 w-4 text-[#059669]" aria-hidden /> {text}
    </span>
  );
}

export default function FormInput({ onSubmit, loading }: Props) {
  const [age, setAge] = useState("");
  const [gender, setGender] = useState<Gender>("pria");
  const [weight, setWeight] = useState("");
  const [height, setHeight] = useState("");
  const [activity, setActivity] = useState<ActivityKey>("light");
  const [goal, setGoal] = useState<GoalKey>("maintain");
  const [notes, setNotes] = useState("");
  const [errors, setErrors] = useState<Record<string, string>>({});

  function validate() {
    const e: Record<string, string> = {};
    const a = Number(age), w = Number(weight), h = Number(height);
    if (!a || a < 10 || a > 100) e.age = "Masukkan usia 10–100 tahun.";
    if (!w || w < 20 || w > 300) e.weight = "Masukkan berat 20–300 kg.";
    if (!h || h < 100 || h > 250) e.height = "Masukkan tinggi 100–250 cm.";
    setErrors(e);
    return Object.keys(e).length === 0;
  }

  function handleSubmit(ev: React.FormEvent) {
    ev.preventDefault();
    if (loading || !validate()) return;
    onSubmit({ age: Number(age), gender, weight: Number(weight), height: Number(height), activity, goal, notes });
  }

  const err = (k: string) => errors[k] && <p className="mt-1.5 text-xs text-rose-600">{errors[k]}</p>;

  return (
    <form
      onSubmit={handleSubmit}
      noValidate
      className="rounded-3xl border border-white/60 bg-white p-6 shadow-xl shadow-slate-200/50 sm:p-10"
    >
      <h2 className="text-2xl font-bold text-[#0F172A]">Data diri Anda</h2>
      <p className="mt-1 text-sm text-slate-500">Semakin akurat datanya, semakin tepat rencananya.</p>

      <div className="mt-8 grid gap-6 sm:grid-cols-2">
        <label className="block">
          <Label icon={CalendarDays} text="Usia (tahun)" />
          <input type="number" inputMode="numeric" value={age} onChange={(e) => setAge(e.target.value)} placeholder="25" className={field} />
          {err("age")}
        </label>

        <fieldset>
          <legend><Label icon={Activity} text="Jenis kelamin" /></legend>
          <div className="grid grid-cols-2 gap-3">
            {(["pria", "wanita"] as Gender[]).map((g) => (
              <label
                key={g}
                className={`cursor-pointer rounded-xl border px-4 py-3 text-center font-medium transition has-[:focus-visible]:ring-4 has-[:focus-visible]:ring-emerald-100 ${
                  gender === g ? "border-[#059669] bg-emerald-50 text-[#064E3B]" : "border-slate-200 text-slate-600 hover:border-slate-300"
                }`}
              >
                <input type="radio" name="gender" value={g} checked={gender === g} onChange={() => setGender(g)} className="sr-only" />
                {g === "pria" ? "Pria" : "Wanita"}
              </label>
            ))}
          </div>
        </fieldset>

        <label className="block">
          <Label icon={Scale} text="Berat badan (kg)" />
          <input type="number" inputMode="decimal" value={weight} onChange={(e) => setWeight(e.target.value)} placeholder="65" className={field} />
          {err("weight")}
        </label>

        <label className="block">
          <Label icon={Ruler} text="Tinggi badan (cm)" />
          <input type="number" inputMode="decimal" value={height} onChange={(e) => setHeight(e.target.value)} placeholder="170" className={field} />
          {err("height")}
        </label>

        <label className="block">
          <Label icon={Activity} text="Aktivitas harian" />
          <select value={activity} onChange={(e) => setActivity(e.target.value as ActivityKey)} className={field}>
            {Object.entries(ACTIVITY).map(([k, v]) => (
              <option key={k} value={k}>{v.label}</option>
            ))}
          </select>
        </label>

        <label className="block">
          <Label icon={Target} text="Target utama" />
          <select value={goal} onChange={(e) => setGoal(e.target.value as GoalKey)} className={field}>
            {Object.entries(GOALS).map(([k, v]) => (
              <option key={k} value={k}>{v}</option>
            ))}
          </select>
        </label>

        <label className="block sm:col-span-2">
          <Label icon={NotebookPen} text="Pantangan makanan & catatan khusus (opsional)" />
          <textarea
            rows={3}
            value={notes}
            onChange={(e) => setNotes(e.target.value)}
            placeholder="Contoh: vegetarian, alergi kacang, asam urat"
            className={`${field} resize-none`}
          />
        </label>
      </div>

      <button
        type="submit"
        disabled={loading}
        className="mt-8 flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-[#064E3B] to-[#059669] px-6 py-4 font-semibold text-white shadow-lg shadow-emerald-900/20 transition hover:brightness-110 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-emerald-200 disabled:cursor-wait disabled:opacity-80"
      >
        {loading ? (
          <><Loader2 className="h-5 w-5 animate-spin" /> Menyusun rencana Anda…</>
        ) : (
          <><Sparkles className="h-5 w-5" /> Proses &amp; Analisis AI</>
        )}
      </button>
    </form>
  );
}
