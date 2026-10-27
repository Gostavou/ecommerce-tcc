import { ref } from 'vue'

const usuarioSalvo = localStorage.getItem('usuario')
const tokenSalvo = localStorage.getItem('token')

export const usuario = ref(usuarioSalvo ? JSON.parse(usuarioSalvo) : null)
export const token = ref(tokenSalvo || '')

export function salvarSessao(dadosUsuario, novoToken) {
  usuario.value = dadosUsuario
  token.value = novoToken

  localStorage.setItem('usuario', JSON.stringify(dadosUsuario))
  localStorage.setItem('token', novoToken)
}

export function encerrarSessao() {
  usuario.value = null
  token.value = ''

  localStorage.removeItem('usuario')
  localStorage.removeItem('token')
}

export function estaAutenticado() {
  return Boolean(token.value)
}

export function ehAdministrador() {
  return usuario.value?.role === 'admin'
}