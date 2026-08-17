import { defineConfig } from "vitest/config";
import react from "@vitejs/plugin-react";
import tailwindcss from "@tailwindcss/vite";
import tsconfigPaths from "vite-tsconfig-paths";

// Deliberately separate from vite.config.ts: mixing `@tanstack/router-plugin` (which
// resolves against the top-level `vite` package) into a config built with `defineConfig`
// from `vitest/config` (which resolves plugin types against vitest's own nested `vite`
// dependency) produces a duplicate-package type conflict. Test config doesn't need the
// router plugin, so it's simplest to just not import it here.
export default defineConfig({
  plugins: [react(), tailwindcss(), tsconfigPaths()],
  test: {
    environment: "jsdom",
    globals: true,
    setupFiles: ["./src/test/setup.ts"],
    css: true,
    // Run files one at a time. Several suites drive real timers through
    // userEvent, and once the suite grew past a handful of files they began
    // timing out purely from competing for CPU rather than from any defect.
    // Determinism is worth more here than the few seconds parallelism saves.
    fileParallelism: false,
  },
});
