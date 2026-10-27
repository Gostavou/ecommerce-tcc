<script setup>
import { computed, onMounted, ref } from "vue";
import axios from "axios";
import { RouterLink, useRouter } from "vue-router";
import { useCarrinho } from "../stores/cart";
import { token, estaAutenticado } from "../stores/auth";

const router = useRouter();
const carrinho = useCarrinho();

const API = "http://localhost:8000/api";

// Estado da página
const carregando = ref(true);
const finalizandoPedido = ref(false);
const erro = ref("");
const sucesso = ref("");
const pedidoCriado = ref(null);

// Endereços
const enderecos = ref([]);
const enderecoSelecionadoId = ref("");
const formularioEnderecoAberto = ref(false);
const salvandoEndereco = ref(false);

const erroEndereco = ref("");
const sucessoEndereco = ref("");
const consultandoCep = ref(false);
const erroCep = ref("");
const cepValidado = ref(false);

function novoEndereco() {
  return {
    nome: "",
    cep: "",
    rua: "",
    numero: "",
    complemento: "",
    bairro: "",
    cidade: "",
    estado: "",
    principal: false,
  };
}

const formularioEndereco = ref(novoEndereco());

// Forma de pagamento
const formaPagamento = ref("pix");

// Valores do carrinho
const subtotal = computed(() =>
  carrinho.itens.value.reduce(
    (soma, item) => soma + Number(item.preco) * Number(item.quantidade),
    0,
  ),
);

// Frete provisório conforme as faixas definidas para a loja
const frete = computed(() => {
  if (subtotal.value >= 500) return 0;
  if (subtotal.value >= 250) return 9.9;
  if (subtotal.value >= 100) return 14.9;
  return 19.9;
});

const total = computed(() => subtotal.value + frete.value);

const haIndisponiveis = computed(() =>
  carrinho.itens.value.some(
    (item) =>
      item.indisponivel ||
      Number(item.estoque) <= 0 ||
      Number(item.quantidade) > Number(item.estoque),
  ),
);

function formatarPreco(valor) {
  return Number(valor || 0).toLocaleString("pt-BR", {
    style: "currency",
    currency: "BRL",
  });
}

function cabecalhos() {
  return {
    Authorization: `Bearer ${token.value}`,
    Accept: "application/json",
  };
}

// Carrega os endereços salvos na conta
async function carregarEnderecos() {
  const resposta = await axios.get(`${API}/enderecos`, {
    headers: cabecalhos(),
  });

  enderecos.value = resposta.data;

  // Mantém a seleção atual ou seleciona o principal.
  const enderecoAtualExiste = enderecos.value.some(
    (endereco) => String(endereco.id) === String(enderecoSelecionadoId.value),
  );

  if (!enderecoAtualExiste) {
    const principal = enderecos.value.find((endereco) => endereco.principal);

    enderecoSelecionadoId.value = principal?.id ?? enderecos.value[0]?.id ?? "";
  }
}

// Abre o cadastro de um novo endereço
function abrirFormularioEndereco() {
  formularioEndereco.value = novoEndereco();
  formularioEnderecoAberto.value = true;

  erroEndereco.value = "";
  sucessoEndereco.value = "";
  erroCep.value = "";
  cepValidado.value = false;
}

// Consulta o CEP e preenche automaticamente o endereço
let consultaCepAtual = 0;

async function consultarCep() {
  const cep = formularioEndereco.value.cep.replace(/\D/g, "");
  const consultaAtual = ++consultaCepAtual;

  erroCep.value = "";
  cepValidado.value = false;

  if (cep.length === 0) {
    return;
  }

  if (cep.length !== 8) {
    erroCep.value = "Digite um CEP com 8 números.";
    return;
  }

  consultandoCep.value = true;

  try {
    const resposta = await axios.get(`https://viacep.com.br/ws/${cep}/json/`);

    if (consultaAtual !== consultaCepAtual) return;

    if (resposta.data.erro) {
      erroCep.value = "CEP não encontrado. Confira os números digitados.";
      return;
    }

    const dados = resposta.data;

    formularioEndereco.value.rua = dados.logradouro || "";
    formularioEndereco.value.bairro = dados.bairro || "";
    formularioEndereco.value.cidade = dados.localidade || "";
    formularioEndereco.value.estado = dados.uf || "";

    cepValidado.value = true;
  } catch {
    if (consultaAtual === consultaCepAtual) {
      erroCep.value = "Não foi possível consultar o CEP. Tente novamente.";
    }
  } finally {
    if (consultaAtual === consultaCepAtual) {
      consultandoCep.value = false;
    }
  }
}

// Cadastra o endereço somente quando o botão for pressionado
async function cadastrarEnderecoCheckout() {
  erroEndereco.value = "";
  sucessoEndereco.value = "";
  erroCep.value = "";

  const cep = formularioEndereco.value.cep.replace(/\D/g, "");

  if (cep.length !== 8) {
    erroCep.value = "O CEP deve conter exatamente 8 números.";
    return;
  }

  salvandoEndereco.value = true;

  try {
    // Valida o CEP novamente antes de salvar.
    const respostaCep = await axios.get(
      `https://viacep.com.br/ws/${cep}/json/`,
    );

    if (respostaCep.data.erro) {
      erroCep.value = "CEP não encontrado. Informe um CEP válido.";
      return;
    }

    const dadosCep = respostaCep.data;

    // Previne o envio dos dados de endereço sem a validação atual.
    formularioEndereco.value.rua =
      dadosCep.logradouro || formularioEndereco.value.rua;

    formularioEndereco.value.bairro =
      dadosCep.bairro || formularioEndereco.value.bairro;

    formularioEndereco.value.cidade =
      dadosCep.localidade || formularioEndereco.value.cidade;

    formularioEndereco.value.estado =
      dadosCep.uf || formularioEndereco.value.estado;

    const dados = {
      nome: formularioEndereco.value.nome.trim(),
      cep: cep.replace(/^(\d{5})(\d{3})$/, "$1-$2"),
      rua: formularioEndereco.value.rua.trim(),
      numero: formularioEndereco.value.numero.trim(),
      complemento: formularioEndereco.value.complemento.trim() || null,
      bairro: formularioEndereco.value.bairro.trim(),
      cidade: formularioEndereco.value.cidade.trim(),
      estado: formularioEndereco.value.estado.trim().toUpperCase(),
      principal: Boolean(formularioEndereco.value.principal),
    };

    // Registra quais endereços já existiam para identificar o novo.
    const idsAnteriores = new Set(
      enderecos.value.map((endereco) => String(endereco.id)),
    );

    await axios.post(`${API}/enderecos`, dados, {
      headers: cabecalhos(),
    });

    // Atualiza a lista dos endereços salvos.
    await carregarEnderecos();

    const enderecoNovo = enderecos.value.find(
      (endereco) => !idsAnteriores.has(String(endereco.id)),
    );

    if (enderecoNovo) {
      enderecoSelecionadoId.value = enderecoNovo.id;
    } else {
      // Alternativa caso a API retorne uma lista sem identificar
      // diretamente qual endereço acabou de ser criado.
      const enderecoCorrespondente = enderecos.value.find(
        (endereco) =>
          endereco.nome === dados.nome &&
          String(endereco.cep).replace(/\D/g, "") === cep,
      );

      if (enderecoCorrespondente) {
        enderecoSelecionadoId.value = enderecoCorrespondente.id;
      }
    }

    formularioEndereco.value = novoEndereco();
    formularioEnderecoAberto.value = false;
    cepValidado.value = false;

    sucessoEndereco.value = "Endereço cadastrado e selecionado para a entrega.";
  } catch (e) {
    if (e.response?.status === 401) {
      erroEndereco.value = "Sua sessão expirou. Entre novamente.";
    } else if (e.response?.status === 422) {
      const erros = e.response.data.errors;

      erroEndereco.value = erros
        ? Object.values(erros).flat().join(" ")
        : e.response.data.message || "Verifique os dados do endereço.";
    } else {
      erroEndereco.value =
        e.response?.data?.message || "Não foi possível cadastrar o endereço.";
    }
  } finally {
    salvandoEndereco.value = false;
  }
}

function cancelarCadastroEndereco() {
  formularioEnderecoAberto.value = false;
  formularioEndereco.value = novoEndereco();

  erroEndereco.value = "";
  erroCep.value = "";
  cepValidado.value = false;
}

// Envia o pedido usando um endereço que já está salvo
async function finalizarCompra() {
  erro.value = "";
  sucesso.value = "";

  if (!estaAutenticado()) {
    router.push("/login");
    return;
  }

  if (carrinho.itens.value.length === 0) {
    erro.value = "Seu carrinho está vazio.";
    return;
  }

  if (haIndisponiveis.value) {
    erro.value =
      "Há produtos indisponíveis ou com quantidade acima do estoque. Volte ao carrinho e confira os itens.";
    return;
  }

  if (!enderecoSelecionadoId.value) {
    erroEndereco.value =
      "Selecione um endereço salvo ou cadastre um novo endereço.";
    return;
  }

  if (formularioEnderecoAberto.value) {
    erroEndereco.value =
      "Salve ou cancele o cadastro do endereço antes de finalizar a compra.";
    return;
  }

  finalizandoPedido.value = true;

  try {
    // Atualiza os dados antes de enviar o pedido.
    await carrinho.sincronizarDadosProdutos();

    if (haIndisponiveis.value) {
      erro.value =
        "O estoque mudou. Confira os produtos no carrinho antes de continuar.";
      return;
    }

    const dadosPedido = {
      endereco_id: Number(enderecoSelecionadoId.value),
      forma_pagamento: formaPagamento.value,
      itens: carrinho.itens.value.map((item) => ({
        produto_id: Number(item.id),
        quantidade: Number(item.quantidade),
      })),
    };

    const resposta = await axios.post(`${API}/pedidos`, dadosPedido, {
      headers: cabecalhos(),
    });

    pedidoCriado.value = resposta.data.pedido;
    sucesso.value =
      resposta.data.message ||
      "Pedido realizado com sucesso! O pagamento foi simulado.";

    // Esvazia o carrinho usando a própria função da loja,
    // preservando o comportamento de persistência do carrinho.
    for (const item of [...carrinho.itens.value]) {
      carrinho.removerDoCarrinho(item.id);
    }
  } catch (e) {
    if (e.response?.status === 401) {
      erro.value = "Sua sessão expirou. Entre novamente.";
    } else if (e.response?.status === 422) {
      const erros = e.response.data.errors;

      erro.value = erros
        ? Object.values(erros).flat().join(" ")
        : e.response.data.message ||
          "Confira os dados do pedido e tente novamente.";
    } else if (e.response?.status === 409) {
      erro.value =
        e.response.data.message ||
        "O estoque mudou. Confira os produtos e tente novamente.";
    } else {
      erro.value =
        e.response?.data?.message || "Não foi possível finalizar o pedido.";
    }
  } finally {
    finalizandoPedido.value = false;
  }
}

onMounted(async () => {
  if (!estaAutenticado()) {
    router.replace("/login");
    return;
  }

  try {
    await Promise.all([
      carrinho.sincronizarDadosProdutos(),
      carregarEnderecos(),
    ]);
  } catch (e) {
    if (e.response?.status === 401) {
      erro.value = "Sua sessão expirou. Entre novamente.";
    } else {
      erro.value =
        "Não foi possível carregar os dados do checkout. Tente atualizar a página.";
    }
  } finally {
    carregando.value = false;
  }
});
</script>

<template>
  <main class="pagina-checkout">
    <header class="cabecalho">
      <div>
        <h1>Finalizar pedido</h1>
        <p class="subtitulo">
          Confira a entrega, o pagamento e os produtos selecionados.
        </p>
      </div>

      <RouterLink to="/carrinho" class="link-voltar">
        ← Voltar ao carrinho
      </RouterLink>
    </header>

    <p v-if="carregando" class="aviso aviso-info" role="status">
      Carregando dados do checkout...
    </p>

    <template v-else>
      <section v-if="pedidoCriado" class="resultado-pedido" aria-live="polite">
        <div class="icone-sucesso" aria-hidden="true">✓</div>

        <h2>Pedido realizado!</h2>

        <p>{{ sucesso }}</p>

        <p v-if="pedidoCriado.id">
          Número do pedido:
          <strong>#{{ pedidoCriado.id }}</strong>
        </p>

        <p class="nota-pagamento">
          Esta compra não utiliza pagamentos reais, é apenas para fins
          academicos e de demonstração do projeto.
        </p>

        <RouterLink to="/" class="botao-principal">
          Voltar para a loja
        </RouterLink>
      </section>

      <template v-else>
        <p v-if="erro" class="mensagem erro" role="alert">
          {{ erro }}
        </p>

        <div class="layout-checkout">
          <div class="coluna-checkout">
            <section class="checkout-card">
              <div class="titulo-secao">
                <span class="numero-secao">01</span>
                <div>
                  <h2>Endereço de entrega</h2>
                  <p>Selecione um endereço ou cadastre um novo.</p>
                </div>
              </div>

              <p v-if="erroEndereco" class="mensagem erro" role="alert">
                {{ erroEndereco }}
              </p>

              <p v-if="sucessoEndereco" class="mensagem sucesso" role="status">
                {{ sucessoEndereco }}
              </p>

              <div v-if="enderecos.length" class="lista-enderecos">
                <label
                  v-for="endereco in enderecos"
                  :key="endereco.id"
                  class="opcao-endereco"
                  :class="{
                    selecionado:
                      String(enderecoSelecionadoId) === String(endereco.id),
                  }"
                >
                  <input
                    v-model="enderecoSelecionadoId"
                    type="radio"
                    name="endereco-entrega"
                    :value="endereco.id"
                  />

                  <span class="dados-endereco">
                    <strong class="nome-endereco">
                      {{ endereco.nome }}
                    </strong>

                    <span v-if="endereco.principal" class="etiqueta-principal">
                      Principal
                    </span>

                    <span>
                      {{ endereco.rua }}, {{ endereco.numero }}
                      <template v-if="endereco.complemento">
                        — {{ endereco.complemento }}
                      </template>
                    </span>

                    <span>
                      {{ endereco.bairro }} — {{ endereco.cidade }}/{{
                        endereco.estado
                      }}
                    </span>

                    <span>CEP: {{ endereco.cep }}</span>
                  </span>
                </label>
              </div>

              <p v-else class="enderecos-vazios">
                Você ainda não possui endereços cadastrados. Cadastre um abaixo
                para continuar.
              </p>

              <button
                v-if="!formularioEnderecoAberto"
                type="button"
                class="botao-secundario"
                @click="abrirFormularioEndereco"
              >
                + Cadastrar novo endereço
              </button>

              <form
                v-if="formularioEnderecoAberto"
                class="formulario-endereco"
                @submit.prevent="cadastrarEnderecoCheckout"
              >
                <h3>Novo endereço</h3>

                <div class="campo">
                  <label for="checkout-nome">Identificação</label>
                  <input
                    id="checkout-nome"
                    v-model.trim="formularioEndereco.nome"
                    required
                    maxlength="255"
                    placeholder="Ex.: Casa ou Trabalho"
                  />
                </div>

                <div class="campo">
                  <label for="checkout-cep">CEP</label>
                  <input
                    id="checkout-cep"
                    v-model.trim="formularioEndereco.cep"
                    required
                    maxlength="9"
                    autocomplete="postal-code"
                    placeholder="00000-000"
                    @blur="consultarCep"
                  />

                  <small v-if="consultandoCep" class="ajuda">
                    Consultando CEP...
                  </small>

                  <small v-else-if="erroCep" class="erro">
                    {{ erroCep }}
                  </small>

                  <small v-else-if="cepValidado" class="sucesso">
                    CEP encontrado! Endereço preenchido automaticamente.
                  </small>
                </div>

                <div class="campo">
                  <label for="checkout-rua">Rua / Avenida</label>
                  <input
                    id="checkout-rua"
                    v-model.trim="formularioEndereco.rua"
                    required
                    maxlength="255"
                    autocomplete="address-line1"
                    placeholder="Nome da rua"
                  />
                </div>

                <div class="grade-campos">
                  <div class="campo">
                    <label for="checkout-numero">Número</label>
                    <input
                      id="checkout-numero"
                      v-model.trim="formularioEndereco.numero"
                      required
                      maxlength="20"
                      placeholder="Número"
                    />
                  </div>

                  <div class="campo">
                    <label for="checkout-complemento"> Complemento </label>
                    <input
                      id="checkout-complemento"
                      v-model.trim="formularioEndereco.complemento"
                      maxlength="255"
                      autocomplete="address-line2"
                      placeholder="Apto, bloco etc. (opcional)"
                    />
                  </div>
                </div>

                <div class="campo">
                  <label for="checkout-bairro">Bairro</label>
                  <input
                    id="checkout-bairro"
                    v-model.trim="formularioEndereco.bairro"
                    required
                    maxlength="255"
                    placeholder="Bairro"
                  />
                </div>

                <div class="grade-campos">
                  <div class="campo">
                    <label for="checkout-cidade">Cidade</label>
                    <input
                      id="checkout-cidade"
                      v-model.trim="formularioEndereco.cidade"
                      required
                      maxlength="255"
                      autocomplete="address-level2"
                      placeholder="Cidade"
                    />
                  </div>

                  <div class="campo">
                    <label for="checkout-estado">Estado (UF)</label>
                    <input
                      id="checkout-estado"
                      v-model.trim="formularioEndereco.estado"
                      required
                      minlength="2"
                      maxlength="2"
                      autocapitalize="characters"
                      placeholder="PR"
                    />
                  </div>
                </div>

                <label class="opcao-principal">
                  <input
                    v-model="formularioEndereco.principal"
                    type="checkbox"
                  />
                  Definir como endereço principal
                </label>

                <div class="acoes-formulario">
                  <button
                    type="submit"
                    class="botao-principal"
                    :disabled="salvandoEndereco || consultandoCep"
                  >
                    {{
                      salvandoEndereco ? "Cadastrando..." : "Cadastrar endereço"
                    }}
                  </button>

                  <button
                    type="button"
                    class="botao-secundario"
                    :disabled="salvandoEndereco"
                    @click="cancelarCadastroEndereco"
                  >
                    Cancelar
                  </button>
                </div>
              </form>
            </section>

            <section class="checkout-card">
              <div class="titulo-secao">
                <span class="numero-secao">02</span>
                <div>
                  <h2>Forma de pagamento</h2>
                  <p>Pagamento simulado para demonstração do projeto.</p>
                </div>
              </div>

              <div class="lista-pagamentos">
                <label
                  class="opcao-pagamento"
                  :class="{ selecionado: formaPagamento === 'pix' }"
                >
                  <input
                    v-model="formaPagamento"
                    type="radio"
                    name="forma-pagamento"
                    value="pix"
                  />

                  <span>
                    <strong>PIX</strong>
                    <small>Pagamento simulado</small>
                  </span>
                </label>

                <label
                  class="opcao-pagamento"
                  :class="{
                    selecionado: formaPagamento === 'cartao_credito',
                  }"
                >
                  <input
                    v-model="formaPagamento"
                    type="radio"
                    name="forma-pagamento"
                    value="cartao_credito"
                  />

                  <span>
                    <strong>Cartão de crédito</strong>
                    <small>Pagamento simulado</small>
                  </span>
                </label>
              </div>

              <p class="nota-pagamento">
                Nenhum dado de cartão será solicitado e nenhuma cobrança real
                será realizada.
              </p>
            </section>
          </div>

          <aside class="resumo-checkout">
            <h2>Resumo do pedido</h2>

            <div class="resumo-linha">
              <span>Produtos</span>
              <strong>{{ carrinho.quantidadeTotal() }}</strong>
            </div>

            <div class="resumo-linha">
              <span>Subtotal</span>
              <strong>{{ formatarPreco(subtotal) }}</strong>
            </div>

            <div class="resumo-linha">
              <span>Frete</span>
              <strong>
                {{ frete === 0 ? "Grátis" : formatarPreco(frete) }}
              </strong>
            </div>

            <div class="resumo-linha total">
              <span>Total do pedido</span>
              <strong>{{ formatarPreco(total) }}</strong>
            </div>

            <div class="resumo-itens">
              <h3>Seus produtos</h3>

              <article
                v-for="item in carrinho.itens.value"
                :key="item.id"
                class="resumo-produto"
              >
                <div>
                  <strong>{{ item.nome }}</strong>
                  <small>Quantidade: {{ item.quantidade }}</small>
                </div>

                <span>
                  {{
                    formatarPreco(Number(item.preco) * Number(item.quantidade))
                  }}
                </span>
              </article>
            </div>

            <p v-if="haIndisponiveis" class="mensagem erro" role="alert">
              Há produtos indisponíveis ou com quantidade acima do estoque.
            </p>

            <button
              type="button"
              class="botao-principal botao-finalizar"
              :disabled="
                finalizandoPedido ||
                haIndisponiveis ||
                carrinho.itens.value.length === 0 ||
                !enderecoSelecionadoId ||
                formularioEnderecoAberto
              "
              @click="finalizarCompra"
            >
              {{
                finalizandoPedido ? "Finalizando pedido..." : "Confirmar pedido"
              }}
            </button>
          </aside>
        </div>
      </template>
    </template>
  </main>
</template>

<style scoped>
.pagina-checkout {
  width: min(1120px, calc(100% - 40px));
  margin: 0 auto;
  padding: 36px 0 72px;
  color: var(--text);
}

.cabecalho {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 20px;
  margin-bottom: 30px;
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
  margin: 9px 0 0;
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

.layout-checkout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 340px;
  align-items: start;
  gap: 24px;
}

.coluna-checkout {
  display: grid;
  gap: 22px;
  min-width: 0;
}

.checkout-card,
.resumo-checkout,
.resultado-pedido {
  border: 1px solid var(--border);
  border-radius: 12px;
  background: var(--surface);
  box-shadow: var(--shadow);
}

.checkout-card {
  padding: 25px;
}

.titulo-secao {
  display: flex;
  align-items: flex-start;
  gap: 13px;
  margin-bottom: 24px;
}

.numero-secao {
  display: grid;
  width: 34px;
  height: 34px;
  flex-shrink: 0;
  place-items: center;
  border: 1px solid var(--border);
  border-radius: 8px;
  font-size: 12px;
  font-weight: 800;
}

.titulo-secao h2,
.resumo-checkout h2 {
  margin: 0;
  font-size: 18px;
}

.titulo-secao p {
  margin: 6px 0 0;
  color: var(--muted);
  font-size: 12px;
  line-height: 1.6;
}

.lista-enderecos,
.lista-pagamentos {
  display: grid;
  gap: 12px;
  margin-bottom: 18px;
}

.opcao-endereco,
.opcao-pagamento {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 16px;
  border: 1px solid var(--border);
  border-radius: 9px;
  background: var(--bg);
  cursor: pointer;
  transition: border-color 0.15s ease;
}

.opcao-endereco.selecionado,
.opcao-pagamento.selecionado {
  border-color: var(--text);
}

.opcao-endereco input,
.opcao-pagamento input,
.opcao-principal input {
  margin-top: 3px;
  accent-color: var(--accent);
  flex-shrink: 0;
}

.dados-endereco {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 6px;
  min-width: 0;
  color: var(--muted);
  font-size: 12px;
  line-height: 1.5;
  overflow-wrap: anywhere;
}

.nome-endereco {
  color: var(--text);
  font-size: 14px;
}

.etiqueta-principal {
  padding: 3px 6px;
  border: 1px solid var(--border);
  border-radius: 4px;
  color: var(--text);
  font-size: 10px;
  font-weight: 700;
}

.enderecos-vazios {
  padding: 16px;
  border: 1px dashed var(--border);
  border-radius: 9px;
  color: var(--muted);
  font-size: 13px;
  line-height: 1.6;
}

.formulario-endereco {
  margin-top: 18px;
  padding: 20px;
  border: 1px solid var(--border);
  border-radius: 9px;
  background: var(--bg);
}

.formulario-endereco h3,
.resumo-itens h3 {
  margin: 0 0 20px;
  font-size: 15px;
}

.campo {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 17px;
}

.campo label {
  font-size: 12px;
  font-weight: 700;
}

.campo input {
  box-sizing: border-box;
  width: 100%;
  min-width: 0;
  padding: 12px 13px;
  border: 1px solid var(--border);
  border-radius: 7px;
  background: var(--surface);
  color: var(--text);
  font: inherit;
  font-size: 13px;
}

.campo input:focus {
  outline: 2px solid var(--muted);
  outline-offset: 1px;
}

.grade-campos {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: 14px;
}

.campo small,
.opcao-pagamento small {
  color: var(--muted);
  font-size: 11px;
  line-height: 1.5;
}

.opcao-principal {
  display: flex;
  align-items: center;
  gap: 9px;
  margin: 3px 0 20px;
  font-size: 12px;
  cursor: pointer;
}

.acoes-formulario {
  display: flex;
  gap: 10px;
}

.botao-principal,
.botao-secundario {
  display: inline-flex;
  min-height: 42px;
  align-items: center;
  justify-content: center;
  padding: 11px 15px;
  border-radius: 7px;
  font: inherit;
  font-size: 12px;
  font-weight: 750;
  text-align: center;
  text-decoration: none;
  cursor: pointer;
  transition:
    opacity 0.15s ease,
    transform 0.15s ease;
}

.botao-principal {
  border: 1px solid var(--accent);
  background: var(--accent);
  color: var(--accent-contrast);
}

.botao-secundario {
  border: 1px solid var(--border);
  background: var(--bg);
  color: var(--text);
}

.botao-principal:hover:not(:disabled),
.botao-secundario:hover:not(:disabled) {
  transform: translateY(-1px);
}

.botao-principal:disabled,
.botao-secundario:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.acoes-formulario .botao-principal,
.acoes-formulario .botao-secundario {
  flex: 1;
}

.opcao-pagamento {
  align-items: center;
}

.opcao-pagamento span {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.opcao-pagamento strong {
  font-size: 13px;
}

.nota-pagamento {
  margin: 14px 0 0;
  color: var(--muted);
  font-size: 11px;
  line-height: 1.6;
}

.resumo-checkout {
  position: sticky;
  top: 24px;
  padding: 24px;
}

.resumo-checkout h2 {
  margin-bottom: 22px;
}

.resumo-linha {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
  color: var(--muted);
  font-size: 12px;
  line-height: 1.5;
}

.resumo-linha strong {
  color: var(--text);
  text-align: right;
}

.regra-frete {
  margin: -5px 0 17px;
  color: var(--muted);
  font-size: 11px;
  line-height: 1.5;
}

.resumo-linha.total {
  margin: 20px 0;
  padding-top: 18px;
  border-top: 1px solid var(--border);
  color: var(--text);
  font-size: 14px;
}

.resumo-linha.total strong {
  font-size: 19px;
}

.resumo-itens {
  padding-top: 18px;
  border-top: 1px solid var(--border);
}

.resumo-itens h3 {
  margin-bottom: 14px;
}

.resumo-produto {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 14px;
  font-size: 11px;
  line-height: 1.5;
}

.resumo-produto div {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}

.resumo-produto strong {
  overflow-wrap: anywhere;
}

.resumo-produto small {
  color: var(--muted);
}

.resumo-produto > span {
  flex-shrink: 0;
  font-weight: 700;
}

.botao-finalizar {
  width: 100%;
  margin-top: 9px;
}

.texto-seguranca {
  margin: 13px 0 0;
  color: var(--muted);
  font-size: 10px;
  line-height: 1.6;
  text-align: center;
}

.mensagem {
  margin: 0 0 16px;
  font-size: 13px;
  line-height: 1.6;
  overflow-wrap: anywhere;
}

.erro {
  color: #c0392b;
}

.sucesso {
  color: #27804a;
}

.resultado-pedido {
  max-width: 600px;
  margin: 40px auto;
  padding: 38px 28px;
  text-align: center;
}

.icone-sucesso {
  display: grid;
  width: 54px;
  height: 54px;
  margin: 0 auto 20px;
  place-items: center;
  border: 1px solid var(--border);
  border-radius: 50%;
  font-size: 27px;
  font-weight: 800;
}

.resultado-pedido h2 {
  margin: 0 0 14px;
  font-size: 24px;
}

.resultado-pedido p {
  color: var(--muted);
  font-size: 13px;
  line-height: 1.7;
}

.resultado-pedido .botao-principal {
  margin-top: 12px;
}

@media (max-width: 820px) {
  .layout-checkout {
    grid-template-columns: minmax(0, 1fr);
  }

  .resumo-checkout {
    position: static;
  }
}

@media (max-width: 560px) {
  .pagina-checkout {
    width: calc(100% - 28px);
    padding: 26px 0 45px;
  }

  .cabecalho {
    align-items: flex-start;
    flex-direction: column;
    gap: 16px;
  }

  .checkout-card,
  .resumo-checkout {
    padding: 19px 16px;
  }

  .formulario-endereco {
    padding: 15px;
  }

  .grade-campos {
    grid-template-columns: minmax(0, 1fr);
    gap: 0;
  }

  .acoes-formulario {
    flex-direction: column;
  }
}
</style>
