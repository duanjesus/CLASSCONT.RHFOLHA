/// <reference types="vite/client" />

interface ImportMetaEnv {
  /** URL do painel administrativo (Twig) do RH. */
  readonly VITE_ADMIN_URL?: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}
