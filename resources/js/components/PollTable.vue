<script setup>
import { ref } from 'vue';
import { usePollStore } from '@/stores/usePollStore';

const emit = defineEmits(['edit']);
const { polls, deletePoll, startPoll } = usePollStore();

const linkCopied = ref(null);
const deleting = ref(null);

function pollStatus(poll) {
  if (poll.is_draft) return { label: 'Brouillon', cls: 'bg-slate-100 text-slate-600' };
  if (poll.ends_at && new Date(poll.ends_at) < new Date())
    return { label: 'Terminé', cls: 'bg-rose-100 text-rose-700' };
  return { label: 'En cours', cls: 'bg-teal-100 text-teal-700' };
}

function fmt(dateStr) {
  if (!dateStr) return null;
  return new Date(dateStr).toLocaleString('fr-FR', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  });
}

function fmtDuration(s) {
  if (!s) return null;
  if (s < 3600) return `${Math.round(s / 60)} min`;
  if (s < 86400) return `${(s / 3600).toFixed(1).replace('.0', '')} h`;
  return `${Math.round(s / 86400)} j`;
}

function voteUrl(poll) {
  return window.location.origin + '/polls/' + poll.secret_token;
}

function copyLink(poll) {
  navigator.clipboard.writeText(voteUrl(poll)).then(() => {
    linkCopied.value = poll.id;
    setTimeout(() => { linkCopied.value = null; }, 2000);
  });
}

async function handleStart(poll) {
  try {
    await startPoll(poll.id);
  } catch (err) {
    alert(err?.data?.message || 'Erreur lors du démarrage.');
  }
}

async function handleDelete(poll) {
  if (!confirm(`Supprimer « ${poll.title || poll.question} » ?`)) return;
  deleting.value = poll.id;
  try {
    await deletePoll(poll.id);
  } catch (err) {
    alert(err?.data?.message || 'Erreur lors de la suppression.');
  } finally {
    deleting.value = null;
  }
}
</script>

<template>
  <div>
    <p v-if="polls.length === 0" class="text-center text-slate-500 py-12">
      Aucun sondage. Créez-en un via le bouton « + Nouveau » !
    </p>

    <div class="space-y-4">
      <div
        v-for="poll in polls"
        :key="poll.id"
        class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm"
      >
        <!-- Titre + badge -->
        <div class="flex items-start justify-between gap-3 mb-2">
          <div class="flex-1 min-w-0">
            <h3 class="font-semibold text-slate-900 truncate">
              {{ poll.title || poll.question }}
            </h3>
            <p v-if="poll.title" class="text-sm text-slate-500 truncate">{{ poll.question }}</p>
          </div>
          <span
            class="text-xs font-medium px-2 py-0.5 rounded-full shrink-0"
            :class="pollStatus(poll).cls"
          >
            {{ pollStatus(poll).label }}
          </span>
        </div>

        <!-- Méta -->
        <div class="text-xs text-slate-400 space-y-0.5 mb-3">
          <p v-if="poll.started_at">Démarré : {{ fmt(poll.started_at) }}</p>
          <p v-if="poll.ends_at">Fin : {{ fmt(poll.ends_at) }}</p>
          <p v-else-if="poll.duration">Durée : {{ fmtDuration(poll.duration) }}</p>
          <p>{{ poll.options?.length ?? 0 }} option{{ (poll.options?.length ?? 0) !== 1 ? 's' : '' }}</p>
          <p v-if="!poll.results_public" class="text-amber-500">Résultats privés</p>
          <p v-if="poll.allow_multiple_choices" class="text-blue-500">Choix multiples</p>
        </div>

        <!-- Lien de partage (hors brouillon) -->
        <div
          v-if="!poll.is_draft"
          class="flex items-center gap-2 bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 mb-3 text-xs"
        >
          <span class="text-slate-500 truncate flex-1">{{ voteUrl(poll) }}</span>
          <button
            @click="copyLink(poll)"
            class="text-teal-600 font-medium hover:text-teal-800 shrink-0"
          >
            {{ linkCopied === poll.id ? '✓ Copié' : 'Copier' }}
          </button>
        </div>

        <!-- Actions -->
        <div class="flex flex-wrap gap-2">
          <button
            v-if="poll.is_draft"
            @click="handleStart(poll)"
            class="text-xs bg-teal-600 text-white px-3 py-1.5 rounded-lg hover:bg-teal-700 transition"
          >
            Démarrer
          </button>

          <button
            @click="emit('edit', poll)"
            class="text-xs bg-slate-100 text-slate-700 px-3 py-1.5 rounded-lg hover:bg-slate-200 transition"
          >
            Modifier
          </button>

          <button
            @click="handleDelete(poll)"
            :disabled="deleting === poll.id"
            class="text-xs bg-rose-50 text-rose-600 border border-rose-200 px-3 py-1.5 rounded-lg hover:bg-rose-100 transition disabled:opacity-50"
          >
            {{ deleting === poll.id ? '...' : 'Supprimer' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
