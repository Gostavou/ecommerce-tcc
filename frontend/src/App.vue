<script setup>
import { computed, onMounted, ref } from "vue";
import { RouterLink, RouterView, useRoute, useRouter } from "vue-router";
import { Sun, Moon } from "lucide-vue-next";
import { useCarrinho } from "./stores/cart";
import { usuario, estaAutenticado, encerrarSessao } from "./stores/auth";

const route = useRoute();
const router = useRouter();
const { quantidadeTotal } = useCarrinho();

const temaEscuro = ref(false);
const isAdmin = computed(() => route.path.startsWith("/admin"));
const logado = computed(() => estaAutenticado());

onMounted(() => {
  temaEscuro.value = localStorage.getItem("tema-xadrez") === "escuro";
  aplicarTema();
});

function aplicarTema() {
  document.documentElement.dataset.theme = temaEscuro.value ? "dark" : "light";

  localStorage.setItem("tema-xadrez", temaEscuro.value ? "escuro" : "claro");
}

function alternarTema() {
  temaEscuro.value = !temaEscuro.value;
  aplicarTema();
}

function sair() {
  encerrarSessao();
  router.push("/");
}
</script>

<template>
  <div class="app-shell">
    <header class="topbar">
      <div class="topbar-conteudo">
        <RouterLink to="/" class="marca" aria-label="Siciliana 64 - Início">
          <span class="marca-texto">
            SICILIANA<span class="marca-numero">64</span>
          </span>
        </RouterLink>

        <nav class="navegacao" aria-label="Navegação principal">
          <!-- Página inicial da loja -->
          <RouterLink
            to="/"
            class="link-nav"
            :class="{ ativo: route.path === '/' }"
          >
            Loja
          </RouterLink>

          <!-- Histórico de pedidos do usuário autenticado -->
          <RouterLink
            v-if="logado"
            to="/meus-pedidos"
            class="link-nav"
            :class="{ ativo: route.path === '/meus-pedidos' }"
          >
            Meus pedidos
          </RouterLink>

          <!-- Área administrativa existente -->
          <RouterLink
            v-if="usuario?.role === 'admin'"
            to="/admin"
            class="link-nav"
            :class="{ ativo: route.path.startsWith('/admin') }"
          >
            Administração
          </RouterLink>
        </nav>

        <div class="acoes-header">
          <!--
          <button
            class="tema-toggle"
            type="button"
            @click="alternarTema"
            :aria-label="
              temaEscuro ? 'Ativar tema claro' : 'Ativar tema escuro'
            "
            :title="temaEscuro ? 'Ativar tema claro' : 'Ativar tema escuro'"
          >
            <Sun
              v-if="!temaEscuro"
              class="icone-tema"
              :size="20"
              :stroke-width="1.8"
              aria-hidden="true"
            />

            <Moon
              v-else
              class="icone-tema"
              :size="20"
              :stroke-width="1.8"
              aria-hidden="true"
            />
          </button>

          -->

          <RouterLink
            to="/carrinho"
            class="acao-icone carrinho-acao"
            :class="{ 'acao-ativa': route.path === '/carrinho' }"
            aria-label="Abrir carrinho"
            title="Carrinho"
          >
            <svg
              viewBox="0 0 24 24"
              class="icone-svg"
              fill="none"
              stroke="currentColor"
              stroke-width="1.7"
              stroke-linecap="round"
              stroke-linejoin="round"
              aria-hidden="true"
            >
              <circle cx="9" cy="21" r="1" />
              <circle cx="20" cy="21" r="1" />
              <path
                d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"
              />
            </svg>

            <span v-if="quantidadeTotal() > 0" class="contador-carrinho">
              {{ quantidadeTotal() > 99 ? "99+" : quantidadeTotal() }}
            </span>
          </RouterLink>

          <div class="separador-header"></div>

          <RouterLink
            v-if="!logado"
            to="/login"
            class="acao-conta"
            :class="{ 'acao-ativa': route.path === '/login' }"
            aria-label="Entrar na conta"
            title="Entrar na conta"
          >
            <span class="conta-icone" aria-hidden="true">♞</span>
            <span class="conta-texto">Entrar</span>
          </RouterLink>

          <div v-else class="conta-logada">
            <RouterLink
              to="/perfil"
              class="conta-identificacao"
              :class="{ 'conta-perfil-ativo': route.path === '/perfil' }"
              aria-label="Abrir meu perfil"
              title="Meu perfil"
            >
              <span class="conta-icone" aria-hidden="true">♞</span>

              <span class="conta-dados">
                <span class="conta-nome">{{ usuario?.name }}</span>
                <span class="conta-tipo">
                  {{ usuario?.role === "admin" ? "Administrador" : "Cliente" }}
                </span>
              </span>
            </RouterLink>

            <button
              class="botao-sair"
              type="button"
              @click="sair"
              title="Encerrar sessão"
            >
              <svg
                viewBox="0 0 24 24"
                class="icone-sair"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
              >
                <path d="M10 17l5-5-5-5" />
                <path d="M15 12H3" />
                <path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6" />
              </svg>
              <span>Sair</span>
            </button>
          </div>
        </div>
      </div>
    </header>

    <main class="conteudo-principal">
      <RouterView />
    </main>

    <footer class="rodape">
      <RouterLink to="/" class="rodape-marca"> ♞ SICILIANA 64 </RouterLink>

      <span>&copy; Todos os direitos reservados</span>
    </footer>
  </div>
</template>

<style>
:root {
  font-family:
    Inter,
    ui-sans-serif,
    system-ui,
    -apple-system,
    BlinkMacSystemFont,
    "Segoe UI",
    sans-serif;
  color-scheme: light;

  --bg: #f5f3ed;
  --surface: #fffefa;
  --surface-alt: #ebe8df;
  --text: #252820;
  --muted: #77796f;
  --border: #dfdcd1;
  --accent: #354435;
  --accent-contrast: #fffefa;
  --square-light: #e9e4d8;
  --square-dark: #354435;
  --shadow: 0 8px 28px rgba(36, 40, 31, 0.055);
  --header-bg: rgba(255, 254, 250, 0.94);
}

:root[data-theme="dark"] {
  color-scheme: dark;

  --bg: #171a16;
  --surface: #222720;
  --surface-alt: #2d342b;
  --text: #f0eee5;
  --muted: #b0b3a7;
  --border: #3b4339;
  --accent: #d9dfce;
  --accent-contrast: #20251e;
  --square-light: #dcd6c4;
  --square-dark: #354435;
  --shadow: 0 8px 28px rgba(0, 0, 0, 0.18);
  --header-bg: rgba(27, 30, 26, 0.95);
}

* {
  box-sizing: border-box;
}

html {
  min-width: 320px;
  min-height: 100%;
  background: var(--bg);
  scroll-behavior: smooth;
}

body {
  margin: 0;
  min-width: 320px;
  min-height: 100vh;
  background: var(--bg);
  color: var(--text);
  transition:
    background 0.25s ease,
    color 0.25s ease;
}

body,
button,
input,
textarea,
select {
  font-family:
    Inter,
    ui-sans-serif,
    system-ui,
    -apple-system,
    BlinkMacSystemFont,
    "Segoe UI",
    sans-serif;
}

button,
input,
textarea,
select {
  font-size: inherit;
}

button,
a {
  -webkit-tap-highlight-color: transparent;
}

a {
  color: inherit;
}

button:focus-visible,
a:focus-visible {
  outline: 2px solid var(--muted);
  outline-offset: 4px;
}

.app-shell {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.topbar {
  position: sticky;
  top: 0;
  z-index: 100;
  width: 100%;
  border-bottom: 1px solid var(--border);
  background: var(--header-bg);
  backdrop-filter: blur(14px);
  -webkit-backdrop-filter: blur(14px);
}

.topbar-conteudo {
  width: min(100% - 56px, 1320px);
  min-height: 82px;
  margin: 0 auto;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 30px;
}

.marca {
  display: inline-flex;
  align-items: center;
  gap: 12px;
  flex-shrink: 0;
  text-decoration: none;
}

.marca-icone {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 33px;
  height: 43px;
  flex-shrink: 0;
  border: none;
  border-radius: 0;
  background: transparent;
  color: var(--text);
  font-size: 36px;
  line-height: 1;
  transition: color 0.2s ease;
}

.marca-texto {
  font-size: 21px;
  font-weight: 850;
  letter-spacing: 2px;
  white-space: nowrap;
}

.marca-numero {
  margin-left: 5px;
  color: var(--muted);
  font-weight: 500;
}

.navegacao {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  margin-left: auto;
}

.link-nav {
  position: relative;
  padding: 10px 15px;
  border-radius: 8px;
  color: var(--muted);
  font-size: 13px;
  font-weight: 550;
  text-decoration: none;
  white-space: nowrap;
  transition:
    color 0.18s ease,
    background 0.18s ease;
}

.link-nav:hover,
.link-nav.ativo {
  color: var(--text);
  background: var(--surface-alt);
}

.link-nav.ativo::after {
  position: absolute;
  right: 15px;
  bottom: 3px;
  left: 15px;
  height: 2px;
  border-radius: 2px;
  background: var(--accent);
  content: "";
}

.acoes-header {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  flex-shrink: 0;
}

.tema-toggle,
.acao-icone,
.acao-conta {
  position: relative;
  min-width: 43px;
  height: 43px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 9px;
  border: 1px solid var(--border);
  border-radius: 11px;
  background: var(--surface);
  color: var(--text);
  text-decoration: none;
  cursor: pointer;
  transition:
    background 0.2s ease,
    border-color 0.2s ease,
    transform 0.2s ease;
}

.tema-toggle:hover,
.acao-icone:hover,
.acao-conta:hover {
  border-color: var(--muted);
  background: var(--surface-alt);
  transform: translateY(-1px);
}

.icone-tema,
.icone-svg {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
}

.carrinho-acao {
  width: 45px;
}

.contador-carrinho {
  position: absolute;
  top: -6px;
  right: -6px;
  min-width: 19px;
  height: 19px;
  display: grid;
  place-items: center;
  padding: 0 4px;
  border: 2px solid var(--bg);
  border-radius: 999px;
  background: var(--accent);
  color: var(--accent-contrast);
  font-size: 10px;
  font-weight: 800;
  line-height: 1;
}

.separador-header {
  width: 1px;
  height: 29px;
  margin: 0 3px;
  background: var(--border);
}

.acao-conta {
  padding: 0 13px;
}

.acao-ativa {
  border-color: var(--muted);
  background: var(--surface-alt);
}

.conta-icone {
  width: 29px;
  height: 29px;
  display: grid;
  place-items: center;
  flex-shrink: 0;
  border-radius: 9px;
  background: var(--accent);
  color: var(--accent-contrast);
  font-size: 22px;
  line-height: 1;
}

.conta-texto {
  font-size: 13px;
  font-weight: 650;
}

.conta-logada {
  display: flex;
  align-items: center;
  gap: 13px;
}

.conta-identificacao {
  display: flex;
  align-items: center;
  gap: 9px;
  min-width: 0;
  color: inherit;
  text-decoration: none;
  cursor: pointer;
}

.conta-identificacao:hover .conta-nome {
  text-decoration: underline;
  text-underline-offset: 3px;
}

.conta-perfil-ativo .conta-nome {
  text-decoration: underline;
  text-underline-offset: 3px;
}

.conta-dados {
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-width: 0;
}

.conta-nome {
  max-width: 135px;
  overflow: hidden;
  color: var(--text);
  font-size: 12px;
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.conta-tipo {
  color: var(--muted);
  font-size: 10px;
}

.botao-sair {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 9px 10px;
  border: 1px solid var(--border);
  border-radius: 9px;
  background: transparent;
  color: var(--muted);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition:
    color 0.2s ease,
    background 0.2s ease,
    border-color 0.2s ease;
}

.botao-sair:hover {
  border-color: var(--muted);
  background: var(--surface-alt);
  color: var(--text);
}

.icone-sair {
  width: 16px;
  height: 16px;
}

.conteudo-principal {
  flex: 1;
  width: 100%;
}

.rodape {
  width: min(100% - 56px, 1320px);
  margin: auto auto 0;
  padding: 27px 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 18px;
  border-top: 1px solid var(--border);
  color: var(--muted);
  font-size: 11px;
}

.rodape-marca {
  color: var(--text);
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 1.4px;
  text-decoration: none;
  white-space: nowrap;
}

.rodape-direitos {
  font-size: 9px;
  letter-spacing: 1.2px;
  text-align: right;
}

@media (max-width: 1000px) {
  .topbar-conteudo {
    width: min(100% - 36px, 1320px);
    gap: 18px;
  }

  .marca-texto {
    font-size: 15px;
  }

  .navegacao {
    gap: 2px;
  }

  .link-nav {
    padding: 10px;
  }

  .conta-nome {
    max-width: 95px;
  }

  .conta-logada {
    gap: 8px;
  }

  .rodape {
    width: min(100% - 36px, 1320px);
  }

  .rodape-direitos {
    display: none;
  }
}

@media (max-width: 720px) {
  .topbar-conteudo {
    width: calc(100% - 28px);
    min-height: auto;
    padding: 13px 0;
    flex-wrap: wrap;
    gap: 13px;
  }

  .marca-icone {
    width: 38px;
    height: 38px;
    font-size: 25px;
  }

  .marca-texto {
    font-size: 14px;
    letter-spacing: 1.4px;
  }

  .acoes-header {
    gap: 7px;
    margin-left: auto;
  }

  .tema-toggle,
  .acao-icone {
    min-width: 39px;
    width: 39px;
    height: 39px;
  }

  .separador-header {
    height: 25px;
    margin: 0;
  }

  .acao-conta {
    min-width: 39px;
    height: 39px;
    padding: 0 7px;
  }

  .conta-texto {
    display: none;
  }

  .conta-logada {
    gap: 7px;
  }

  .conta-identificacao {
    gap: 5px;
  }

  .conta-icone {
    width: 27px;
    height: 27px;
    font-size: 20px;
  }

  .conta-dados {
    display: none;
  }

  .botao-sair {
    padding: 8px;
  }

  .navegacao {
    order: 3;
    width: 100%;
    justify-content: flex-start;
    margin-left: 0;
    padding-top: 3px;
    border-top: 1px solid var(--border);
  }

  .link-nav {
    padding: 9px 12px;
    font-size: 12px;
  }

  .link-nav.ativo::after {
    right: 12px;
    bottom: 2px;
    left: 12px;
  }

  .rodape {
    width: calc(100% - 28px);
    padding: 22px 0;
    flex-wrap: wrap;
    font-size: 10px;
  }
}

@media (max-width: 380px) {
  .topbar-conteudo {
    width: calc(100% - 20px);
  }

  .marca {
    gap: 7px;
  }

  .marca-icone {
    width: 34px;
    height: 34px;
  }

  .marca-texto {
    font-size: 12px;
    letter-spacing: 1px;
  }

  .acoes-header {
    gap: 5px;
  }

  .tema-toggle,
  .acao-icone {
    width: 35px;
    min-width: 35px;
    height: 35px;
  }

  .icone-tema,
  .icone-svg {
    width: 18px;
    height: 18px;
  }

  .botao-sair {
    gap: 4px;
    padding: 7px 6px;
    font-size: 11px;
  }

  .icone-sair {
    width: 14px;
    height: 14px;
  }
}
</style>
