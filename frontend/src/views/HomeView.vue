<script setup>
import { computed, onMounted, ref } from "vue";
import axios from "axios";
import { RouterLink } from "vue-router";

const api = "http://localhost:8000/api/produtos";
const baseUrl = "http://localhost:8000";

const produtos = ref([]);
const carregando = ref(true);
const erro = ref("");
const busca = ref("");
const buscaAplicada = ref("");
const ordenacao = ref("recentes");

const produtosFiltrados = computed(() => {
  const termo = buscaAplicada.value.trim().toLocaleLowerCase("pt-BR");

  let resultado = produtos.value.filter((produto) => {
    if (!termo) return true;

    return `${produto.nome} ${produto.descricao ?? ""}`
      .toLocaleLowerCase("pt-BR")
      .includes(termo);
  });

  resultado.sort((a, b) => {
    const estoqueA = Number(a.estoque) || 0;
    const estoqueB = Number(b.estoque) || 0;

    const indisponivelA = estoqueA < 1;
    const indisponivelB = estoqueB < 1;

    if (indisponivelA !== indisponivelB) {
      return indisponivelA ? 1 : -1;
    }

    switch (ordenacao.value) {
      case "caros":
        return Number(b.preco) - Number(a.preco);

      case "baratos":
        return Number(a.preco) - Number(b.preco);

      case "recentes":
      default:
        return Number(b.id) - Number(a.id);
    }
  });

  return resultado;
});

function pesquisar() {
  buscaAplicada.value = busca.value.trim();
}

function imagemUrl(imagem) {
  if (!imagem) return "";

  if (imagem.startsWith("http://") || imagem.startsWith("https://")) {
    return imagem;
  }

  return `${baseUrl}/storage/${imagem.replace(/^\//, "")}`;
}

function formatarPreco(preco) {
  return Number(preco).toLocaleString("pt-BR", {
    style: "currency",
    currency: "BRL",
  });
}

async function carregarProdutos() {
  carregando.value = true;
  erro.value = "";

  try {
    const resposta = await axios.get(api);
    produtos.value = resposta.data;
  } catch (e) {
    erro.value =
      "Não foi possível carregar os produtos. Confira se o Laravel e o banco estão rodando.";
    console.error(e);
  } finally {
    carregando.value = false;
  }
}

onMounted(carregarProdutos);
</script>

<template>
  <main class="loja-container">
    <section class="hero">
      <div class="hero-conteudo">
        <h1>
          Boas escolhas,<br />
          <span>Movimentos inteligentes.</span>
        </h1>

        <p class="hero-descricao">
          Tecnologia e acessórios para acompanhar cada movimento do seu dia a
          dia
        </p>
      </div>

      <div class="imagem-hero" aria-hidden="true">
        <img src="/rei-xadrez.png" alt="" class="imagem-rei" />
      </div>
    </section>

    <section id="catalogo" class="catalogo-loja">
      <div class="catalogo-topo">
        <div>
          <h2>Encontre sua próxima peça.</h2>
        </div>
      </div>

      <form class="barra-filtros" @submit.prevent="pesquisar">
        <input
          v-model="busca"
          class="campo-pesquisa"
          type="search"
          placeholder="Buscar por nome"
          aria-label="Buscar produto por nome"
        />

        <select
          id="ordenacao-produtos"
          v-model="ordenacao"
          class="seletor-ordenacao"
          aria-label="Ordenar produtos"
        >
          <option value="recentes">Mais recentes</option>
          <option value="caros">Maior preço</option>
          <option value="baratos">Menor preço</option>
        </select>

        <button
          class="botao-pesquisar"
          type="submit"
          aria-label="Pesquisar produtos"
          title="Pesquisar"
        >
          <svg
            viewBox="0 0 24 24"
            width="19"
            height="19"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
          >
            <circle cx="10.8" cy="10.8" r="6.5" />
            <path d="m16 16 4.2 4.2" />
          </svg>
        </button>
      </form>

      <p v-if="erro" class="mensagem erro">{{ erro }}</p>

      <div v-else-if="carregando" class="mensagem">Carregando catálogo...</div>

      <div v-else-if="produtosFiltrados.length === 0" class="mensagem vazio">
        {{
          buscaAplicada
            ? "Nenhum produto encontrado para essa busca."
            : "Ainda não há produtos no catálogo."
        }}
      </div>

      <div v-else class="grade-produtos">
        <RouterLink
          v-for="produto in produtosFiltrados"
          :key="produto.id"
          :to="`/produto/${produto.id}`"
          class="cartao-produto"
        >
          <div class="imagem-wrap">
            <img
              v-if="produto.imagem"
              :src="imagemUrl(produto.imagem)"
              :alt="produto.nome"
              class="imagem-produto"
            />

            <span class="seta-produto" aria-hidden="true">↗</span>
          </div>

          <div class="info-produto">
            <div class="nome-linha">
              <h3>{{ produto.nome }}</h3>
            </div>

            <p v-if="produto.descricao" class="descricao-card">
              {{ produto.descricao }}
            </p>

            <div class="preco-linha">
              <strong>{{ formatarPreco(produto.preco) }}</strong>

              <span
                :class="[
                  'estoque',
                  { indisponivel: Number(produto.estoque) < 1 },
                ]"
              >
                {{
                  Number(produto.estoque) > 0
                    ? `${produto.estoque} em estoque`
                    : "Indisponível"
                }}
              </span>
            </div>
          </div>
        </RouterLink>
      </div>
    </section>
  </main>
</template>

<style scoped>
.loja-container {
  width: min(100% - 40px, 1240px);
  margin: 0 auto;
}

.hero {
  position: relative;
  min-height: 430px;
  padding: 70px 7.5%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 30px;
  overflow: hidden;
  border-bottom: 1px solid var(--border);
}

.hero-conteudo {
  position: relative;
  z-index: 2;
  max-width: 590px;
}

.eyebrow {
  margin: 0 0 20px;
  color: var(--muted);
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 2px;
}

h1 {
  margin: 0;
  font-size: clamp(38px, 5vw, 64px);
  line-height: 1.03;
  letter-spacing: -2.7px;
  font-weight: 750;
}

h1 span {
  color: var(--muted);
  font-weight: 450;
}

.hero-descricao {
  max-width: 390px;
  margin: 22px 0 26px;
  color: var(--muted);
  font-size: 15px;
  line-height: 1.7;
}

.imagem-hero {
  position: relative;
  width: min(34vw, 310px);
  aspect-ratio: 1;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.imagem-rei {
  display: block;
  width: 500px;
  height: 400px;
  object-fit: contain;
}

.hero-linha {
  position: absolute;
  bottom: 0;
  left: 0;
  width: 28%;
  height: 3px;
  background: var(--accent);
}

.catalogo-loja {
  padding: 54px 0 72px;
}

.catalogo-topo {
  display: flex;
  justify-content: space-between;
  align-items: end;
  gap: 24px;
  margin-bottom: 26px;
}

.catalogo-topo .eyebrow {
  margin-bottom: 10px;
}

h2 {
  margin: 0;
  font-size: clamp(25px, 3vw, 34px);
  letter-spacing: -1.2px;
}

.barra-filtros {
  display: grid;
  grid-template-columns: minmax(0, 1.5fr) minmax(170px, 0.85fr) 68px;
  align-items: center;
  gap: 8px;
  width: 100%;
  margin-bottom: 24px;
}

.campo-pesquisa,
.seletor-ordenacao {
  box-sizing: border-box;
  width: 100%;
  height: 42px;
  min-width: 0;
  padding: 0 12px;
  border: 1px solid var(--border);
  border-radius: 5px;
  background: var(--surface);
  color: var(--text);
  font-family: inherit;
  font-size: 12px;
  outline: none;
  transition:
    border-color 0.2s ease,
    box-shadow 0.2s ease;
}

.campo-pesquisa::placeholder {
  color: var(--muted);
  opacity: 0.85;
}

.campo-pesquisa:focus,
.seletor-ordenacao:focus {
  border-color: #8eb8f5;
  box-shadow: 0 0 0 3px rgba(83, 145, 230, 0.17);
}

.campo-pesquisa {
  appearance: none;
}

.campo-pesquisa::-webkit-search-cancel-button {
  cursor: pointer;
}

.seletor-ordenacao {
  padding-right: 10px;
  cursor: pointer;
}

.botao-pesquisar {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 68px;
  height: 42px;
  padding: 0;
  border: none;
  border-radius: 5px;
  background: #3a5f4e;
  color: #fff;
  cursor: pointer;
  transition:
    background 0.2s ease,
    transform 0.2s ease;
}

.botao-pesquisar:hover {
  background: #304d40;
}

.botao-pesquisar:active {
  transform: scale(0.97);
}

.botao-pesquisar:focus-visible {
  outline: 3px solid rgba(83, 145, 230, 0.4);
  outline-offset: 2px;
}

.grade-produtos {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 18px;
}

.cartao-produto {
  min-width: 0;
  overflow: hidden;
  border: 1px solid var(--border);
  border-radius: 9px;
  background: var(--surface);
  color: var(--text);
  text-decoration: none;
  transition:
    transform 0.2s ease,
    box-shadow 0.2s ease,
    border-color 0.2s ease;
}

.cartao-produto:hover {
  transform: translateY(-4px);
  box-shadow: var(--shadow);
  border-color: var(--muted);
}

.imagem-wrap {
  position: relative;
  aspect-ratio: 1.2;
  overflow: hidden;
  background: var(--surface-alt);
}

.imagem-produto {
  width: 100%;
  height: 100%;
  display: block;
  object-fit: cover;
  transition: transform 0.3s ease;
}

.cartao-produto:hover .imagem-produto {
  transform: scale(1.035);
}

.numero-produto {
  position: absolute;
  top: 12px;
  left: 12px;
  padding: 6px 8px;
  border: 1px solid rgba(255, 255, 255, 0.3);
  border-radius: 4px;
  background: rgba(25, 28, 23, 0.78);
  color: white;
  font-size: 9px;
  font-weight: 700;
  letter-spacing: 1px;
}

.seta-produto {
  position: absolute;
  right: 12px;
  bottom: 12px;
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: var(--surface);
  color: var(--text);
  font-size: 19px;
}

.info-produto {
  padding: 17px;
}

.nome-linha {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 10px;
}

.nome-linha h3 {
  margin: 0;
  overflow-wrap: anywhere;
  font-size: 16px;
  font-weight: 650;
}

.mini-codigo {
  color: var(--muted);
  font-size: 10px;
}

.descricao-card {
  display: -webkit-box;
  overflow: hidden;
  min-height: 35px;
  margin: 9px 0 14px;
  color: var(--muted);
  font-size: 12px;
  line-height: 1.5;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.preco-linha {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 8px;
  padding-top: 13px;
  border-top: 1px solid var(--border);
}

.preco-linha strong {
  font-size: 16px;
}

.estoque {
  color: var(--muted);
  font-size: 10px;
}

.estoque.indisponivel {
  color: #c36b5f;
}

.mensagem {
  padding: 35px 20px;
  border: 1px dashed var(--border);
  border-radius: 8px;
  color: var(--muted);
  text-align: center;
}

.mensagem.erro {
  color: #c36b5f;
}

/* Tablet */

@media (max-width: 850px) {
  .hero {
    padding: 55px 3%;
  }

  .imagem-hero {
    width: min(31vw, 250px);
  }

  .grade-produtos {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .barra-filtros {
    grid-template-columns: minmax(0, 1fr) minmax(140px, 0.8fr) 58px;
  }

  .botao-pesquisar {
    width: 58px;
  }
}

/* Celular */

@media (max-width: 560px) {
  .loja-container {
    width: min(100% - 28px, 1240px);
  }

  .hero {
    min-height: 0;
    padding: 42px 0 50px;
  }

  .imagem-hero {
    display: none;
  }

  h1 {
    letter-spacing: -1.8px;
  }

  .catalogo-loja {
    padding: 38px 0 48px;
  }

  .catalogo-topo {
    align-items: stretch;
    flex-direction: column;
    gap: 18px;
  }

  .barra-filtros {
    grid-template-columns: minmax(0, 1fr) 52px;
    gap: 8px;
  }

  .campo-pesquisa {
    grid-column: 1 / -1;
  }

  .seletor-ordenacao {
    height: 42px;
  }

  .botao-pesquisar {
    width: 52px;
    height: 42px;
  }

  .grade-produtos {
    grid-template-columns: 1fr;
  }

  .imagem-wrap {
    aspect-ratio: 1.35;
  }
}
</style>
