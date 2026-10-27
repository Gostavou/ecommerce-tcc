<script setup>
import { ref } from "vue";
import { useRouter, RouterLink } from "vue-router";
import axios from "axios";
import { salvarSessao } from "../stores/auth";

const router = useRouter();

const email = ref("");
const senha = ref("");
const mostrarSenha = ref(false);
const carregando = ref(false);
const erro = ref("");

async function fazerLogin() {
  erro.value = "";
  carregando.value = true;

  try {
    const resposta = await axios.post("http://localhost:8000/api/login", {
      email: email.value,
      password: senha.value,
    });

    salvarSessao(resposta.data.usuario, resposta.data.token);

    if (resposta.data.usuario.role === "admin") {
      router.push("/admin");
    } else {
      router.push("/");
    }
  } catch (e) {
    if (e.response?.status === 422) {
      erro.value = "E-mail ou senha incorretos.";
    } else {
      erro.value =
        e.response?.data?.message ||
        "Não foi possível realizar o login. Verifique se o Laravel está rodando.";
    }
  } finally {
    carregando.value = false;
  }
}
</script>

<template>
  <main class="login-container">
    <section class="login-card">
      <h1>Entrar na conta</h1>
      <p class="descricao">Informe seus dados abaixo para continuar.</p>

      <form @submit.prevent="fazerLogin">
        <div class="campo">
          <label for="email">E-mail</label>
          <input
            id="email"
            v-model.trim="email"
            type="email"
            autocomplete="email"
            required
            placeholder="seu@email.com"
          />
        </div>

        <div class="campo">
          <label for="senha">Senha</label>

          <div class="senha-input">
            <input
              id="senha"
              v-model="senha"
              :type="mostrarSenha ? 'text' : 'password'"
              autocomplete="current-password"
              required
              placeholder="Sua senha"
            />

            <button
              type="button"
              class="botao-olho"
              :aria-label="mostrarSenha ? 'Ocultar senha' : 'Mostrar senha'"
              :title="mostrarSenha ? 'Ocultar senha' : 'Mostrar senha'"
              @click="mostrarSenha = !mostrarSenha"
            >
              <svg
                v-if="!mostrarSenha"
                xmlns="http://www.w3.org/2000/svg"
                width="20"
                height="20"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
              >
                <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z" />
                <circle cx="12" cy="12" r="3" />
              </svg>

              <svg
                v-else
                xmlns="http://www.w3.org/2000/svg"
                width="20"
                height="20"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
              >
                <path d="M3 3l18 18" />
                <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8" />
                <path
                  d="M9.9 5.2A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7a15.8 15.8 0 0 1-4 4.7"
                />
                <path
                  d="M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7a10.6 10.6 0 0 0 4-.8"
                />
              </svg>
            </button>
          </div>

          <a href="#" class="esqueceu-senha" @click.prevent>
            Esqueceu sua senha?
          </a>
        </div>

        <p v-if="erro" class="erro">{{ erro }}</p>

        <button class="botao" type="submit" :disabled="carregando">
          {{ carregando ? "Entrando..." : "Entrar" }}
        </button>
      </form>

      <p class="voltar">
        Ainda não tem uma conta?
        <RouterLink to="/registrar">Registre-se agora</RouterLink>
      </p>
    </section>
  </main>
</template>

<style scoped>
.login-container {
  flex: 1;
  display: grid;
  place-items: center;
  padding: 40px 20px;
}

.login-card {
  width: min(100%, 420px);
  padding: 32px;
  border: 1px solid var(--border);
  border-radius: 14px;
  background: var(--surface);
  box-shadow: var(--shadow);
}

.marca {
  display: inline-block;
  margin-bottom: 30px;
  font-size: 18px;
  font-weight: 800;
  letter-spacing: 1px;
  text-decoration: none;
}

.marca b {
  color: var(--muted);
  font-weight: 500;
}

.subtitulo {
  margin-bottom: 8px;
  color: var(--muted);
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 1.5px;
}

h1 {
  margin: 0;
  font-size: 28px;
}

.descricao {
  margin-bottom: 28px;
  color: var(--muted);
  font-size: 14px;
}

.campo {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 18px;
}

.campo label {
  font-size: 14px;
  font-weight: 600;
}

.campo input {
  width: 100%;
  padding: 12px;
  border: 1px solid var(--border);
  border-radius: 7px;
  background: var(--bg);
  color: var(--text);
}

.campo input:focus {
  outline: 2px solid var(--muted);
  outline-offset: 1px;
}

.senha-input {
  position: relative;
  width: 100%;
}

.senha-input input {
  box-sizing: border-box;
  padding-right: 44px;
}

.botao-olho {
  position: absolute;
  top: 50%;
  right: 12px;
  display: grid;
  place-items: center;
  padding: 0;
  border: none;
  background: transparent;
  color: var(--muted);
  cursor: pointer;
  transform: translateY(-50%);
}

.botao-olho:hover {
  color: var(--text);
}

.botao-olho:focus-visible {
  outline: 2px solid var(--muted);
  outline-offset: 3px;
  border-radius: 4px;
}

.esqueceu-senha {
  align-self: flex-end;
  color: var(--muted);
  font-size: 13px;
  text-decoration: none;
  cursor: pointer;
}

.esqueceu-senha:hover {
  color: var(--text);
  text-decoration: underline;
}

.botao {
  width: 100%;
  padding: 12px;
  border: 0;
  border-radius: 7px;
  background: var(--accent);
  color: var(--accent-contrast);
  font-weight: 700;
  cursor: pointer;
}

.botao:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.erro {
  color: #c0392b;
  font-size: 14px;
}

.voltar {
  margin: 24px 0 0;
  color: var(--muted);
  font-size: 13px;
  text-align: center;
}

.voltar a {
  color: var(--text);
  font-weight: 700;
}
</style>
