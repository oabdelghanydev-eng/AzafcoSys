import type { Metadata } from "next";
import { Cairo } from "next/font/google";
import "./globals.css";
import { AuthProvider } from "@/hooks/useAuth";
import { QueryProvider } from "@/lib/query-provider";
import { AppLayout } from "@/components/layout/Sidebar";

const cairo = Cairo({
  variable: "--font-cairo",
  subsets: ["arabic", "latin"],
});

export const metadata: Metadata = {
  title: "نظام إدارة المخزون",
  description: "نظام متكامل لإدارة المخزون والمبيعات والتحصيل",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="ar" dir="rtl">
      <body className={`${cairo.variable} font-sans antialiased`}>
        <QueryProvider>
          <AuthProvider>
            <AppLayout>
              {children}
            </AppLayout>
          </AuthProvider>
        </QueryProvider>
      </body>
    </html>
  );
}

