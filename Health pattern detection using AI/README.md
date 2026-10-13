# PolaSehat.id

1. Buat proyek: `npx create-next-app@14 polasehat --typescript --tailwind --app --eslint` (gunakan Tailwind v3), lalu salin semua file ini ke dalamnya (timpa file yang sama).
2. Install dependensi: `npm i @google/genai lucide-react`
3. Buat `.env.local` berisi: `GEMINI_API_KEY=isi_api_key_anda`
4. Pastikan alias `@/*` aktif di `tsconfig.json` (default dari create-next-app).
5. Jalankan: `npm run dev` lalu buka http://localhost:3000
