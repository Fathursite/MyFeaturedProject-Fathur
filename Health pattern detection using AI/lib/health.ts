export type Gender = "pria" | "wanita";
export type ActivityKey = "sedentary" | "light" | "moderate" | "very";
export type GoalKey = "lose" | "maintain" | "gain";

export type Profile = {
  age: number;
  gender: Gender;
  weight: number;
  height: number;
  activity: ActivityKey;
  goal: GoalKey;
  notes: string;
};

export const ACTIVITY: Record<ActivityKey, { label: string; factor: number }> = {
  sedentary: { label: "Jarang Bergerak", factor: 1.2 },
  light: { label: "Aktif Ringan", factor: 1.375 },
  moderate: { label: "Aktif Moderat", factor: 1.55 },
  very: { label: "Sangat Aktif", factor: 1.725 },
};

export const GOALS: Record<GoalKey, string> = {
  lose: "Menurunkan Berat Badan",
  maintain: "Menjaga Berat Badan & Kebugaran",
  gain: "Menaikkan Berat Badan / Massa Otot",
};

export type Metrics = {
  bmi: number;
  category: "Underweight" | "Normal" | "Overweight" | "Obesity";
  bmr: number;
  tdee: number;
  water: number;
};

export function calcMetrics(p: Profile): Metrics {
  const h = p.height / 100;
  const bmi = p.weight / (h * h);
  const category =
    bmi < 18.5 ? "Underweight" : bmi < 25 ? "Normal" : bmi < 30 ? "Overweight" : "Obesity";
  
  // Mifflin-St Jeor
  const bmr =
    10 * p.weight + 6.25 * p.height - 5 * p.age + (p.gender === "pria" ? 5 : -161);
  const tdee = bmr * ACTIVITY[p.activity].factor;
  const water = p.weight * 0.033 + (p.activity === "very" ? 0.7 : p.activity === "moderate" ? 0.4 : 0);
  
  return {
    bmi: Math.round(bmi * 10) / 10,
    category,
    bmr: Math.round(bmr),
    tdee: Math.round(tdee),
    water: Math.round(water * 10) / 10,
  };
}

export type Plan = {
  summary: string;
  nutrition: {
    macros: { calories: number; carbs: number; protein: number; fat: number };
    mealSchedule: { time: string; meal: string; note: string }[];
    menu: { meal: string; items: string }[];
    tips: string[];
  };
  exercise: {
    weeklyFrequency: string;
    sessions: { day: string; activity: string; duration: string; intensity: string }[];
    tips: string[];
  };
  recovery: {
    sleepHours: string;
    bedtimeRoutine: string[];
    stressManagement: string[];
  };
  habits: string[];
  disclaimer: string;
};

export function profileContext(p: Profile, m: Metrics): string {
  return `Usia: ${p.age} tahun
Jenis kelamin: ${p.gender === "pria" ? "Pria" : "Wanita"}
Berat: ${p.weight} kg | Tinggi: ${p.height} cm
BMI: ${m.bmi} (${m.category}) | BMR: ${m.bmr} kkal | TDEE: ${m.tdee} kkal | Air: ${m.water} L/hari
Aktivitas: ${ACTIVITY[p.activity].label}
Target: ${GOALS[p.goal]}
Catatan/pantangan: ${p.notes.trim() || "Tidak ada"}`;
}