<script setup>
import { onMounted, ref } from "vue";
import axios from "axios";
import { useRouter } from "vue-router";
import { token, usuario, salvarSessao, estaAutenticado } from "../stores/auth";

const router = useRouter();
const API = "http://localhost:8000/api";

const nome = ref("");
const email = ref("");
const carregando = ref(true);
const salvando = ref(false);
const erro = ref("");
const sucesso = ref("");

const enderecos = ref([]);
const carregandoEnderecos = ref(false);
const salvandoEndereco = ref(false);
const erroEndereco = ref("");
const sucessoEndereco = ref("");
const formularioAberto = ref(false);
const enderecoEditando = ref(null);

function cabecalhos() {
  return {
    Authorization: `Bearer ${token.value}`,
    Accept: "application/json",
  };
}

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

const consultandoCep = ref(false);
const erroCep = ref("");
const cepValidado = ref(false);

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

async function carregarPerfil() {
  if (!estaAutenticado()) {
    router.replace("/login");
    return;
  }

  try {
    const resposta = await axios.get(`${API}/perfil`, {
      headers: cabecalhos(),
    });

    nome.value = resposta.data.name;
    email.value = resposta.data.email;
  } catch (e) {
    if (e.response?.status === 401) {
      erro.value = "Sua sessão expirou. Entre novamente.";
    } else {
      erro.value = "Não foi possível carregar os dados do perfil.";
    }
  } finally {
    carregando.value = false;
  }
}

async function salvarPerfil() {
  erro.value = "";
  sucesso.value = "";
  salvando.value = true;

  try {
    const resposta = await axios.put(
      `${API}/perfil`,
      {
        name: nome.value.trim(),
        email: email.value.trim(),
      },
      { headers: cabecalhos() },
    );

    const usuarioAtualizado = resposta.data.usuario;
    salvarSessao(usuarioAtualizado, token.value);

    nome.value = usuarioAtualizado.name;
    email.value = usuarioAtualizado.email;
    sucesso.value = resposta.data.message || "Perfil atualizado com sucesso.";
  } catch (e) {
    if (e.response?.status === 401) {
      erro.value = "Sua sessão expirou. Entre novamente.";
    } else if (e.response?.status === 422) {
      const erros = e.response.data.errors;

      if (erros?.email) {
        erro.value =
          "Informe um e-mail válido que ainda não esteja cadastrado.";
      } else if (erros?.name) {
        erro.value = "Informe um nome válido.";
      } else {
        erro.value = "Verifique os dados informados.";
      }
    } else {
      erro.value = "Não foi possível salvar as alterações.";
    }
  } finally {
    salvando.value = false;
  }
}

async function carregarEnderecos() {
  carregandoEnderecos.value = true;
  erroEndereco.value = "";

  try {
    const resposta = await axios.get(`${API}/enderecos`, {
      headers: cabecalhos(),
    });

    enderecos.value = resposta.data;
  } catch (e) {
    if (e.response?.status === 401) {
      erroEndereco.value = "Sua sessão expirou. Entre novamente.";
    } else {
      erroEndereco.value = "Não foi possível carregar seus endereços.";
    }
  } finally {
    carregandoEnderecos.value = false;
  }
}

function abrirNovoEndereco() {
  enderecoEditando.value = null;
  formularioEndereco.value = novoEndereco();
  erroEndereco.value = "";
  sucessoEndereco.value = "";
  erroCep.value = "";
  cepValidado.value = false;
  formularioAberto.value = true;
}

function editarEndereco(endereco) {
  enderecoEditando.value = endereco.id;
  formularioEndereco.value = {
    nome: endereco.nome ?? "",
    cep: endereco.cep ?? "",
    rua: endereco.rua ?? "",
    numero: endereco.numero ?? "",
    complemento: endereco.complemento ?? "",
    bairro: endereco.bairro ?? "",
    cidade: endereco.cidade ?? "",
    estado: endereco.estado ?? "",
    principal: Boolean(endereco.principal),
  };

  erroEndereco.value = "";
  sucessoEndereco.value = "";
  erroCep.value = "";
  cepValidado.value = false;
  formularioAberto.value = true;
}

function cancelarFormulario() {
  formularioAberto.value = false;
  enderecoEditando.value = null;
  formularioEndereco.value = novoEndereco();
  erroEndereco.value = "";
  erroCep.value = "";
  cepValidado.value = false;
}

async function salvarEndereco() {
  erroEndereco.value = "";
  sucessoEndereco.value = "";
  salvandoEndereco.value = true;

  const cep = formularioEndereco.value.cep.replace(/\D/g, "");

  if (cep.length !== 8) {
    erroEndereco.value = "O CEP deve conter exatamente 8 números.";
    salvandoEndereco.value = false;
    return;
  }

  formularioEndereco.value.cep = cep.replace(/^(\d{5})(\d{3})$/, "$1-$2");

  try {
    const resposta = await axios.get(`https://viacep.com.br/ws/${cep}/json/`);

    if (resposta.data.erro) {
      erroEndereco.value = "CEP não encontrado. Informe um CEP válido.";
      salvandoEndereco.value = false;
      return;
    }
  } catch {
    erroEndereco.value = "Não foi possível verificar o CEP. Tente novamente.";
    salvandoEndereco.value = false;
    return;
  }

  const dados = {
    nome: formularioEndereco.value.nome.trim(),
    cep: formularioEndereco.value.cep.trim(),
    rua: formularioEndereco.value.rua.trim(),
    numero: formularioEndereco.value.numero.trim(),
    complemento: formularioEndereco.value.complemento.trim() || null,
    bairro: formularioEndereco.value.bairro.trim(),
    cidade: formularioEndereco.value.cidade.trim(),
    estado: formularioEndereco.value.estado.trim().toUpperCase(),
    principal: Boolean(formularioEndereco.value.principal),
  };

  try {
    if (enderecoEditando.value !== null) {
      await axios.put(`${API}/enderecos/${enderecoEditando.value}`, dados, {
        headers: cabecalhos(),
      });

      sucessoEndereco.value = "Endereço atualizado com sucesso.";
    } else {
      await axios.post(`${API}/enderecos`, dados, {
        headers: cabecalhos(),
      });

      sucessoEndereco.value = "Endereço cadastrado com sucesso.";
    }

    formularioAberto.value = false;
    enderecoEditando.value = null;
    formularioEndereco.value = novoEndereco();

    await carregarEnderecos();
  } catch (e) {
    if (e.response?.status === 401) {
      erroEndereco.value = "Sua sessão expirou. Entre novamente.";
    } else if (e.response?.status === 422) {
      const erros = e.response.data.errors;
      const mensagem = e.response.data.message;

      erroEndereco.value = erros
        ? Object.values(erros).flat().join(" ")
        : mensagem || "Verifique os dados do endereço.";
    } else {
      erroEndereco.value =
        e.response?.data?.message || "Não foi possível salvar o endereço.";
    }
  } finally {
    salvandoEndereco.value = false;
  }
}

async function excluirEndereco(endereco) {
  const confirmado = window.confirm(
    `Deseja excluir o endereço "${endereco.nome}"?`,
  );

  if (!confirmado) return;

  erroEndereco.value = "";
  sucessoEndereco.value = "";

  try {
    await axios.delete(`${API}/enderecos/${endereco.id}`, {
      headers: cabecalhos(),
    });

    sucessoEndereco.value = "Endereço excluído com sucesso.";
    await carregarEnderecos();
  } catch (e) {
    if (e.response?.status === 401) {
      erroEndereco.value = "Sua sessão expirou. Entre novamente.";
    } else {
      erroEndereco.value =
        e.response?.data?.message || "Não foi possível excluir o endereço.";
    }
  }
}

async function definirPrincipal(endereco) {
  erroEndereco.value = "";
  sucessoEndereco.value = "";

  try {
    await axios.put(
      `${API}/enderecos/${endereco.id}`,
      {
        nome: endereco.nome,
        cep: endereco.cep,
        rua: endereco.rua,
        numero: endereco.numero,
        complemento: endereco.complemento || null,
        bairro: endereco.bairro,
        cidade: endereco.cidade,
        estado: endereco.estado,
        principal: true,
      },
      { headers: cabecalhos() },
    );

    sucessoEndereco.value = "Endereço principal atualizado.";
    await carregarEnderecos();
  } catch (e) {
    if (e.response?.status === 401) {
      erroEndereco.value = "Sua sessão expirou. Entre novamente.";
    } else {
      erroEndereco.value =
        e.response?.data?.message ||
        "Não foi possível definir o endereço principal.";
    }
  }
}

function formatarCep(cep) {
  const numeros = String(cep ?? "").replace(/\D/g, "");

  if (numeros.length === 8) {
    return `${numeros.slice(0, 5)}-${numeros.slice(5)}`;
  }

  return cep;
}

onMounted(async () => {
  await carregarPerfil();

  if (estaAutenticado()) {
    await carregarEnderecos();
  }
});
</script>

<template>
  <main class="perfil-container">
    <div class="perfil-conteudo">
      <RouterLink to="/" class="voltar-loja"> ← Voltar para a loja </RouterLink>

      <header class="perfil-cabecalho">
        <div>
          <h1>Meu perfil</h1>
          <p class="descricao">Consulte e atualize seus dados pessoais.</p>
        </div>
      </header>

      <p v-if="carregando" class="estado">Carregando seus dados...</p>

      <template v-else>
        <p v-if="erro" class="mensagem erro" role="alert">
          {{ erro }}
        </p>

        <p v-if="sucesso" class="mensagem sucesso" role="status">
          {{ sucesso }}
        </p>

        <section class="perfil-card">
          <div class="secao-cabecalho">
            <div>
              <h2>Dados pessoais</h2>
              <p>Informações relacionadas a sua conta.</p>
            </div>
          </div>

          <form @submit.prevent="salvarPerfil">
            <div class="campo">
              <label for="nome">Nome completo</label>
              <input
                id="nome"
                v-model.trim="nome"
                type="text"
                autocomplete="name"
                maxlength="255"
                required
                placeholder="Seu nome"
              />
            </div>

            <div class="campo">
              <label for="email">E-mail</label>
              <input
                id="email"
                v-model.trim="email"
                type="email"
                autocomplete="email"
                maxlength="255"
                required
                placeholder="seu@email.com"
              />
            </div>

            <div class="campo">
              <label>Tipo de conta</label>
              <input
                :value="usuario?.role === 'admin' ? 'Administrador' : 'Cliente'"
                type="text"
                disabled
              />
              <small>O tipo de conta não pode ser alterado por aqui.</small>
            </div>

            <button class="botao" type="submit" :disabled="salvando">
              {{ salvando ? "Salvando..." : "Salvar alterações" }}
            </button>
          </form>
        </section>

        <section class="perfil-card enderecos-card">
          <div class="secao-cabecalho">
            <div>
              <h2>Endereços para entrega</h2>
              <p>Gerencie seus endereços de entrega. Limite de 5.</p>
            </div>

            <button
              class="botao-secundario"
              type="button"
              @click="abrirNovoEndereco"
            >
              + Adicionar endereço
            </button>
          </div>

          <p v-if="erroEndereco" class="mensagem erro" role="alert">
            {{ erroEndereco }}
          </p>

          <p v-if="sucessoEndereco" class="mensagem sucesso" role="status">
            {{ sucessoEndereco }}
          </p>

          <p v-if="carregandoEnderecos" class="estado">
            Carregando endereços...
          </p>

          <div
            v-else-if="enderecos.length === 0 && !formularioAberto"
            class="enderecos-vazio"
          >
            <h3>Nenhum endereço cadastrado</h3>
            <p>Adicione seu primeiro endereço para facilitar nas compras</p>
          </div>

          <form
            v-if="formularioAberto"
            class="formulario-endereco"
            @submit.prevent="salvarEndereco"
          >
            <h3>
              {{ enderecoEditando ? "Editar endereço" : "Novo endereço" }}
            </h3>

            <div class="campo">
              <label for="endereco-nome">Identificação do endereço</label>
              <input
                id="endereco-nome"
                v-model.trim="formularioEndereco.nome"
                required
                maxlength="255"
                placeholder="Ex.: Casa, Trabalho"
              />
            </div>

            <div class="grade-campos">
              <div class="campo">
                <label for="endereco-cep">CEP</label>
                <input
                  id="endereco-cep"
                  v-model.trim="formularioEndereco.cep"
                  required
                  maxlength="9"
                  autocomplete="postal-code"
                  placeholder="00000-000"
                  @blur="consultarCep"
                />

                <small v-if="consultandoCep">Consultando CEP...</small>
                <small v-else-if="erroCep" class="erro">
                  {{ erroCep }}
                </small>
                <small v-else-if="cepValidado" class="sucesso">
                  CEP encontrado! Endereço preenchido automaticamente.
                </small>
              </div>

              <div class="campo">
                <label for="endereco-estado">Estado (UF)</label>
                <input
                  id="endereco-estado"
                  v-model.trim="formularioEndereco.estado"
                  required
                  minlength="2"
                  maxlength="2"
                  placeholder="PR"
                  autocapitalize="characters"
                />
              </div>
            </div>

            <div class="campo">
              <label for="endereco-rua">Rua / Avenida</label>
              <input
                id="endereco-rua"
                v-model.trim="formularioEndereco.rua"
                required
                maxlength="255"
                autocomplete="address-line1"
                placeholder="Nome da rua"
              />
            </div>

            <div class="grade-campos">
              <div class="campo">
                <label for="endereco-numero">Número</label>
                <input
                  id="endereco-numero"
                  v-model.trim="formularioEndereco.numero"
                  required
                  maxlength="20"
                  placeholder="Número"
                />
              </div>

              <div class="campo">
                <label for="endereco-complemento">Complemento</label>
                <input
                  id="endereco-complemento"
                  v-model.trim="formularioEndereco.complemento"
                  maxlength="255"
                  autocomplete="address-line2"
                  placeholder="Apto, bloco etc. (opcional)"
                />
              </div>
            </div>

            <div class="campo">
              <label for="endereco-bairro">Bairro</label>
              <input
                id="endereco-bairro"
                v-model.trim="formularioEndereco.bairro"
                required
                maxlength="255"
                placeholder="Bairro"
              />
            </div>

            <div class="campo">
              <label for="endereco-cidade">Cidade</label>
              <input
                id="endereco-cidade"
                v-model.trim="formularioEndereco.cidade"
                required
                maxlength="255"
                autocomplete="address-level2"
                placeholder="Cidade"
              />
            </div>

            <label class="opcao-principal">
              <input v-model="formularioEndereco.principal" type="checkbox" />
              Definir como endereço principal
            </label>

            <div class="acoes-formulario">
              <button class="botao" type="submit" :disabled="salvandoEndereco">
                {{
                  salvandoEndereco
                    ? "Salvando..."
                    : enderecoEditando
                      ? "Salvar edição"
                      : "Cadastrar endereço"
                }}
              </button>

              <button
                class="botao-secundario"
                type="button"
                :disabled="salvandoEndereco"
                @click="cancelarFormulario"
              >
                Cancelar
              </button>
            </div>
          </form>

          <div v-else-if="enderecos.length" class="lista-enderecos">
            <article
              v-for="endereco in enderecos"
              :key="endereco.id"
              class="endereco-item"
            >
              <div class="endereco-topo">
                <div>
                  <h3>{{ endereco.nome }}</h3>
                  <span v-if="endereco.principal" class="etiqueta-principal">
                    Principal
                  </span>
                </div>
              </div>

              <p class="endereco-linha">
                {{ endereco.rua }}, {{ endereco.numero }}
                <span v-if="endereco.complemento">
                  — {{ endereco.complemento }}
                </span>
              </p>

              <p class="endereco-linha">
                {{ endereco.bairro }} — {{ endereco.cidade }}/{{
                  endereco.estado
                }}
              </p>

              <p class="endereco-cep">CEP: {{ formatarCep(endereco.cep) }}</p>

              <div class="acoes-endereco">
                <button
                  v-if="!endereco.principal"
                  class="link-acao"
                  type="button"
                  @click="definirPrincipal(endereco)"
                >
                  Definir como principal
                </button>

                <button
                  class="link-acao"
                  type="button"
                  @click="editarEndereco(endereco)"
                >
                  Editar
                </button>

                <button
                  class="link-acao link-perigo"
                  type="button"
                  @click="excluirEndereco(endereco)"
                >
                  Excluir
                </button>
              </div>
            </article>
          </div>
        </section>
      </template>
    </div>
  </main>
</template>

<style scoped>
.perfil-container {
  width: 100%;
  padding: 40px 24px 64px;
}

.perfil-conteudo {
  width: min(100%, 850px);
  margin: 0 auto;
}

.voltar-loja {
  display: inline-block;
  margin-bottom: 28px;
  color: var(--muted);
  font-size: 13px;
  text-decoration: none;
}

.voltar-loja:hover {
  color: var(--text);
}

.perfil-cabecalho {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  margin-bottom: 28px;
}

.subtitulo {
  margin: 0 0 8px;
  color: var(--muted);
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 1.5px;
}

h1 {
  margin: 0;
  font-size: clamp(28px, 4vw, 36px);
  letter-spacing: -1px;
}

.descricao {
  margin: 10px 0 0;
  color: var(--muted);
  font-size: 14px;
  line-height: 1.6;
}

.perfil-icone {
  width: 58px;
  height: 58px;
  display: grid;
  place-items: center;
  flex-shrink: 0;
  border: 1px solid var(--border);
  border-radius: 15px;
  background: var(--surface);
  font-size: 38px;
}

.perfil-card {
  margin-bottom: 22px;
  padding: 28px;
  border: 1px solid var(--border);
  border-radius: 14px;
  background: var(--surface);
  box-shadow: var(--shadow);
}

.secao-cabecalho {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 25px;
}

.secao-cabecalho h2 {
  margin: 0;
  font-size: 18px;
}

.secao-cabecalho p {
  margin: 7px 0 0;
  color: var(--muted);
  font-size: 13px;
  line-height: 1.6;
}

.campo {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 20px;
}

.campo label {
  font-size: 13px;
  font-weight: 650;
}

.campo input {
  width: 100%;
  padding: 12px 13px;
  border: 1px solid var(--border);
  border-radius: 7px;
  background: var(--bg);
  color: var(--text);
}

.campo input:focus {
  outline: 2px solid var(--muted);
  outline-offset: 1px;
}

.campo input:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.campo small {
  color: var(--muted);
  font-size: 11px;
}

.botao {
  width: 100%;
  padding: 13px;
  border: 0;
  border-radius: 7px;
  background: var(--accent);
  color: var(--accent-contrast);
  font-weight: 700;
  cursor: pointer;
}

.botao:disabled,
.botao-secundario:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.mensagem {
  margin: 0 0 18px;
  font-size: 14px;
  line-height: 1.5;
}

.erro {
  color: #c0392b;
}

.sucesso {
  color: #27804a;
}

.estado {
  padding: 30px 0;
  color: var(--muted);
  text-align: center;
}

.botao-secundario {
  padding: 10px 13px;
  border: 1px solid var(--border);
  border-radius: 7px;
  background: var(--bg);
  color: var(--text);
  font-size: 12px;
  font-weight: 650;
  cursor: pointer;
}

.enderecos-vazio {
  padding: 24px 15px;
  border: 1px dashed var(--border);
  border-radius: 10px;
  text-align: center;
}

.endereco-icone {
  color: var(--muted);
  font-size: 30px;
}

.enderecos-vazio h3 {
  margin: 12px 0 8px;
  font-size: 15px;
}

.enderecos-vazio p {
  max-width: 390px;
  margin: 0 auto;
  color: var(--muted);
  font-size: 13px;
  line-height: 1.6;
}

.formulario-endereco {
  padding: 20px;
  border: 1px solid var(--border);
  border-radius: 10px;
  background: var(--bg);
}

.formulario-endereco h3 {
  margin: 0 0 22px;
  font-size: 16px;
}

.grade-campos {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.opcao-principal {
  display: flex;
  align-items: center;
  gap: 9px;
  margin: 4px 0 22px;
  font-size: 13px;
  cursor: pointer;
}

.opcao-principal input {
  accent-color: var(--accent);
}

.acoes-formulario {
  display: flex;
  gap: 12px;
}

.acoes-formulario .botao {
  flex: 1;
}

.lista-enderecos {
  display: grid;
  gap: 14px;
}

.endereco-item {
  padding: 20px;
  border: 1px solid var(--border);
  border-radius: 10px;
}

.endereco-topo {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  margin-bottom: 12px;
}

.endereco-topo h3 {
  display: inline-block;
  margin: 0 9px 0 0;
  font-size: 15px;
}

.etiqueta-principal {
  display: inline-block;
  padding: 4px 7px;
  border: 1px solid var(--border);
  border-radius: 5px;
  color: var(--muted);
  font-size: 10px;
  font-weight: 700;
}

.endereco-linha,
.endereco-cep {
  margin: 5px 0;
  color: var(--muted);
  font-size: 13px;
  line-height: 1.6;
}

.acoes-endereco {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  margin-top: 17px;
  padding-top: 14px;
  border-top: 1px solid var(--border);
}

.link-acao {
  padding: 0;
  border: 0;
  background: transparent;
  color: var(--text);
  font-size: 12px;
  text-decoration: underline;
  text-underline-offset: 3px;
  cursor: pointer;
}

.link-perigo {
  color: #c0392b;
}

@media (max-width: 560px) {
  .perfil-container {
    padding: 28px 16px 45px;
  }

  .perfil-card {
    padding: 21px 17px;
  }

  .perfil-icone {
    width: 48px;
    height: 48px;
    font-size: 31px;
  }

  .secao-cabecalho {
    flex-wrap: wrap;
  }

  .grade-campos {
    grid-template-columns: 1fr;
    gap: 0;
  }

  .acoes-formulario {
    flex-direction: column;
  }
}
</style>
