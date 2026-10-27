<script setup>
import { computed, onMounted, onBeforeUnmount, ref } from "vue";
import { RouterLink, useRoute } from "vue-router";
import axios from "axios";
import { token } from "../stores/auth";

const api = "http://localhost:8000/api/produtos";
const baseUrl = "http://localhost:8000";
const route = useRoute();

const produtos = ref([]);
const carregando = ref(false);
const salvando = ref(false);
const erro = ref("");
const sucesso = ref("");
const editandoId = ref(null);
const arquivoImagem = ref(null);
const imagemPreview = ref("");
const imagemAtual = ref("");
const removerImagem = ref(false);
const busca = ref("");
const ordenacao = ref("recentes");

const formularioVazio = () => ({
  nome: "",
  descricao: "",
  preco: "",
  estoque: 1,
});

const formulario = ref(formularioVazio());

function cabecalhos() {
  return {
    Authorization: `Bearer ${token.value}`,
    Accept: "application/json",
  };
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

const produtosFiltrados = computed(() => {
  const termo = busca.value.trim().toLocaleLowerCase("pt-BR");

  const resultado = produtos.value.filter((produto) =>
    `${produto.nome ?? ""} ${produto.descricao ?? ""}`
      .toLocaleLowerCase("pt-BR")
      .includes(termo),
  );

  resultado.sort((a, b) => {
    if (ordenacao.value === "baratos") {
      return Number(a.preco) - Number(b.preco);
    }

    if (ordenacao.value === "caros") {
      return Number(b.preco) - Number(a.preco);
    }

    if (ordenacao.value === "estoque") {
      return Number(a.estoque) - Number(b.estoque);
    }

    return Number(b.id) - Number(a.id);
  });

  return resultado;
});

const quantidadeProdutos = computed(() => produtos.value.length);

const quantidadeEstoque = computed(() =>
  produtos.value.reduce(
    (total, produto) => total + Math.max(0, Number(produto.estoque) || 0),
    0,
  ),
);

const quantidadeIndisponiveis = computed(
  () => produtos.value.filter((produto) => Number(produto.estoque) <= 0).length,
);

const valorEstoque = computed(() =>
  produtos.value.reduce(
    (total, produto) =>
      total +
      Number(produto.preco || 0) * Math.max(0, Number(produto.estoque) || 0),
    0,
  ),
);

function mensagemErroApi(e, padrao) {
  if (e.response?.status === 401) {
    return "Sua sessão expirou. Entre novamente.";
  }

  if (e.response?.status === 403) {
    return "Você não tem permissão para realizar esta ação.";
  }

  if (e.response?.status === 422) {
    const erros = e.response.data?.errors;

    return erros
      ? Object.values(erros).flat().join(" ")
      : e.response.data?.message || "Confira os dados informados.";
  }

  return e.response?.data?.message || padrao;
}

async function carregarProdutos() {
  carregando.value = true;
  erro.value = "";

  try {
    const resposta = await axios.get(api, {
      headers: cabecalhos(),
    });

    produtos.value = Array.isArray(resposta.data)
      ? resposta.data
      : (resposta.data.data ?? []);
  } catch (e) {
    console.error(e);

    erro.value = mensagemErroApi(
      e,
      "Não foi possível carregar os produtos. Confira se o Laravel está rodando.",
    );
  } finally {
    carregando.value = false;
  }
}

function limparPreview() {
  if (imagemPreview.value.startsWith("blob:")) {
    URL.revokeObjectURL(imagemPreview.value);
  }

  imagemPreview.value = "";
}

function selecionarImagem(evento) {
  const arquivo = evento.target.files?.[0] ?? null;

  arquivoImagem.value = null;
  limparPreview();

  if (!arquivo) return;

  const tiposPermitidos = ["image/jpeg", "image/png", "image/webp"];

  if (!tiposPermitidos.includes(arquivo.type)) {
    erro.value = "Selecione uma imagem JPG, PNG ou WebP.";
    evento.target.value = "";
    return;
  }

  if (arquivo.size > 2 * 1024 * 1024) {
    erro.value = "A imagem deve ter no máximo 2 MB.";
    evento.target.value = "";
    return;
  }

  arquivoImagem.value = arquivo;
  imagemPreview.value = URL.createObjectURL(arquivo);

  removerImagem.value = false;

  erro.value = "";
  sucesso.value = "";
}

function removerImagemSelecionada() {
  erro.value = "";
  sucesso.value = "";

  if (imagemPreview.value) {
    arquivoImagem.value = null;
    limparPreview();

    const campoImagem = document.getElementById("imagem");

    if (campoImagem) {
      campoImagem.value = "";
    }

    removerImagem.value = false;

    return;
  }

  if (editandoId.value !== null && imagemAtual.value) {
    removerImagem.value = true;
  }
}

function cancelarRemocaoImagem() {
  removerImagem.value = false;
  erro.value = "";
  sucesso.value = "";
}

function limparFormulario() {
  limparPreview();

  editandoId.value = null;
  arquivoImagem.value = null;
  imagemAtual.value = "";
  removerImagem.value = false;
  formulario.value = formularioVazio();

  const campoImagem = document.getElementById("imagem");

  if (campoImagem) {
    campoImagem.value = "";
  }
}

function prepararEdicao(produto) {
  limparPreview();

  editandoId.value = produto.id;
  arquivoImagem.value = null;
  imagemAtual.value = produto.imagem ?? "";
  removerImagem.value = false;

  formulario.value = {
    nome: produto.nome ?? "",
    descricao: produto.descricao ?? "",
    preco: produto.preco ?? "",
    estoque: produto.estoque ?? 0,
  };

  erro.value = "";
  sucesso.value = "";

  const campoImagem = document.getElementById("imagem");

  if (campoImagem) {
    campoImagem.value = "";
  }

  window.scrollTo({ top: 0, behavior: "smooth" });
}

function cancelarEdicao() {
  limparFormulario();
  erro.value = "";
  sucesso.value = "";
}

async function salvarProduto() {
  erro.value = "";
  sucesso.value = "";

  const nome = formulario.value.nome.trim();
  const preco = Number(formulario.value.preco);
  const estoque = Number(formulario.value.estoque);
  const estaEditando = editandoId.value !== null;

  if (!nome) {
    erro.value = "Informe o nome do produto.";
    return;
  }

  if (formulario.value.preco === "" || !Number.isFinite(preco) || preco <= 0) {
    erro.value = "O preço do produto precisa ser maior que R$ 0,00.";
    return;
  }

  if (
    formulario.value.estoque === "" ||
    !Number.isInteger(estoque) ||
    estoque < 0
  ) {
    erro.value =
      "O estoque deve ser um número inteiro igual ou maior que zero.";
    return;
  }

  if (!estaEditando && estoque === 0) {
    erro.value = "Não é possível cadastrar um produto sem estoque.";
    return;
  }

  salvando.value = true;

  const dados = new FormData();

  dados.append("nome", nome);
  dados.append("descricao", formulario.value.descricao.trim());
  dados.append("preco", String(preco));
  dados.append("estoque", String(estoque));

  if (arquivoImagem.value) {
    dados.append("imagem", arquivoImagem.value);
  }

  if (estaEditando && removerImagem.value) {
    dados.append("remover_imagem", "1");
  }

  try {
    if (estaEditando) {
      dados.append("_method", "PATCH");

      await axios.post(`${api}/${editandoId.value}`, dados, {
        headers: cabecalhos(),
      });

      sucesso.value = "Produto atualizado com sucesso.";
    } else {
      await axios.post(api, dados, {
        headers: cabecalhos(),
      });

      sucesso.value = "Produto cadastrado com sucesso.";
    }

    limparFormulario();
    await carregarProdutos();
  } catch (e) {
    console.error(e);

    erro.value = mensagemErroApi(e, "Não foi possível salvar o produto.");
  } finally {
    salvando.value = false;
  }
}

async function excluirProduto(produto) {
  if (!window.confirm(`Deseja excluir "${produto.nome}"?`)) return;

  erro.value = "";
  sucesso.value = "";

  try {
    await axios.delete(`${api}/${produto.id}`, {
      headers: cabecalhos(),
    });

    produtos.value = produtos.value.filter((item) => item.id !== produto.id);

    if (editandoId.value === produto.id) {
      limparFormulario();
    }

    sucesso.value = "Produto excluído com sucesso.";
  } catch (e) {
    console.error(e);

    erro.value = mensagemErroApi(e, "Não foi possível excluir o produto.");
  }
}

function classeEstoque(estoque) {
  const quantidade = Number(estoque);

  if (quantidade <= 0) return "estoque-indisponivel";
  if (quantidade <= 5) return "estoque-baixo";

  return "estoque-disponivel";
}

onMounted(carregarProdutos);
onBeforeUnmount(limparPreview);
</script>

<template>
  <main class="admin-container">
    <nav class="abas-admin" aria-label="Seções da administração">
      <RouterLink
        to="/admin"
        class="aba-admin"
        :class="{ 'aba-ativa': route.path === '/admin' }"
      >
        Produtos
      </RouterLink>

      <RouterLink
        to="/admin/pedidos"
        class="aba-admin"
        :class="{ 'aba-ativa': route.path.startsWith('/admin/pedidos') }"
      >
        Pedidos
      </RouterLink>

      <RouterLink
        to="/admin/usuarios"
        class="aba-admin"
        :class="{ 'aba-ativa': route.path.startsWith('/admin/usuarios') }"
      >
        Usuários
      </RouterLink>
    </nav>

    <header class="admin-cabecalho">
      <div>
        <p class="eyebrow">PAINEL ADMINISTRATIVO</p>
        <h1>Produtos</h1>
        <p class="subtitulo">Gerencie o catálogo da sua loja.</p>
      </div>

      <RouterLink to="/" class="botao secundario">
        Voltar para a loja
      </RouterLink>
    </header>

    <section class="resumo-admin">
      <article class="resumo-card">
        <span>Produtos cadastrados</span>
        <strong>{{ quantidadeProdutos }}</strong>
      </article>

      <article class="resumo-card">
        <span>Unidades em estoque</span>
        <strong>{{ quantidadeEstoque }}</strong>
      </article>

      <article class="resumo-card">
        <span>Produtos indisponíveis</span>
        <strong>{{ quantidadeIndisponiveis }}</strong>
      </article>

      <article class="resumo-card">
        <span>Valor estimado do estoque</span>
        <strong>{{ formatarPreco(valorEstoque) }}</strong>
      </article>
    </section>

    <p v-if="erro" class="mensagem erro" role="alert">
      {{ erro }}
    </p>

    <p v-if="sucesso" class="mensagem sucesso" role="status">
      {{ sucesso }}
    </p>

    <section class="admin-card">
      <header class="secao-cabecalho">
        <div>
          <h2>
            {{ editandoId !== null ? "Editar produto" : "Cadastrar produto" }}
          </h2>
          <p>Preencha os dados do produto.</p>
        </div>
      </header>

      <form class="formulario-produto" @submit.prevent="salvarProduto">
        <div class="campo">
          <label for="nome">Nome *</label>
          <input
            id="nome"
            v-model.trim="formulario.nome"
            required
            maxlength="255"
            placeholder="Ex.: Notebook Gamer"
          />
        </div>

        <div class="campo">
          <label for="descricao">Descrição</label>
          <textarea
            id="descricao"
            v-model="formulario.descricao"
            rows="3"
            placeholder="Descrição do produto"
          />
        </div>

        <div class="linha-campos">
          <div class="campo">
            <label for="preco">Preço (R$) *</label>
            <input
              id="preco"
              v-model="formulario.preco"
              type="number"
              min="0.01"
              step="0.01"
              required
              placeholder="Ex.: 149,90"
            />
          </div>

          <div class="campo">
            <label for="estoque">Estoque *</label>
            <input
              id="estoque"
              v-model="formulario.estoque"
              type="number"
              :min="editandoId !== null ? 0 : 1"
              step="1"
              required
            />
          </div>
        </div>

        <div class="campo">
          <label for="imagem">Imagem do produto (opcional)</label>

          <input
            id="imagem"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            @change="selecionarImagem"
          />

          <small>JPG, PNG ou WebP. Tamanho máximo: 2 MB.</small>

          <div
            v-if="!removerImagem && (imagemPreview || imagemAtual)"
            class="preview-container"
          >
            <img
              :src="imagemPreview || imagemUrl(imagemAtual)"
              alt="Pré-visualização da imagem do produto"
              class="imagem-preview"
            />

            <div class="acoes-imagem">
              <span>
                {{ imagemPreview ? "Nova imagem selecionada" : "Imagem atual" }}
              </span>

              <button
                class="botao perigo"
                type="button"
                :disabled="salvando"
                @click="removerImagemSelecionada"
              >
                Remover imagem
              </button>
            </div>
          </div>

          <p
            v-if="
              editandoId !== null &&
              imagemAtual &&
              !arquivoImagem &&
              !removerImagem
            "
            class="ajuda"
          >
            Selecione outro arquivo para substituir a imagem atual ou clique em
            “Remover imagem” para excluí-la.
          </p>
        </div>

        <div class="acoes-formulario">
          <button class="botao principal" type="submit" :disabled="salvando">
            {{
              salvando
                ? "Salvando..."
                : editandoId !== null
                  ? "Salvar alterações"
                  : "Cadastrar produto"
            }}
          </button>

          <button
            v-if="editandoId !== null"
            class="botao secundario"
            type="button"
            :disabled="salvando"
            @click="cancelarEdicao"
          >
            Cancelar
          </button>
        </div>
      </form>
    </section>

    <section class="admin-card">
      <header class="secao-cabecalho">
        <div>
          <h2>Catálogo</h2>
          <p>{{ produtosFiltrados.length }} produto(s) encontrado(s).</p>
        </div>

        <button
          class="botao secundario"
          type="button"
          :disabled="carregando"
          @click="carregarProdutos"
        >
          Atualizar lista
        </button>
      </header>

      <div class="filtros-produtos">
        <input
          v-model="busca"
          type="search"
          placeholder="Buscar por nome ou descrição"
          aria-label="Buscar produtos"
        />

        <select v-model="ordenacao" aria-label="Ordenar produtos">
          <option value="recentes">Mais recentes</option>
          <option value="baratos">Menor preço</option>
          <option value="caros">Maior preço</option>
          <option value="estoque">Menor estoque</option>
        </select>
      </div>

      <p v-if="carregando" class="estado-lista">Carregando produtos...</p>

      <p v-else-if="produtosFiltrados.length === 0" class="estado-lista">
        Nenhum produto encontrado.
      </p>

      <div v-else class="lista-produtos">
        <article
          v-for="produto in produtosFiltrados"
          :key="produto.id"
          class="produto-card"
        >
          <div class="imagem-produto">
            <img
              v-if="produto.imagem"
              :src="imagemUrl(produto.imagem)"
              :alt="produto.nome"
            />

            <svg
              v-else
              class="icone-imagem"
              viewBox="0 0 48 48"
              fill="none"
              stroke="currentColor"
              stroke-width="1.8"
              stroke-linecap="round"
              stroke-linejoin="round"
              aria-label="Produto sem imagem"
              role="img"
            >
              <rect x="6" y="7" width="36" height="34" rx="4" />
              <circle cx="17" cy="18" r="3" />
              <path d="m9 35 10-10 7 7 5-5 8 8" />
            </svg>
          </div>

          <div class="dados-produto">
            <div class="nome-produto-linha">
              <h3>{{ produto.nome }}</h3>
              <span class="id-produto">ID {{ produto.id }}</span>
            </div>

            <p v-if="produto.descricao" class="descricao-produto">
              {{ produto.descricao }}
            </p>

            <div class="detalhes-produto">
              <strong>{{ formatarPreco(produto.preco) }}</strong>

              <span
                class="estado-estoque"
                :class="classeEstoque(produto.estoque)"
              >
                {{
                  Number(produto.estoque) > 0
                    ? `${produto.estoque} unidade(s) em estoque`
                    : "Indisponível"
                }}
              </span>
            </div>
          </div>

          <div class="acoes-produto">
            <button
              class="botao secundario"
              type="button"
              @click="prepararEdicao(produto)"
            >
              Editar
            </button>

            <button
              class="botao perigo"
              type="button"
              @click="excluirProduto(produto)"
            >
              Excluir
            </button>
          </div>
        </article>
      </div>
    </section>
  </main>
</template>

<style scoped>
.admin-container {
  width: min(1180px, calc(100% - 40px));
  margin: 0 auto;
  padding: 34px 0 72px;
  color: var(--text);
}

.abas-admin {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 30px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--border);
}

.aba-admin {
  padding: 11px 18px;
  border: 1px solid var(--border);
  border-radius: 6px;
  color: var(--muted);
  font-size: 13px;
  text-decoration: none;
}

.aba-admin:hover,
.aba-ativa {
  background: var(--surface);
  color: var(--text);
}

.aba-ativa {
  border-color: var(--text);
  font-weight: 700;
}

.admin-cabecalho,
.secao-cabecalho {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 18px;
}

.admin-cabecalho {
  margin-bottom: 28px;
}

.eyebrow {
  margin: 0 0 9px;
  color: var(--muted);
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 2px;
}

h1 {
  margin: 0;
  font-size: clamp(30px, 4vw, 40px);
  letter-spacing: -1.3px;
}

.subtitulo,
.secao-cabecalho p {
  margin: 9px 0 0;
  color: var(--muted);
  font-size: 13px;
  line-height: 1.6;
}

.resumo-admin {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 14px;
  margin-bottom: 24px;
}

.resumo-card,
.admin-card {
  min-width: 0;
  border: 1px solid var(--border);
  border-radius: 9px;
  background: var(--surface);
}

.resumo-card {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 20px;
}

.resumo-card span {
  color: var(--muted);
  font-size: 12px;
}

.resumo-card strong {
  font-size: 22px;
  overflow-wrap: anywhere;
}

.admin-card {
  padding: 24px;
  margin-bottom: 24px;
}

.secao-cabecalho {
  margin-bottom: 22px;
}

.secao-cabecalho h2 {
  margin: 0;
  font-size: 20px;
}

.formulario-produto {
  display: grid;
  gap: 18px;
}

.campo {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.campo label {
  font-size: 13px;
  font-weight: 650;
}

.campo input,
.campo textarea,
.filtros-produtos input,
.filtros-produtos select {
  width: 100%;
  min-width: 0;
  border: 1px solid var(--border);
  border-radius: 6px;
  padding: 12px;
  background: var(--background);
  color: var(--text);
  font: inherit;
  font-size: 13px;
}

.campo input[type="file"] {
  padding: 10px;
  cursor: pointer;
}

.campo textarea {
  resize: vertical;
}

.campo small,
.ajuda {
  color: var(--muted);
  font-size: 11px;
  line-height: 1.5;
}

.linha-campos {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}

.preview-container {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
  margin-top: 8px;
  color: var(--muted);
  font-size: 12px;
}

.imagem-preview {
  width: 100px;
  height: 100px;
  border: 1px solid var(--border);
  border-radius: 8px;
  object-fit: contain;
  background: var(--background);
}

.acoes-imagem {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 10px;
}

.aviso-remocao-imagem {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
  margin-top: 8px;
  padding: 12px;
  border: 1px solid var(--border);
  border-radius: 6px;
  font-size: 12px;
  line-height: 1.5;
}

.acoes-formulario,
.acoes-produto {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.botao {
  display: inline-flex;
  justify-content: center;
  align-items: center;
  padding: 10px 14px;
  border: 1px solid #d4d4d4;
  border-radius: 6px;
  font: inherit;
  font-size: 12px;
  font-weight: 700;
  text-decoration: none;
  cursor: pointer;
  opacity: 1;
}

.botao,
.botao:visited {
  -webkit-text-fill-color: currentColor;
}

.botao.principal {
  background: #202020 !important;
  border-color: #202020 !important;
  color: #ffffff !important;
  -webkit-text-fill-color: #ffffff !important;
}

.botao.secundario {
  background: #ffffff !important;
  border-color: #d4d4d4 !important;
  color: #202020 !important;
  -webkit-text-fill-color: #202020 !important;
}

.botao.perigo {
  background: #ffffff !important;
  border-color: #c36b5f !important;
  color: #b42318 !important;
  -webkit-text-fill-color: #b42318 !important;
}

.botao:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.mensagem {
  margin: 0 0 18px;
  padding: 13px 15px;
  border: 1px solid var(--border);
  border-radius: 6px;
  font-size: 13px;
  line-height: 1.5;
}

.erro {
  color: #c36b5f;
}

.sucesso {
  color: #39805b;
}

.filtros-produtos {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 190px;
  gap: 12px;
  margin-bottom: 20px;
}

.estado-lista {
  padding: 35px 15px;
  color: var(--muted);
  text-align: center;
  font-size: 13px;
}

.lista-produtos {
  display: grid;
  gap: 12px;
}

.produto-card {
  display: grid;
  grid-template-columns: 88px minmax(0, 1fr) auto;
  align-items: center;
  gap: 18px;
  padding: 15px;
  border: 1px solid var(--border);
  border-radius: 8px;
}

.imagem-produto {
  display: grid;
  place-items: center;
  width: 88px;
  height: 88px;
  overflow: hidden;
  border-radius: 6px;
  background: var(--background);
  color: var(--muted);
}

.imagem-produto img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.icone-imagem {
  width: 42px;
  height: 42px;
}

.dados-produto {
  min-width: 0;
}

.nome-produto-linha,
.detalhes-produto {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
}

.nome-produto-linha {
  justify-content: space-between;
}

.nome-produto-linha h3 {
  margin: 0;
  font-size: 14px;
  overflow-wrap: anywhere;
}

.id-produto {
  color: var(--muted);
  font-size: 10px;
}

.descricao-produto {
  display: -webkit-box;
  overflow: hidden;
  margin: 8px 0;
  color: var(--muted);
  font-size: 12px;
  line-height: 1.5;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.detalhes-produto {
  margin-top: 10px;
}

.detalhes-produto strong {
  font-size: 13px;
}

.estado-estoque {
  font-size: 11px;
}

.estoque-disponivel {
  color: #39805b;
}

.estoque-baixo {
  color: #b88836;
}

.estoque-indisponivel {
  color: #c36b5f;
}

@media (max-width: 900px) {
  .resumo-admin {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .produto-card {
    grid-template-columns: 76px minmax(0, 1fr);
  }

  .imagem-produto {
    width: 76px;
    height: 76px;
  }

  .acoes-produto {
    grid-column: 1 / -1;
  }
}

@media (max-width: 560px) {
  .admin-container {
    width: min(100% - 24px, 1180px);
    padding-top: 22px;
  }

  .admin-card {
    padding: 16px;
  }

  .admin-cabecalho,
  .secao-cabecalho {
    align-items: flex-start;
  }

  .admin-cabecalho {
    flex-direction: column;
  }

  .linha-campos,
  .filtros-produtos {
    grid-template-columns: 1fr;
  }

  .resumo-card {
    padding: 13px;
  }

  .resumo-card strong {
    font-size: 18px;
  }

  .aba-admin {
    padding: 10px 13px;
  }

  .produto-card {
    gap: 12px;
    padding: 12px;
  }

  .aviso-remocao-imagem {
    align-items: flex-start;
    flex-direction: column;
  }
}
</style>
