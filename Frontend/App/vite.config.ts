import path from "node:path";
import tailwindcss from "@tailwindcss/vite";
import vue from "@vitejs/plugin-vue";
import { defineConfig } from "vite";

export default defineConfig({
  plugins: [vue(), tailwindcss()],
  resolve: {
    alias: {
      "@": path.resolve(__dirname, "./src"),
    },
  },
  base: "./",
  build: {
    outDir: "../Components/apichan/dist/",
    emptyOutDir: true,
    rollupOptions: {
      input: [
        path.resolve(__dirname, "admin.html"),
        path.resolve(__dirname, "user.html"),
        path.resolve(__dirname, "remote.html"),
      ],
    },
  },
});
