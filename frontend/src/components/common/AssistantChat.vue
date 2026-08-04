<script setup>
/**
 * AssistantChat.vue
 * Botón flotante + chat con Gemini.
 * El system prompt se carga desde guides/{module}.txt
 *
 * Props:
 *   module: 'admin' | 'pedido' | 'cocina' | 'caja'
 */

import { ref, computed, nextTick, onMounted } from 'vue';
import './AssistantChat.css';

// ─── Importar los 4 guías como texto plano (Vite los incluye en el bundle) ───
import guideAdmin  from './guides/admin.txt?raw';
import guidePedido from './guides/pedido.txt?raw';
import guideCocina from './guides/cocina.txt?raw';
import guideCaja   from './guides/caja.txt?raw';

const GUIDES = {
  admin:  guideAdmin,
  pedido: guidePedido,
  cocina: guideCocina,
  caja:   guideCaja,
};

// ─── Props ───────────────────────────────────────────────────
const props = defineProps({
  module: {
    type: String,
    required: true,
    validator: (v) => ['admin', 'pedido', 'cocina', 'caja'].includes(v),
  },
});

// ─── Groq API (compatible con OpenAI) ────────────────────────
const GROQ_KEY   = 'retirado por seguridad';
const GROQ_URL   = 'https://api.groq.com/openai/v1/chat/completions';
const GROQ_MODEL = 'llama-3.3-70b-versatile';

// ─── Meta visual por módulo ──────────────────────────────────
const MODULE_META = {
  admin:  { label: 'Administrador', emoji: '🛠️' },
  pedido: { label: 'Mesero',        emoji: '🧾' },
  cocina: { label: 'Cocina',        emoji: '🍳' },
  caja:   { label: 'Caja',          emoji: '💰' },
};

const meta         = computed(() => MODULE_META[props.module]);
const systemPrompt = computed(() => GUIDES[props.module] ?? '');

// ─── Estado del chat ─────────────────────────────────────────
const isOpen     = ref(false);
const opened     = ref(false);
const loading    = ref(false);
const inputText  = ref('');
const messages   = ref([]);
const messagesEl = ref(null);

// ─── Helpers ─────────────────────────────────────────────────
const now = () =>
  new Date().toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' });

const scrollBottom = () => {
  if (messagesEl.value)
    messagesEl.value.scrollTop = messagesEl.value.scrollHeight;
};

// Markdown básico → HTML (negrita, cursiva, saltos de línea)
const fmt = (t) =>
  t
    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
    .replace(/\*(.*?)\*/g,     '<em>$1</em>')
    .replace(/\n/g,            '<br/>');

// ─── Abrir / cerrar ──────────────────────────────────────────
const toggle = () => {
  isOpen.value = !isOpen.value;
  if (isOpen.value && !opened.value) {
    opened.value = true;
    messages.value.push({
      role: 'bot',
      text: `¡Hola! Soy tu asistente del módulo **${meta.value.label}** ${meta.value.emoji}\n\n¿En qué te puedo ayudar?`,
      time: now(),
    });
  }
  if (isOpen.value) nextTick(scrollBottom);
};

// ─── Enviar mensaje ──────────────────────────────────────────
const send = async () => {
  const text = inputText.value.trim();
  if (!text || loading.value) return;

  messages.value.push({ role: 'user', text, time: now() });
  inputText.value = '';
  loading.value   = true;
  await nextTick(scrollBottom);

  try {
    // Construir mensajes para Groq (formato OpenAI)
    const chatMessages = [
      // System prompt con la guía del módulo
      { role: 'system', content: systemPrompt.value },
      // Historial de la conversación (últimos 20 mensajes)
      ...messages.value.slice(-20).map(m => ({
        role:    m.role === 'user' ? 'user' : 'assistant',
        content: m.text,
      })),
    ];

    const body = {
      model:       GROQ_MODEL,
      messages:    chatMessages,
      max_tokens:  1024,
      temperature: 0.65,
    };

    const res = await fetch(GROQ_URL, {
      method:  'POST',
      headers: {
        'Content-Type':  'application/json',
        'Authorization': `Bearer ${GROQ_KEY}`,
      },
      body: JSON.stringify(body),
    });

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err?.error?.message ?? `Error HTTP ${res.status}`);
    }

    const data  = await res.json();
    const reply = data?.choices?.[0]?.message?.content
      ?? 'No pude obtener una respuesta. Intenta de nuevo.';

    messages.value.push({ role: 'bot', text: reply, time: now() });

  } catch (e) {
    console.error('Groq error:', e);
    const isRateLimit = e.message?.includes('429') || e.message?.toLowerCase().includes('rate');
    messages.value.push({
      role: 'bot',
      text: isRateLimit
        ? 'El asistente está recibiendo muchas solicitudes. Espera unos segundos e intenta de nuevo.'
        : `Ocurrió un error al contactar al asistente: ${e.message}`,
      time: now(),
    });
  } finally {
    loading.value = false;
    await nextTick(scrollBottom);
  }
};

// Enter envía, Shift+Enter = nueva línea
const onKeydown = (e) => {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    send();
  }
};
</script>

<template>
  <!-- Botón flotante FAB -->
  <button
    class="ac-fab"
    @click="toggle"
    :title="`Asistente ${meta.label}`"
    aria-label="Abrir asistente"
  >
    <img src="/ll.png" :alt="`Asistente ${meta.label}`" />
  </button>

  <!-- Ventana del chat (fuera del DOM del componente padre via Teleport) -->
  <Teleport to="body">
    <div v-if="isOpen" class="ac-window" role="dialog" aria-label="Asistente IA">

      <!-- Header -->
      <div class="ac-header">
        <img src="/ll.png" alt="Asistente" class="ac-header-avatar" />
        <div class="ac-header-info">
          <p class="ac-header-title">Asistente IA</p>
          <span class="ac-module-badge">{{ meta.emoji }} {{ meta.label }}</span>
        </div>
        <button class="ac-header-close" @click="toggle" aria-label="Cerrar">✕</button>
      </div>

      <!-- Mensajes -->
      <div class="ac-messages" ref="messagesEl" aria-live="polite">
        <div
          v-for="(msg, i) in messages"
          :key="i"
          :class="['ac-msg', `ac-msg--${msg.role}`]"
        >
          <div class="ac-bubble" v-html="fmt(msg.text)"></div>
          <span class="ac-msg-time">{{ msg.time }}</span>
        </div>

        <!-- Indicador de escritura -->
        <div v-if="loading" class="ac-msg ac-msg--bot">
          <div class="ac-typing" aria-label="Escribiendo...">
            <span></span><span></span><span></span>
          </div>
        </div>
      </div>

      <!-- Input -->
      <div class="ac-input-area">
        <textarea
          class="ac-input"
          v-model="inputText"
          @keydown="onKeydown"
          placeholder="Escribe tu pregunta... (Enter para enviar)"
          rows="1"
          :disabled="loading"
          aria-label="Mensaje para el asistente"
        ></textarea>
        <button
          class="ac-send"
          @click="send"
          :disabled="!inputText.trim() || loading"
          title="Enviar"
          aria-label="Enviar mensaje"
        >➤</button>
      </div>

    </div>
  </Teleport>
</template>
