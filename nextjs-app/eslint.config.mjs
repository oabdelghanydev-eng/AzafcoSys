import { defineConfig, globalIgnores } from "eslint/config";
import nextVitals from "eslint-config-next/core-web-vitals";
import nextTs from "eslint-config-next/typescript";

const eslintConfig = defineConfig([
  ...nextVitals,
  ...nextTs,
  // Override default ignores of eslint-config-next.
  globalIgnores([
    // Default ignores of eslint-config-next:
    ".next/**",
    "out/**",
    "build/**",
    "next-env.d.ts",
  ]),
  // Custom rule overrides - apply to all project files
  {
    files: ["src/**/*.{ts,tsx,js,jsx}"],
    rules: {
      // Turn off React Compiler rules completely (form pre-filling is a valid pattern)
      "react-compiler/react-compiler": "off",
      // Turn off React Hooks rules that are too strict for valid patterns
      "react-hooks/immutability": "off",
      "react-hooks/set-state-in-effect": "off",
      "react-hooks/refs": "off",
      // Allow underscore-prefixed variables to be unused
      "@typescript-eslint/no-unused-vars": [
        "warn",
        {
          argsIgnorePattern: "^_",
          varsIgnorePattern: "^_",
          caughtErrorsIgnorePattern: "^_",
        },
      ],
      // Allow unescaped apostrophes in JSX (common for English text)
      "react/no-unescaped-entities": "off",
    },
  },
]);

export default eslintConfig;
