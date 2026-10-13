import type { Metadata } from "next";
import { Plus_Jakarta_Sans } from "next/font/google";
import "./globals.css";

const font = Plus_Jakarta_Sans({ subsets: ["latin"], variable: "--font-jakarta" });

export const metadata: Metadata = {
  title: "PolaSehat.id — Panduan Pola Hidup Sehat Berbasis AI",
  description: "Rencana makan, olahraga, tidur, dan kebiasaan sehat yang dipersonalisasi dengan Google Gemini.",
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="id">
      <body className={`${font.variable} font-sans antialiased`}>{children}</body>
    </html>
  );
}
