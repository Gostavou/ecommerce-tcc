<script setup>
import { onMounted, ref } from "vue";
import axios from "axios";
import { RouterLink, useRouter } from "vue-router";
import { token, estaAutenticado } from "../stores/auth";

const router = useRouter();
const API = "http://localhost:8000/api";
const baseUrl = "http://localhost:8000";

const pedidos = ref([]);
const carregando = ref(true);
const erro = ref("");
const pedidoAbertoId = ref(null);

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

function formatarPreco(valor) {
  return Number(valor ?? 0).toLocaleString("pt-BR", {
    style: "currency",
    currency: "BRL",
  });
}

function formatarData(data) {
  if (!data) return "Data não disponível";

  const dataValida = new Date(data);

  if (Number.isNaN(dataValida.getTime())) {
    return "Data não disponível";
  }

  return dataValida.toLocaleString("pt-BR", {
    dateStyle: "short",
    timeStyle: "short",
  });
}

function statusPedido(status) {
  const statusMapeado = {
    pendente: "Pendente",
    confirmado: "Confirmado",
    processando: "Em preparação",
    enviado: "Enviado",
    entregue: "Entregue",
    cancelado: "Cancelado",
  };

  return statusMapeado[status] || status || "Não informado";
}

function formaPagamentoTexto(forma) {
  const formas = {
    pix: "PIX",
    cartao_credito: "Cartão de crédito",
  };

  return formas[forma] || forma || "Não informado";
}

function statusPagamentoTexto(status) {
  const statusMapeado = {
    aprovado_simulado: "Aprovado",
    pendente: "Pendente",
    aprovado: "Aprovado",
    recusado: "Recusado",
    cancelado: "Cancelado",
  };

  return statusMapeado[status] || status || "Não informado";
}

function alternarDetalhes(pedidoId) {
  pedidoAbertoId.value = pedidoAbertoId.value === pedidoId ? null : pedidoId;
}

async function carregarPedidos() {
  carregando.value = true;
  erro.value = "";

  if (!estaAutenticado()) {
    router.replace("/login");
    return;
  }

  try {
    const resposta = await axios.get(`${API}/pedidos`, {
      headers: cabecalhos(),
    });

    pedidos.value = Array.isArray(resposta.data) ? resposta.data : [];
  } catch (e) {
    if (e.response?.status === 401) {
      erro.value = "Sua sessão expirou. Entre novamente.";
    } else {
      erro.value =
        "Não foi possível carregar seus pedidos. Verifique se o servidor está funcionando e tente novamente.";
    }
  } finally {
    carregando.value = false;
  }
}

onMounted(carregarPedidos);
</script>

<template>
  <main class="pagina-pedidos">
    <header class="cabecalho">
      <div>
        <h1>Meus pedidos</h1>
        <p class="subtitulo">
          Consulte suas compras e acompanhe os detalhes de cada pedido.
        </p>
      </div>

      <RouterLink to="/" class="link-voltar"> ← Voltar para a loja </RouterLink>
    </header>

    <div class="acoes-topo">
      <span>{{ pedidos.length }} pedido(s) encontrado(s)</span>

      <button
        type="button"
        class="botao-secundario"
        :disabled="carregando"
        @click="carregarPedidos"
      >
        {{ carregando ? "Atualizando..." : "Atualizar pedidos" }}
      </button>
    </div>

    <p v-if="carregando" class="estado" role="status">
      Carregando seu histórico de compras...
    </p>

    <section v-else-if="erro" class="estado estado-erro" role="alert">
      <p>{{ erro }}</p>

      <button type="button" class="botao-principal" @click="carregarPedidos">
        Tentar novamente
      </button>
    </section>

    <section v-else-if="pedidos.length === 0" class="estado vazio">
      <span class="icone-vazio" aria-hidden="true">♞</span>

      <h2>Você ainda não realizou compras</h2>

      <p>Quando finalizar um pedido, ele aparecerá aqui.</p>

      <RouterLink to="/" class="botao-principal">
        Explorar produtos
      </RouterLink>
    </section>

    <section v-else class="lista-pedidos">
      <article v-for="pedido in pedidos" :key="pedido.id" class="cartao-pedido">
        <header class="pedido-cabecalho">
          <div>
            <p class="rotulo">NÚMERO DO PEDIDO</p>
            <h2>#{{ pedido.id }}</h2>
            <p class="data-pedido">
              Realizado em {{ formatarData(pedido.created_at) }}
            </p>
          </div>

          <span class="etiqueta-status" :class="`status-${pedido.status}`">
            {{ statusPedido(pedido.status) }}
          </span>
        </header>

        <div class="resumo-pedido">
          <div class="resumo-item">
            <span>Produtos</span>
            <strong>
              {{
                (pedido.itens || []).reduce(
                  (soma, item) => soma + Number(item.quantidade || 0),
                  0,
                )
              }}
              unidade(s)
            </strong>
          </div>

          <div class="resumo-item">
            <span>Subtotal</span>
            <strong>{{ formatarPreco(pedido.subtotal) }}</strong>
          </div>

          <div class="resumo-item">
            <span>Frete</span>
            <strong>
              {{
                Number(pedido.frete) === 0
                  ? "Grátis"
                  : formatarPreco(pedido.frete)
              }}
            </strong>
          </div>

          <div class="resumo-item total">
            <span>Total</span>
            <strong>{{ formatarPreco(pedido.total) }}</strong>
          </div>
        </div>

        <button
          type="button"
          class="botao-detalhes"
          :aria-expanded="pedidoAbertoId === pedido.id"
          @click="alternarDetalhes(pedido.id)"
        >
          {{
            pedidoAbertoId === pedido.id
              ? "Ocultar detalhes"
              : "Ver detalhes do pedido"
          }}

          <span aria-hidden="true">
            {{ pedidoAbertoId === pedido.id ? "−" : "+" }}
          </span>
        </button>

        <div v-if="pedidoAbertoId === pedido.id" class="detalhes-pedido">
          <section class="bloco-detalhes">
            <h3>Produtos comprados</h3>

            <div
              v-for="(item, indice) in pedido.itens || []"
              :key="item.id ?? indice"
              class="linha-produto"
            >
              <img
                v-if="item.produto?.imagem"
                :src="imagemUrl(item.produto.imagem)"
                :alt="item.nome_produto"
                class="imagem-item-pedido"
                loading="lazy"
              />

              <div class="informacoes-produto">
                <strong>{{ item.nome_produto }}</strong>

                <span>
                  {{ item.quantidade }} ×
                  {{ formatarPreco(item.preco_unitario) }}
                </span>
              </div>

              <strong class="subtotal-item">
                {{ formatarPreco(item.subtotal) }}
              </strong>
            </div>

            <p
              v-if="!pedido.itens || pedido.itens.length === 0"
              class="texto-secundario"
            >
              Não há itens disponíveis para exibir neste pedido.
            </p>
          </section>

          <section class="bloco-detalhes">
            <h3>Endereço de entrega</h3>

            <p>
              <strong>
                {{ pedido.endereco_nome || "Endereço utilizado na compra" }}
              </strong>
            </p>

            <p>
              {{ pedido.rua }}, {{ pedido.numero }}
              <span v-if="pedido.complemento">
                — {{ pedido.complemento }}
              </span>
            </p>

            <p>{{ pedido.bairro }} — {{ pedido.cidade }}/{{ pedido.estado }}</p>

            <p>CEP: {{ pedido.cep }}</p>
          </section>

          <section class="bloco-detalhes">
            <h3>Pagamento</h3>

            <p>
              Forma:
              <strong>
                {{ formaPagamentoTexto(pedido.forma_pagamento) }}
              </strong>
            </p>

            <p>
              Status do pagamento:
              <strong>
                {{ statusPagamentoTexto(pedido.status_pagamento) }}
              </strong>
            </p>

            <p class="nota-pagamento">O pagamento é simulado.</p>
          </section>
        </div>
      </article>
    </section>
  </main>
</template>

<style scoped>
.pagina-pedidos {
  width: min(1000px, calc(100% - 40px));
  margin: 0 auto;
  padding: 36px 0 72px;
  color: var(--text);
}

.cabecalho {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 20px;
  margin-bottom: 28px;
}

.eyebrow {
  margin: 0 0 10px;
  color: var(--muted);
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 2px;
}

h1 {
  margin: 0;
  font-size: clamp(30px, 4vw, 38px);
  letter-spacing: -1.5px;
}

.subtitulo {
  margin: 10px 0 0;
  color: var(--muted);
  font-size: 14px;
  line-height: 1.6;
}

.link-voltar {
  flex-shrink: 0;
  color: var(--text);
  font-size: 13px;
  text-decoration: none;
}

.link-voltar:hover {
  text-decoration: underline;
}

.acoes-topo {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  margin-bottom: 20px;
  color: var(--muted);
  font-size: 12px;
}

.botao-secundario {
  padding: 10px 13px;
  border: 1px solid var(--border);
  border-radius: 7px;
  background: var(--surface);
  color: var(--text);
  font-size: 12px;
  font-weight: 650;
  cursor: pointer;
}

.botao-secundario:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.estado {
  padding: 45px 20px;
  border: 1px solid var(--border);
  border-radius: 12px;
  background: var(--surface);
  color: var(--muted);
  font-size: 13px;
  line-height: 1.7;
  text-align: center;
}

.estado h2 {
  margin: 12px 0 8px;
  color: var(--text);
  font-size: 19px;
}

.estado p {
  margin: 8px 0 18px;
}

.icone-vazio {
  color: var(--muted);
  font-size: 38px;
}

.botao-principal {
  display: inline-flex;
  min-height: 42px;
  align-items: center;
  justify-content: center;
  padding: 12px 16px;
  border: 1px solid var(--accent);
  border-radius: 7px;
  background: var(--accent);
  color: var(--accent-contrast);
  font-size: 12px;
  font-weight: 750;
  text-decoration: none;
  cursor: pointer;
}

.estado-erro {
  color: #c0392b;
}

.lista-pedidos {
  display: grid;
  gap: 18px;
}

.cartao-pedido {
  overflow: hidden;
  border: 1px solid var(--border);
  border-radius: 12px;
  background: var(--surface);
  box-shadow: var(--shadow);
}

.pedido-cabecalho {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  padding: 22px 24px;
}

.rotulo {
  margin: 0 0 7px;
  color: var(--muted);
  font-size: 9px;
  font-weight: 800;
  letter-spacing: 1.5px;
}

.pedido-cabecalho h2 {
  margin: 0;
  font-size: 22px;
}

.data-pedido {
  margin: 7px 0 0;
  color: var(--muted);
  font-size: 12px;
}

.etiqueta-status {
  display: inline-flex;
  align-items: center;
  padding: 6px 9px;
  border: 1px solid var(--border);
  border-radius: 6px;
  color: var(--text);
  font-size: 10px;
  font-weight: 750;
  white-space: nowrap;
}

.status-confirmado,
.status-entregue {
  border-color: #27804a55;
  color: #27804a;
}

.status-cancelado {
  border-color: #c0392b55;
  color: #c0392b;
}

.resumo-pedido {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 16px;
  padding: 18px 24px;
  border-top: 1px solid var(--border);
  border-bottom: 1px solid var(--border);
  background: var(--bg);
}

.resumo-item {
  display: flex;
  flex-direction: column;
  gap: 7px;
}

.resumo-item span {
  color: var(--muted);
  font-size: 11px;
}

.resumo-item strong {
  font-size: 13px;
}

.resumo-item.total strong {
  font-size: 16px;
}

.botao-detalhes {
  display: flex;
  width: 100%;
  align-items: center;
  justify-content: space-between;
  padding: 16px 24px;
  border: 0;
  background: transparent;
  color: var(--text);
  font-size: 12px;
  font-weight: 700;
  text-align: left;
  cursor: pointer;
}

.botao-detalhes:hover {
  background: var(--bg);
}

.botao-detalhes span {
  font-size: 18px;
}

.detalhes-pedido {
  display: grid;
  gap: 22px;
  padding: 22px 24px;
  border-top: 1px solid var(--border);
}

.bloco-detalhes h3 {
  margin: 0 0 14px;
  font-size: 14px;
}

.bloco-detalhes p {
  margin: 6px 0;
  color: var(--muted);
  font-size: 12px;
  line-height: 1.6;
  overflow-wrap: anywhere;
}

.bloco-detalhes p strong {
  color: var(--text);
}

.linha-produto {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  padding: 12px 0;
  border-bottom: 1px solid var(--border);
  font-size: 12px;
}

.linha-produto:first-of-type {
  border-top: 1px solid var(--border);
}

.imagem-item-pedido {
  display: block;
  width: 64px;
  height: 64px;
  flex: 0 0 64px;
  border: 1px solid var(--border);
  border-radius: 8px;
  background: var(--surface);
  object-fit: cover;
}

.informacoes-produto {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 5px;
  min-width: 0;
}

.informacoes-produto strong {
  overflow-wrap: anywhere;
}

.informacoes-produto span {
  color: var(--muted);
  font-size: 11px;
}

.subtotal-item {
  flex-shrink: 0;
  text-align: right;
}

.texto-secundario,
.nota-pagamento {
  color: var(--muted);
  font-size: 11px;
  line-height: 1.6;
}

@media (max-width: 650px) {
  .pagina-pedidos {
    width: calc(100% - 28px);
    padding: 27px 0 48px;
  }

  .cabecalho {
    align-items: flex-start;
    flex-direction: column;
    gap: 15px;
  }

  .resumo-pedido {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    padding: 17px;
  }

  .pedido-cabecalho {
    padding: 18px 17px;
  }

  .botao-detalhes {
    padding: 15px 17px;
  }

  .detalhes-pedido {
    padding: 18px 17px;
  }
}

@media (max-width: 390px) {
  .acoes-topo {
    align-items: flex-start;
    flex-direction: column;
  }

  .etiqueta-status {
    white-space: normal;
  }

  .resumo-pedido {
    gap: 20px 12px;
  }

  .linha-produto {
    flex-wrap: wrap;
    gap: 10px;
  }

  .imagem-item-pedido {
    width: 54px;
    height: 54px;
    flex-basis: 54px;
  }

  .subtotal-item {
    margin-left: auto;
  }
}
</style>
