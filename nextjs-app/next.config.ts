import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // Allow cross-origin requests from LAN devices during development
  allowedDevOrigins: [
    "192.168.1.3",
    "192.168.192.8",
    "10.147.20.8",
    "localhost",
    "127.0.0.1",
  ],
};

export default nextConfig;



