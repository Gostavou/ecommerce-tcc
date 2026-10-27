<script setup>
import { useRoute } from "vue-router";

const route = useRoute();

const abas = [
  { nome: "Produtos", caminho: "/admin" },
  { nome: "Pedidos", caminho: "/admin/pedidos" },
  { nome: "Usuários", caminho: "/admin/usuarios" },
];
</script>

<template>
  <nav class="abas-admin" aria-label="Administração">
    <RouterLink
      v-for="aba in abas"
      :key="aba.caminho"
      :to="aba.caminho"
      class="aba-admin"
      :class="{ 'aba-ativa': route.path === aba.caminho }"
    >
      {{ aba.nome }}
    </RouterLink>
  </nav>
</template>

<style scoped>
.abas-admin {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 28px;
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
  transition:
    background 0.2s,
    color 0.2s;
}

.aba-admin:hover {
  color: var(--text);
  background: var(--surface);
}

.aba-ativa {
  color: var(--text);
  border-color: var(--text);
  background: var(--surface);
  font-weight: 700;
}
</style>
