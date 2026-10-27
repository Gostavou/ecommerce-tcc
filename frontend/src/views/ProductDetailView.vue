<script setup>
import { computed, onMounted, onBeforeUnmount, ref, watch } from "vue";
import axios from "axios";
import { RouterLink, useRoute } from "vue-router";
import { useCarrinho } from "../stores/cart";

const route = useRoute();
const { adicionarAoCarrinho, mensagem: mensagemCarrinho } = useCarrinho();

const api = "http://localhost:8000/api/produtos";
const baseUrl = "http://localhost:8000";

const produto = ref(null);
const carregando = ref(true);
const erro = ref("");
const quantidade = ref(1);
const adicionado = ref(false);
const mensagemFeedback = ref("");
let temporizadorFeedback = null;

const disponivel = computed(() => Number(produto.value?.estoque ?? 0) > 0);

const precoFormatado = computed(() =>
  Number(produto.value?.preco ?? 0).toLocaleString("pt-BR", {
    style: "currency",
    currency: "BRL",
  }),
);

function imagemUrl(imagem) {
  if (!imagem) return "";

  if (imagem.startsWith("http://") || imagem.startsWith("https://")) {
    return imagem;
  }

  return `${baseUrl}/storage/${imagem.replace(/^\//, "")}`;
}

function limparFeedback() {
  adicionado.value = false;
  mensagemFeedback.value = "";

  if (temporizadorFeedback) {
    clearTimeout(temporizadorFeedback);
    temporizadorFeedback = null;
  }
}

function mostrarFeedback() {
  adicionado.value = true;
  mensagemFeedback.value = "Produto adicionado ao carrinho com sucesso!";

  if (temporizadorFeedback) {
    clearTimeout(temporizadorFeedback);
  }

  temporizadorFeedback = setTimeout(() => {
    adicionado.value = false;
    mensagemFeedback.value = "";
    temporizadorFeedback = null;
  }, 3500);
}

async function carregarProduto() {
  carregando.value = true;
  erro.value = "";
  produto.value = null;
  quantidade.value = 1;
  limparFeedback();

  try {
    const resposta = await axios.get(`${api}/${route.params.id}`);
    produto.value = resposta.data;
  } catch (e) {
    erro.value =
      e.response?.status === 404
        ? "Este produto não foi encontrado."
        : "Não foi possível carregar o produto.";

    console.error(e);
  } finally {
    carregando.value = false;
  }
}

function adicionarProduto() {
  if (!produto.value || !disponivel.value) return;

  limparFeedback();

  const sucesso = adicionarAoCarrinho(produto.value, Number(quantidade.value));

  if (sucesso) {
    mostrarFeedback();
  } else {
    mensagemFeedback.value =
      mensagemCarrinho.value || "Não foi possível adicionar o produto.";
  }
}

function alterarQuantidade(valor) {
  if (!produto.value || !disponivel.value) return;

  limparFeedback();

  quantidade.value = Math.max(
    1,
    Math.min(Number(produto.value.estoque), Number(quantidade.value) + valor),
  );
}

onMounted(carregarProduto);

watch(() => route.params.id, carregarProduto);

onBeforeUnmount(() => {
  if (temporizadorFeedback) {
    clearTimeout(temporizadorFeedback);
  }
});
</script>

<template>
  <main class="detalhe-container">
    <RouterLink to="/" class="voltar"> ← Voltar ao catálogo </RouterLink>

    <p v-if="carregando" class="estado">Carregando produto...</p>

    <p v-else-if="erro" class="estado erro">
      {{ erro }}
      <RouterLink to="/">Voltar à loja</RouterLink>
    </p>

    <section v-else-if="produto" class="detalhe-grid">
      <div class="foto-produto">
        <img
          v-if="produto.imagem"
          :src="imagemUrl(produto.imagem)"
          :alt="produto.nome"
        />
      </div>

      <div class="detalhe-info">
        <h1>{{ produto.nome }}</h1>

        <p class="preco">{{ precoFormatado }}</p>

        <div class="divisor"></div>

        <p class="rotulo">DESCRIÇÃO</p>

        <p class="descricao">
          {{
            produto.descricao ||
            "Uma escolha para completar seu conjunto. Consulte as informações disponíveis e confira a quantidade em estoque."
          }}
        </p>

        <div class="estoque-linha">
          <span class="indicador" :class="{ indisponivel: !disponivel }"></span>

          <span>
            {{
              disponivel
                ? `${produto.estoque} unidade(s) disponível(is)`
                : "Produto indisponível no momento"
            }}
          </span>
        </div>

        <div class="comprar">
          <div class="quantidade" aria-label="Quantidade do produto">
            <button
              type="button"
              :disabled="!disponivel || quantidade <= 1"
              aria-label="Diminuir quantidade"
              @click="alterarQuantidade(-1)"
            >
              −
            </button>

            <span>{{ quantidade }}</span>

            <button
              type="button"
              :disabled="!disponivel || quantidade >= Number(produto.estoque)"
              aria-label="Aumentar quantidade"
              @click="alterarQuantidade(1)"
            >
              +
            </button>
          </div>

          <button
            class="botao-comprar"
            type="button"
            :disabled="!disponivel"
            @click="adicionarProduto"
          >
            <template v-if="!disponivel"> Sem estoque </template>

            <template v-else> Adicionar ao carrinho </template>
          </button>
        </div>

        <Transition name="feedback">
          <div
            v-if="mensagemFeedback"
            class="feedback-carrinho"
            :class="{ sucesso: adicionado }"
            role="status"
            aria-live="polite"
          >
            <span class="feedback-icone">
              {{ adicionado ? "✓" : "!" }}
            </span>

            <span>{{ mensagemFeedback }}</span>
          </div>
        </Transition>
      </div>
    </section>
  </main>
</template>

<style scoped>
.detalhe-container {
  width: min(100% - 40px, 1120px);
  margin: 0 auto;
  padding: 34px 0 80px;
  flex: 1;
}

.voltar {
  display: inline-block;
  margin-bottom: 28px;
  color: var(--muted);
  font-size: 13px;
  text-decoration: none;
}

.voltar:hover {
  color: var(--text);
}

.detalhe-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.05fr) minmax(0, 0.95fr);
  gap: clamp(28px, 6vw, 76px);
  align-items: start;
}

.foto-produto {
  position: relative;
  min-width: 0;
  aspect-ratio: 1 / 1.05;
  overflow: hidden;
  border: 1px solid var(--border);
  border-radius: 9px;
  background: var(--surface-alt);
}

.foto-produto img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.etiqueta-foto {
  position: absolute;
  top: 16px;
  left: 16px;
  padding: 8px 10px;
  border-radius: 4px;
  background: var(--surface);
  color: var(--text);
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 1px;
}

.detalhe-info {
  padding-top: 10px;
}

.eyebrow,
.rotulo {
  color: var(--muted);
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 1.8px;
}

.eyebrow {
  margin: 0 0 17px;
}

h1 {
  margin: 0;
  overflow-wrap: anywhere;
  font-size: clamp(34px, 4vw, 49px);
  line-height: 1.08;
  letter-spacing: -1.8px;
}

.preco {
  margin: 22px 0;
  font-size: 25px;
  font-weight: 750;
}

.divisor {
  height: 1px;
  background: var(--border);
}

.rotulo {
  margin: 23px 0 10px;
}

.descricao {
  color: var(--muted);
  font-size: 14px;
  line-height: 1.8;
}

.estoque-linha {
  display: flex;
  align-items: center;
  gap: 9px;
  margin-top: 24px;
  font-size: 12px;
}

.indicador {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #829875;
}

.indicador.indisponivel {
  background: #c36b5f;
}

.comprar {
  display: flex;
  gap: 10px;
  margin-top: 22px;
}

.quantidade {
  display: flex;
  align-items: center;
  flex-shrink: 0;
  border: 1px solid var(--border);
  border-radius: 6px;
  background: var(--surface);
}

.quantidade button {
  width: 38px;
  height: 44px;
  border: 0;
  background: transparent;
  color: var(--text);
  cursor: pointer;
  font-size: 20px;
}

.quantidade button:disabled {
  opacity: 0.3;
  cursor: not-allowed;
}

.quantidade span {
  min-width: 26px;
  text-align: center;
  font-size: 13px;
}

.botao-comprar {
  display: inline-flex;
  flex: 1;
  min-width: 0;
  align-items: center;
  justify-content: center;
  padding: 12px;
  border: 0;
  border-radius: 6px;
  background: var(--accent);
  color: var(--accent-contrast);
  cursor: pointer;
  font: inherit;
  font-size: 12px;
  font-weight: 700;
  transition:
    opacity 0.2s,
    transform 0.2s,
    background 0.2s;
}

.botao-comprar:hover:not(:disabled) {
  opacity: 0.85;
  transform: translateY(-1px);
}

.botao-comprar:active:not(:disabled) {
  transform: translateY(0);
}

.botao-comprar:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.feedback-carrinho {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
  margin-top: 14px;
  border: 1px solid var(--border);
  border-radius: 7px;
  color: var(--text);
  font-size: 13px;
  line-height: 1.5;
}

.feedback-carrinho.sucesso {
  border-color: #b8d9c2;
  background: #eff8f1;
  color: #216e39;
}

.feedback-icone {
  display: inline-flex;
  width: 23px;
  height: 23px;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  border: 1px solid currentColor;
  border-radius: 50%;
  font-weight: 800;
}

.feedback-enter-active,
.feedback-leave-active {
  transition:
    opacity 0.2s,
    transform 0.2s;
}

.feedback-enter-from,
.feedback-leave-to {
  opacity: 0;
  transform: translateY(-5px);
}

.estado {
  padding: 40px;
  color: var(--muted);
  text-align: center;
}

.estado.erro {
  color: #c36b5f;
}

.estado a {
  color: var(--text);
}

@media (max-width: 760px) {
  .detalhe-container {
    width: min(100% - 28px, 1120px);
    padding-top: 24px;
  }

  .detalhe-grid {
    grid-template-columns: 1fr;
    gap: 30px;
  }

  .foto-produto {
    max-height: 480px;
  }

  .detalhe-info {
    padding-top: 0;
  }
}

@media (prefers-reduced-motion: reduce) {
  .botao-comprar,
  .feedback-enter-active,
  .feedback-leave-active {
    transition: none;
  }
}
</style>
