<script setup>
import { computed, onMounted, ref } from "vue";
import { RouterLink, useRouter } from "vue-router";
import { useCarrinho } from "../stores/cart";

const carrinho = useCarrinho();
const router = useRouter();
const sincronizando = ref(false);
const erroSincronizacao = ref("");

const total = computed(() =>
  carrinho.itens.value.reduce(
    (soma, item) => soma + Number(item.preco) * Number(item.quantidade),
    0,
  ),
);

const haIndisponiveis = computed(() =>
  carrinho.itens.value.some(
    (item) => item.indisponivel || Number(item.estoque) <= 0,
  ),
);

function imagemUrl(imagem) {
  if (!imagem) return "";

  if (imagem.startsWith("http://") || imagem.startsWith("https://")) {
    return imagem;
  }

  return `http://localhost:8000/storage/${imagem.replace(/^\//, "")}`;
}

function revisarPedido() {
  if (sincronizando.value || haIndisponiveis.value) {
    return;
  }

  router.push("/finalizar-compra");
}

async function atualizarCarrinho() {
  sincronizando.value = true;
  erroSincronizacao.value = "";

  try {
    await carrinho.sincronizarDadosProdutos();
  } catch (erro) {
    erroSincronizacao.value =
      "Não foi possível atualizar preços e estoque. Os dados exibidos podem estar desatualizados.";
  } finally {
    sincronizando.value = false;
  }
}

onMounted(atualizarCarrinho);
</script>

<template>
  <main class="pagina-carrinho">
    <header class="cabecalho">
      <div>
        <h1>Meu carrinho</h1>
        <p class="subtitulo">
          Confira os produtos e as quantidades selecionadas
        </p>
      </div>

      <RouterLink to="/" class="link-voltar">
        ← Continuar comprando
      </RouterLink>
    </header>

    <p v-if="sincronizando" class="aviso aviso-info" role="status">
      Atualizando preços e estoque...
    </p>

    <p v-if="erroSincronizacao" class="aviso aviso-erro" role="alert">
      {{ erroSincronizacao }}
      <button type="button" @click="atualizarCarrinho">Tentar novamente</button>
    </p>

    <p v-if="carrinho.mensagem.value" class="aviso aviso-info" role="status">
      {{ carrinho.mensagem.value }}
    </p>

    <section v-if="carrinho.itens.value.length === 0" class="carrinho-vazio">
      <span class="icone-vazio" aria-hidden="true">♞</span>
      <h2>Seu carrinho está vazio</h2>
      <p>Explore o catálogo e encontre seu proximo produto</p>
      <RouterLink to="/" class="botao-principal">
        Explorar produtos
      </RouterLink>
    </section>

    <div v-else class="conteudo-carrinho">
      <section class="lista-produtos">
        <article
          v-for="item in carrinho.itens.value"
          :key="item.id"
          class="item-carrinho"
        >
          <RouterLink
            :to="`/produto/${item.id}`"
            class="link-imagem"
            :aria-label="`Ver ${item.nome}`"
          >
            <img
              v-if="item.imagem"
              :src="imagemUrl(item.imagem)"
              :alt="item.nome"
              class="imagem-produto"
            />

            <div v-else class="imagem-placeholder" aria-hidden="true">♞</div>
          </RouterLink>

          <div class="informacoes-produto">
            <h2>
              <RouterLink :to="`/produto/${item.id}`">
                {{ item.nome }}
              </RouterLink>
            </h2>

            <p class="preco-unitario">
              {{ carrinho.formatarPreco(item.preco) }} / unidade
            </p>

            <p
              v-if="item.indisponivel || Number(item.estoque) <= 0"
              class="estado-estoque indisponivel"
            >
              Produto indisponível
            </p>

            <p
              v-else-if="Number(item.estoque) <= 5"
              class="estado-estoque estoque-baixo"
            >
              Apenas {{ item.estoque }} unidade(s) disponível(is)
            </p>

            <p v-else class="estado-estoque">
              {{ item.estoque }} unidades disponíveis
            </p>

            <div class="acoes-item">
              <div class="controles-quantidade" aria-label="Quantidade">
                <button
                  type="button"
                  aria-label="Diminuir quantidade"
                  :disabled="
                    item.quantidade <= 1 ||
                    item.indisponivel ||
                    Number(item.estoque) <= 0
                  "
                  @click="
                    carrinho.alterarQuantidade(
                      item.id,
                      Number(item.quantidade) - 1,
                    )
                  "
                >
                  −
                </button>

                <span>{{ item.quantidade }}</span>

                <button
                  type="button"
                  aria-label="Aumentar quantidade"
                  :disabled="
                    item.indisponivel ||
                    Number(item.estoque) <= 0 ||
                    Number(item.quantidade) >= Number(item.estoque)
                  "
                  @click="
                    carrinho.alterarQuantidade(
                      item.id,
                      Number(item.quantidade) + 1,
                    )
                  "
                >
                  +
                </button>
              </div>

              <button
                type="button"
                class="botao-remover"
                @click="carrinho.removerDoCarrinho(item.id)"
              >
                Remover
              </button>
            </div>
          </div>

          <div class="subtotal-item">
            <span>Subtotal</span>
            <strong>
              {{
                carrinho.formatarPreco(
                  Number(item.preco) * Number(item.quantidade),
                )
              }}
            </strong>
          </div>
        </article>
      </section>

      <aside class="resumo-carrinho">
        <h2>Resumo do pedido</h2>

        <div class="linha-resumo">
          <span>Itens no carrinho</span>
          <strong>{{ carrinho.quantidadeTotal() }}</strong>
        </div>

        <div class="linha-resumo">
          <span>Subtotal dos produtos</span>
          <strong>{{ carrinho.formatarPreco(total) }}</strong>
        </div>

        <div class="linha-resumo">
          <span>Frete</span>
          <strong>calculado durante a finalização</strong>
        </div>

        <p v-if="haIndisponiveis" class="aviso-resumo" role="alert">
          Seu carrinho contém produtos indisponíveis. Remova-os antes de
          prosseguir.
        </p>

        <p v-else class="texto-resumo">
          O valor do frete será calculado na próxima etapa, junto aos dados de
          entrega.
        </p>

        <button
          type="button"
          class="botao-principal"
          :disabled="haIndisponiveis || sincronizando"
          @click="revisarPedido"
        >
          {{
            sincronizando
              ? "Atualizando carrinho..."
              : "Revisar e finalizar pedido"
          }}
        </button>
      </aside>
    </div>
  </main>
</template>

<style scoped>
.pagina-carrinho {
  width: min(1120px, calc(100% - 40px));
  margin: 0 auto;
  padding: 36px 0 72px;
  color: var(--text);
}

.cabecalho {
  display: flex;
  align-items: end;
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

.conteudo-carrinho {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 310px;
  gap: 28px;
  align-items: start;
}

.lista-produtos {
  display: flex;
  flex-direction: column;
  gap: 14px;
  min-width: 0;
}

.item-carrinho {
  display: grid;
  grid-template-columns: 112px minmax(0, 1fr) auto;
  align-items: center;
  gap: 18px;
  padding: 18px;
  border: 1px solid var(--border);
  border-radius: 9px;
  background: var(--surface);
}

.link-imagem {
  display: block;
  overflow: hidden;
  width: 112px;
  height: 112px;
  border-radius: 6px;
  background: var(--surface-alt);
}

.imagem-produto {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.imagem-placeholder {
  display: grid;
  width: 100%;
  height: 100%;
  place-items: center;
  color: var(--muted);
  font-size: 54px;
}

.informacoes-produto {
  min-width: 0;
}

.id-produto {
  color: var(--muted);
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 1px;
}

.informacoes-produto h2 {
  margin: 7px 0;
  overflow-wrap: anywhere;
  font-size: 17px;
}

.informacoes-produto h2 a {
  color: var(--text);
  text-decoration: none;
}

.informacoes-produto h2 a:hover {
  text-decoration: underline;
}

.preco-unitario {
  margin: 7px 0;
  color: var(--muted);
  font-size: 12px;
}

.estado-estoque {
  margin: 8px 0;
  color: #67835c;
  font-size: 11px;
}

.estado-estoque.estoque-baixo {
  color: #a56d19;
}

.estado-estoque.indisponivel {
  color: #c36b5f;
}

.acoes-item {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 14px;
  margin-top: 12px;
}

.controles-quantidade {
  display: inline-flex;
  align-items: center;
  border: 1px solid var(--border);
  border-radius: 6px;
}

.controles-quantidade button {
  width: 34px;
  height: 34px;
  border: 0;
  border-radius: 5px;
  background: transparent;
  color: var(--text);
  cursor: pointer;
  font-size: 19px;
}

.controles-quantidade button:hover:not(:disabled) {
  background: var(--surface-alt);
}

.controles-quantidade button:disabled {
  cursor: not-allowed;
  opacity: 0.35;
}

.controles-quantidade span {
  min-width: 28px;
  text-align: center;
  font-size: 13px;
}

.botao-remover {
  padding: 5px 0;
  border: 0;
  background: transparent;
  color: var(--muted);
  cursor: pointer;
  font-size: 12px;
  text-decoration: underline;
}

.botao-remover:hover {
  color: #c36b5f;
}

.subtotal-item {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 7px;
  white-space: nowrap;
}

.subtotal-item span {
  color: var(--muted);
  font-size: 11px;
}

.subtotal-item strong {
  font-size: 14px;
}

.resumo-carrinho {
  padding: 22px;
  border: 1px solid var(--border);
  border-radius: 9px;
  background: var(--surface);
}

.resumo-carrinho h2 {
  margin: 0 0 22px;
  font-size: 19px;
}

.linha-resumo {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 0;
  color: var(--muted);
  font-size: 13px;
}

.linha-resumo strong {
  color: var(--text);
}

.linha-resumo.total {
  margin-top: 5px;
  padding-top: 18px;
  border-top: 1px solid var(--border);
  color: var(--text);
  font-size: 14px;
}

.linha-resumo.total strong {
  font-size: 18px;
}

.texto-resumo {
  color: var(--muted);
  font-size: 12px;
  line-height: 1.7;
}

.aviso-resumo {
  padding: 11px;
  border-radius: 6px;
  background: var(--surface-alt);
  color: #c36b5f;
  font-size: 12px;
  line-height: 1.6;
}

.botao-principal {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  padding: 13px 15px;
  border: 0;
  border-radius: 6px;
  background: var(--accent);
  color: var(--accent-contrast);
  font-family: inherit;
  font-size: 12px;
  font-weight: 700;
  text-align: center;
  text-decoration: none;
  cursor: pointer;
}

.botao-principal:hover:not(:disabled) {
  opacity: 0.85;
}

.botao-principal:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.carrinho-vazio {
  padding: 50px 20px;
  border: 1px solid var(--border);
  border-radius: 9px;
  background: var(--surface);
  text-align: center;
}

.icone-vazio {
  display: block;
  margin-bottom: 12px;
  font-size: 55px;
  color: var(--muted);
}

.carrinho-vazio h2 {
  margin: 0;
  font-size: 22px;
}

.carrinho-vazio p {
  margin: 10px 0 22px;
  color: var(--muted);
  font-size: 14px;
}

.carrinho-vazio .botao-principal {
  width: auto;
  display: inline-flex;
}

.aviso {
  padding: 12px 15px;
  margin-bottom: 18px;
  border: 1px solid var(--border);
  border-radius: 7px;
  font-size: 13px;
  line-height: 1.6;
}

.aviso-info {
  color: var(--text);
}

.aviso-erro {
  color: #c36b5f;
}

.aviso button {
  margin-left: 8px;
  padding: 5px 8px;
  border: 1px solid var(--border);
  border-radius: 5px;
  background: var(--surface);
  color: var(--text);
  cursor: pointer;
}

@media (max-width: 850px) {
  .conteudo-carrinho {
    grid-template-columns: minmax(0, 1fr);
  }

  .resumo-carrinho {
    order: -1;
  }
}

@media (max-width: 560px) {
  .pagina-carrinho {
    width: calc(100% - 28px);
    padding-top: 24px;
  }

  .cabecalho {
    align-items: flex-start;
    flex-direction: column;
  }

  .item-carrinho {
    grid-template-columns: 78px minmax(0, 1fr);
    gap: 12px;
    padding: 13px;
  }

  .link-imagem {
    width: 78px;
    height: 78px;
  }

  .informacoes-produto h2 {
    font-size: 15px;
  }

  .subtotal-item {
    grid-column: 2;
    flex-direction: row;
    justify-content: space-between;
    align-items: center;
    padding-top: 10px;
    border-top: 1px solid var(--border);
  }

  .resumo-carrinho {
    padding: 18px;
  }
}
</style>
