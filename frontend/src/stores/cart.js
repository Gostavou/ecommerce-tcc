
import { ref } from 'vue'
import axios from 'axios'

const STORAGE_KEY = 'siciliana64-carrinho'
const API_URL = 'http://localhost:8000/api/produtos'

function lerCarrinhoSalvo() {
  try {
    const dados = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]')
    return Array.isArray(dados) ? dados : []
  } catch {
    return []
  }
}

const itens = ref(lerCarrinhoSalvo())
const mensagem = ref('')

function persistir() {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(itens.value))
  } catch (erro) {
    console.error('Não foi possível salvar o carrinho:', erro)
    mensagem.value = 'Não foi possível salvar o carrinho neste navegador.'
  }
}

function quantidadeTotal() {
  return itens.value.reduce(
    (total, item) => total + Number(item.quantidade || 0),
    0,
  )
}

function adicionarAoCarrinho(produto, quantidade = 1) {
  const estoque = Math.max(0, Math.trunc(Number(produto.estoque) || 0))
  const quantidadeAdicionar = Number(quantidade)

  if (!Number.isInteger(quantidadeAdicionar) || quantidadeAdicionar < 1) {
    mensagem.value = 'Selecione uma quantidade válida.'
    return false
  }

  if (estoque <= 0) {
    mensagem.value = 'Este produto está sem estoque.'
    return false
  }

  const existente = itens.value.find(
    (item) => String(item.id) === String(produto.id),
  )

  const quantidadeAtual = Number(existente?.quantidade || 0)

  if (quantidadeAtual + quantidadeAdicionar > estoque) {
    mensagem.value = `Há apenas ${estoque} unidade(s) disponível(is) deste produto.`
    return false
  }

  if (existente) {
    Object.assign(existente, {
      nome: produto.nome,
      descricao: produto.descricao ?? '',
      preco: Number(produto.preco),
      estoque,
      imagem: produto.imagem ?? '',
      quantidade: quantidadeAtual + quantidadeAdicionar,
      indisponivel: false,
    })
  } else {
    itens.value.push({
      id: produto.id,
      nome: produto.nome,
      descricao: produto.descricao ?? '',
      preco: Number(produto.preco),
      estoque,
      imagem: produto.imagem ?? '',
      quantidade: quantidadeAdicionar,
      indisponivel: false,
    })
  }

  mensagem.value = `${produto.nome} adicionado ao carrinho.`
  persistir()
  return true
}

function alterarQuantidade(id, novaQuantidade) {
  const item = itens.value.find(
    (produto) => String(produto.id) === String(id),
  )

  if (!item) return

  const quantidade = Number(novaQuantidade)
  const estoque = Math.max(0, Math.trunc(Number(item.estoque) || 0))

  if (!Number.isInteger(quantidade)) {
    mensagem.value = 'Selecione uma quantidade válida.'
    return
  }

  if (quantidade <= 0) {
    removerDoCarrinho(id)
    return
  }

  if (item.indisponivel || estoque <= 0) {
    mensagem.value = `${item.nome} está indisponível no momento.`
    return
  }

  if (quantidade > estoque) {
    mensagem.value = `Há apenas ${estoque} unidade(s) disponível(is) de ${item.nome}.`
    return
  }

  item.quantidade = quantidade
  mensagem.value = ''
  persistir()
}

function removerDoCarrinho(id) {
  itens.value = itens.value.filter(
    (item) => String(item.id) !== String(id),
  )

  mensagem.value = ''
  persistir()
}

function limparCarrinho() {
  itens.value = []
  mensagem.value = ''
  persistir()
}

async function sincronizarDadosProdutos() {
  if (itens.value.length === 0) return

  try {
    const resposta = await axios.get(API_URL)

    const produtos = Array.isArray(resposta.data)
      ? resposta.data
      : resposta.data?.data

    if (!Array.isArray(produtos)) {
      throw new Error('A API não retornou uma lista válida de produtos.')
    }

    const catalogo = new Map(
      produtos.map((produto) => [String(produto.id), produto]),
    )

    let quantidadeAjustada = false
    let encontrouIndisponivel = false

    for (const item of itens.value) {
      const produtoAtual = catalogo.get(String(item.id))

      if (!produtoAtual) {
        item.estoque = 0
        item.indisponivel = true
        encontrouIndisponivel = true
        continue
      }

      const estoqueAtual = Math.max(
        0,
        Math.trunc(Number(produtoAtual.estoque) || 0),
      )

      Object.assign(item, {
        nome: produtoAtual.nome,
        descricao: produtoAtual.descricao ?? '',
        preco: Number(produtoAtual.preco),
        estoque: estoqueAtual,
        imagem: produtoAtual.imagem ?? '',
        indisponivel: estoqueAtual <= 0,
      })

      if (estoqueAtual <= 0) {
        encontrouIndisponivel = true
      } else if (Number(item.quantidade) > estoqueAtual) {
        item.quantidade = estoqueAtual
        quantidadeAjustada = true
      }
    }

    if (quantidadeAjustada) {
      mensagem.value =
        'Algumas quantidades foram ajustadas conforme o estoque atual.'
    } else if (encontrouIndisponivel) {
      mensagem.value =
        'Há produtos indisponíveis no carrinho. Remova-os para continuar.'
    } else {
      mensagem.value = ''
    }

    persistir()
  } catch (erro) {
    console.error('Erro ao sincronizar o carrinho:', erro)
    throw erro
  }
}

function formatarPreco(valor) {
  return Number(valor ?? 0).toLocaleString('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  })
}

export function useCarrinho() {
  return {
    itens,
    mensagem,
    quantidadeTotal,
    adicionarAoCarrinho,
    alterarQuantidade,
    removerDoCarrinho,
    limparCarrinho,
    sincronizarDadosProdutos,
    formatarPreco,
  }
}