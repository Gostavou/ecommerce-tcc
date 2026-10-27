<script setup>
import { computed, onMounted, ref } from "vue";
import { RouterLink, useRoute } from "vue-router";
import axios from "axios";
import { token } from "../stores/auth";

const API = "http://localhost:8000/api";
const BASE_URL = "http://localhost:8000";
const route = useRoute();

const pedidos = ref([]);
const statusSelecionados = ref({});
const errosPedido = ref({});
const sucessosPedido = ref({});
const carregando = ref(false);
const erroCarregamento = ref("");
const atualizandoId = ref(null);
const filtroStatus = ref("todos");
const busca = ref("");
const pedidoAbertoId = ref(null);

const transicoesPermitidas = {
  pendente: ["confirmado"],
  confirmado: ["processando"],
  processando: ["enviado"],
  enviado: ["entregue"],
  entregue: [],
  cancelado: [],
};

const statusDisponiveis = [
  { valor: "pendente", texto: "Pendente" },
  { valor: "confirmado", texto: "Confirmado" },
  { valor: "processando", texto: "Em preparação" },
  { valor: "enviado", texto: "Enviado" },
  { valor: "entregue", texto: "Entregue" },
  { valor: "cancelado", texto: "Cancelado" },
];

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

  return `${BASE_URL}/storage/${imagem.replace(/^\//, "")}`;
}

function formatarPreco(valor) {
  const numero = Number(valor);

  if (!Number.isFinite(numero)) return "—";

  return numero.toLocaleString("pt-BR", {
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

function normalizarStatus(status) {
  return String(status ?? "")
    .trim()
    .toLowerCase();
}

function textoStatus(status) {
  const valor = normalizarStatus(status);

  const encontrado = statusDisponiveis.find((item) => item.valor === valor);

  return encontrado?.texto ?? status ?? "Não informado";
}

function classeStatus(status) {
  return `status-${normalizarStatus(status)}`;
}

function nomeCliente(pedido) {
  return (
    pedido.usuario?.name ??
    pedido.usuario?.nome ??
    pedido.user?.name ??
    pedido.user?.nome ??
    pedido.nome_cliente ??
    pedido.cliente?.name ??
    pedido.cliente?.nome ??
    "Cliente não informado"
  );
}

function emailCliente(pedido) {
  return (
    pedido.usuario?.email ??
    pedido.user?.email ??
    pedido.cliente?.email ??
    pedido.email_cliente ??
    ""
  );
}

function itensPedido(pedido) {
  return Array.isArray(pedido.itens) ? pedido.itens : [];
}

function quantidadeUnidades(pedido) {
  return itensPedido(pedido).reduce(
    (soma, item) => soma + Number(item.quantidade ?? item.quantity ?? 0),
    0,
  );
}

function precoUnitario(item) {
  return Number(
    item.preco_unitario ??
      item.preco ??
      item.unit_price ??
      item.produto?.preco ??
      0,
  );
}

function subtotalItem(item) {
  if (item.subtotal !== undefined && item.subtotal !== null) {
    return Number(item.subtotal);
  }

  return precoUnitario(item) * Number(item.quantidade ?? item.quantity ?? 1);
}

function subtotalPedido(pedido) {
  if (pedido.subtotal !== undefined && pedido.subtotal !== null) {
    return Number(pedido.subtotal);
  }

  return itensPedido(pedido).reduce(
    (soma, item) => soma + subtotalItem(item),
    0,
  );
}

function fretePedido(pedido) {
  return Number(pedido.frete ?? pedido.valor_frete ?? 0);
}

function totalPedido(pedido) {
  if (pedido.total !== undefined && pedido.total !== null) {
    return Number(pedido.total);
  }

  if (pedido.valor_total !== undefined && pedido.valor_total !== null) {
    return Number(pedido.valor_total);
  }

  return subtotalPedido(pedido) + fretePedido(pedido);
}

function pagamentoPedido(pedido) {
  if (pedido.pagamento && !Array.isArray(pedido.pagamento)) {
    return pedido.pagamento;
  }

  if (pedido.payment && !Array.isArray(pedido.payment)) {
    return pedido.payment;
  }

  if (Array.isArray(pedido.pagamentos) && pedido.pagamentos.length) {
    return pedido.pagamentos[0];
  }

  if (Array.isArray(pedido.payments) && pedido.payments.length) {
    return pedido.payments[0];
  }

  if (Array.isArray(pedido.pagamento) && pedido.pagamento.length) {
    return pedido.pagamento[0];
  }

  if (Array.isArray(pedido.payment) && pedido.payment.length) {
    return pedido.payment[0];
  }

  return null;
}

function formaPagamentoValor(pedido) {
  const pagamento = pagamentoPedido(pedido);

  return (
    pedido.forma_pagamento ??
    pedido.metodo_pagamento ??
    pedido.payment_method ??
    pedido.metodo ??
    pagamento?.forma_pagamento ??
    pagamento?.metodo_pagamento ??
    pagamento?.metodo ??
    pagamento?.method ??
    pagamento?.payment_method ??
    pagamento?.payment_method_name ??
    null
  );
}

function formatarFormaPagamento(forma) {
  if (!forma) return "Não informado";

  if (typeof forma === "object") {
    forma =
      forma.nome ??
      forma.name ??
      forma.label ??
      forma.tipo ??
      forma.method ??
      forma.payment_method ??
      "";
  }

  const original = String(forma).trim();

  if (!original) return "Não informado";

  const valor = original.toLowerCase();

  const formasConhecidas = {
    pix: "PIX",
    cartao_credito: "Cartão de crédito",
    cartao_de_credito: "Cartão de crédito",
    cartao_debito: "Cartão de débito",
    cartao_de_debito: "Cartão de débito",
    "cartao debito": "Cartão de débito",
    boleto: "Boleto bancário",
    dinheiro: "Dinheiro",
  };

  if (formasConhecidas[valor]) {
    return formasConhecidas[valor];
  }

  return original
    .replace(/[_-]+/g, " ")
    .replace(/\s+/g, " ")
    .replace(/\b\p{L}/gu, (letra) => letra.toLocaleUpperCase("pt-BR"));
}

function statusPagamentoValor(pedido) {
  const pagamento = pagamentoPedido(pedido);

  return (
    pedido.status_pagamento ??
    pedido.payment_status ??
    pagamento?.status_pagamento ??
    pagamento?.payment_status ??
    pagamento?.status ??
    "Não informado"
  );
}

function textoStatusPagamento(status) {
  if (!status || status === "Não informado") {
    return "Não informado";
  }

  const valor = String(status).trim().toLowerCase();

  const rotulos = {
    aprovado_simulado: "Aprovado",
    aprovado: "Aprovado",
    approved: "Aprovado",
    succeeded: "Aprovado",
    paid: "Pago",
    paid_out: "Pago",
    pago: "Pago",
    pending: "Pendente",
    pendente: "Pendente",
    unpaid: "Não pago",
    failed: "Falhou",
    recusado: "Recusado",
    falhou: "Falhou",
    refunded: "Estornado",
    estornado: "Estornado",
    canceled: "Cancelado",
    cancelled: "Cancelado",
    cancelado: "Cancelado",
  };

  return rotulos[valor] ?? formatarFormaPagamento(status);
}

function valorPagamento(pedido) {
  const pagamento = pagamentoPedido(pedido);

  return (
    pagamento?.amount ??
    pagamento?.valor ??
    pagamento?.valor_pago ??
    pedido.valor_pago ??
    null
  );
}

function dataPagamento(pedido) {
  const pagamento = pagamentoPedido(pedido);

  return (
    pagamento?.paid_at ??
    pagamento?.data_pagamento ??
    pagamento?.pago_em ??
    pedido.paid_at ??
    null
  );
}

function enderecoPedido(pedido) {
  const endereco = pedido.endereco ?? {};

  return {
    nome:
      pedido.endereco_nome ??
      endereco.nome ??
      endereco.name ??
      "Endereço utilizado na compra",
    rua: pedido.rua ?? endereco.rua ?? endereco.street ?? "",
    numero: pedido.numero ?? endereco.numero ?? endereco.number ?? "",
    complemento:
      pedido.complemento ?? endereco.complemento ?? endereco.complement ?? "",
    bairro: pedido.bairro ?? endereco.bairro ?? endereco.neighborhood ?? "",
    cidade: pedido.cidade ?? endereco.cidade ?? endereco.city ?? "",
    estado: pedido.estado ?? endereco.estado ?? endereco.state ?? "",
    cep: pedido.cep ?? endereco.cep ?? endereco.postal_code ?? "",
  };
}

function statusDoPedido(pedido) {
  return (
    statusSelecionados.value[pedido.id] ??
    normalizarStatus(pedido.status) ??
    "pendente"
  );
}

function proximaEtapa(pedido) {
  const atual = normalizarStatus(pedido.status);
  const proxima = transicoesPermitidas[atual]?.[0];

  return statusDisponiveis.find((item) => item.valor === proxima) ?? null;
}

function etapaFinalizada(pedido) {
  const status = normalizarStatus(pedido.status);

  return (
    status === "entregue" ||
    status === "cancelado" ||
    !Object.prototype.hasOwnProperty.call(transicoesPermitidas, status)
  );
}

function nomeStatusAtual(pedido) {
  return textoStatus(pedido.status);
}

function alternarDetalhes(pedidoId) {
  pedidoAbertoId.value = pedidoAbertoId.value === pedidoId ? null : pedidoId;
}

function mensagemErroApi(e, padrao) {
  if (e.response?.status === 401) {
    return "Sua sessão expirou. Entre novamente.";
  }

  if (e.response?.status === 403) {
    return "Você não tem permissão para realizar esta ação.";
  }

  if (e.response?.status === 404) {
    return "O pedido não foi encontrado ou a rota não existe.";
  }

  if (e.response?.status === 422) {
    const erros = e.response.data?.errors;

    return erros
      ? Object.values(erros).flat().join(" ")
      : e.response.data?.message ||
          "O status enviado não foi aceito pelo servidor.";
  }

  return e.response?.data?.message || padrao;
}

const pedidosFiltrados = computed(() => {
  const termo = busca.value.trim().toLocaleLowerCase("pt-BR");

  return pedidos.value.filter((pedido) => {
    const status = normalizarStatus(pedido.status);

    const correspondeStatus =
      filtroStatus.value === "todos" || status === filtroStatus.value;

    const correspondeBusca =
      !termo ||
      String(pedido.id).includes(termo) ||
      nomeCliente(pedido).toLocaleLowerCase("pt-BR").includes(termo) ||
      emailCliente(pedido).toLocaleLowerCase("pt-BR").includes(termo);

    return correspondeStatus && correspondeBusca;
  });
});

const quantidadePedidos = computed(() => pedidos.value.length);

const quantidadePendentes = computed(
  () =>
    pedidos.value.filter(
      (pedido) => normalizarStatus(pedido.status) === "pendente",
    ).length,
);

const quantidadeEmPreparacao = computed(
  () =>
    pedidos.value.filter(
      (pedido) => normalizarStatus(pedido.status) === "processando",
    ).length,
);

const valorEntregue = computed(() =>
  pedidos.value
    .filter((pedido) => normalizarStatus(pedido.status) === "entregue")
    .reduce((total, pedido) => total + totalPedido(pedido), 0),
);

async function carregarPedidos() {
  carregando.value = true;
  erroCarregamento.value = "";

  try {
    const resposta = await axios.get(`${API}/admin/pedidos`, {
      headers: cabecalhos(),
    });

    const lista = Array.isArray(resposta.data)
      ? resposta.data
      : (resposta.data.data ?? resposta.data.pedidos ?? []);

    pedidos.value = lista;

    const selecoes = {};

    for (const pedido of lista) {
      selecoes[pedido.id] = normalizarStatus(pedido.status) || "pendente";
    }

    statusSelecionados.value = selecoes;
    errosPedido.value = {};
    sucessosPedido.value = {};
  } catch (e) {
    console.error(e);

    erroCarregamento.value = mensagemErroApi(
      e,
      "Não foi possível carregar os pedidos. Confira se o Laravel está rodando.",
    );
  } finally {
    carregando.value = false;
  }
}

function selecionarStatus(pedido, novoStatus) {
  statusSelecionados.value[pedido.id] = novoStatus;
  errosPedido.value[pedido.id] = "";
  sucessosPedido.value[pedido.id] = "";
}

async function atualizarStatus(pedido) {
  const id = pedido.id;

  errosPedido.value[id] = "";
  sucessosPedido.value[id] = "";

  const statusAtual = normalizarStatus(pedido.status);
  const novoStatus = normalizarStatus(statusDoPedido(pedido));
  const permitidos = transicoesPermitidas[statusAtual] ?? [];

  if (!permitidos.includes(novoStatus)) {
    statusSelecionados.value[id] = statusAtual;

    if (etapaFinalizada(pedido)) {
      errosPedido.value[id] =
        `O pedido está "${nomeStatusAtual(pedido)}" e não pode avançar para outra etapa.`;
    } else {
      const proxima = proximaEtapa(pedido);

      errosPedido.value[id] = proxima
        ? `Alteração inválida. A próxima etapa permitida é "${proxima.texto}".`
        : "Não existe uma próxima etapa permitida para este pedido.";
    }

    return;
  }

  atualizandoId.value = id;

  try {
    const resposta = await axios.patch(
      `${API}/admin/pedidos/${id}/status`,
      { status: novoStatus },
      { headers: cabecalhos() },
    );

    const pedidoRetornado = resposta.data?.pedido;

    pedidos.value = pedidos.value.map((item) => {
      if (item.id !== id) return item;

      return {
        ...item,
        ...(pedidoRetornado ?? {}),
        status: normalizarStatus(pedidoRetornado?.status) || novoStatus,
      };
    });

    statusSelecionados.value[id] =
      normalizarStatus(pedidoRetornado?.status) || novoStatus;

    sucessosPedido.value[id] =
      resposta.data?.message ??
      resposta.data?.mensagem ??
      `Status atualizado para "${textoStatus(novoStatus)}".`;
  } catch (e) {
    console.error(e);

    statusSelecionados.value[id] = statusAtual;

    errosPedido.value[id] = mensagemErroApi(
      e,
      "Não foi possível atualizar este pedido. Confira as transições permitidas no PedidoController.",
    );
  } finally {
    atualizandoId.value = null;
  }
}

onMounted(carregarPedidos);
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
        <h1>Pedidos</h1>
        <p class="subtitulo">
          Consulte as compras, confira os pagamentos e acompanhe as entregas.
        </p>
      </div>

      <RouterLink to="/" class="botao secundario">
        Voltar para a loja
      </RouterLink>
    </header>

    <p v-if="erroCarregamento" class="mensagem erro" role="alert">
      {{ erroCarregamento }}
    </p>

    <section class="resumo-admin">
      <article class="resumo-card">
        <span>Total de pedidos</span>
        <strong>{{ quantidadePedidos }}</strong>
      </article>

      <article class="resumo-card">
        <span>Pendentes</span>
        <strong>{{ quantidadePendentes }}</strong>
      </article>

      <article class="resumo-card">
        <span>Em preparação</span>
        <strong>{{ quantidadeEmPreparacao }}</strong>
      </article>

      <article class="resumo-card">
        <span>Valor de pedidos entregues</span>
        <strong>{{ formatarPreco(valorEntregue) }}</strong>
      </article>
    </section>

    <section class="admin-card">
      <header class="secao-cabecalho">
        <div>
          <h2>Pedidos registrados</h2>
          <p>{{ pedidosFiltrados.length }} pedido(s) encontrado(s).</p>
        </div>

        <button
          class="botao secundario"
          type="button"
          :disabled="carregando"
          @click="carregarPedidos"
        >
          {{ carregando ? "Atualizando..." : "Atualizar lista" }}
        </button>
      </header>

      <div class="filtros-pedidos">
        <input
          v-model="busca"
          type="search"
          placeholder="Buscar por pedido, cliente ou e-mail"
          aria-label="Buscar pedidos"
        />

        <select v-model="filtroStatus" aria-label="Filtrar pedidos por status">
          <option value="todos">Todos os status</option>

          <option
            v-for="status in statusDisponiveis"
            :key="status.valor"
            :value="status.valor"
          >
            {{ status.texto }}
          </option>
        </select>
      </div>

      <p v-if="carregando && pedidos.length === 0" class="estado-lista">
        Carregando pedidos...
      </p>

      <p v-else-if="pedidosFiltrados.length === 0" class="estado-lista">
        Nenhum pedido encontrado.
      </p>

      <div v-else class="lista-pedidos">
        <article
          v-for="pedido in pedidosFiltrados"
          :key="pedido.id"
          class="cartao-pedido"
        >
          <header class="pedido-cabecalho">
            <div>
              <p class="rotulo">NÚMERO DO PEDIDO</p>
              <h2>#{{ pedido.id }}</h2>
              <p class="data-pedido">
                Realizado em
                {{ formatarData(pedido.created_at ?? pedido.data) }}
              </p>
            </div>

            <span class="etiqueta-status" :class="classeStatus(pedido.status)">
              {{ textoStatus(pedido.status) }}
            </span>
          </header>

          <section class="informacoes-cliente">
            <div>
              <span class="rotulo">CLIENTE</span>
              <strong>{{ nomeCliente(pedido) }}</strong>
              <span v-if="emailCliente(pedido)" class="texto-secundario">
                {{ emailCliente(pedido) }}
              </span>
            </div>
          </section>

          <div class="resumo-pedido">
            <div class="resumo-item">
              <span>Produtos</span>
              <strong>{{ quantidadeUnidades(pedido) }} unidade(s)</strong>
            </div>

            <div class="resumo-item">
              <span>Subtotal</span>
              <strong>{{ formatarPreco(subtotalPedido(pedido)) }}</strong>
            </div>

            <div class="resumo-item">
              <span>Frete</span>
              <strong>
                {{
                  fretePedido(pedido) === 0
                    ? "Grátis"
                    : formatarPreco(fretePedido(pedido))
                }}
              </strong>
            </div>

            <div class="resumo-item total">
              <span>Total</span>
              <strong>{{ formatarPreco(totalPedido(pedido)) }}</strong>
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
                v-for="(item, indice) in itensPedido(pedido)"
                :key="item.id ?? indice"
                class="linha-produto"
              >
                <img
                  v-if="item.produto?.imagem"
                  :src="imagemUrl(item.produto.imagem)"
                  :alt="
                    item.nome_produto ??
                    item.produto?.nome ??
                    item.nome ??
                    'Produto'
                  "
                  class="imagem-item-pedido"
                  loading="lazy"
                />

                <div
                  v-else
                  class="imagem-item-pedido imagem-sem-foto"
                  aria-label="Produto sem imagem"
                >
                  <svg
                    viewBox="0 0 48 48"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                  >
                    <rect x="6" y="7" width="36" height="34" rx="4" />
                    <circle cx="17" cy="18" r="3" />
                    <path d="m9 35 10-10 7 7 5-5 8 8" />
                  </svg>
                </div>

                <div class="informacoes-produto">
                  <strong>
                    {{
                      item.nome_produto ??
                      item.produto?.nome ??
                      item.nome ??
                      "Produto"
                    }}
                  </strong>

                  <span>
                    {{ item.quantidade ?? item.quantity ?? 1 }} ×
                    {{ formatarPreco(precoUnitario(item)) }}
                  </span>
                </div>

                <strong class="subtotal-item">
                  {{ formatarPreco(subtotalItem(item)) }}
                </strong>
              </div>

              <p
                v-if="itensPedido(pedido).length === 0"
                class="texto-secundario"
              >
                Não há itens disponíveis para exibir neste pedido.
              </p>
            </section>

            <section class="bloco-detalhes">
              <h3>Endereço de entrega</h3>

              <template v-if="enderecoPedido(pedido).rua">
                <p>
                  <strong>{{ enderecoPedido(pedido).nome }}</strong>
                </p>

                <p>
                  {{ enderecoPedido(pedido).rua }},
                  {{ enderecoPedido(pedido).numero }}

                  <span v-if="enderecoPedido(pedido).complemento">
                    — {{ enderecoPedido(pedido).complemento }}
                  </span>
                </p>

                <p>
                  {{ enderecoPedido(pedido).bairro }}
                  <template v-if="enderecoPedido(pedido).cidade">
                    — {{ enderecoPedido(pedido).cidade }}
                  </template>
                  <template v-if="enderecoPedido(pedido).estado">
                    /{{ enderecoPedido(pedido).estado }}
                  </template>
                </p>

                <p>CEP: {{ enderecoPedido(pedido).cep || "Não informado" }}</p>
              </template>

              <p v-else class="texto-secundario">
                Nenhum endereço de entrega foi retornado pela API.
              </p>
            </section>

            <section class="bloco-detalhes">
              <h3>Pagamento</h3>

              <p>
                Forma:
                <strong>
                  {{ formatarFormaPagamento(formaPagamentoValor(pedido)) }}
                </strong>
              </p>

              <p>
                Status do pagamento:
                <strong>
                  {{ textoStatusPagamento(statusPagamentoValor(pedido)) }}
                </strong>
              </p>

              <p v-if="valorPagamento(pedido) !== null">
                Valor pago:
                <strong>{{ formatarPreco(valorPagamento(pedido)) }}</strong>
              </p>

              <p v-if="dataPagamento(pedido)">
                Data do pagamento:
                <strong>{{ formatarData(dataPagamento(pedido)) }}</strong>
              </p>

              <p class="nota-pagamento">O pagamento é simulado.</p>
            </section>
          </div>

          <footer class="acoes-pedido">
            <div class="controle-status">
              <label :for="`status-${pedido.id}`">
                Atualizar status do pedido
              </label>

              <select
                :id="`status-${pedido.id}`"
                :value="statusDoPedido(pedido)"
                :disabled="atualizandoId === pedido.id"
                @change="selecionarStatus(pedido, $event.target.value)"
              >
                <option
                  v-for="status in statusDisponiveis"
                  :key="status.valor"
                  :value="status.valor"
                >
                  {{ status.texto }}
                </option>
              </select>

              <small v-if="proximaEtapa(pedido) && !etapaFinalizada(pedido)">
                Próxima etapa permitida:
                {{ proximaEtapa(pedido).texto }}.
              </small>

              <small v-else-if="etapaFinalizada(pedido)" class="aviso-final">
                Este pedido está em um estado final.
              </small>
            </div>

            <button
              type="button"
              class="botao principal"
              :disabled="
                atualizandoId === pedido.id ||
                statusDoPedido(pedido) === normalizarStatus(pedido.status)
              "
              @click="atualizarStatus(pedido)"
            >
              {{
                atualizandoId === pedido.id ? "Salvando..." : "Salvar status"
              }}
            </button>
          </footer>

          <p
            v-if="errosPedido[pedido.id]"
            class="feedback-pedido erro"
            role="alert"
          >
            {{ errosPedido[pedido.id] }}
          </p>

          <p
            v-if="sucessosPedido[pedido.id]"
            class="feedback-pedido sucesso"
            role="status"
          >
            {{ sucessosPedido[pedido.id] }}
          </p>
        </article>
      </div>
    </section>
  </main>
</template>

<style scoped>
.admin-container {
  width: min(1000px, calc(100% - 40px));
  margin: 0 auto;
  padding: 36px 0 72px;
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

.admin-cabecalho {
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

.botao {
  display: inline-flex;
  min-height: 40px;
  align-items: center;
  justify-content: center;
  padding: 10px 13px;
  border: 1px solid #d4d4d4;
  border-radius: 7px;
  font: inherit;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  text-decoration: none;
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

.botao:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.resumo-admin {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 14px;
  margin-bottom: 24px;
}

.resumo-card,
.admin-card,
.cartao-pedido {
  min-width: 0;
  border: 1px solid var(--border);
  border-radius: 12px;
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
  font-size: 21px;
  overflow-wrap: anywhere;
}

.admin-card {
  padding: 24px;
}

.secao-cabecalho {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 18px;
  margin-bottom: 22px;
}

.secao-cabecalho h2 {
  margin: 0;
  font-size: 20px;
}

.secao-cabecalho p {
  margin: 9px 0 0;
  color: var(--muted);
  font-size: 13px;
  line-height: 1.6;
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
  color: #b42318;
}

.sucesso {
  color: #247247;
}

.filtros-pedidos {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 200px;
  gap: 12px;
  margin-bottom: 20px;
}

.filtros-pedidos input,
.filtros-pedidos select,
.controle-status select {
  width: 100%;
  min-width: 0;
  border: 1px solid var(--border);
  border-radius: 7px;
  padding: 11px;
  background: var(--background);
  color: var(--text);
  font: inherit;
  font-size: 13px;
}

.estado-lista {
  padding: 45px 20px;
  color: var(--muted);
  font-size: 13px;
  line-height: 1.7;
  text-align: center;
}

.lista-pedidos {
  display: grid;
  gap: 18px;
}

.cartao-pedido {
  overflow: hidden;
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
  display: block;
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

.status-processando,
.status-enviado {
  border-color: #245ea855;
  color: #245ea8;
}

.status-pendente {
  border-color: #b8883655;
  color: #946200;
}

.status-cancelado {
  border-color: #c0392b55;
  color: #c0392b;
}

.informacoes-cliente {
  padding: 14px 24px;
  border-top: 1px solid var(--border);
}

.informacoes-cliente > div {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 5px;
}

.informacoes-cliente strong {
  font-size: 13px;
  overflow-wrap: anywhere;
}

.texto-secundario {
  color: var(--muted);
  font-size: 11px;
  line-height: 1.6;
  overflow-wrap: anywhere;
}

.resumo-pedido {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 16px;
  padding: 18px 24px;
  border-top: 1px solid var(--border);
  border-bottom: 1px solid var(--border);
  background: var(--background);
}

.resumo-item {
  display: flex;
  flex-direction: column;
  gap: 7px;
  min-width: 0;
}

.resumo-item span {
  color: var(--muted);
  font-size: 11px;
}

.resumo-item strong {
  font-size: 13px;
  overflow-wrap: anywhere;
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
  background: var(--background);
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

.imagem-sem-foto {
  display: grid;
  place-items: center;
  color: var(--muted);
}

.imagem-sem-foto svg {
  width: 34px;
  height: 34px;
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
  font-size: 12px;
  text-align: right;
}

.nota-pagamento {
  color: var(--muted);
  font-size: 11px !important;
  line-height: 1.6;
}

.acoes-pedido {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: 12px;
  padding: 18px 24px;
  border-top: 1px solid var(--border);
}

.controle-status {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 7px;
  max-width: 360px;
}

.controle-status label {
  font-size: 12px;
  font-weight: 700;
}

.controle-status small {
  color: var(--muted);
  font-size: 11px;
  line-height: 1.5;
}

.controle-status .aviso-final {
  color: #b42318;
}

.feedback-pedido {
  margin: 0 24px 14px;
  padding: 11px 12px;
  border: 1px solid currentColor;
  border-radius: 6px;
  font-size: 12px;
  line-height: 1.5;
  overflow-wrap: anywhere;
}

.feedback-pedido.erro {
  background: #fff1f0;
  color: #b42318;
}

.feedback-pedido.sucesso {
  background: #edf9f0;
  color: #247247;
}

@media (max-width: 900px) {
  .resumo-admin {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 650px) {
  .admin-container {
    width: calc(100% - 28px);
    padding: 27px 0 48px;
  }

  .admin-card {
    padding: 16px;
  }

  .admin-cabecalho,
  .secao-cabecalho {
    align-items: flex-start;
    flex-direction: column;
  }

  .filtros-pedidos {
    grid-template-columns: 1fr;
  }

  .pedido-cabecalho {
    padding: 18px 17px;
  }

  .informacoes-cliente {
    padding: 14px 17px;
  }

  .resumo-pedido {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    padding: 17px;
  }

  .botao-detalhes {
    padding: 15px 17px;
  }

  .detalhes-pedido {
    padding: 18px 17px;
  }

  .acoes-pedido {
    align-items: stretch;
    flex-direction: column;
    padding: 16px 17px;
  }

  .controle-status {
    max-width: none;
  }

  .feedback-pedido {
    margin-right: 17px;
    margin-left: 17px;
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
}

@media (max-width: 390px) {
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
