<script setup>
import { computed, onMounted, ref } from "vue";
import { RouterLink, useRoute } from "vue-router";
import axios from "axios";
import { token, usuario } from "../stores/auth";

const API = "http://localhost:8000/api/admin/usuarios";
const route = useRoute();

const usuarios = ref([]);
const papeisSelecionados = ref({});
const carregando = ref(false);
const erro = ref("");
const sucesso = ref("");
const busca = ref("");
const filtroTipo = ref("todos");
const salvandoId = ref(null);
const excluindoId = ref(null);

function cabecalhos() {
  return {
    Authorization: `Bearer ${token.value}`,
    Accept: "application/json",
  };
}

function nomeUsuario(item) {
  return item.name ?? item.nome ?? "Nome não informado";
}

function tipoUsuario(item) {
  return item.role ?? item.tipo ?? "cliente";
}

function tipoSelecionado(item) {
  return papeisSelecionados.value[item.id] ?? tipoUsuario(item);
}

function textoTipo(tipo) {
  return tipo === "admin" ? "Administrador" : "Cliente";
}

function formatarData(data) {
  if (!data) return "—";

  const dataValida = new Date(data);

  if (Number.isNaN(dataValida.getTime())) return "—";

  return dataValida.toLocaleDateString("pt-BR");
}

function ehUsuarioLogado(item) {
  return (
    usuario.value?.id !== undefined &&
    Number(usuario.value.id) === Number(item.id)
  );
}

function mensagemErroApi(e, padrao) {
  if (e.response?.status === 401) {
    return "Sua sessão expirou. Entre novamente.";
  }

  if (e.response?.status === 403) {
    return "Você não tem permissão para realizar esta ação.";
  }

  if (e.response?.status === 404) {
    return "O usuário não foi encontrado ou a rota não existe.";
  }

  if (e.response?.status === 422) {
    const erros = e.response.data?.errors;

    return erros
      ? Object.values(erros).flat().join(" ")
      : e.response.data?.message || "Confira os dados informados.";
  }

  return e.response?.data?.message || padrao;
}

const usuariosFiltrados = computed(() => {
  const termo = busca.value.trim().toLocaleLowerCase("pt-BR");

  return usuarios.value.filter((item) => {
    const correspondeBusca =
      !termo ||
      String(item.id).includes(termo) ||
      nomeUsuario(item).toLocaleLowerCase("pt-BR").includes(termo) ||
      String(item.email ?? "")
        .toLocaleLowerCase("pt-BR")
        .includes(termo);

    const correspondeTipo =
      filtroTipo.value === "todos" || tipoUsuario(item) === filtroTipo.value;

    return correspondeBusca && correspondeTipo;
  });
});

const quantidadeAdministradores = computed(
  () => usuarios.value.filter((item) => tipoUsuario(item) === "admin").length,
);

const quantidadeClientes = computed(
  () => usuarios.value.filter((item) => tipoUsuario(item) !== "admin").length,
);

async function carregarUsuarios() {
  carregando.value = true;
  erro.value = "";

  try {
    const resposta = await axios.get(API, {
      headers: cabecalhos(),
    });

    const lista = Array.isArray(resposta.data)
      ? resposta.data
      : (resposta.data.data ?? resposta.data.usuarios ?? []);

    usuarios.value = lista;

    const papeis = {};

    for (const item of lista) {
      papeis[item.id] = item.role ?? item.tipo ?? "cliente";
    }

    papeisSelecionados.value = papeis;
  } catch (e) {
    console.error(e);

    erro.value = mensagemErroApi(
      e,
      "Não foi possível carregar os usuários. Confira se a rota administrativa existe no Laravel.",
    );
  } finally {
    carregando.value = false;
  }
}

async function salvarTipoUsuario(item) {
  erro.value = "";
  sucesso.value = "";

  const tipoOriginal = tipoUsuario(item);
  const novoTipo = tipoSelecionado(item);

  if (!["cliente", "admin"].includes(novoTipo)) {
    erro.value = "Selecione um tipo de conta válido.";
    papeisSelecionados.value[item.id] = tipoOriginal;
    return;
  }

  if (ehUsuarioLogado(item) && novoTipo !== "admin") {
    erro.value = "Você não pode remover seu próprio acesso de administrador.";
    papeisSelecionados.value[item.id] = tipoOriginal;
    return;
  }

  if (novoTipo === tipoOriginal) {
    sucesso.value = "O tipo de conta não foi alterado.";
    return;
  }

  salvandoId.value = item.id;

  try {
    const resposta = await axios.patch(
      `${API}/${item.id}`,
      { role: novoTipo },
      { headers: cabecalhos() },
    );

    const usuarioAtualizado = resposta.data?.usuario ?? resposta.data;

    usuarios.value = usuarios.value.map((usuarioItem) =>
      usuarioItem.id === item.id
        ? {
            ...usuarioItem,
            ...usuarioAtualizado,
            role: usuarioAtualizado?.role ?? novoTipo,
          }
        : usuarioItem,
    );

    papeisSelecionados.value[item.id] = usuarioAtualizado?.role ?? novoTipo;

    sucesso.value = `Tipo de conta de ${nomeUsuario(item)} atualizado com sucesso.`;
  } catch (e) {
    console.error(e);

    papeisSelecionados.value[item.id] = tipoOriginal;

    erro.value = mensagemErroApi(
      e,
      "Não foi possível atualizar o tipo de conta.",
    );
  } finally {
    salvandoId.value = null;
  }
}

async function excluirUsuario(item) {
  erro.value = "";
  sucesso.value = "";

  if (ehUsuarioLogado(item)) {
    erro.value = "Você não pode excluir a própria conta por esta tela.";
    return;
  }

  const confirmado = window.confirm(
    `Deseja realmente excluir o usuário "${nomeUsuario(item)}" (${item.email})?`,
  );

  if (!confirmado) return;

  excluindoId.value = item.id;

  try {
    await axios.delete(`${API}/${item.id}`, {
      headers: cabecalhos(),
    });

    usuarios.value = usuarios.value.filter(
      (usuarioItem) => usuarioItem.id !== item.id,
    );

    const papeis = { ...papeisSelecionados.value };
    delete papeis[item.id];
    papeisSelecionados.value = papeis;

    sucesso.value = "Usuário excluído com sucesso.";
  } catch (e) {
    console.error(e);

    erro.value = mensagemErroApi(
      e,
      "Não foi possível excluir o usuário. Verifique se ele possui pedidos ou endereços vinculados.",
    );
  } finally {
    excluindoId.value = null;
  }
}

onMounted(carregarUsuarios);
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
        <h1>Usuários</h1>
        <p class="subtitulo">
          Gerencie as contas cadastradas e seus tipos de acesso.
        </p>
      </div>

      <RouterLink to="/" class="botao secundario">
        Voltar para a loja
      </RouterLink>
    </header>

    <section class="resumo-admin">
      <article class="resumo-card">
        <span>Total de usuários</span>
        <strong>{{ usuarios.length }}</strong>
      </article>

      <article class="resumo-card">
        <span>Clientes</span>
        <strong>{{ quantidadeClientes }}</strong>
      </article>

      <article class="resumo-card">
        <span>Administradores</span>
        <strong>{{ quantidadeAdministradores }}</strong>
      </article>
    </section>

    <p v-if="erro" class="mensagem erro" role="alert">{{ erro }}</p>
    <p v-if="sucesso" class="mensagem sucesso" role="status">
      {{ sucesso }}
    </p>

    <section class="admin-card">
      <header class="secao-cabecalho">
        <div>
          <h2>Contas cadastradas</h2>
          <p>{{ usuariosFiltrados.length }} usuário(s) encontrado(s).</p>
        </div>

        <button
          class="botao secundario"
          type="button"
          :disabled="carregando"
          @click="carregarUsuarios"
        >
          Atualizar lista
        </button>
      </header>

      <div class="filtros-usuarios">
        <input
          v-model="busca"
          type="search"
          placeholder="Buscar por nome, e-mail ou ID"
          aria-label="Buscar usuários"
        />

        <select v-model="filtroTipo" aria-label="Filtrar tipo de usuário">
          <option value="todos">Todos os tipos</option>
          <option value="cliente">Clientes</option>
          <option value="admin">Administradores</option>
        </select>
      </div>

      <p v-if="carregando" class="estado-lista">Carregando usuários...</p>

      <p v-else-if="usuariosFiltrados.length === 0" class="estado-lista">
        Nenhum usuário encontrado.
      </p>

      <div v-else class="tabela-responsiva">
        <table class="tabela-usuarios">
          <thead>
            <tr>
              <th>ID</th>
              <th>Usuário</th>
              <th>Cadastro</th>
              <th>Tipo de conta</th>
              <th>Ações</th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="item in usuariosFiltrados" :key="item.id">
              <td class="id-celula">#{{ item.id }}</td>

              <td class="usuario-celula">
                <strong>{{ nomeUsuario(item) }}</strong>
                <span>{{ item.email }}</span>

                <small v-if="ehUsuarioLogado(item)" class="etiqueta-proprio">
                  Sua conta
                </small>
              </td>

              <td>{{ formatarData(item.created_at) }}</td>

              <td>
                <span
                  class="etiqueta-tipo"
                  :class="
                    tipoUsuario(item) === 'admin'
                      ? 'tipo-administrador'
                      : 'tipo-cliente'
                  "
                >
                  {{ textoTipo(tipoUsuario(item)) }}
                </span>
              </td>

              <td>
                <div class="acoes-usuario">
                  <select
                    :value="tipoSelecionado(item)"
                    :aria-label="`Tipo de conta de ${nomeUsuario(item)}`"
                    :disabled="
                      salvandoId === item.id ||
                      (ehUsuarioLogado(item) && tipoUsuario(item) === 'admin')
                    "
                    @change="papeisSelecionados[item.id] = $event.target.value"
                  >
                    <option value="cliente">Cliente</option>
                    <option value="admin">Administrador</option>
                  </select>

                  <button
                    class="botao principal"
                    type="button"
                    :disabled="
                      salvandoId === item.id ||
                      excluindoId === item.id ||
                      tipoSelecionado(item) === tipoUsuario(item)
                    "
                    @click="salvarTipoUsuario(item)"
                  >
                    {{ salvandoId === item.id ? "Salvando..." : "Salvar" }}
                  </button>

                  <button
                    class="botao perigo"
                    type="button"
                    :disabled="
                      excluindoId === item.id ||
                      salvandoId === item.id ||
                      ehUsuarioLogado(item)
                    "
                    @click="excluirUsuario(item)"
                  >
                    {{ excluindoId === item.id ? "Excluindo..." : "Excluir" }}
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
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
  justify-content: space-between;
  align-items: center;
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
  grid-template-columns: repeat(3, minmax(0, 1fr));
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
  font-size: 24px;
}

.admin-card {
  padding: 24px;
}

.secao-cabecalho {
  margin-bottom: 22px;
}

.secao-cabecalho h2 {
  margin: 0;
  font-size: 20px;
}

.botao {
  display: inline-flex;
  justify-content: center;
  align-items: center;
  border: 1px solid #d4d4d4;
  border-radius: 6px;
  padding: 10px 12px;
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

.botao.perigo {
  background: #ffffff !important;
  border-color: #c36b5f !important;
  color: #b42318 !important;
  -webkit-text-fill-color: #b42318 !important;
}

.botao:disabled,
select:disabled {
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

.filtros-usuarios {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 200px;
  gap: 12px;
  margin-bottom: 20px;
}

.filtros-usuarios input,
.filtros-usuarios select,
.acoes-usuario select {
  min-width: 0;
  border: 1px solid var(--border);
  border-radius: 6px;
  padding: 10px;
  background: var(--background);
  color: var(--text);
  font: inherit;
  font-size: 12px;
}

.filtros-usuarios input {
  width: 100%;
}

.estado-lista {
  padding: 35px 15px;
  color: var(--muted);
  text-align: center;
  font-size: 13px;
}

.tabela-responsiva {
  width: 100%;
  overflow-x: auto;
}

.tabela-usuarios {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
}

.tabela-usuarios th,
.tabela-usuarios td {
  padding: 14px 10px;
  border-bottom: 1px solid var(--border);
  font-size: 12px;
  vertical-align: middle;
}

.tabela-usuarios th {
  color: var(--muted);
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.5px;
  text-transform: uppercase;
}

.id-celula {
  color: var(--muted);
  white-space: nowrap;
}

.usuario-celula {
  min-width: 170px;
}

.usuario-celula strong,
.usuario-celula span {
  display: block;
  overflow-wrap: anywhere;
}

.usuario-celula strong {
  font-size: 12px;
}

.usuario-celula span {
  margin-top: 5px;
  color: var(--muted);
  font-size: 11px;
}

.etiqueta-proprio {
  display: inline-block;
  margin-top: 7px;
  color: var(--muted);
  font-size: 10px;
}

.etiqueta-tipo {
  display: inline-block;
  border: 1px solid var(--border);
  border-radius: 20px;
  padding: 5px 9px;
  font-size: 10px;
  white-space: nowrap;
}

.tipo-administrador {
  color: #5685be;
}

.tipo-cliente {
  color: var(--muted);
}

.acoes-usuario {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 7px;
  min-width: 255px;
}

.acoes-usuario select {
  flex: 1;
  min-width: 110px;
}

.observacao {
  margin: 18px 0 0;
  color: var(--muted);
  font-size: 11px;
  line-height: 1.6;
}

@media (max-width: 900px) {
  .tabela-usuarios {
    min-width: 760px;
  }
}

@media (max-width: 600px) {
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
    flex-direction: column;
  }

  .filtros-usuarios {
    grid-template-columns: 1fr;
  }

  .resumo-card {
    padding: 13px;
  }

  .resumo-card strong {
    font-size: 20px;
  }

  .aba-admin {
    padding: 10px 13px;
  }
}
</style>
